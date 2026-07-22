<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_employee_cannot_access_product_administration(): void
    {
        $this->get(route('admin.products.index'))->assertRedirect(route('login'));

        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_active_admin_can_access_every_product_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk()->assertViewIs('admin.products.index');
        $this->get(route('admin.products.create'))->assertOk()->assertViewIs('admin.products.create');
        $this->get(route('admin.products.show', $product))->assertOk()->assertViewIs('admin.products.show');
        $this->get(route('admin.products.edit', $product))->assertOk()->assertViewIs('admin.products.edit');
    }

    public function test_index_uses_admin_layout_and_renders_accessible_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Productos')
            ->assertSee('No hay productos registrados')
            ->assertSee(route('admin.products.create'))
            ->assertSee('role="status"', false);
    }

    public function test_index_displays_contractual_columns_and_excludes_soft_deleted_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Categoría visible']);
        Product::factory()->for($category)->inactive()->create([
            'name' => 'Producto visible',
            'sku' => 'VISIBLE-001',
            'price' => '125.50',
        ]);
        $deletedProduct = Product::factory()->for($category)->create([
            'name' => 'Producto eliminado',
            'sku' => 'DELETED-001',
        ]);
        $deletedProduct->delete();

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSeeInOrder(['Nombre', 'SKU', 'Categoría', 'Precio', 'Estado', 'Acciones'])
            ->assertSee('Producto visible')
            ->assertSee('VISIBLE-001')
            ->assertSee('Categoría visible')
            ->assertSee('$125.50')
            ->assertSee('Inactivo')
            ->assertDontSee('Producto eliminado')
            ->assertDontSee('DELETED-001');
    }

    public function test_admin_can_create_product_with_only_contractual_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->getKey(),
            'name' => '  Producto   contractual  ',
            'sku' => '  SKU-001  ',
            'description' => '  Descripción del producto  ',
            'price' => '25.40',
            'is_active' => '0',
            'deleted_at' => now(),
        ]);

        $product = Product::query()->sole();

        $response->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHas('success', 'Producto creado correctamente.');
        $this->assertSame($category->getKey(), $product->category_id);
        $this->assertSame('Producto contractual', $product->name);
        $this->assertSame('SKU-001', $product->sku);
        $this->assertSame('Descripción del producto', $product->description);
        $this->assertSame('25.40', $product->price);
        $this->assertFalse($product->is_active);
        $this->assertNull($product->image);
        $this->assertNull($product->deleted_at);
        $this->assertTrue($product->category->is($category));
    }

    public function test_store_validates_required_fields_category_price_and_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'category_id' => 999999,
                'name' => '',
                'sku' => '',
                'price' => '-0.01',
                'is_active' => 'not-a-boolean',
            ])
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors(['category_id', 'name', 'sku', 'price', 'is_active']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_price_rejects_more_than_two_decimals_and_values_outside_column_capacity(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        foreach (['1.234', '100000000.00'] as $invalidPrice) {
            $this->actingAs($admin)
                ->post(route('admin.products.store'), $this->validPayload($category, [
                    'sku' => 'PRICE-'.str_replace('.', '-', $invalidPrice),
                    'price' => $invalidPrice,
                ]))
                ->assertSessionHasErrors('price');
        }

        $this->assertDatabaseCount('products', 0);
    }

    public function test_sku_must_be_unique_when_creating_product(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = Product::factory()->create(['sku' => 'UNIQUE-001']);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($existing->category, [
                'sku' => 'UNIQUE-001',
            ]))
            ->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('products', 1);
    }

    public function test_forms_include_only_managed_fields_and_preserve_old_values(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create();

        $this->actingAs($admin)
            ->withSession(['_old_input' => [
                'name' => 'Nombre anterior',
                'sku' => 'OLD-SKU',
                'price' => '15.25',
            ]])
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('value="Nombre anterior"', false)
            ->assertSee('value="OLD-SKU"', false)
            ->assertSee('value="15.25"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="image"', false)
            ->assertSee('type="file"', false)
            ->assertDontSee('name="deleted_at"', false);
    }

    public function test_category_selector_includes_every_status_and_level_with_hierarchy_labels(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->inactive()->create([
            'name' => 'Principal inactiva',
            'display_order' => 1,
        ]);
        $child = Category::factory()->childOf($main)->create([
            'name' => 'Hija activa',
            'display_order' => 2,
        ]);
        Category::factory()->childOf($child)->inactive()->create([
            'name' => 'Nieta inactiva',
            'display_order' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSeeInOrder(['Principal inactiva', '— Hija activa', '— — Nieta inactiva']);
    }

    public function test_show_displays_contractual_information_without_image_or_future_modules(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'name' => 'Producto detalle',
            'sku' => 'DETAIL-001',
            'description' => 'Descripción visible',
            'price' => '80.10',
            'image' => 'products/private-path.jpg',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Producto detalle')
            ->assertSee('DETAIL-001')
            ->assertSee('Descripción visible')
            ->assertSee('$80.10')
            ->assertSee($product->category->name)
            ->assertDontSee('products/private-path.jpg')
            ->assertDontSee('Stock físico')
            ->assertDontSee('Ventas asociadas');
    }

    public function test_admin_can_update_product_keep_its_sku_and_preserve_image(): void
    {
        $admin = User::factory()->admin()->create();
        $originalCategory = Category::factory()->create();
        $newCategory = Category::factory()->inactive()->create();
        $product = Product::factory()->for($originalCategory)->create([
            'sku' => 'STABLE-001',
            'image' => 'products/original.jpg',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'category_id' => $newCategory->getKey(),
            'name' => 'Producto actualizado',
            'sku' => 'STABLE-001',
            'description' => '',
            'price' => '99.90',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.products.show', $product))
            ->assertSessionHas('success', 'Producto actualizado correctamente.');

        $product->refresh();
        $this->assertSame($newCategory->getKey(), $product->category_id);
        $this->assertSame('Producto actualizado', $product->name);
        $this->assertSame('STABLE-001', $product->sku);
        $this->assertNull($product->description);
        $this->assertSame('99.90', $product->price);
        $this->assertFalse($product->is_active);
        $this->assertSame('products/original.jpg', $product->image);
    }

    public function test_update_rejects_sku_owned_by_another_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['sku' => 'CURRENT-001']);
        $otherProduct = Product::factory()->create(['sku' => 'OTHER-001']);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'sku' => $otherProduct->sku,
            ]))
            ->assertSessionHasErrors('sku');

        $this->assertSame('CURRENT-001', $product->fresh()->sku);
    }

    public function test_destroy_soft_deletes_product_keeps_category_and_excludes_normal_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $category = $product->category;

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Producto eliminado correctamente.');

        $this->assertSoftDeleted($product);
        $this->assertModelExists($category);
        $this->assertNotNull(Product::withTrashed()->find($product->getKey()));
        $this->get(route('admin.products.show', $product->getKey()))->assertNotFound();
        $this->get(route('admin.products.edit', $product->getKey()))->assertNotFound();
        $this->delete(route('admin.products.destroy', $product->getKey()))->assertNotFound();
    }

    public function test_guest_and_employee_cannot_delete_or_modify_products(): void
    {
        $product = Product::factory()->create();

        $this->delete(route('admin.products.destroy', $product))->assertRedirect(route('login'));

        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category))
            ->assertForbidden();
        $this->delete(route('admin.products.destroy', $product))->assertForbidden();
        $this->assertNotSoftDeleted($product);
    }

    public function test_index_paginates_fifteen_products_in_deterministic_name_order(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        foreach (range(17, 1) as $number) {
            Product::factory()->for($category)->create([
                'name' => sprintf('Producto %02d', $number),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.products.index'));

        $response->assertOk()->assertViewHas('products', function ($products): bool {
            return $products->perPage() === 15
                && $products->total() === 17
                && $products->pluck('name')->values()->all() === collect(range(1, 15))
                    ->map(fn (int $number): string => sprintf('Producto %02d', $number))
                    ->all();
        });
    }

    public function test_index_eager_loads_categories_without_n_plus_one_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(3)->create();

        foreach ($categories as $category) {
            Product::factory()->count(3)->for($category)->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)->get(route('admin.products.index'))->assertOk();

        $categoryQueries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $query): bool => str_contains(strtolower($query), 'from "categories"'));

        $this->assertCount(2, $categoryQueries);
    }

    public function test_product_resource_has_only_crud_routes_with_administrative_middleware(): void
    {
        $expectedRoutes = [
            'admin.products.index',
            'admin.products.create',
            'admin.products.store',
            'admin.products.show',
            'admin.products.edit',
            'admin.products.update',
            'admin.products.destroy',
        ];

        $actualRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.products.'))
            ->sort()
            ->values()
            ->all();

        sort($expectedRoutes);
        $this->assertSame($expectedRoutes, $actualRoutes);

        foreach ($expectedRoutes as $routeName) {
            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertContains('admin', $middleware);
        }

        $productUris = collect(Route::getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => str_contains($uri, 'products'));

        $this->assertTrue($productUris->every(fn (string $uri): bool => str_starts_with($uri, 'admin/products')));
        $this->assertFalse(Route::has('admin.products.restore'));
        $this->assertFalse(Route::has('admin.products.force-delete'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->getKey(),
            'name' => 'Producto válido',
            'sku' => 'VALID-001',
            'description' => 'Descripción válida',
            'price' => '10.25',
            'is_active' => '1',
        ], $overrides);
    }
}
