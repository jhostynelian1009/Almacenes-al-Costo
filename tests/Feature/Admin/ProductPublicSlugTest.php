<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductSlugService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductPublicSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_schema_and_model_contract_are_exact(): void
    {
        $this->assertTrue(Schema::hasColumn('products', 'slug'));
        $this->assertSame('varchar', Schema::getColumnType('products', 'slug'));

        $column = collect(Schema::getColumns('products'))->firstWhere('name', 'slug');
        $index = collect(Schema::getIndexes('products'))->firstWhere('name', 'products_slug_unique');

        $this->assertFalse($column['nullable']);
        $this->assertTrue($index['unique']);
        $this->assertSame(['slug'], $index['columns']);

        $migration = file_get_contents($this->slugMigrationPath());

        $this->assertStringContainsString('MAX_LENGTH = 255', $migration);
        $this->assertStringContainsString('nullable(false)->change()', $migration);
        $this->assertStringNotContainsString('App\\Models', $migration);
        $this->assertStringNotContainsString('Product::', $migration);
        $this->assertNotContains('slug', (new Product)->getFillable());
        $this->assertSame('id', (new Product)->getRouteKeyName());
    }

    public function test_server_generates_safe_slug_and_ignores_manual_input(): void
    {
        $product = Product::factory()->create([
            'name' => '  Aceite Cástrol GTX / 20W-50? #  ',
            'sku' => 'SECRET-SKU-999',
            'slug' => 'slug-manual',
        ]);

        $this->assertSame('aceite-castrol-gtx-20w-50', $product->slug);
        $this->assertSame(Str::lower($product->slug), $product->slug);
        $this->assertStringNotContainsString('/', $product->slug);
        $this->assertStringNotContainsString('?', $product->slug);
        $this->assertStringNotContainsString('#', $product->slug);
        $this->assertStringNotContainsString((string) $product->getKey(), $product->slug);
        $this->assertStringNotContainsString(Str::lower($product->sku), $product->slug);
        $this->assertLessThanOrEqual(255, Str::length($product->slug));
    }

    public function test_invalid_names_use_the_incremental_fallback(): void
    {
        $first = Product::factory()->create(['name' => ' ? # / ']);
        $second = Product::factory()->create(['name' => '/// ??? ###']);
        $third = Product::factory()->create(['name' => '***']);

        $this->assertSame('producto', $first->slug);
        $this->assertSame('producto-2', $second->slug);
        $this->assertSame('producto-3', $third->slug);
    }

    public function test_collisions_include_inactive_and_soft_deleted_products(): void
    {
        $first = Product::factory()->active()->create(['name' => 'Taladro Industrial']);
        $second = Product::factory()->inactive()->create(['name' => 'Taladro Industrial']);
        $third = Product::factory()->create(['name' => 'Taladro Industrial']);
        $third->delete();
        $fourth = Product::factory()->create(['name' => 'Taladro Industrial']);

        $this->assertSame('taladro-industrial', $first->slug);
        $this->assertSame('taladro-industrial-2', $second->slug);
        $this->assertSame('taladro-industrial-3', Product::withTrashed()->findOrFail($third->id)->slug);
        $this->assertSame('taladro-industrial-4', $fourth->slug);

        $third->restore();

        $this->assertSame('taladro-industrial-3', $third->fresh()->slug);
        $this->assertFalse($second->is_active);
    }

    public function test_suffixes_reserve_space_within_maximum_length(): void
    {
        $longName = str_repeat('Árbol largo ', 40);
        $first = Product::factory()->create(['name' => $longName]);
        $second = Product::factory()->create(['name' => $longName]);

        $this->assertSame(ProductSlugService::MAX_LENGTH, Str::length($first->slug));
        $this->assertSame(ProductSlugService::MAX_LENGTH, Str::length($second->slug));
        $this->assertStringEndsWith('-2', $second->slug);
        $this->assertFalse(Str::endsWith($second->slug, '--2'));
        $this->assertStringNotContainsString('...', $first->slug);
        $this->assertStringNotContainsString('...', $second->slug);
    }

    public function test_slug_is_stable_across_updates_soft_delete_and_restore(): void
    {
        $originalCategory = Category::factory()->create();
        $newCategory = Category::factory()->create();
        $product = Product::factory()->for($originalCategory)->create([
            'name' => 'Nombre original',
            'price' => '10.00',
            'is_active' => true,
        ]);
        $originalSlug = $product->slug;

        $product->update([
            'category_id' => $newCategory->id,
            'name' => 'Nombre totalmente distinto',
            'price' => '99.99',
            'is_active' => false,
            'slug' => 'intento-de-cambio',
        ]);

        $product->refresh();
        $this->assertSame($originalSlug, $product->slug);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertSame('99.99', $product->price);
        $this->assertFalse($product->is_active);

        $product->delete();
        $trashed = Product::withTrashed()->findOrFail($product->id);

        $this->assertSame($originalSlug, $trashed->slug);

        $trashed->restore();

        $this->assertSame($originalSlug, $trashed->fresh()->slug);
    }

    public function test_administrative_requests_cannot_control_the_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Producto desde administración',
            'slug' => 'slug-manipulado-en-create',
            'sku' => 'PUBLIC-SLUG-001',
            'description' => 'Descripción',
            'price' => '25.50',
            'is_active' => '1',
        ]);

        $product = Product::query()->where('sku', 'PUBLIC-SLUG-001')->sole();

        $response->assertRedirect(route('admin.products.show', $product));
        $this->assertSame('producto-desde-administracion', $product->slug);

        $response = $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Nombre administrativo cambiado',
            'slug' => 'slug-manipulado-en-update',
            'sku' => 'PUBLIC-SLUG-001',
            'description' => 'Descripción cambiada',
            'price' => '30.00',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.products.show', $product));
        $this->assertSame('producto-desde-administracion', $product->fresh()->slug);
        $this->get(route('admin.products.create'))->assertDontSee('name=slug', false);
        $this->get(route('admin.products.edit', $product))->assertDontSee('name=slug', false);
    }

    public function test_database_rejects_null_and_duplicate_slugs(): void
    {
        $category = Category::factory()->create();

        try {
            DB::table('products')->insert([
                'category_id' => $category->id,
                'name' => 'Producto sin slug',
                'sku' => 'NO-SLUG-001',
                'price' => '1.00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The database must reject a product without a slug.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('products', ['sku' => 'NO-SLUG-001']);
        }

        $first = Product::factory()->create(['name' => 'Duplicado directo']);
        $second = Product::factory()->create(['name' => 'Producto independiente']);

        try {
            DB::table('products')->where('id', $second->id)->update(['slug' => $first->slug]);
            $this->fail('The unique constraint must reject duplicate slugs.');
        } catch (QueryException) {
            $this->assertSame('producto-independiente', $second->fresh()->slug);
        }
    }

    private function slugMigrationPath(): string
    {
        return database_path('migrations/2026_07_22_000004_add_slug_to_products_table.php');
    }
}
