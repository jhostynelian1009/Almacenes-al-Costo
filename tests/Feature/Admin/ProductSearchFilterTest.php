<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_keeps_administrative_authorization_without_filters(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertViewIs('admin.products.index');

        $this->post(route('logout'));
        $this->get(route('admin.products.index'))->assertRedirect(route('login'));

        $employee = User::factory()->create();
        $this->actingAs($employee)->get(route('admin.products.index'))->assertForbidden();
    }

    public function test_search_matches_exact_and_partial_names_and_skus(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Product::factory()->create([
            'name' => 'Taladro Industrial',
            'sku' => 'TL-900-PRO',
        ]);
        Product::factory()->create([
            'name' => 'Producto diferente',
            'sku' => 'OTHER-001',
        ]);

        foreach (['Taladro Industrial', 'Industrial', 'TL-900'] as $search) {
            $this->actingAs($admin)
                ->get(route('admin.products.index', ['q' => $search]))
                ->assertOk()
                ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                    && $products->first()->is($match));
        }
    }

    public function test_search_is_case_insensitive_when_supported_and_excludes_unrelated_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Product::factory()->create([
            'name' => 'Audio Profesional',
            'sku' => 'AUDIO-001',
        ]);
        $unrelated = Product::factory()->create([
            'name' => 'Campo distinto',
            'sku' => 'UNRELATED-SKU',
            'description' => 'NOSEARCHTOKEN',
            'price' => '98765.43',
            'image' => 'products/NOSEARCHTOKEN.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['q' => 'audio profesional']))
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                && $products->first()->is($match));

        foreach (['NOSEARCHTOKEN', '98765.43', (string) $unrelated->getKey()] as $search) {
            $this->get(route('admin.products.index', ['q' => $search]))
                ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->isEmpty());
        }
    }

    public function test_search_normalizes_spaces_and_empty_search_lists_every_product(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Product::factory()->create(['name' => 'Audio para Casa']);
        $other = Product::factory()->create(['name' => 'Mueble auxiliar']);

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['q' => '  Audio   para  ']))
            ->assertViewHas('filters', fn (array $filters): bool => $filters['q'] === 'Audio para')
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                && $products->first()->is($match));

        $this->get(route('admin.products.index', ['q' => '   ']))
            ->assertViewHas('filters', fn (array $filters): bool => $filters['q'] === null)
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->pluck('id')->sort()->values()->all() === [
                $match->getKey(),
                $other->getKey(),
            ]);
    }

    public function test_search_excludes_soft_deleted_products(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['name' => 'Producto existente']);
        $deleted = Product::factory()->create([
            'name' => 'Producto eliminado buscable',
            'sku' => 'DELETED-SEARCH',
        ]);
        $deleted->delete();

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['q' => 'eliminado buscable']))
            ->assertOk()
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->isEmpty())
            ->assertDontSee('DELETED-SEARCH');
    }

    public function test_category_filter_is_exact_and_accepts_active_or_inactive_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->inactive()->create();
        $child = Category::factory()->childOf($parent)->active()->create();
        $match = Product::factory()->for($parent)->create();
        Product::factory()->for($child)->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['category_id' => $parent->getKey()]))
            ->assertOk()
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                && $products->first()->is($match));

        $this->get(route('admin.products.index', ['category_id' => $child->getKey()]))
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                && (int) $products->first()->category_id === (int) $child->getKey());
    }

    public function test_category_filter_rejects_nonexistent_or_non_integer_values(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([999999, 'invalid'] as $categoryId) {
            $this->actingAs($admin)
                ->from(route('admin.products.index'))
                ->get(route('admin.products.index', ['category_id' => $categoryId]))
                ->assertRedirect(route('admin.products.index'))
                ->assertSessionHasErrors('category_id');
        }
    }

    public function test_status_filter_handles_active_inactive_and_absent_values(): void
    {
        $admin = User::factory()->admin()->create();
        $active = Product::factory()->active()->create();
        $inactive = Product::factory()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['status' => 'active']))
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                && $products->first()->is($active));

        $this->get(route('admin.products.index', ['status' => 'inactive']))
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 1
                && $products->first()->is($inactive));

        $this->get(route('admin.products.index'))
            ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->count() === 2);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.products.index'))
            ->get(route('admin.products.index', ['status' => 'archived']))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_search_category_and_status_can_be_combined_in_every_required_way(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $match = Product::factory()->for($category)->active()->create(['name' => 'Audio Especial']);
        $sameCategoryOtherName = Product::factory()->for($category)->active()->create(['name' => 'Mueble Especial']);
        $sameCategoryInactive = Product::factory()->for($category)->inactive()->create(['name' => 'Audio Inactivo']);
        $otherCategoryActive = Product::factory()->for($otherCategory)->active()->create(['name' => 'Audio Alterno']);

        $cases = [
            [
                ['q' => 'Audio', 'category_id' => $category->getKey()],
                [$match->getKey(), $sameCategoryInactive->getKey()],
            ],
            [
                ['q' => 'Audio', 'status' => 'active'],
                [$match->getKey(), $otherCategoryActive->getKey()],
            ],
            [
                ['category_id' => $category->getKey(), 'status' => 'active'],
                [$match->getKey(), $sameCategoryOtherName->getKey()],
            ],
            [
                ['q' => 'Audio', 'category_id' => $category->getKey(), 'status' => 'active'],
                [$match->getKey()],
            ],
        ];

        foreach ($cases as [$filters, $expectedIds]) {
            sort($expectedIds);

            $this->actingAs($admin)
                ->get(route('admin.products.index', $filters))
                ->assertOk()
                ->assertViewHas('products', fn (LengthAwarePaginator $products): bool => $products->pluck('id')->sort()->values()->all() === $expectedIds);
        }
    }

    public function test_initial_and_filtered_empty_states_are_distinct(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('No hay productos registrados')
            ->assertDontSee('No hay productos que coincidan');

        $this->get(route('admin.products.index', ['q' => 'sin coincidencias']))
            ->assertOk()
            ->assertSee('No hay productos registrados')
            ->assertDontSee('No hay productos que coincidan');

        Product::factory()->create(['name' => 'Producto existente']);

        $this->get(route('admin.products.index', ['q' => 'sin coincidencias']))
            ->assertOk()
            ->assertSee('No hay productos que coincidan')
            ->assertSee('Limpiar filtros')
            ->assertDontSee('No hay productos registrados');
    }

    public function test_pagination_uses_fifteen_items_and_stable_name_id_order(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Product::factory()->count(16)->for($category)->create(['name' => 'Producto estable']);
        $expectedIds = Product::query()->orderBy('name')->orderBy('id')->pluck('id');

        $firstPage = $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->viewData('products');
        $secondPage = $this->get(route('admin.products.index', ['page' => 2]))
            ->assertOk()
            ->viewData('products');

        $this->assertSame(15, $firstPage->perPage());
        $this->assertSame(16, $firstPage->total());
        $this->assertSame($expectedIds->take(15)->all(), $firstPage->pluck('id')->all());
        $this->assertSame([$expectedIds->last()], $secondPage->pluck('id')->all());
        $this->assertEmpty($firstPage->pluck('id')->intersect($secondPage->pluck('id')));
    }

    public function test_pagination_preserves_only_validated_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Product::factory()->count(16)->for($category)->active()->create([
            'name' => fn (array $attributes): string => 'Filtro '.fake()->unique()->numerify('####'),
        ]);

        $products = $this->actingAs($admin)
            ->get(route('admin.products.index', [
                'q' => 'Filtro',
                'category_id' => $category->getKey(),
                'status' => 'active',
                'price_min' => '10',
            ]))
            ->assertOk()
            ->viewData('products');
        parse_str((string) parse_url($products->url(2), PHP_URL_QUERY), $query);

        $this->assertSame('Filtro', $query['q']);
        $this->assertSame((string) $category->getKey(), $query['category_id']);
        $this->assertSame('active', $query['status']);
        $this->assertSame('2', $query['page']);
        $this->assertArrayNotHasKey('price_min', $query);
    }

    public function test_filter_form_is_accessible_responsive_and_preserves_values(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->inactive()->create(['name' => 'Principal inactiva', 'display_order' => 1]);
        $child = Category::factory()->childOf($main)->active()->create(['name' => 'Hija activa', 'display_order' => 2]);
        Category::factory()->childOf($child)->inactive()->create(['name' => 'Nieta inactiva', 'display_order' => 3]);

        $response = $this->actingAs($admin)->get(route('admin.products.index', [
            'q' => 'Audio',
            'category_id' => $child->getKey(),
            'status' => 'inactive',
        ]));
        $content = $response->getContent();

        $response->assertOk()
            ->assertSee('<form method="GET" action="'.route('admin.products.index').'">', false)
            ->assertSee('for="q"', false)
            ->assertSee('for="category_id"', false)
            ->assertSee('for="status"', false)
            ->assertSee('value="Audio"', false)
            ->assertSee('value="'.$child->getKey().'" selected', false)
            ->assertSee('value="inactive" selected', false)
            ->assertSeeInOrder(['Principal inactiva', '— Hija activa', '— — Nieta inactiva'])
            ->assertSee('href="'.route('admin.products.index').'"', false)
            ->assertSee('Limpiar filtros')
            ->assertDontSee('name="price_min"', false)
            ->assertDontSee('name="price_max"', false);

        $this->assertSame(1, substr_count($content, '<h1'));
    }

    public function test_product_and_category_queries_stay_bounded_without_n_plus_one(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(3)->create();

        foreach ($categories as $category) {
            Product::factory()->count(3)->for($category)->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $products = $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->viewData('products');
        $queries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $productQueries = $queries->filter(fn (string $query): bool => preg_match('/from [`"]?products[`"]?/', $query) === 1);
        $categoryQueries = $queries->filter(fn (string $query): bool => preg_match('/from [`"]?categories[`"]?/', $query) === 1);
        $selectorQueries = $categoryQueries->filter(fn (string $query): bool => str_contains($query, 'parent_id'));

        $this->assertCount(2, $productQueries);
        $this->assertCount(2, $categoryQueries);
        $this->assertCount(1, $selectorQueries);
        $this->assertTrue($products->getCollection()->every(fn (Product $product): bool => $product->relationLoaded('category')));
    }

    public function test_filtering_keeps_image_thumbnails_and_missing_file_states(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = 'products/filter-thumbnail.jpg';
        Storage::disk('public')->put($path, 'image bytes');
        Product::factory()->active()->create(['name' => 'Imagen disponible', 'image' => $path]);
        Product::factory()->active()->create(['name' => 'Imagen faltante', 'image' => 'products/missing.jpg']);

        $this->actingAs($admin)
            ->get(route('admin.products.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('src="'.asset('storage/'.$path).'"', false)
            ->assertSee('alt="Imagen principal de Imagen disponible"', false)
            ->assertSee('Imagen no disponible')
            ->assertDontSee('src="'.asset('storage/products/missing.jpg').'"', false);
    }

    public function test_filtering_adds_no_routes_or_public_product_filters(): void
    {
        $expectedRoutes = [
            'admin.products.create',
            'admin.products.destroy',
            'admin.products.edit',
            'admin.products.index',
            'admin.products.show',
            'admin.products.store',
            'admin.products.update',
        ];
        $productRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.products.'))
            ->sort()
            ->values()
            ->all();
        $publicFilterRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => ! str_starts_with($route->uri(), 'admin/'))
            ->filter(fn ($route): bool => str_contains($route->uri(), 'products'));

        $this->assertSame($expectedRoutes, $productRoutes);
        $this->assertCount(0, $publicFilterRoutes);
    }
}
