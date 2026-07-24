<?php

namespace Tests\Feature;

use App\Events\PaymentCompleted;
use App\Jobs\ReleaseReservedStock;
use App\Jobs\UpdateInventoryOnPayment;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatafastPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->configureDatafast();
    }

    public function test_payment_page_hides_datafast_when_disabled(): void
    {
        config(['payment.datafast.enabled' => false]);

        $response = $this->get(route('orders.payment.show', $this->makeReadyOrder()->reference));

        $response->assertOk();
        $response->assertDontSee('value="datafast"', false);
        $response->assertDontSee('Pagar con tarjeta');
    }

    public function test_enabled_but_unready_datafast_is_not_selectable(): void
    {
        $order = $this->makeUnreadyOrder();

        $response = $this->get(route('orders.payment.show', $order->reference));

        $response->assertOk();
        $response->assertSee('faltan datos', false);
        $response->assertDontSee('value="datafast"', false);
    }

    public function test_process_redirects_to_widget_after_checkout_initialization(): void
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => 'checkoutwidget1',
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        [$order] = $this->makeReadyOrderWithInventory();

        $response = $this->post(route('orders.payment.process', $order->reference), [
            'payment_method' => 'datafast',
            'amount' => '0.01',
            'currency' => 'EUR',
            'payment_status' => 'completed',
            'order_status' => 'paid',
        ]);

        $payment = Payment::query()->firstOrFail();

        $response->assertRedirect(route('orders.payment.datafast.widget', [
            'orderReference' => $order->reference,
            'paymentReference' => $payment->transaction_id,
        ]));
        $this->assertSame('112.00', (string) $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
    }

    public function test_widget_page_renders_official_form_without_card_fields_or_secrets(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutwidget2');

        $response = $this->get(route('orders.payment.datafast.widget', [
            'orderReference' => $order->reference,
            'paymentReference' => $payment->transaction_id,
        ]));

        $response->assertOk();
        $response->assertSee('checkoutwidget2', false);
        $response->assertSee('https://widgets.datafast.test/v1/paymentWidgets.js?checkoutId=checkoutwidget2', false);
        $response->assertSee('class="paymentWidgets"', false);
        $response->assertSee('data-brands="VISA MASTER"', false);
        $response->assertDontSee('card_number', false);
        $response->assertDontSee('card-number', false);
        $response->assertDontSee('cvv', false);
        $response->assertDontSee('expiration', false);
        $response->assertDontSee('"id":'.$order->id, false);
        $response->assertDontSee('/pedido/'.$order->id, false);
        $response->assertDontSee('SECRET_DATAFAST_TOKEN');
        $response->assertDontSee('ENTITY123');
    }

    public function test_production_approved_code_completes_payment_and_dispatches_completion_event(): void
    {
        Event::fake([PaymentCompleted::class]);

        [$order, $payment] = $this->initializeDatafastPayment('checkoutapproved1');
        $this->fakeStatusResponse('checkoutapproved1', $payment, '000.000.000');

        $this->get($this->resultUrl($order, $payment, 'checkoutapproved1'))->assertOk();

        Event::assertDispatchedTimes(PaymentCompleted::class, 1);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
    }

    public function test_approved_result_dispatches_one_inventory_settlement_job(): void
    {
        Queue::fake();

        [$order, $payment] = $this->initializeDatafastPayment('checkoutqueue1');
        $this->fakeStatusResponse('checkoutqueue1', $payment, '000.000.000');

        $this->get($this->resultUrl($order, $payment, 'checkoutqueue1'))->assertOk();
        $this->get($this->resultUrl($order, $payment->fresh(), 'checkoutqueue1'))->assertOk();

        Queue::assertPushed(UpdateInventoryOnPayment::class, 1);
        Queue::assertNotPushed(ReleaseReservedStock::class);
    }

    public function test_duplicate_approved_return_does_not_move_inventory_twice(): void
    {
        [$order, $payment, $product] = $this->initializeDatafastPayment('checkoutinventory1');
        $this->fakeStatusResponse('checkoutinventory1', $payment, '000.000.000');

        $this->get($this->resultUrl($order, $payment, 'checkoutinventory1'))->assertOk();
        $this->get($this->resultUrl($order, $payment->fresh(), 'checkoutinventory1'))->assertOk();

        $inventory = $product->inventory->fresh();
        $this->assertSame(3, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
    }

    public function test_sandbox_approval_codes_complete_only_in_sandbox(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutsandbox1');
        $this->fakeStatusResponse('checkoutsandbox1', $payment, '000.100.110');

        $this->get($this->resultUrl($order, $payment, 'checkoutsandbox1'))->assertOk();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);

        config(['payment.datafast.environment' => 'production']);
        [$productionOrder, $productionPayment] = $this->initializeDatafastPayment('checkoutprodcode1');
        $this->fakeStatusResponse('checkoutprodcode1', $productionPayment, '000.100.112');

        $this->get($this->resultUrl($productionOrder, $productionPayment, 'checkoutprodcode1'))->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $productionPayment->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $productionOrder->fresh()->status);
    }

    public function test_checkout_creation_and_unknown_codes_do_not_complete_payment(): void
    {
        foreach (['000.200.100', '000.900.999'] as $code) {
            [$order, $payment] = $this->initializeDatafastPayment('checkout'.str_replace('.', '', $code));
            $this->fakeStatusResponse((string) $this->storedCheckoutId($payment), $payment, $code);

            $this->get($this->resultUrl($order, $payment, (string) $this->storedCheckoutId($payment)))->assertOk();

            $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
            $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        }
    }

    public function test_rejected_terminal_status_uses_existing_failed_transition_once(): void
    {
        [$order, $payment, $product] = $this->initializeDatafastPayment('checkoutfailed1');
        $this->fakeStatusResponse('checkoutfailed1', $payment, '800.100.100');

        $this->get($this->resultUrl($order, $payment, 'checkoutfailed1'))->assertOk();
        $this->get($this->resultUrl($order, $payment->fresh(), 'checkoutfailed1'))->assertOk();

        $this->assertSame(Order::STATUS_CANCELED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(5, $product->inventory->fresh()->stock);
        $this->assertSame(0, $product->inventory->fresh()->reserved_stock);
    }

    public function test_mismatched_status_response_does_not_approve_payment(): void
    {
        foreach ([
            ['amount' => '111.99'],
            ['currency' => 'EUR'],
            ['merchantTransactionId' => 'DFWRONGREFERENCE'],
            ['checkoutId' => 'checkoutother1'],
        ] as $override) {
            [$order, $payment] = $this->initializeDatafastPayment('checkoutmismatch'.Str::lower((string) Str::ulid()));
            $this->fakeStatusResponse((string) $this->storedCheckoutId($payment), $payment, '000.000.000', $override);

            $this->get($this->resultUrl($order, $payment, (string) $this->storedCheckoutId($payment)))
                ->assertOk()
                ->assertSee('pendiente de verificacion', false);

            $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
            $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->fresh()->status);
        }
    }

    public function test_malformed_status_response_displays_safe_spanish_message(): void
    {
        [$order, $payment] = $this->initializeDatafastPayment('checkoutbadstatus1');

        Http::fake([
            'https://datafast.test/v1/checkouts/checkoutbadstatus1/payment*' => Http::response('not-json', 200),
        ]);

        $this->get($this->resultUrl($order, $payment, 'checkoutbadstatus1'))
            ->assertOk()
            ->assertSee('pendiente de verificacion', false);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    /**
     * @return array{0: Order, 1: Payment, 2: Product}
     */
    private function initializeDatafastPayment(string $checkoutId): array
    {
        Http::fake([
            'https://datafast.test/v1/checkouts' => Http::response([
                'id' => $checkoutId,
                'result' => ['code' => '000.200.100'],
            ], 200),
        ]);

        [$order, $product] = $this->makeReadyOrderWithInventory();
        app(PaymentService::class)->initialize($order, 'datafast', 'card');

        return [
            $order->fresh('items'),
            Payment::query()->where('order_id', $order->id)->firstOrFail(),
            $product,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeStatusResponse(
        string $checkoutId,
        Payment $payment,
        string $code,
        array $overrides = [],
    ): void {
        Http::fake([
            "https://datafast.test/v1/checkouts/{$checkoutId}/payment*" => Http::response(array_merge([
                'id' => 'provider-payment-1',
                'checkoutId' => $checkoutId,
                'merchantTransactionId' => $payment->transaction_id,
                'amount' => '112.00',
                'currency' => 'USD',
                'result' => ['code' => $code],
            ], $overrides), 200),
        ]);
    }

    private function resultUrl(Order $order, Payment $payment, string $checkoutId): string
    {
        return route('orders.payment.datafast.result', [
            'orderReference' => $order->reference,
            'paymentReference' => $payment->transaction_id,
            'resourcePath' => "/v1/checkouts/{$checkoutId}/payment",
        ]);
    }

    private function storedCheckoutId(Payment $payment): ?string
    {
        $payloads = $payment->transactions()->latest('id')->pluck('payload');

        foreach ($payloads as $payload) {
            if (is_array($payload) && isset($payload['checkout_id'])) {
                return (string) $payload['checkout_id'];
            }
        }

        return null;
    }

    /**
     * @return array{0: Order, 1: Product}
     */
    private function makeReadyOrderWithInventory(): array
    {
        $sku = 'DF-'.Str::upper((string) Str::ulid());

        $product = Product::factory()
            ->for(Category::factory()->create())
            ->create([
                'name' => 'Producto Datafast',
                'sku' => $sku,
                'price' => '56.00',
                'is_active' => true,
            ]);

        $product->inventory()->update([
            'stock' => 5,
            'reserved_stock' => 2,
            'min_stock' => 0,
        ]);

        $order = $this->makeReadyOrder();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => '56.00',
            'subtotal' => '112.00',
        ]);

        return [$order->load('items'), $product];
    }

    private function makeReadyOrder(): Order
    {
        return Order::query()->create([
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
    }

    private function makeUnreadyOrder(): Order
    {
        $order = Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Cliente Sin Tax',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '0991112222',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '25.00',
            'shipping_cost' => '0.00',
            'total' => '25.00',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Producto',
            'product_sku' => 'SKU',
            'quantity' => 1,
            'unit_price' => '25.00',
            'subtotal' => '25.00',
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
