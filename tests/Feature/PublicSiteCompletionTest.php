<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicSiteCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_featured_products_banners_and_public_navigation(): void
    {
        $category = Category::factory()->create(['name' => 'Destacados']);
        $available = $this->createPublicProduct($category, 'Producto disponible destacado', 3, 1);
        $soldOut = $this->createPublicProduct($category, 'Producto agotado destacado', 2, 2);
        $hidden = Product::factory()->for($category)->inactive()->create(['name' => 'Producto oculto destacado']);

        $response = $this->get(route('home'))->assertOk();

        $response
            ->assertSee('Precios al costo en todo el catálogo')
            ->assertSee('Encuentra lo que necesitas por categoría')
            ->assertSee('Ver catálogo')
            ->assertSee('Ver categorías')
            ->assertSee($available->name)
            ->assertSee($soldOut->name)
            ->assertSee('Disponible')
            ->assertSee('Agotado')
            ->assertSee(route('catalog.show', $available->slug), false)
            ->assertDontSee($hidden->name)
            ->assertSee('aria-label="Navegación principal"', false)
            ->assertSee('Todos los derechos reservados');
    }

    public function test_home_limits_featured_products_to_eight_and_prioritizes_available_stock(): void
    {
        $category = Category::factory()->create();

        foreach (range(1, 6) as $number) {
            $this->createPublicProduct($category, sprintf('Destacado disponible %02d', $number), 5, 0);
        }

        foreach (range(1, 4) as $number) {
            $this->createPublicProduct($category, sprintf('Destacado agotado %02d', $number), 1, 1);
        }

        $response = $this->get(route('home'))->assertOk();
        $content = $response->getContent();

        $this->assertSame(8, substr_count($content, 'Ver producto'));
        $this->assertTrue(strpos($content, 'Destacado disponible 01') < strpos($content, 'Destacado agotado 01'));
    }

    public function test_home_featured_empty_state_remains_when_no_public_products_exist(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('No hay productos destacados disponibles');
    }

    public function test_catalog_cards_link_to_public_detail_using_slug(): void
    {
        $category = Category::factory()->create();
        $product = $this->createPublicProduct($category, 'Producto enlazado');

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee(route('catalog.show', $product->slug), false)
            ->assertDontSee('/product/'.$product->id, false)
            ->assertDontSee($product->sku);
    }

    public function test_public_site_route_total_matches_ep003_scope(): void
    {
        $this->assertTrue(Route::has('catalog.show'));
        $this->assertCount(39, Route::getRoutes());
    }

    private function createPublicProduct(
        Category $category,
        string $name,
        int $stock = 1,
        int $reservedStock = 0,
    ): Product {
        $product = Product::factory()
            ->for($category)
            ->create([
                'name' => $name,
                'price' => '15.00',
                'is_active' => true,
            ]);

        $product->inventory()->update([
            'stock' => $stock,
            'reserved_stock' => $reservedStock,
            'min_stock' => 0,
        ]);

        return $product;
    }
}
