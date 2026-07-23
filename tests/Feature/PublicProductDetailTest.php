<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicProductController;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_show_route_contract_and_public_access(): void
    {
        $route = Route::getRoutes()->getByName('catalog.show');

        $this->assertNotNull($route);
        $this->assertSame('product/{product}', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(PublicProductController::class.'@show', $route->getActionName());
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertCount(48, Route::getRoutes());
    }

    public function test_visible_product_is_accessible_by_slug_without_exposing_internal_data(): void
    {
        Storage::fake('public');
        $path = 'products/detail-visible.jpg';
        Storage::disk('public')->put($path, 'image bytes');

        $category = $this->category(['name' => 'Herramientas', 'slug' => 'herramientas']);
        $product = $this->product($category, 'Taladro Percutor', [
            'slug' => 'taladro-percutor',
            'description' => 'Descripción <strong>segura</strong>.',
            'price' => '199.90',
            'sku' => 'SKU-INTERNO-001',
            'image' => $path,
        ], ['stock' => 5, 'reserved_stock' => 2, 'min_stock' => 10]);

        $response = $this->get(route('catalog.show', $product->slug))->assertOk();

        $response
            ->assertViewIs('public.products.show')
            ->assertSee('Taladro Percutor')
            ->assertSee('Descripción &lt;strong&gt;segura&lt;/strong&gt;.', false)
            ->assertSee('$ 199.90')
            ->assertSee('Herramientas')
            ->assertSee('Disponible')
            ->assertSee('Volver al catálogo')
            ->assertSee(route('catalog.index', ['category' => 'herramientas']), false)
            ->assertSee('storage/products/detail-visible.jpg', false)
            ->assertDontSee('SKU-INTERNO-001')
            ->assertDontSee('/product/'.$product->id, false)
            ->assertDontSee('reserved_stock')
            ->assertDontSee('min_stock')
            ->assertDontSee('Comprar')
            ->assertSee('Agregar al carrito');

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_missing_image_renders_the_documented_placeholder(): void
    {
        $category = $this->category();
        $product = $this->product($category, 'Producto sin imagen', ['image' => null]);

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Sin imagen')
            ->assertSee('Sin imagen para Producto sin imagen');
    }

    public function test_sold_out_product_remains_public_and_shows_agotado(): void
    {
        $category = $this->category();
        $product = $this->product(
            $category,
            'Producto agotado visible',
            [],
            ['stock' => 4, 'reserved_stock' => 4, 'min_stock' => 0],
        );

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Agotado')
            ->assertDontSee('Disponible');
    }

    public function test_min_stock_does_not_affect_public_availability(): void
    {
        $category = $this->category();
        $product = $this->product(
            $category,
            'Producto con mínimo alto',
            [],
            ['stock' => 2, 'reserved_stock' => 0, 'min_stock' => 50],
        );

        $this->get(route('catalog.show', $product->slug))
            ->assertOk()
            ->assertSee('Disponible');
    }

    public function test_hidden_or_missing_products_return_a_uniform_404(): void
    {
        $publicCategory = $this->category(['slug' => 'publica']);
        $inactiveCategory = $this->category(['slug' => 'inactiva', 'is_active' => false]);
        $inactiveAncestor = $this->category(['is_active' => false]);
        $hiddenChild = $this->category(['parent_id' => $inactiveAncestor->id, 'slug' => 'hija-oculta']);

        $inactiveProduct = $this->product($publicCategory, 'Producto inactivo', ['is_active' => false]);
        $deletedProduct = $this->product($publicCategory, 'Producto eliminado');
        $deletedProduct->delete();
        $categoryHidden = $this->product($inactiveCategory, 'Producto categoría inactiva');
        $ancestorHidden = $this->product($hiddenChild, 'Producto ancestro inactivo');
        $withoutInventory = $this->product($publicCategory, 'Producto sin inventario');
        $withoutInventory->inventory()->delete();

        foreach ([
            'slug-inexistente',
            $inactiveProduct->slug,
            $deletedProduct->slug,
            $categoryHidden->slug,
            $ancestorHidden->slug,
            $withoutInventory->slug,
        ] as $slug) {
            $this->get(route('catalog.show', $slug))->assertNotFound();
        }
    }

    public function test_detail_does_not_mutate_inventory_or_create_movements(): void
    {
        $category = $this->category();
        $product = $this->product(
            $category,
            'Producto sin mutaciones',
            [],
            ['stock' => 7, 'reserved_stock' => 1, 'min_stock' => 3],
        );
        $inventoryBefore = $product->inventory->getAttributes();
        $movementCountBefore = InventoryMovement::query()->count();

        $this->get(route('catalog.show', $product->slug))->assertOk();

        $this->assertSame($inventoryBefore, $product->inventory->fresh()->getAttributes());
        $this->assertSame($movementCountBefore, InventoryMovement::query()->count());
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
