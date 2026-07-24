<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_routes_are_public_and_registered(): void
    {
        $this->assertSame('checkout', Route::getRoutes()->getByName('checkout.create')->uri());
        $this->assertSame('checkout/review', Route::getRoutes()->getByName('checkout.review')->uri());
        $this->assertSame('checkout', Route::getRoutes()->getByName('checkout.store')->uri());
        $this->assertSame(
            'pedido/{orderReference}/confirmacion',
            Route::getRoutes()->getByName('orders.confirmation')->uri(),
        );
    }

    public function test_guest_and_authenticated_users_can_access_checkout_flow(): void
    {
        $product = $this->seedCartProduct();

        $this->post(route('cart.items.store'), ['product' => $product->slug]);

        $this->get(route('checkout.create'))->assertOk()->assertViewIs('public.checkout.create');

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.create'))
            ->assertOk();
    }

    public function test_checkout_validation_and_conditional_delivery_fields(): void
    {
        $product = $this->seedCartProduct();
        $this->post(route('cart.items.store'), ['product' => $product->slug]);

        $this->post(route('checkout.review'), [])
            ->assertSessionHasErrors(['customer_name', 'customer_email', 'customer_phone', 'customer_identification', 'billing_province', 'billing_city', 'billing_address', 'delivery_method']);

        $this->post(route('checkout.review'), [
            'customer_name' => 'Ana Pérez',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '0987654321',
            'delivery_method' => Order::DELIVERY_HOME,
        ])->assertSessionHasErrors(['customer_identification', 'billing_province', 'billing_city', 'billing_address', 'province', 'city', 'address']);

        $this->post(route('checkout.review'), [
            'customer_name' => 'Ana Pérez',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '0987654321',
            'customer_identification' => '0912345678',
            'billing_province' => 'Pichincha',
            'billing_city' => 'Quito',
            'billing_address' => 'Av. Amazonas 123',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '1.00',
            'total' => '1.00',
            'user_id' => 999,
            'status' => 'paid',
        ])->assertSessionHasErrors(['subtotal', 'total', 'user_id', 'status']);
    }

    public function test_review_page_recalculates_totals_on_server(): void
    {
        $product = $this->product($this->category(), 'Producto checkout', ['price' => '12.34'], ['stock' => 2]);
        $this->post(route('cart.items.store'), ['product' => $product->slug, 'quantity' => 2]);

        $this->post(route('checkout.review'), [
            'customer_name' => 'Ana Pérez',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '0987654321',
            'customer_identification' => '0912345678',
            'billing_province' => 'Pichincha',
            'billing_city' => 'Quito',
            'billing_address' => 'Av. Amazonas 123',
            'delivery_method' => Order::DELIVERY_HOME,
            'province' => 'Pichincha',
            'city' => 'Quito',
            'address' => 'Av. Amazonas 123',
        ])->assertOk()
            ->assertViewIs('public.checkout.review')
            ->assertSee('$ 24.68')
            ->assertSee('Costo por confirmar')
            ->assertSee('Confirmar pedido');
    }

    public function test_store_pickup_does_not_require_address_fields(): void
    {
        $product = $this->seedCartProduct();
        $this->post(route('cart.items.store'), ['product' => $product->slug]);

        $this->post(route('checkout.review'), [
            'customer_name' => 'Luis Gómez',
            'customer_email' => 'luis@example.com',
            'customer_phone' => '0991111111',
            'customer_identification' => '0912345678',
            'billing_province' => 'Guayas',
            'billing_city' => 'Guayaquil',
            'billing_address' => 'Calle 1',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
        ])->assertOk()->assertSee('$ 0.00');
    }

    public function test_invalid_order_reference_returns_not_found(): void
    {
        $this->get(route('orders.confirmation', 'referencia-inexistente'))->assertNotFound();
    }

    public function test_empty_cart_blocks_checkout(): void
    {
        $this->get(route('checkout.create'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('warning');
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
            'stock' => 3,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ], $inventoryAttributes));

        return $product;
    }

    private function seedCartProduct(): Product
    {
        return $this->product($this->category(), 'Producto base checkout');
    }
}
