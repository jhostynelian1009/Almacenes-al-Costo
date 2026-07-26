<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class DatafastCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_command_exists_in_artisan_list(): void
    {
        Artisan::call('list');

        $this->assertStringContainsString('payments:datafast-check', Artisan::output());
    }

    public function test_command_runs_without_the_order_option(): void
    {
        $this->configureDatafast();

        $exitCode = Artisan::call('payments:datafast-check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Datafast - diagnostico de preparacion', $output);
        $this->assertStringContainsString('LISTO PARA DATAFAST', $output);
    }

    public function test_disabled_datafast_returns_exit_code_one(): void
    {
        $this->configureDatafast(['enabled' => false]);

        $exitCode = Artisan::call('payments:datafast-check');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[AVISO] Datafast se encuentra desactivado.', Artisan::output());
    }

    public function test_missing_credentials_are_reported_without_exposing_values(): void
    {
        $this->configureDatafast([
            'base_url' => '',
            'widget_url' => '',
            'entity_id' => '',
            'authorization' => 'SECRET_AUTHORIZATION_TOKEN',
            'mid' => '',
            'tid' => '',
            'eci' => '',
            'pserv' => '',
            'risk_name' => '',
            'brands' => '',
        ]);

        $exitCode = Artisan::call('payments:datafast-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[ERROR] API base URL no configurada.', $output);
        $this->assertStringContainsString('[ERROR] URL del widget no configurada.', $output);
        $this->assertStringContainsString('[ERROR] Entity ID no configurado.', $output);
        $this->assertStringContainsString('[OK] Credencial de autorizacion configurado.', $output);
        $this->assertStringNotContainsString('SECRET_AUTHORIZATION_TOKEN', $output);
    }

    public function test_complete_fake_configuration_can_return_ready_when_appropriate(): void
    {
        $this->configureDatafast();

        $exitCode = Artisan::call('payments:datafast-check');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('LISTO PARA DATAFAST', Artisan::output());
    }

    public function test_invalid_order_reference_returns_exit_code_two(): void
    {
        $this->configureDatafast();

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => 'NO-EXISTE']);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('Pedido no encontrado.', Artisan::output());
    }

    public function test_internal_numeric_order_id_is_not_accepted_as_a_public_reference(): void
    {
        $this->configureDatafast();

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => '123']);

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('La referencia del pedido debe ser publica, no un ID interno.', Artisan::output());
    }

    public function test_order_missing_customer_identification_is_reported(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder([
            'customer_identification' => null,
        ]);

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => $order->reference]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[ERROR] Datos de identificacion incompletos.', Artisan::output());
    }

    public function test_order_missing_billing_information_is_reported(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder([
            'billing_province' => null,
            'billing_city' => null,
            'billing_address' => null,
        ]);

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => $order->reference]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[ERROR] Datos de facturacion incompletos.', Artisan::output());
    }

    public function test_order_missing_tax_snapshots_is_reported(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder([
            'tax_base_zero' => null,
            'tax_base_taxable' => null,
            'tax_amount' => null,
        ]);

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => $order->reference]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[ERROR] Desglose tributario no disponible.', Artisan::output());
    }

    public function test_tax_equation_mismatch_is_reported(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder([
            'tax_base_zero' => '0.00',
            'tax_base_taxable' => '100.00',
            'tax_amount' => '11.00',
            'total' => '112.00',
        ]);

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => $order->reference]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('[ERROR] Las bases imponibles mas IVA no coinciden con el total.', Artisan::output());
    }

    public function test_fully_ready_order_returns_exit_code_zero(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder();

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => $order->reference]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[OK] Referencia publica encontrada.', $output);
        $this->assertStringContainsString('LISTO PARA DATAFAST', $output);
    }

    public function test_customer_personal_values_are_not_printed(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder([
            'customer_name' => 'Persona Secreta',
            'customer_email' => 'persona@example.com',
            'customer_phone' => '0999999999',
            'customer_identification' => '0912345678',
        ]);

        Artisan::call('payments:datafast-check', ['--order' => $order->reference]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('Persona Secreta', $output);
        $this->assertStringNotContainsString('persona@example.com', $output);
        $this->assertStringNotContainsString('0912345678', $output);
    }

    public function test_credential_values_are_not_printed(): void
    {
        $this->configureDatafast([
            'authorization' => 'SECRET_DATAFAST_TOKEN',
            'entity_id' => 'ENTITY_SECRET',
            'mid' => 'MID_SECRET',
            'tid' => 'TID_SECRET',
            'eci' => 'ECI_SECRET',
            'pserv' => 'PSERV_SECRET',
        ]);

        Artisan::call('payments:datafast-check');
        $output = Artisan::output();

        foreach ([
            'SECRET_DATAFAST_TOKEN',
            'ENTITY_SECRET',
            'MID_SECRET',
            'TID_SECRET',
            'ECI_SECRET',
            'PSERV_SECRET',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_command_performs_no_http_requests(): void
    {
        $this->configureDatafast();
        Http::fake();

        Artisan::call('payments:datafast-check');

        Http::assertNothingSent();
    }

    public function test_command_does_not_modify_payments_orders_inventory_or_movements(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder();
        $paymentsBefore = Payment::query()->count();
        $ordersBefore = Order::query()->count();
        $itemsBefore = $order->items()->count();
        $movementsBefore = InventoryMovement::query()->count();

        Artisan::call('payments:datafast-check', ['--order' => $order->reference]);

        $this->assertSame($paymentsBefore, Payment::query()->count());
        $this->assertSame($ordersBefore, Order::query()->count());
        $this->assertSame($itemsBefore, $order->items()->count());
        $this->assertSame($movementsBefore, InventoryMovement::query()->count());
    }

    public function test_existing_readiness_logic_is_reused_rather_than_duplicated(): void
    {
        $this->configureDatafast();

        $order = $this->makeOrder();

        $this->mock(PaymentService::class, function (MockInterface $mock) use ($order): void {
            $mock->shouldReceive('datafastReadiness')
                ->once()
                ->withArgs(function (Order $receivedOrder) use ($order): bool {
                    return $receivedOrder->is($order);
                })
                ->andReturn([
                    'enabled' => true,
                    'ready' => true,
                    'message' => 'Pago con tarjeta disponible.',
                    'missing_configuration' => [],
                ]);
        });

        $exitCode = Artisan::call('payments:datafast-check', ['--order' => $order->reference]);

        $this->assertSame(0, $exitCode);
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
    private function makeOrder(array $overrides = []): Order
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
