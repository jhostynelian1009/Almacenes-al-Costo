<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_routes_are_public_and_registered(): void
    {
        $routes = [
            'cart.index' => ['GET', 'carrito'],
            'cart.items.store' => ['POST', 'carrito/items'],
            'cart.items.update' => ['PATCH', 'carrito/items/{product}'],
            'cart.items.destroy' => ['DELETE', 'carrito/items/{product}'],
            'cart.clear' => ['DELETE', 'carrito'],
        ];

        foreach ($routes as $name => [$method, $uri]) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing route {$name}");
            $this->assertContains($method, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertNotContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_guest_and_authenticated_users_can_access_cart(): void
    {
        $this->get(route('cart.index'))->assertOk()->assertViewIs('public.cart.index');

        $this->actingAs(User::factory()->create())
            ->get(route('cart.index'))
            ->assertOk();
    }

    public function test_guest_can_add_update_remove_and_clear_cart_items(): void
    {
        $category = $this->category();
        $product = $this->product($category, 'Producto carrito', [], ['stock' => 5]);

        $this->post(route('cart.items.store'), [
            'product' => $product->slug,
            'quantity' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Producto carrito')
            ->assertSee('$ 10.00');

        $this->post(route('cart.items.store'), [
            'product' => $product->slug,
            'quantity' => 2,
        ])->assertRedirect();

        $this->get(route('cart.index'))->assertSee('value="3"', false);

        $this->patch(route('cart.items.update', $product->slug), [
            'quantity' => 2,
        ])->assertRedirect()->assertSessionHas('success');

        $this->delete(route('cart.items.destroy', $product->slug))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->post(route('cart.items.store'), [
            'product' => $product->slug,
        ])->assertRedirect();

        $this->delete(route('cart.clear'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success');

        $this->get(route('cart.index'))->assertSee('Tu carrito está vacío');
    }

    public function test_cart_persists_in_session_and_shows_navbar_count(): void
    {
        $category = $this->category();
        $first = $this->product($category, 'Primer producto', [], ['stock' => 3]);
        $second = $this->product($category, 'Segundo producto', [], ['stock' => 2]);

        $this->post(route('cart.items.store'), ['product' => $first->slug, 'quantity' => 2]);
        $this->post(route('cart.items.store'), ['product' => $second->slug, 'quantity' => 1]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('>3<', false)
            ->assertSee('productos en el carrito', false);
    }

    public function test_invalid_quantities_and_unavailable_products_are_rejected(): void
    {
        $category = $this->category();
        $product = $this->product($category, 'Producto limitado', [], ['stock' => 2]);

        $this->patch(route('cart.items.update', $product->slug), [
            'quantity' => 0,
        ])->assertSessionHasErrors('quantity');

        $this->post(route('cart.items.store'), [
            'product' => $product->slug,
            'quantity' => 3,
        ])->assertRedirect()->assertSessionHas('error');

        $soldOut = $this->product($category, 'Agotado', [], ['stock' => 1, 'reserved_stock' => 1]);
        $this->post(route('cart.items.store'), [
            'product' => $soldOut->slug,
        ])->assertRedirect()->assertSessionHas('error');

        $hidden = $this->product($category, 'Oculto', ['is_active' => false]);
        $this->post(route('cart.items.store'), [
            'product' => $hidden->slug,
        ])->assertNotFound();
    }

    public function test_cart_ignores_manipulated_monetary_fields_and_does_not_touch_inventory(): void
    {
        $category = $this->category();
        $product = $this->product($category, 'Producto precio', ['price' => '25.50'], ['stock' => 4]);

        $this->post(route('cart.items.store'), [
            'product' => $product->slug,
            'price' => '1.00',
            'subtotal' => '1.00',
            'total' => '1.00',
        ])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertSee('$ 25.50')
            ->assertSee('$ 25.50');

        $inventory = $product->inventory()->firstOrFail();
        $this->assertSame(4, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
        $this->assertSame(0, InventoryMovement::query()->count());
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
            'stock' => 1,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ], $inventoryAttributes));

        return $product;
    }
}
