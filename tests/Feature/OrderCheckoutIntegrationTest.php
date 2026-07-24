<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrderCheckoutIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_checkout_creates_order_reserves_inventory_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $category = $this->category();
        $first = $this->product($category, 'Producto A', ['price' => '10.00', 'sku' => 'SKU-A'], ['stock' => 5]);
        $second = $this->product($category, 'Producto B', ['price' => '5.50', 'sku' => 'SKU-B'], ['stock' => 3]);

        $this->actingAs($user);
        $this->post(route('cart.items.store'), ['product' => $first->slug, 'quantity' => 2]);
        $this->post(route('cart.items.store'), ['product' => $second->slug, 'quantity' => 1]);

        $this->post(route('checkout.review'), [
            'customer_name' => 'Cliente Integración',
            'customer_email' => 'integracion@example.com',
            'customer_phone' => '0998887777',
            'customer_identification' => '0912345678',
            'billing_province' => 'Guayas',
            'billing_city' => 'Guayaquil',
            'billing_address' => 'Calle Principal 456',
            'delivery_method' => Order::DELIVERY_HOME,
            'province' => 'Guayas',
            'city' => 'Guayaquil',
            'address' => 'Calle Principal 456',
        ])->assertOk();

        $response = $this->post(route('checkout.store'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $order = Order::query()->with('items')->firstOrFail();

        $response->assertRedirect(route('orders.confirmation', $order->reference));

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('25.50', $order->subtotal);
        $this->assertSame('25.50', $order->total);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertCount(2, $order->items);

        $this->assertSame('0912345678', $order->customer_identification);
        $this->assertSame('Guayas', $order->billing_province);
        $this->assertSame('Guayaquil', $order->billing_city);
        $this->assertSame('Calle Principal 456', $order->billing_address);

        config([
            'payment.datafast' => [
                'enabled' => true,
                'environment' => 'production',
                'base_url' => 'https://datafast.test',
                'widget_url' => 'https://widgets.datafast.test',
                'entity_id' => 'ENTITY123',
                'authorization' => 'TOKEN123',
                'mid' => 'MID123',
                'tid' => 'TID123',
                'eci' => 'ECI123',
                'pserv' => 'PSERV123',
                'risk_name' => 'RISK123',
                'version' => '2',
                'currency' => 'USD',
                'payment_type' => 'DB',
                'brands' => 'VISA MASTER',
            ],
        ]);

        $readiness = app(PaymentService::class)->datafastReadiness($order->fresh(['items']));
        $this->assertFalse($readiness['ready']);
        $this->assertSame([], $readiness['missing_configuration']);
        $this->assertSame('faltan bases imponibles e IVA autorizados para Datafast.', $readiness['message']);

        $firstItem = $order->items->firstWhere('product_sku', 'SKU-A');
        $this->assertSame('Producto A', $firstItem->product_name);
        $this->assertSame('20.00', $firstItem->subtotal);

        $this->assertSame(5, $first->inventory()->first()->fresh()->stock);
        $this->assertSame(2, $first->inventory()->first()->fresh()->reserved_stock);
        $this->assertSame(3, $second->inventory()->first()->fresh()->stock);
        $this->assertSame(1, $second->inventory()->first()->fresh()->reserved_stock);

        $this->assertSame(2, InventoryMovement::query()->where('type', InventoryMovement::TYPE_RESERVE)->count());

        $this->get(route('cart.index'))->assertSee('Tu carrito está vacío');

        // Verify the confirmation route uses the public order reference
        $route = Route::getRoutes()->getByName('orders.confirmation');
        $this->assertNotNull($route);
        $this->assertSame('pedido/{orderReference}/confirmacion', $route->uri());

        $response = $this->get(route('orders.confirmation', $order->reference))
            ->assertOk()
            ->assertSee($order->reference)
            ->assertSee('Pendiente de pago');

        // Verify that an internal-ID confirmation URL or link is not rendered
        $response->assertDontSee('/pedido/'.$order->id.'/confirmacion');
        $response->assertDontSee('/pedido/'.$order->id);
        $response->assertDontSee(url('/pedido/'.$order->id.'/confirmacion'));
        $response->assertDontSee(url('/pedido/'.$order->id));

        // Verify that the internal order ID is not used as the public route identifier (fails with 404)
        $this->get(route('orders.confirmation', $order->id))
            ->assertNotFound();
    }

    public function test_guest_checkout_leaves_user_id_null(): void
    {
        $product = $this->product($this->category(), 'Producto invitado');
        $this->post(route('cart.items.store'), ['product' => $product->slug]);
        $this->post(route('checkout.review'), $this->checkoutPayload());
        $this->post(route('checkout.store'))->assertRedirect();

        $this->assertNull(Order::query()->first()->user_id);
    }

    public function test_failed_reservation_rolls_back_order_and_keeps_cart(): void
    {
        $product = $this->product($this->category(), 'Producto conflictivo', [], ['stock' => 1]);
        $this->post(route('cart.items.store'), ['product' => $product->slug]);

        $product->inventory()->update(['stock' => 1, 'reserved_stock' => 1]);

        $this->post(route('checkout.review'), $this->checkoutPayload());
        $this->post(route('checkout.store'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::query()->count());
        $this->get(route('cart.index'))->assertSee('Producto conflictivo');
    }

    public function test_repeated_confirmation_is_idempotent(): void
    {
        $product = $this->product($this->category(), 'Producto idempotente', [], ['stock' => 4]);
        $this->post(route('cart.items.store'), ['product' => $product->slug, 'quantity' => 2]);
        $this->post(route('checkout.review'), $this->checkoutPayload());

        $first = $this->post(route('checkout.store'))->assertRedirect();
        $second = $this->post(route('checkout.store'))->assertRedirect();

        $order = Order::query()->firstOrFail();
        $first->assertRedirect(route('orders.confirmation', $order->reference));
        $second->assertRedirect(route('orders.confirmation', $order->reference));

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertSame(2, $product->inventory()->first()->fresh()->reserved_stock);
    }

    public function test_no_payment_or_admin_order_routes_are_registered(): void
    {
        foreach ([
            'admin.orders.index',
            'checkout.index',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Unexpected route {$routeName}");
        }
    }

    /**
     * @return array<string, string>
     */
    private function checkoutPayload(): array
    {
        return [
            'customer_name' => 'Cliente Invitado',
            'customer_email' => 'invitado@example.com',
            'customer_phone' => '0991234567',
            'customer_identification' => '0912345678',
            'billing_province' => 'Guayas',
            'billing_city' => 'Guayaquil',
            'billing_address' => 'Calle 1',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function category(array $attributes = []): Category
    {
        return Category::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $productAttributes
     * @param  array<string, int>  $inventoryAttributes
     */
    private function product(
        Category $category,
        string $name,
        array $productAttributes = [],
        array $inventoryAttributes = [],
    ): Product {
        $product = Product::factory()
            ->for($category)
            ->create(array_merge([
                'name' => $name,
                'price' => '10.00',
                'is_active' => true,
            ], $productAttributes));

        $product->inventory()->update(array_merge([
            'stock' => 2,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ], $inventoryAttributes));

        return $product;
    }
}
