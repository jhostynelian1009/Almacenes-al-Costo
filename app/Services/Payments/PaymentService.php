<?php

namespace App\Services\Payments;

use App\DTOs\PaymentResponse;
use App\Events\OrderApproved;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentInitiated;
use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly PaymentFactory $factory,
    ) {}

    /**
     * Initialize a payment for an order.
     *
     * Idempotent: returns the existing pending payment if one already exists.
     *
     * @param  array<string, mixed>  $options
     *
     * @throws PaymentOperationException
     */
    public function initialize(
        Order $order,
        string $gateway,
        string $paymentMethod,
        array $options = [],
    ): PaymentResponse {
        if (! $order->isPayable()) {
            throw PaymentOperationException::orderNotPayable();
        }

        $adapter = $this->factory->make($gateway);

        return DB::transaction(function () use ($order, $gateway, $paymentMethod, $options, $adapter): PaymentResponse {
            // Idempotency: reuse an existing pending payment for this order+method
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('gateway', $gateway)
                ->where('payment_method', $paymentMethod)
                ->where('status', Payment::STATUS_PENDING)
                ->first();

            $isNew = $payment === null;

            if ($isNew) {
                $payment = Payment::query()->create([
                    'order_id' => $order->id,
                    'gateway' => $gateway,
                    'payment_method' => $paymentMethod,
                    'amount' => $order->total,
                    'currency' => 'USD',
                    'status' => Payment::STATUS_PENDING,
                ]);
            }

            // Record the initiation request in transactions
            $this->recordTransaction($payment, PaymentTransaction::EVENT_REQUEST, [
                'gateway' => $gateway,
                'method' => $paymentMethod,
                'order_reference' => $order->reference,
                'amount' => (string) $order->total,
            ]);

            $response = $adapter->initializePayment($order, $options);

            // Record the response
            $this->recordTransaction($payment, PaymentTransaction::EVENT_RESPONSE, [
                'status' => $response->status,
                'transaction_id' => $response->transactionId,
                'has_redirect' => $response->redirectUrl !== null,
                'payload_summary' => $this->sanitizedPayload($response->payload),
            ]);

            // Persist provider reference if available
            if ($response->transactionId !== null) {
                $payment->update(['transaction_id' => $response->transactionId]);
            }

            // Only dispatch PaymentInitiated for new payments (idempotency)
            if ($isNew) {
                event(new PaymentInitiated($order, $payment));
            }

            Log::info('Payment initialized', [
                'order_reference' => $order->reference,
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'method' => $paymentMethod,
                'has_redirect' => $response->redirectUrl !== null,
                'is_new' => $isNew,
            ]);

            return $response;
        });
    }

    /**
     * Handle a provider webhook notification.
     * Validates signature, enforces idempotency, updates payment and order state.
     *
     * @throws PaymentOperationException on invalid signature
     */
    public function handleWebhook(Request $request, string $gateway): PaymentResponse
    {
        $adapter = $this->factory->make($gateway);

        // 1. Read raw body BEFORE any parsing
        $rawBody = $request->getContent();

        // 2. Validate signature
        if (! $adapter->validateSignature($request)) {
            Log::warning('Webhook signature validation failed', [
                'gateway' => $gateway,
                'ip' => $request->ip(),
            ]);

            throw PaymentOperationException::invalidSignature();
        }

        $payload = $request->json()->all();

        // 3. Validate timestamp (5 minute tolerance)
        $eventTimestamp = $payload['created_at'] ?? $payload['timestamp'] ?? null;
        if ($eventTimestamp !== null) {
            $ts = is_numeric($eventTimestamp) ? (int) $eventTimestamp : strtotime((string) $eventTimestamp);
            if ($ts !== false && abs(time() - $ts) > 300) {
                Log::info('Webhook timestamp expired (replay protection)', [
                    'gateway' => $gateway,
                    'event_timestamp' => $eventTimestamp,
                ]);

                return PaymentResponse::pending(message: 'Evento descartado: timestamp expirado.');
            }
        }

        // 4. Extract event_id for idempotency
        $eventId = (string) ($payload['id'] ?? $payload['event_id'] ?? md5($rawBody));
        $eventType = (string) ($payload['type'] ?? $payload['event_type'] ?? 'unknown');

        // 5. Check for duplicate event
        $existingLog = WebhookLog::query()
            ->where('event_id', $eventId)
            ->first();

        if ($existingLog !== null && $existingLog->processed) {
            Log::info('Webhook duplicate event ignored (idempotency)', [
                'gateway' => $gateway,
                'event_id' => $eventId,
            ]);

            return PaymentResponse::pending(message: 'Evento ya procesado.');
        }

        // 6. Insert or reuse webhook log (unprocessed)
        if ($existingLog === null) {
            $existingLog = WebhookLog::query()->create([
                'gateway' => $gateway,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'payload' => $this->sanitizedPayload($payload),
                'signature_verified' => true,
                'processed' => false,
            ]);
        }

        // 7. Parse the provider response
        $response = $adapter->handleWebhook($request);

        // 8. Find the associated payment
        $orderReference = $payload['order_id'] ?? $payload['order_reference'] ?? $payload['metadata']['order_reference'] ?? null;

        $payment = null;
        if ($orderReference !== null) {
            $order = Order::query()->where('reference', $orderReference)->first();
            if ($order !== null) {
                $payment = $order->payments()->where('status', '!=', Payment::STATUS_COMPLETED)->first()
                    ?? $order->payments()->latest()->first();
            }
        }

        if ($payment !== null) {
            $this->applyWebhookResult($payment, $response, $eventId);
        } else {
            Log::warning('Webhook: payment/order not found', [
                'gateway' => $gateway,
                'event_id' => $eventId,
                'order_reference' => $orderReference,
            ]);
        }

        // 9. Record transaction
        if ($payment !== null) {
            $this->recordTransaction($payment, PaymentTransaction::EVENT_WEBHOOK, [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'response_status' => $response->status,
            ]);
        }

        // 10. Mark as processed
        $existingLog->update(['processed' => true]);

        return $response;
    }

    /**
     * Apply webhook result to payment and order states transactionally.
     */
    private function applyWebhookResult(Payment $payment, PaymentResponse $response, string $eventId): void
    {
        DB::transaction(function () use ($payment, $response): void {
            $order = $payment->order;

            if ($response->status === 'completed') {
                if ($this->transitionPayment($payment, Payment::STATUS_COMPLETED, $response->transactionId)) {
                    if ($order !== null && ! $order->isPaid()) {
                        $order->update(['status' => Order::STATUS_PAID]);
                        event(new PaymentCompleted($order, $payment));
                    }
                }
            } elseif (in_array($response->status, ['failed', 'expired'], true)) {
                if ($this->transitionPayment($payment, Payment::STATUS_FAILED)) {
                    if ($order !== null && $order->status !== Order::STATUS_CANCELED) {
                        $order->update(['status' => Order::STATUS_CANCELED]);
                        event(new PaymentFailed($order, $payment));
                    }
                }
            }
        });
    }

    /**
     * Update payment status safely (only from allowed prior states).
     */
    private function transitionPayment(Payment $payment, string $newStatus, ?string $transactionId = null): bool
    {
        $terminalStatuses = [Payment::STATUS_COMPLETED, Payment::STATUS_REFUNDED];

        if (in_array($payment->status, $terminalStatuses, true)) {
            // Already terminal — idempotent, do not change
            return false;
        }

        $updateData = ['status' => $newStatus];
        if ($transactionId !== null) {
            $updateData['transaction_id'] = $transactionId;
        }

        $payment->update($updateData);

        return true;
    }

    /**
     * Approve a manual payment receipt (admin action).
     *
     * @throws PaymentOperationException
     */
    public function approveManualPayment(Order $order, Payment $payment): void
    {
        if (! $order->isValidating()) {
            throw PaymentOperationException::invalidStateTransition($order->status, Order::STATUS_APPROVED);
        }

        DB::transaction(function () use ($order, $payment): void {
            $payment->update(['status' => Payment::STATUS_COMPLETED]);
            $order->update(['status' => Order::STATUS_APPROVED]);
            event(new OrderApproved($order, $payment));
        });

        Log::info('Manual payment approved', [
            'order_reference' => $order->reference,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Reject a manual payment receipt (admin action).
     *
     * @throws PaymentOperationException
     */
    public function rejectManualPayment(Order $order, Payment $payment, string $reason): void
    {
        if (! $order->isValidating()) {
            throw PaymentOperationException::invalidStateTransition($order->status, Order::STATUS_PENDING_PAYMENT);
        }

        DB::transaction(function () use ($order, $payment, $reason): void {
            $payment->update(['status' => Payment::STATUS_FAILED]);
            $order->update(['status' => Order::STATUS_PENDING_PAYMENT]);

            // Record the rejection reason on the receipt
            $receipt = $order->receipt;
            if ($receipt !== null) {
                $receipt->update(['rejection_reason' => $reason]);
            }
        });

        Log::info('Manual payment rejected', [
            'order_reference' => $order->reference,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Record a payment lifecycle event in payment_transactions.
     *
     * @param  array<string, mixed>  $payload
     */
    private function recordTransaction(Payment $payment, string $eventType, array $payload): void
    {
        $payment->transactions()->create([
            'event_type' => $eventType,
            'payload' => $this->sanitizedPayload($payload),
        ]);
    }

    /**
     * Remove sensitive keys from any logged payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizedPayload(array $payload): array
    {
        $sensitiveKeys = ['api_key', 'secret', 'password', 'token', 'authorization', 'webhook_secret'];
        foreach ($sensitiveKeys as $key) {
            if (isset($payload[$key])) {
                $payload[$key] = '[REDACTED]';
            }
        }

        return $payload;
    }
}
