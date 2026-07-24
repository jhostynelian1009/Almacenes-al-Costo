<?php

namespace Tests\Feature;

use App\Events\PaymentCompleted;
use App\Exceptions\PaymentOperationException;
use App\Jobs\UpdateInventoryOnPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\DatafastGateway;
use App\Payments\DeunaGateway;
use App\Payments\ManualPaymentAdapter;
use App\Services\Payments\PaymentFactory;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatafastGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->configureDatafast();
    }

    public function test_factory_resolves_datafast_and_retains_existing_gateways(): void
    {
        $factory = new PaymentFactory;

        $this->assertInstanceOf(DatafastGateway::class, $factory->make('datafast'));
        $this->assertInstanceOf(ManualPaymentAdapter::class, $factory->make('manual'));
        $this->assertInstanceOf(ManualPaymentAdapter::class, $factory->make('transfer'));
        $this->assertInstanceOf(DeunaGateway::class, $factory->make('deuna'));
    }

    public function test_disabled_datafast_cannot_be_initialized(): void
    {
        config(['payment.datafast.enabled' => false]);
        Http::fake();

        $this->expectException(PaymentOperationException::class);

        app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');

        Http::assertNothingSent();
    }

    public function test_missing_configuration_fails_safely_without_exposing_values(): void
    {
        config([
            'payment.datafast.base_url' => '',
            'payment.datafast.entity_id' => '',
            'payment.datafast.authorization' => 'SECRET_AUTHORIZATION_TOKEN',
        ]);
        Http::fake();

        try {
            app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');
            $this->fail('Datafast should not initialize with incomplete configuration.');
        } catch (PaymentOperationException $exception) {
            $this->assertStringContainsString('Pago con tarjeta no disponible', $exception->getMessage());
            $this->assertStringNotContainsString('SECRET_AUTHORIZATION_TOKEN', $exception->getMessage());
        }

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_checkout_request_uses_configured_endpoint_and_trusted_order_values(): void
    {
        Event::fake();
        Queue::fake();
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => 'checkout123456',
                'result' => [
                    'code' => '000.200.100',
                    'description' => 'checkout created',
                ],
            ], 200),
        ]);

        $order = $this->makeReadyOrder();

        $response = app(PaymentService::class)->initialize($order, 'datafast', 'card', [
            'amount' => '0.01',
            'currency' => 'EUR',
            'status' => 'completed',
        ]);

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('pending', $response->status);
        $this->assertSame('checkout123456', $response->payload['checkout_id']);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertSame('112.00', (string) $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertSame('000.200.100', $payment->gateway_response_code);
        $this->assertStringStartsWith('DF', (string) $payment->transaction_id);
        $this->assertNotSame((string) $payment->id, (string) $payment->transaction_id);

        Http::assertSent(function ($request): bool {
            $body = urldecode($request->body());

            return $request->url() === 'https://datafast.test/v1/checkouts'
                && $request->header('Authorization')[0] === 'Bearer SECRET_DATAFAST_TOKEN'
                && str_contains($body, 'entityId=ENTITY123')
                && str_contains($body, 'amount=112.00')
                && ! str_contains($body, 'amount=0.01')
                && str_contains($body, 'currency=USD')
                && ! str_contains($body, 'currency=EUR')
                && str_contains($body, 'paymentType=DB')
                && str_contains($body, 'customParameters[SHOPPER_MID]=MID123')
                && str_contains($body, 'customParameters[SHOPPER_TID]=TID123')
                && str_contains($body, 'customParameters[SHOPPER_ECI]=ECI123')
                && str_contains($body, 'customParameters[SHOPPER_PSERV]=PSERV123')
                && str_contains($body, 'customParameters[SHOPPER_VERSIONDF]=2')
                && str_contains($body, 'risk.parameters[USER_DATA2]=RISK123')
                && str_contains($body, 'customParameters[SHOPPER_VAL_BASE0]=0.00')
                && str_contains($body, 'customParameters[SHOPPER_VAL_BASEIMP]=100.00')
                && str_contains($body, 'customParameters[SHOPPER_VAL_IVA]=12.00')
                && str_contains($body, 'customer.givenName=Cliente')
                && str_contains($body, 'customer.surname=Datafast')
                && str_contains($body, 'customer.email=cliente@example.com')
                && str_contains($body, 'customer.mobile=0991112222')
                && str_contains($body, 'customer.identificationDocId=0912345678')
                && str_contains($body, 'billing.street1=Av Siempre Viva 123')
                && str_contains($body, 'shipping.street1=Av Entrega 456')
                && str_contains($body, 'cart.items[0].name=Producto Datafast')
                && str_contains($body, 'cart.items[0].sku=DF-001')
                && str_contains($body, 'cart.items[0].quantity=2')
                && str_contains($body, 'cart.items[0].price=56.00')
                && str_contains($body, 'cart.items[0].totalAmount=112.00')
                && str_contains($body, 'shopperResultUrl=')
                && str_contains($body, 'testMode=EXTERNAL');
        });

        $transactionPayload = json_encode($payment->transactions()->pluck('payload')->all());
        $this->assertIsString($transactionPayload);
        $this->assertStringContainsString('checkout123456', $transactionPayload);
        $this->assertStringNotContainsString('SECRET_DATAFAST_TOKEN', $transactionPayload);
        $this->assertStringNotContainsString('ENTITY123', $transactionPayload);
        Event::assertNotDispatched(PaymentCompleted::class);
        Queue::assertNotPushed(UpdateInventoryOnPayment::class);
    }

    public function test_production_checkout_omits_sandbox_test_mode(): void
    {
        config(['payment.datafast.environment' => 'production']);
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => 'checkoutprod1',
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');

        Http::assertSent(fn ($request): bool => ! str_contains(urldecode($request->body()), 'testMode='));
    }

    public function test_missing_authoritative_tax_blocks_initialization_without_http(): void
    {
        Http::fake();

        $order = $this->makeReadyOrder(['tax_base_zero' => null]);

        $this->expectException(PaymentOperationException::class);

        app(PaymentService::class)->initialize($order, 'datafast', 'card');

        Http::assertNothingSent();
    }

    public function test_repeated_initialization_reuses_stored_checkout_without_second_provider_post(): void
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => 'checkoutreuse1',
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        $order = $this->makeReadyOrder();
        $service = app(PaymentService::class);

        $first = $service->initialize($order, 'datafast', 'card');
        $second = $service->initialize($order, 'datafast', 'card');

        $this->assertSame($first->transactionId, $second->transactionId);
        $this->assertSame('checkoutreuse1', $second->payload['checkout_id']);
        $this->assertTrue($second->payload['idempotent_reuse']);
        $this->assertSame(1, Payment::query()->count());
        Http::assertSentCount(1);
    }

    public function test_checkout_creation_code_is_not_payment_approval(): void
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => 'checkoutonly1',
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        $order = $this->makeReadyOrder();
        app(PaymentService::class)->initialize($order, 'datafast', 'card');

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_PENDING, Payment::query()->firstOrFail()->status);
    }

    public function test_checkout_missing_id_fails_safely(): void
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        $this->expectException(PaymentOperationException::class);

        app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');
    }

    public function test_checkout_malformed_json_fails_safely(): void
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response('not-json', 200),
        ]);

        $this->expectException(PaymentOperationException::class);

        app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');
    }

    public function test_checkout_http_and_transport_failures_fail_safely(): void
    {
        foreach ([400, 500] as $status) {
            Http::fake([
                'https://datafast.test/v1/checkouts' => Http::response(['error' => 'provider error'], $status),
            ]);

            try {
                app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');
                $this->fail("HTTP {$status} should fail safely.");
            } catch (PaymentOperationException) {
                $this->assertTrue(true);
            }
        }

        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->expectException(PaymentOperationException::class);

        app(PaymentService::class)->initialize($this->makeReadyOrder(), 'datafast', 'card');
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeReadyOrder(array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
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
        ], $overrides));

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
}
