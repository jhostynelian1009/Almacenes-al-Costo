<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentResponse;
use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DeunaGateway adapter.
 *
 * All endpoints and credentials are configuration-driven.
 * No real API contract was found in the SPEC; this implementation uses
 * a structured architecture behind a transport boundary (Laravel HTTP Client).
 * Live sandbox verification is PENDING — tests use Http::fake.
 *
 * Per SPEC: Deuna in MVP acts as a QR-based manual payment.
 * The gateway integration structure is provided for future activation
 * when the official Deuna API contract and credentials are available.
 */
class DeunaGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $merchantId,
        private readonly string $apiKey,
        private readonly string $webhookSecret,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function initializePayment(Order $order, array $options = []): PaymentResponse
    {
        $idempotencyKey = $options['idempotency_key'] ?? (string) Str::ulid();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'X-Merchant-Id' => $this->merchantId,
                'X-Idempotency-Key' => $idempotencyKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeoutSeconds)
                ->post("{$this->baseUrl}/orders", [
                    'order_id' => $order->reference,
                    'amount' => (float) $order->total,
                    'currency' => 'USD',
                    'callback_url' => $options['callback_url'] ?? null,
                    'metadata' => ['order_reference' => $order->reference],
                ]);

            if ($response->failed()) {
                Log::warning('DeunaGateway: initializePayment failed', [
                    'order_reference' => $order->reference,
                    'status' => $response->status(),
                    'body_preview' => Str::limit($response->body(), 200),
                ]);

                throw PaymentOperationException::providerError('La pasarela Deuna rechazó la solicitud.');
            }

            $data = $response->json() ?? [];
            $redirectUrl = $data['redirect_url'] ?? $data['payment_url'] ?? null;
            $transactionId = $data['order_id'] ?? $data['transaction_id'] ?? null;

            if ($redirectUrl !== null) {
                return PaymentResponse::withRedirect(
                    redirectUrl: $redirectUrl,
                    transactionId: $transactionId,
                    payload: $this->sanitizedPayload($data),
                );
            }

            return PaymentResponse::pending(
                transactionId: $transactionId,
                message: 'Redirigiendo a Deuna...',
                payload: $this->sanitizedPayload($data),
            );
        } catch (ConnectionException) {
            throw PaymentOperationException::providerTimeout();
        }
    }

    public function handleCallback(Request $request): PaymentResponse
    {
        // Per SPEC: Do not trust browser callbacks to confirm payment.
        // Only read the current database state; never update status here.
        $status = $request->query('status', 'pending');

        return new PaymentResponse(
            status: in_array($status, ['completed', 'paid'], true) ? 'processing' : 'pending',
            message: 'Verificando estado del pago con Deuna...',
        );
    }

    public function handleWebhook(Request $request): PaymentResponse
    {
        $payload = $request->json()->all();
        $status = $payload['status'] ?? $payload['event_type'] ?? 'unknown';

        return match (true) {
            in_array($status, ['paid', 'completed', 'payment.completed'], true) => PaymentResponse::completed(
                transactionId: $payload['transaction_id'] ?? $payload['order_id'] ?? null,
                payload: $this->sanitizedPayload($payload),
            ),
            in_array($status, ['failed', 'rejected', 'payment.failed'], true) => PaymentResponse::failed(
                message: 'Pago rechazado por Deuna.',
                payload: $this->sanitizedPayload($payload),
            ),
            default => PaymentResponse::pending(
                message: "Evento Deuna desconocido: {$status}",
                payload: $this->sanitizedPayload($payload),
            ),
        };
    }

    public function validateSignature(Request $request): bool
    {
        $receivedSignature = $request->header('X-Deuna-Signature', '');
        $rawBody = $request->getContent();

        if ($receivedSignature === '' || $rawBody === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $this->webhookSecret);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Remove sensitive fields from provider payloads before logging.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizedPayload(array $payload): array
    {
        foreach (['api_key', 'secret', 'password', 'token', 'authorization'] as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
