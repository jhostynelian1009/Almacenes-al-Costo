<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\DatafastCheckoutResponse;
use App\DTOs\DatafastPaymentResult;
use App\DTOs\PaymentResponse;
use App\Exceptions\DatafastOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\DatafastRequestBuilder;
use App\Services\Payments\DatafastResourcePathValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class DatafastGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly DatafastRequestBuilder $requestBuilder,
        private readonly DatafastResourcePathValidator $resourcePathValidator,
    ) {}

    public function initializePayment(Order $order, array $options = []): PaymentResponse
    {
        $merchantTransactionId = (string) ($options['payment_reference'] ?? '');
        $shopperResultUrl = (string) ($options['shopper_result_url'] ?? '');
        $existingCheckoutId = $options['existing_checkout_id'] ?? null;

        if ($merchantTransactionId === '' || $shopperResultUrl === '') {
            throw DatafastOperationException::checkoutCreationFailed();
        }

        if (is_string($existingCheckoutId) && $this->resourcePathValidator->isValidCheckoutId($existingCheckoutId)) {
            return PaymentResponse::pending(
                transactionId: $merchantTransactionId,
                message: 'Pago con tarjeta listo para continuar.',
                payload: [
                    'gateway' => 'datafast',
                    'checkout_id' => $existingCheckoutId,
                    'merchant_transaction_id' => $merchantTransactionId,
                    'idempotent_reuse' => true,
                ],
            );
        }

        $payload = $this->requestBuilder->buildCheckoutPayload(
            $order,
            $merchantTransactionId,
            $shopperResultUrl,
        );

        try {
            $response = Http::asForm()
                ->withToken($this->requestBuilder->authorizationToken())
                ->connectTimeout($this->requestBuilder->connectTimeout())
                ->timeout($this->requestBuilder->timeout())
                ->post($this->requestBuilder->checkoutEndpoint(), $payload);
        } catch (ConnectionException) {
            Log::warning('Datafast checkout transport failure', [
                'gateway' => 'datafast',
                'order_reference' => $order->reference,
                'payment_reference' => $merchantTransactionId,
                'failure_category' => 'transport',
            ]);

            throw DatafastOperationException::checkoutCreationFailed();
        }

        if ($response->failed()) {
            Log::warning('Datafast checkout HTTP failure', [
                'gateway' => 'datafast',
                'order_reference' => $order->reference,
                'payment_reference' => $merchantTransactionId,
                'http_status' => $response->status(),
                'failure_category' => 'http_error',
            ]);

            throw DatafastOperationException::checkoutCreationFailed();
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw DatafastOperationException::checkoutCreationFailed();
        }

        $checkoutId = (string) ($data['id'] ?? '');
        $resultCode = $this->providerResultCode($data);
        $resultDescription = $this->providerResultDescription($data);

        if (
            ! $this->requestBuilder->isCheckoutCreationCode($resultCode)
            || ! $this->resourcePathValidator->isValidCheckoutId($checkoutId)
        ) {
            Log::warning('Datafast checkout rejected by provider response', [
                'gateway' => 'datafast',
                'order_reference' => $order->reference,
                'payment_reference' => $merchantTransactionId,
                'provider_result_code' => $resultCode,
                'failure_category' => 'unexpected_checkout_response',
            ]);

            throw DatafastOperationException::checkoutCreationFailed();
        }

        $checkout = new DatafastCheckoutResponse(
            checkoutId: $checkoutId,
            merchantTransactionId: $merchantTransactionId,
            resultCode: $resultCode,
            resultDescription: $resultDescription,
            payload: $this->safeProviderMetadata($data),
        );

        return PaymentResponse::pending(
            transactionId: $checkout->merchantTransactionId,
            message: 'Continua el pago con tarjeta en el formulario seguro de Datafast.',
            payload: [
                'gateway' => 'datafast',
                'checkout_id' => $checkout->checkoutId,
                'merchant_transaction_id' => $checkout->merchantTransactionId,
                'result_code' => $checkout->resultCode,
                'result_description' => $checkout->resultDescription,
                'provider_metadata' => $checkout->payload,
            ],
        );
    }

    public function handleCallback(Request $request): PaymentResponse
    {
        return PaymentResponse::pending(message: 'Verificando estado del pago con Datafast.');
    }

    public function handleWebhook(Request $request): PaymentResponse
    {
        throw new RuntimeException('handleWebhook no aplica para Datafast sin contrato de notificacion.');
    }

    public function validateSignature(Request $request): bool
    {
        throw new RuntimeException('validateSignature no aplica para Datafast sin contrato de webhook.');
    }

    public function verifyPaymentResult(Payment $payment, string $resourcePath): DatafastPaymentResult
    {
        $checkoutId = $this->storedCheckoutId($payment);
        if ($checkoutId === null || ! is_string($payment->transaction_id) || $payment->transaction_id === '') {
            throw DatafastOperationException::verificationFailed();
        }

        $validatedPath = $this->resourcePathValidator->validate($resourcePath, $checkoutId);

        try {
            $response = Http::withToken($this->requestBuilder->authorizationToken())
                ->connectTimeout($this->requestBuilder->connectTimeout())
                ->timeout($this->requestBuilder->timeout())
                ->get($this->requestBuilder->resourceUrl($validatedPath), [
                    'entityId' => $this->requestBuilder->entityId(),
                ]);
        } catch (ConnectionException) {
            Log::warning('Datafast status transport failure', [
                'gateway' => 'datafast',
                'payment_reference' => $payment->transaction_id,
                'failure_category' => 'transport',
            ]);

            throw DatafastOperationException::verificationFailed();
        }

        if ($response->failed()) {
            Log::warning('Datafast status HTTP failure', [
                'gateway' => 'datafast',
                'payment_reference' => $payment->transaction_id,
                'http_status' => $response->status(),
                'failure_category' => 'http_error',
            ]);

            throw DatafastOperationException::verificationFailed();
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw DatafastOperationException::verificationFailed();
        }

        $resultCode = $this->providerResultCode($data);
        $resultDescription = $this->providerResultDescription($data);
        $merchantTransactionId = (string) ($data['merchantTransactionId'] ?? '');
        $amount = isset($data['amount']) ? $this->requestBuilder->normalizeMoney($data['amount']) : null;
        $currency = isset($data['currency']) ? (string) $data['currency'] : null;
        $providerPaymentId = isset($data['id']) ? (string) $data['id'] : null;
        $responseCheckoutId = $data['checkoutId'] ?? $data['checkout_id'] ?? $data['ndc'] ?? null;

        if (
            $merchantTransactionId === ''
            || ! hash_equals((string) $payment->transaction_id, $merchantTransactionId)
            || $amount === null
            || $this->requestBuilder->normalizeMoney($payment->amount) !== $amount
            || $currency !== $this->requestBuilder->currency()
            || ($responseCheckoutId !== null && ! hash_equals($checkoutId, (string) $responseCheckoutId))
        ) {
            Log::warning('Datafast status verification mismatch', [
                'gateway' => 'datafast',
                'payment_reference' => $payment->transaction_id,
                'provider_result_code' => $resultCode,
                'failure_category' => 'verification_mismatch',
            ]);

            throw DatafastOperationException::verificationFailed();
        }

        $status = match (true) {
            $this->requestBuilder->isApprovedPaymentCode($resultCode) => 'completed',
            $this->requestBuilder->isFailedPaymentCode($resultCode) => 'failed',
            default => 'pending',
        };

        return new DatafastPaymentResult(
            status: $status,
            merchantTransactionId: $merchantTransactionId,
            checkoutId: $checkoutId,
            providerPaymentId: $providerPaymentId,
            resultCode: $resultCode,
            resultDescription: $resultDescription,
            amount: $amount,
            currency: $currency,
            payload: [
                'gateway' => 'datafast',
                'checkout_id' => $checkoutId,
                'merchant_transaction_id' => $merchantTransactionId,
                'provider_payment_id' => $providerPaymentId,
                'result_code' => $resultCode,
                'result_description' => $resultDescription,
                'provider_metadata' => $this->safeProviderMetadata($data),
            ],
        );
    }

    public function widgetScriptUrl(string $checkoutId): string
    {
        return $this->requestBuilder->widgetScriptUrl($checkoutId);
    }

    public function brands(): string
    {
        return $this->requestBuilder->brands();
    }

    /**
     * @return array{enabled: bool, ready: bool, message: string, missing_configuration: list<string>}
     */
    public function readiness(Order $order): array
    {
        return $this->requestBuilder->readiness($order);
    }

    private function storedCheckoutId(Payment $payment): ?string
    {
        $transactions = $payment->transactions()
            ->latest('id')
            ->get();

        foreach ($transactions as $transaction) {
            $payload = $transaction->payload;
            if (! is_array($payload)) {
                continue;
            }

            $checkoutId = $payload['checkout_id'] ?? $payload['payload_summary']['checkout_id'] ?? null;
            if (is_string($checkoutId) && $this->resourcePathValidator->isValidCheckoutId($checkoutId)) {
                return $checkoutId;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerResultCode(array $payload): ?string
    {
        $code = $payload['result']['code'] ?? null;

        return is_scalar($code) ? (string) $code : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerResultDescription(array $payload): ?string
    {
        $description = $payload['result']['description'] ?? null;

        return is_scalar($description) ? Str::limit((string) $description, 160, '') : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function safeProviderMetadata(array $payload): array
    {
        $safe = [
            'id' => isset($payload['id']) && is_scalar($payload['id']) ? (string) $payload['id'] : null,
            'result_code' => $this->providerResultCode($payload),
            'result_description' => $this->providerResultDescription($payload),
        ];

        return array_filter($safe, fn (mixed $value): bool => $value !== null);
    }
}
