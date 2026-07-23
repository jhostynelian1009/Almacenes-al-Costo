<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PublicProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_name_and_description(): void
    {
        $category = $this->category();
        $byName = $this->product($category, 'Martillo de acero', ['description' => 'Herramienta básica.']);
        $byDescription = $this->product($category, 'Llave ajustable', ['description' => 'Llave multipropósito reforzada.']);
        $other = $this->product($category, 'Brocha grande', ['description' => 'Para pintura general.']);

        $this->get(route('catalog.index', ['q' => 'martillo']))
            ->assertOk()
            ->assertSee($byName->name)
            ->assertDontSee($byDescription->name)
            ->assertDontSee($other->name);

        $this->get(route('catalog.index', ['q' => 'multipropósito']))
            ->assertOk()
            ->assertSee($byDescription->name)
            ->assertDontSee($byName->name);
    }

    public function test_search_excludes_hidden_products_and_does_not_query_by_sku(): void
    {
        $category = $this->category();
        $visible = $this->product($category, 'Producto visible', ['sku' => 'BUSCAR-VISIBLE-SKU']);
        $hidden = $this->product($category, 'Producto oculto', ['is_active' => false, 'sku' => 'BUSCAR-OCULTO-SKU']);

        $this->get(route('catalog.index', ['q' => 'visible']))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);

        $this->get(route('catalog.index', ['q' => 'BUSCAR-ESTE-SKU']))
            ->assertOk()
            ->assertDontSee($visible->name)
            ->assertDontSee($hidden->name)
            ->assertSee('No se encontraron productos para tu búsqueda.');
    }

    public function test_search_without_results_shows_the_documented_message(): void
    {
        $category = $this->category();
        $this->product($category, 'Producto disponible');

        $this->get(route('catalog.index', ['q' => 'termino-inexistente']))
            ->assertOk()
            ->assertSee('No se encontraron productos para tu búsqueda.')
            ->assertDontSee('No hay productos disponibles en este momento.');
    }

    public function test_search_combines_with_category_filter_and_supports_clearing(): void
    {
        $tools = $this->category(['name' => 'Herramientas', 'slug' => 'herramientas']);
        $paint = $this->category(['name' => 'Pinturas', 'slug' => 'pinturas']);
        $toolProduct = $this->product($tools, 'Martillo combinado');
        $paintProduct = $this->product($paint, 'Martillo decorativo');

        $this->get(route('catalog.index', ['q' => 'martillo', 'category' => $tools->slug]))
            ->assertOk()
            ->assertSee($toolProduct->name)
            ->assertDontSee($paintProduct->name)
            ->assertSee('name="q"', false)
            ->assertSee('value="martillo"', false)
            ->assertSee('value="'.$tools->slug.'"', false);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee($toolProduct->name)
            ->assertSee($paintProduct->name);
    }

    public function test_search_parameters_are_preserved_during_pagination(): void
    {
        $category = $this->category(['slug' => 'busqueda-paginada']);

        foreach (range(1, 13) as $number) {
            $this->product($category, 'Busqueda paginada '.$number, [
                'description' => 'Resultado de prueba paginada.',
            ]);
        }

        $this->get(route('catalog.index', ['q' => 'paginada', 'category' => $category->slug]))
            ->assertOk()
            ->assertViewHas('products', function (LengthAwarePaginator $products) use ($category): bool {
                return $products->total() === 13
                    && str_contains($products->url(2), 'q=paginada')
                    && str_contains($products->url(2), 'category='.$category->slug);
            });
    }

    public function test_search_input_is_trimmed_and_bounded(): void
    {
        $category = $this->category();
        $product = $this->product($category, 'Producto acotado');

        $this->get(route('catalog.index', ['q' => '  acotado  ']))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Resultados para:')
            ->assertSee('acotado');

        $longTerm = str_repeat('a', 120);
        $this->get(route('catalog.index', ['q' => $longTerm]))
            ->assertOk()
            ->assertSee('Resultados para:')
            ->assertSee(str_repeat('a', 100), false);
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
     */
    private function product(Category $category, string $name, array $productAttributes = []): Product
    {
        $product = Product::factory()
            ->for($category)
            ->create(array_merge([
                'name' => $name,
                'price' => '10.00',
                'is_active' => true,
            ], $productAttributes));

        $product->inventory()->update([
            'stock' => 1,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ]);

        return $product;
    }
}
