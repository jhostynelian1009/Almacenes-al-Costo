<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test_webhook_secret_value';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.deuna.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    // ────────────────────────────── Signature validation ──────────────────────────────

    private function postWebhook(string $gateway, string $body, ?string $signature = null)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($signature !== null) {
            $server['HTTP_X_DEUNA_SIGNATURE'] = $signature;
        }

        return $this->call('POST', route('payment.webhook', $gateway), [], [], [], $server, $body);
    }

    public function test_webhook_with_valid_signature_is_accepted(): void
    {
        Event::fake();

        $body = json_encode([
            'id' => 'evt_valid_001',
            'type' => 'payment.completed',
            'status' => 'paid',
            'order_id' => null,
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $response = $this->postWebhook('deuna', $body, $signature);

        $response->assertStatus(200);
    }

    public function test_webhook_with_invalid_signature_is_rejected_with_401(): void
    {
        $body = json_encode(['id' => 'evt_invalid_001', 'type' => 'payment.completed']);

        $response = $this->postWebhook('deuna', $body, 'invalid_signature_value');

        $response->assertStatus(401);
    }

    public function test_webhook_with_missing_signature_is_rejected(): void
    {
        $body = json_encode(['id' => 'evt_nosig_001', 'type' => 'payment.completed']);

        $response = $this->postWebhook('deuna', $body);

        $response->assertStatus(401);
    }

    public function test_unknown_gateway_returns_400(): void
    {
        $body = json_encode(['id' => 'evt_001']);

        $response = $this->postWebhook('unknown_gateway', $body, 'any_sig');

        $response->assertStatus(400);
    }

    // ────────────────────────────── Replay protection / Timestamp ──────────────────────────────

    public function test_stale_timestamp_is_silently_discarded_with_200(): void
    {
        Event::fake();

        $staleTimestamp = time() - 600; // 10 minutes ago
        $body = json_encode([
            'id' => 'evt_stale_001',
            'type' => 'payment.completed',
            'created_at' => $staleTimestamp,
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $response = $this->postWebhook('deuna', $body, $signature);

        $response->assertStatus(200);
    }

    // ────────────────────────────── Idempotency ──────────────────────────────

    public function test_duplicate_event_id_is_ignored_idempotently(): void
    {
        Event::fake();

        WebhookLog::query()->create([
            'gateway' => 'deuna',
            'event_id' => 'evt_dup_001',
            'event_type' => 'payment.completed',
            'payload' => [],
            'signature_verified' => true,
            'processed' => true,
        ]);

        $body = json_encode([
            'id' => 'evt_dup_001',
            'type' => 'payment.completed',
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $response = $this->postWebhook('deuna', $body, $signature);

        $response->assertStatus(200);
        $this->assertSame(1, WebhookLog::query()->where('event_id', 'evt_dup_001')->count());
    }

    public function test_duplicate_webhook_does_not_create_duplicate_audit_entry(): void
    {
        Event::fake();

        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        // Pre-insert processed event
        WebhookLog::query()->create([
            'gateway' => 'deuna',
            'event_id' => 'evt_nodup_001',
            'payload' => [],
            'signature_verified' => true,
            'processed' => true,
        ]);

        $body = json_encode([
            'id' => 'evt_nodup_001',
            'type' => 'payment.completed',
            'order_id' => $order->reference,
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $this->postWebhook('deuna', $body, $signature);

        $payment->refresh();
        // Payment should NOT change since event was already processed
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
    }

    // ────────────────────────────── Webhook event processing ──────────────────────────────

    public function test_valid_completed_webhook_updates_payment_and_order(): void
    {
        Event::fake();

        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        $body = json_encode([
            'id' => 'evt_complete_001',
            'type' => 'payment.completed',
            'status' => 'paid',
            'transaction_id' => 'deuna_tx_abc',
            'metadata' => ['order_reference' => $order->reference],
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $response = $this->postWebhook('deuna', $body, $signature);

        $response->assertStatus(200);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
    }

    public function test_valid_failed_webhook_cancels_order(): void
    {
        Event::fake();

        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        $body = json_encode([
            'id' => 'evt_fail_001',
            'type' => 'payment.failed',
            'status' => 'failed',
            'metadata' => ['order_reference' => $order->reference],
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $response = $this->postWebhook('deuna', $body, $signature);

        $response->assertStatus(200);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_CANCELED, $order->status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
    }

    public function test_completed_webhook_for_unknown_order_returns_200_safely(): void
    {
        Event::fake();

        $body = json_encode([
            'id' => 'evt_unknown_order_001',
            'type' => 'payment.completed',
            'order_id' => 'NONEXISTENT-ORDER-REF',
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $response = $this->postWebhook('deuna', $body, $signature);

        $response->assertStatus(200);
    }

    public function test_webhook_stores_sanitized_event_record(): void
    {
        Event::fake();

        $body = json_encode([
            'id' => 'evt_sanitize_001',
            'type' => 'payment.completed',
            'api_key' => 'SHOULD_BE_REDACTED',
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $this->postWebhook('deuna', $body, $signature);

        $log = WebhookLog::query()->where('event_id', 'evt_sanitize_001')->first();
        $this->assertNotNull($log);
        $this->assertNotSame('SHOULD_BE_REDACTED', $log->payload['api_key'] ?? 'NOT_PRESENT');
    }

    public function test_terminal_payment_is_not_changed_by_duplicate_webhook(): void
    {
        Event::fake();

        $order = $this->makeOrder(Order::STATUS_PAID);
        $payment = $this->makePayment($order, Payment::STATUS_COMPLETED);

        $body = json_encode([
            'id' => 'evt_terminal_dup_001',
            'type' => 'payment.completed',
            'status' => 'paid',
            'metadata' => ['order_reference' => $order->reference],
        ]);

        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        $this->postWebhook('deuna', $body, $signature);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status); // Unchanged
    }

    // ────────────────────────────── Helpers ──────────────────────────────

    private function makeOrder(string $status = Order::STATUS_PENDING_PAYMENT): Order
    {
        return Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Webhook Test User',
            'customer_email' => 'webhook@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '30.00',
            'shipping_cost' => '0.00',
            'total' => '30.00',
            'status' => $status,
        ]);
    }

    private function makePayment(Order $order, string $status = Payment::STATUS_PENDING): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'deuna',
            'payment_method' => 'deuna',
            'amount' => $order->total,
            'status' => $status,
        ]);
    }
}
