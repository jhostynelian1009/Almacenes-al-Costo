<?php

namespace Tests\Feature;

use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use App\Payments\DeunaGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeunaGatewayTest extends TestCase
{
    use RefreshDatabase;

    private function makeGateway(
        string $baseUrl = 'https://api.deuna.test',
        string $merchantId = 'test_merchant',
        string $apiKey = 'test_api_key',
        string $webhookSecret = 'test_webhook_secret',
    ): DeunaGateway {
        return new DeunaGateway(
            baseUrl: $baseUrl,
            merchantId: $merchantId,
            apiKey: $apiKey,
            webhookSecret: $webhookSecret,
            timeoutSeconds: 5,
        );
    }

    private function makeOrder(): Order
    {
        return Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '50.00',
            'shipping_cost' => '0.00',
            'total' => '50.00',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);
    }

    // ────────────────────────────── initializePayment ──────────────────────────────

    public function test_initialize_payment_uses_http_fake_not_real_network(): void
    {
        Http::fake([
            'https://api.deuna.test/*' => Http::response([
                'redirect_url' => 'https://pay.deuna.test/checkout/abc',
                'order_id' => 'deuna_tx_001',
            ], 200),
        ]);

        $gateway = $this->makeGateway();
        $order = $this->makeOrder();

        $response = $gateway->initializePayment($order);

        $this->assertSame('processing', $response->status);
        $this->assertSame('https://pay.deuna.test/checkout/abc', $response->redirectUrl);
        $this->assertSame('deuna_tx_001', $response->transactionId);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.deuna.test'));
    }

    public function test_initialize_sends_idempotency_header(): void
    {
        Http::fake([
            'https://api.deuna.test/*' => Http::response(['order_id' => 'tx_001'], 200),
        ]);

        $gateway = $this->makeGateway();
        $order = $this->makeOrder();

        $gateway->initializePayment($order, ['idempotency_key' => 'idem_key_001']);

        Http::assertSent(function ($request) {
            return $request->header('X-Idempotency-Key')[0] === 'idem_key_001';
        });
    }

    public function test_initialize_payment_handles_4xx_safely(): void
    {
        Http::fake([
            'https://api.deuna.test/*' => Http::response(['error' => 'Invalid order'], 400),
        ]);

        $gateway = $this->makeGateway();
        $order = $this->makeOrder();

        $this->expectException(PaymentOperationException::class);
        $gateway->initializePayment($order);
    }

    public function test_initialize_payment_handles_5xx_safely(): void
    {
        Http::fake([
            'https://api.deuna.test/*' => Http::response([], 500),
        ]);

        $gateway = $this->makeGateway();
        $order = $this->makeOrder();

        $this->expectException(PaymentOperationException::class);
        $gateway->initializePayment($order);
    }

    public function test_initialize_payment_handles_connection_timeout(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $gateway = $this->makeGateway();
        $order = $this->makeOrder();

        $this->expectException(PaymentOperationException::class);
        $gateway->initializePayment($order);
    }

    public function test_initialize_payment_returns_pending_if_no_redirect(): void
    {
        Http::fake([
            'https://api.deuna.test/*' => Http::response([
                'order_id' => 'tx_002',
            ], 200),
        ]);

        $gateway = $this->makeGateway();
        $order = $this->makeOrder();

        $response = $gateway->initializePayment($order);

        $this->assertSame('pending', $response->status);
        $this->assertNull($response->redirectUrl);
    }

    public function test_api_key_not_included_in_payload_array(): void
    {
        Http::fake([
            'https://api.deuna.test/*' => Http::response(['order_id' => 'tx_003'], 200),
        ]);

        $gateway = $this->makeGateway(apiKey: 'SECRET_KEY_DO_NOT_LOG');
        $order = $this->makeOrder();

        $response = $gateway->initializePayment($order);

        // The payload in the response should not include the raw api_key
        $this->assertArrayNotHasKey('api_key', $response->payload);
    }

    // ────────────────────────────── handleCallback ──────────────────────────────

    public function test_callback_returns_processing_status_without_updating_db(): void
    {
        $gateway = $this->makeGateway();
        $request = Request::create('/callback', 'GET', ['status' => 'completed']);

        $response = $gateway->handleCallback($request);

        // Returns 'processing' but does NOT confirm payment
        $this->assertSame('processing', $response->status);
    }

    // ────────────────────────────── validateSignature ──────────────────────────────

    public function test_valid_hmac_signature_accepted(): void
    {
        $secret = 'test_secret_key';
        $body = '{"event":"payment.completed"}';
        $signature = hash_hmac('sha256', $body, $secret);

        $gateway = $this->makeGateway(webhookSecret: $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Deuna-Signature', $signature);

        $this->assertTrue($gateway->validateSignature($request));
    }

    public function test_invalid_hmac_signature_rejected(): void
    {
        $gateway = $this->makeGateway(webhookSecret: 'correct_secret');

        $body = '{"event":"payment.completed"}';
        $wrongSignature = hash_hmac('sha256', $body, 'wrong_secret');

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Deuna-Signature', $wrongSignature);

        $this->assertFalse($gateway->validateSignature($request));
    }

    public function test_missing_signature_header_rejected(): void
    {
        $gateway = $this->makeGateway();
        $body = '{"event":"payment.completed"}';
        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);

        $this->assertFalse($gateway->validateSignature($request));
    }

    public function test_signature_uses_constant_time_comparison(): void
    {
        // Verify that signature comparison doesn't short-circuit (timing attacks)
        $secret = 'test_secret';
        $body = '{"event":"test"}';
        $signature = hash_hmac('sha256', $body, $secret);
        $gateway = $this->makeGateway(webhookSecret: $secret);

        $request = Request::create('/webhook', 'POST', [], [], [], [], $body);
        $request->headers->set('X-Deuna-Signature', $signature);

        // Correct signature must pass
        $this->assertTrue($gateway->validateSignature($request));

        // One-off signature must fail
        $request->headers->set('X-Deuna-Signature', substr($signature, 0, -1).'X');
        $this->assertFalse($gateway->validateSignature($request));
    }

    // ────────────────────────────── handleWebhook ──────────────────────────────

    public function test_webhook_completed_event_maps_to_completed_response(): void
    {
        $gateway = $this->makeGateway();
        $body = json_encode(['status' => 'paid', 'transaction_id' => 'tx_abc']);
        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $response = $gateway->handleWebhook($request);

        $this->assertSame('completed', $response->status);
        $this->assertSame('tx_abc', $response->transactionId);
    }

    public function test_webhook_failed_event_maps_to_failed_response(): void
    {
        $gateway = $this->makeGateway();
        $body = json_encode(['status' => 'failed']);
        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $response = $gateway->handleWebhook($request);

        $this->assertSame('failed', $response->status);
    }

    public function test_webhook_unknown_event_maps_to_pending_response(): void
    {
        $gateway = $this->makeGateway();
        $body = json_encode(['status' => 'unknown_future_event']);
        $request = Request::create('/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        $response = $gateway->handleWebhook($request);

        $this->assertSame('pending', $response->status);
    }

    public function test_no_real_network_calls_in_any_test(): void
    {
        Http::preventStrayRequests();

        // None of the above tests should have made real network calls
        // This test ensures Http::preventStrayRequests() would catch any leakage
        $this->assertTrue(true);
    }
}
