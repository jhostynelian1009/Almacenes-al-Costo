<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicProductController;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_keeps_its_public_route_contract_and_access(): void
    {
        $route = Route::getRoutes()->getByName('catalog.index');

        $this->assertNotNull($route);
        $this->assertSame('catalogo', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(PublicProductController::class.'@index', $route->getActionName());
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertNotContains('active', $route->gatherMiddleware());
        $this->assertNotContains('admin', $route->gatherMiddleware());
        $this->assertCount(57, Route::getRoutes());

        $this->get('/catalogo')
            ->assertOk()
            ->assertViewIs('public.products.index');
        $this->get('/catalogo?category=')->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('catalog.index'))
            ->assertOk();

        $catalogRoutes = collect(Route::getRoutes())
            ->filter(fn ($registeredRoute): bool => $registeredRoute->uri() === 'catalogo');

        $this->assertCount(1, $catalogRoutes);

        foreach (['catalog.search', 'products.index', 'products.show', 'checkout.index'] as $routeName) {
            $this->assertFalse(Route::has($routeName));
        }

        $this->assertTrue(Route::has('catalog.show'));
        $this->assertTrue(Route::has('cart.index'));
    }

    public function test_only_products_from_completely_public_category_branches_are_visible(): void
    {
        $root = $this->category(['name' => 'Raíz pública']);
        $publicChild = $this->category([
            'name' => 'Rama pública válida',
            'parent_id' => $root->id,
        ]);
        $inactiveCategory = $this->category([
            'name' => 'Categoría inactiva',
            'is_active' => false,
        ]);
        $inactiveAncestor = $this->category([
            'name' => 'Ancestro inactivo',
            'is_active' => false,
        ]);
        $hiddenDescendant = $this->category([
            'name' => 'Descendiente activo no promovido',
            'parent_id' => $inactiveAncestor->id,
        ]);

        $visible = $this->product($publicChild, 'Producto público visible');
        $inactive = $this->product($publicChild, 'Producto inactivo oculto', ['is_active' => false]);
        $deleted = $this->product($publicChild, 'Producto eliminado oculto');
        $deleted->delete();
        $categoryHidden = $this->product($inactiveCategory, 'Producto de categoría inactiva');
        $ancestorHidden = $this->product($hiddenDescendant, 'Producto con ancestro inactivo');

        $response = $this->get(route('catalog.index'))->assertOk();

        $response
            ->assertSee($visible->name)
            ->assertDontSee($inactive->name)
            ->assertDontSee($deleted->name)
            ->assertDontSee($categoryHidden->name)
            ->assertDontSee($ancestorHidden->name)
            ->assertDontSee($hiddenDescendant->name);
    }

    public function test_product_without_inventory_and_internal_product_data_are_not_exposed(): void
    {
        $category = $this->category(['name' => 'Categoría pública segura']);
        $visible = $this->product(
            $category,
            'Producto público seguro',
            ['sku' => 'SKU-NO-PUBLICO-93817'],
            ['stock' => 98317, 'reserved_stock' => 731, 'min_stock' => 419],
        );
        $withoutInventory = $this->product($category, 'Producto sin inventario');
        $withoutInventory->inventory()->delete();

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($withoutInventory->name)
            ->assertDontSee('SKU-NO-PUBLICO-93817')
            ->assertDontSee('98317')
            ->assertDontSee('731')
            ->assertDontSee('419')
            ->assertDontSee('reserved_stock')
            ->assertDontSee('min_stock')
            ->assertDontSee('deleted_at');
    }

    public function test_availability_uses_available_stock_without_mutating_inventory(): void
    {
        $category = $this->category();
        $available = $this->product(
            $category,
            'Producto disponible por saldo',
            [],
            ['stock' => 9, 'reserved_stock' => 8, 'min_stock' => 50],
        );
        $soldOut = $this->product(
            $category,
            'Producto agotado por reservas',
            [],
            ['stock' => 11, 'reserved_stock' => 11, 'min_stock' => 0],
        );
        $availableInventoryBefore = $available->inventory->getAttributes();
        $soldOutInventoryBefore = $soldOut->inventory->getAttributes();
        $movementCountBefore = InventoryMovement::query()->count();

        $response = $this->get(route('catalog.index'))->assertOk();

        $response
            ->assertSeeInOrder([$available->name, 'Disponible'])
            ->assertSeeInOrder([$soldOut->name, 'Agotado'])
            ->assertDontSee('Stock:')
            ->assertDontSee('Reservado:')
            ->assertDontSee('Mínimo:');
        $this->assertSame($availableInventoryBefore, $available->inventory->fresh()->getAttributes());
        $this->assertSame($soldOutInventoryBefore, $soldOut->inventory->fresh()->getAttributes());
        $this->assertSame($movementCountBefore, InventoryMovement::query()->count());
    }

    public function test_category_slug_filter_includes_public_descendants_and_excludes_other_branches(): void
    {
        $root = $this->category(['name' => 'Herramientas', 'slug' => 'herramientas']);
        $child = $this->category([
            'name' => 'Herramientas manuales',
            'slug' => 'herramientas-manuales',
            'parent_id' => $root->id,
        ]);
        $grandchild = $this->category([
            'name' => 'Llaves',
            'slug' => 'llaves',
            'parent_id' => $child->id,
        ]);
        $otherBranch = $this->category(['name' => 'Pinturas', 'slug' => 'pinturas']);
        $rootProduct = $this->product($root, 'Producto de la raíz');
        $childProduct = $this->product($child, 'Producto de la hija');
        $grandchildProduct = $this->product($grandchild, 'Producto de la nieta');
        $otherProduct = $this->product($otherBranch, 'Producto de otra rama');

        $response = $this->get(route('catalog.index', ['category' => $root->slug]))
            ->assertOk()
            ->assertViewHas('selectedCategory', fn (Category $category): bool => $category->is($root))
            ->assertViewHas('products', function (LengthAwarePaginator $products) use ($rootProduct, $childProduct, $grandchildProduct): bool {
                return $products->pluck('id')->all() === collect([
                    $childProduct,
                    $grandchildProduct,
                    $rootProduct,
                ])->sortBy([['name', 'asc'], ['id', 'asc']])->pluck('id')->values()->all();
            });

        $quote = chr(34);

        $response
            ->assertSee($rootProduct->name)
            ->assertSee($childProduct->name)
            ->assertSee($grandchildProduct->name)
            ->assertDontSee($otherProduct->name)
            ->assertSee('Categoría seleccionada:')
            ->assertSee('name='.$quote.'category'.$quote, false)
            ->assertSee('name='.$quote.'q'.$quote, false)
            ->assertSee('value='.$quote.$root->slug.$quote, false)
            ->assertDontSee('name='.$quote.'category_id'.$quote, false)
            ->assertSee('href='.$quote.route('catalog.index').$quote, false);
    }

    public function test_non_public_or_invalid_category_filters_return_a_safe_404(): void
    {
        $inactive = $this->category(['slug' => 'categoria-inactiva', 'is_active' => false]);
        $inactiveAncestor = $this->category(['is_active' => false]);
        $hiddenChild = $this->category([
            'slug' => 'descendiente-oculta',
            'parent_id' => $inactiveAncestor->id,
        ]);

        foreach ([
            ['category' => 'no-existe'],
            ['category' => $inactive->slug],
            ['category' => $hiddenChild->slug],
            ['category' => str_repeat('a', 256)],
            ['category' => ['manipulado']],
        ] as $query) {
            $this->get(route('catalog.index', $query))->assertNotFound();
        }
    }

    public function test_catalog_paginates_twelve_products_in_stable_name_and_id_order(): void
    {
        $category = $this->category(['slug' => 'categoria-paginada']);
        $expected = collect();

        foreach (range(1, 11) as $number) {
            $expected->push($this->product($category, sprintf('Artículo %02d', $number)));
        }

        $firstDuplicate = $this->product($category, 'Producto repetido');
        $secondDuplicate = $this->product($category, 'Producto repetido');
        $last = $this->product($category, 'Último producto');
        $expected->push($firstDuplicate, $secondDuplicate, $last);
        $expectedIds = $expected
            ->sortBy([['name', 'asc'], ['id', 'asc']])
            ->pluck('id')
            ->values();

        $this->get(route('catalog.index', ['category' => $category->slug]))
            ->assertOk()
            ->assertViewHas('products', function (LengthAwarePaginator $products) use ($expectedIds, $category): bool {
                return $products->perPage() === 12
                    && $products->total() === 14
                    && $products->pluck('id')->all() === $expectedIds->take(12)->all()
                    && str_contains($products->url(2), 'category='.$category->slug);
            });

        $this->get(route('catalog.index', [
            'category' => $category->slug,
            'page' => 2,
        ]))
            ->assertOk()
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->pluck('id')->all() === $expectedIds->slice(12)->values()->all())
            ->assertSee($secondDuplicate->name)
            ->assertSee($last->name);

        $this->assertSame($firstDuplicate->id, $expectedIds[11]);
        $this->assertSame($secondDuplicate->id, $expectedIds[12]);
    }

    public function test_product_images_use_public_urls_and_missing_images_have_an_alternative_state(): void
    {
        Storage::fake('public');
        $category = $this->category();
        $path = 'products/catalog-image.jpg';
        Storage::disk('public')->put($path, 'image bytes');
        $withImage = $this->product($category, 'Producto con imagen', ['image' => $path]);
        $missingImage = $this->product($category, 'Producto con archivo faltante', ['image' => 'products/missing.jpg']);
        $withoutImage = $this->product($category, 'Producto sin imagen', ['image' => null]);
        $applicationUrl = 'http://localhost/tienda/public';
        $expectedUrl = $applicationUrl.'/storage/'.$path;

        URL::forceRootUrl($applicationUrl);
        $this->withServerVariables([
            'HTTP_HOST' => 'localhost',
            'PHP_SELF' => '/tienda/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'SCRIPT_NAME' => '/tienda/public/index.php',
        ]);

        try {
            $response = $this->get('/catalogo')->assertOk();
            $quote = chr(34);

            $response
                ->assertSee('src='.$quote.$expectedUrl.$quote, false)
                ->assertSee('alt='.$quote.$withImage->name.$quote, false)
                ->assertDontSee('src='.$quote.$applicationUrl.'/storage/products/missing.jpg'.$quote, false)
                ->assertSee('Imagen no disponible para '.$missingImage->name)
                ->assertSee('Imagen no disponible para '.$withoutImage->name)
                ->assertSee('Sin imagen')
                ->assertDontSee('storage/public/products/', false)
                ->assertDontSee('C:\\', false);
        } finally {
            URL::forceRootUrl(null);
            $this->withServerVariables([]);
        }

        $this->assertSame($path, $withImage->fresh()->image);
    }

    public function test_catalog_distinguishes_general_and_filtered_empty_states(): void
    {
        $category = $this->category(['name' => 'Categoría vacía', 'slug' => 'categoria-vacia']);
        $emptyStateAttribute = 'data-empty-state='.chr(34).'public-products'.chr(34);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee($emptyStateAttribute, false)
            ->assertSee('No hay productos disponibles en este momento.')
            ->assertDontSee('No hay productos disponibles en esta categoría.');

        $this->get(route('catalog.index', ['category' => $category->slug]))
            ->assertOk()
            ->assertSee($emptyStateAttribute, false)
            ->assertSee('No hay productos disponibles en esta categoría.')
            ->assertDontSee('No hay productos disponibles en este momento.');
    }

    public function test_catalog_cards_render_documented_public_information(): void
    {
        $category = $this->category(['name' => 'Categoría de exhibición']);
        $product = $this->product($category, 'Producto de exhibición', [
            'description' => 'Descripción pública breve.',
            'price' => '1234.50',
            'sku' => 'SKU-INTERNO-555',
        ]);

        $response = $this->get(route('catalog.index'))->assertOk();

        $response->assertSee($product->name)
            ->assertSee('Descripción pública breve.')
            ->assertSee('$ 1,234.50')
            ->assertSee($category->name)
            ->assertSee('Disponible')
            ->assertSee(route('catalog.show', $product->slug), false)
            ->assertSee('Todos los derechos reservados')
            ->assertDontSee('SKU-INTERNO-555')
            ->assertSee('Agregar al carrito')
            ->assertDontSee('Comprar')
            ->assertDontSee('Añadir al carrito');

        $response->assertDontSee('/admin/products', false);
        $this->assertSame(1, substr_count($response->getContent(), chr(60).'h1'));
        $this->assertTrue(Route::has('catalog.show'));
    }

    public function test_catalog_uses_bounded_queries_eager_loading_and_database_pagination(): void
    {
        Storage::fake('public');
        $root = $this->category();
        $child = $this->category(['parent_id' => $root->id]);

        foreach (range(1, 20) as $number) {
            $this->product($child, sprintf('Producto consulta %02d', $number));
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertViewHas('products', function (LengthAwarePaginator $products): bool {
                return $products->perPage() === 12
                    && $products->count() === 12
                    && $products->total() === 20
                    && $products->every(fn (Product $product): bool => $product->relationLoaded('category')
                        && $product->relationLoaded('inventory'));
            });

        $this->assertCount(5, $queries);

        $controller = file_get_contents(app_path('Http/Controllers/PublicProductController.php'));
        $this->assertIsString($controller);
        $this->assertStringContainsString('->paginate(12)', $controller);
        $this->assertStringNotContainsString('InventoryService', $controller);
        $this->assertStringNotContainsString('InventoryMovement', $controller);
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
