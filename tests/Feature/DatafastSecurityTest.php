<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\DatafastResourcePathValidator;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatafastSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->configureDatafast();
    }

    public function test_exact_stored_checkout_payment_path_is_accepted(): void
    {
        $validator = new DatafastResourcePathValidator;

        $this->assertSame(
            '/v1/checkouts/checkoutsecure1/payment',
            $validator->validate('/v1/checkouts/checkoutsecure1/payment', 'checkoutsecure1'),
        );
    }

    public function test_untrusted_resource_paths_are_rejected_without_provider_request(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutsecure2');

        foreach ($this->maliciousResourcePaths('checkoutsecure2') as $path) {
            Http::fake();

            $this->get(route('orders.payment.datafast.result', [
                'orderReference' => $order->reference,
                'paymentReference' => $payment->transaction_id,
                'resourcePath' => $path,
            ]))
                ->assertOk()
                ->assertSee('No se pudo verificar', false);

            Http::assertNothingSent();
            $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
            $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        }
    }

    public function test_another_checkout_id_is_rejected(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutsecure3');
        Http::fake();

        $this->get(route('orders.payment.datafast.result', [
            'orderReference' => $order->reference,
            'paymentReference' => $payment->transaction_id,
            'resourcePath' => '/v1/checkouts/checkoutother3/payment',
        ]))->assertOk();

        Http::assertNothingSent();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_public_html_and_transaction_payloads_do_not_expose_credentials_or_card_fields(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutsecure4');

        $html = $this->get(route('orders.payment.datafast.widget', [
            'orderReference' => $order->reference,
            'paymentReference' => $payment->transaction_id,
        ]))->getContent();

        $payload = json_encode($payment->transactions()->pluck('payload')->all());
        $this->assertIsString($payload);

        foreach ([
            'SECRET_DATAFAST_TOKEN',
            'ENTITY123',
            'MID123',
            'TID123',
            'PSERV123',
            '0912345678',
            'card_number',
            'cvv',
            'expiration',
        ] as $secretOrCardField) {
            $this->assertStringNotContainsString($secretOrCardField, $html);
            $this->assertStringNotContainsString($secretOrCardField, $payload);
        }

        $this->assertStringNotContainsString('"id":'.$order->id, $html);
        $this->assertStringNotContainsString('/pedido/'.$order->id, $html);
    }

    public function test_datafast_result_route_does_not_accept_client_status_or_amount_as_authoritative(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutsecure5');
        Http::fake([
            'https://datafast.test/v1/checkouts/checkoutsecure5/payment*' => Http::response([
                'id' => 'provider-payment-1',
                'checkoutId' => 'checkoutsecure5',
                'merchantTransactionId' => $payment->transaction_id,
                'amount' => '112.00',
                'currency' => 'USD',
                'result' => ['code' => '000.900.999'],
            ], 200),
        ]);

        $this->get(route('orders.payment.datafast.result', [
            'orderReference' => $order->reference,
            'paymentReference' => $payment->transaction_id,
            'resourcePath' => '/v1/checkouts/checkoutsecure5/payment',
            'amount' => '0.01',
            'currency' => 'EUR',
            'payment_status' => 'completed',
            'order_status' => 'paid',
            'approved' => 'true',
        ]))->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_process_route_keeps_csrf_protection_for_datafast_mutation(): void
    {
        $order = $this->makeReadyOrder();
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => 'checkoutcsrf1',
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        $response = $this->call('POST', route('orders.payment.process', $order->reference), [
            'payment_method' => 'datafast',
        ], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'wrong-token',
        ]);

        $this->assertContains($response->getStatusCode(), [302, 419]);
    }

    public function test_disabled_datafast_process_response_does_not_expose_configuration_values(): void
    {
        config(['payment.datafast.enabled' => false]);

        $response = $this->from(route('orders.payment.show', $this->makeReadyOrder()->reference))
            ->post(route('orders.payment.process', Order::query()->firstOrFail()->reference), [
                'payment_method' => 'datafast',
            ]);

        $response->assertSessionHas('error');
        $response->assertSessionMissing('SECRET_DATAFAST_TOKEN');
    }

    /**
     * @return list<string>
     */
    private function maliciousResourcePaths(string $checkoutId): array
    {
        return [
            "https://evil.test/v1/checkouts/{$checkoutId}/payment",
            "//evil.test/v1/checkouts/{$checkoutId}/payment",
            "http://datafast.test/v1/checkouts/{$checkoutId}/payment",
            "/v1/checkouts/{$checkoutId}\\payment",
            "/v1/checkouts/../{$checkoutId}/payment",
            "/v1/checkouts/%2e%2e/{$checkoutId}/payment",
            "/v1/checkouts/{$checkoutId}/payment?next=https://evil.test",
            "/v1/checkouts/{$checkoutId}/payment#fragment",
            "/v1/checkouts/{$checkoutId}%00/payment",
            "/v1/registrations/{$checkoutId}/payment",
            "/v1/checkouts/{$checkoutId}/registration",
            "/v1/payments/{$checkoutId}",
        ];
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function initializeDatafastPayment(string $checkoutId): array
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => $checkoutId,
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        $order = $this->makeReadyOrder();
        app(PaymentService::class)->initialize($order, 'datafast', 'card');

        return [
            $order->fresh('items'),
            Payment::query()->where('order_id', $order->id)->firstOrFail(),
        ];
    }

    private function makeReadyOrder(): Order
    {
        $order = Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Cliente Datafast',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '0991112222',
            'customer_identification' => '0912345678',
            'delivery_method' => Order::DELIVERY_HOME,
            'province' => 'Guayas',
            'city' => 'Guayaquil',
            'address' => 'Av Entrega 456',
            'billing_province' => 'Guayas',
            'billing_city' => 'Guayaquil',
            'billing_address' => 'Av Siempre Viva 123',
            'subtotal' => '112.00',
            'shipping_cost' => '0.00',
            'tax_base_zero' => '0.00',
            'tax_base_taxable' => '100.00',
            'tax_amount' => '12.00',
            'total' => '112.00',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Producto Datafast',
            'product_sku' => 'DF-001',
            'quantity' => 2,
            'unit_price' => '56.00',
            'subtotal' => '112.00',
        ]);

        return $order->load('items');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureDatafast(array $overrides = []): void
    {
        config(['payment.datafast' => array_merge([
            'enabled' => true,
            'environment' => 'sandbox',
            'base_url' => 'https://datafast.test',
            'widget_url' => 'https://widgets.datafast.test',
            'entity_id' => 'ENTITY123',
            'authorization' => 'SECRET_DATAFAST_TOKEN',
            'mid' => 'MID123',
            'tid' => 'TID123',
            'eci' => 'ECI123',
            'pserv' => 'PSERV123',
            'risk_name' => 'RISK123',
            'version' => '2',
            'currency' => 'USD',
            'payment_type' => 'DB',
            'test_mode' => 'EXTERNAL',
            'brands' => 'VISA MASTER',
            'connect_timeout' => 2,
            'timeout' => 5,
        ], $overrides)]);
    }
}
