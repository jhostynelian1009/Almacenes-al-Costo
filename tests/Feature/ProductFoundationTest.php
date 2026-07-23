<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_table_contains_only_the_documented_foundation_columns(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasColumns('products', [
            'id',
            'category_id',
            'name',
            'slug',
            'sku',
            'description',
            'price',
            'image',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $this->assertSame([
            'id',
            'category_id',
            'name',
            'sku',
            'description',
            'price',
            'image',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
            'slug',
        ], Schema::getColumnListing('products'));
    }

    public function test_products_table_contains_the_documented_indexes(): void
    {
        $indexes = collect(Schema::getIndexes('products'))->keyBy('name');

        $this->assertTrue($indexes->has('products_sku_unique'));
        $this->assertTrue($indexes->get('products_sku_unique')['unique']);
        $this->assertSame(['sku'], $indexes->get('products_sku_unique')['columns']);
        $this->assertTrue($indexes->has('products_slug_unique'));
        $this->assertTrue($indexes->get('products_slug_unique')['unique']);
        $this->assertSame(['slug'], $indexes->get('products_slug_unique')['columns']);
        $this->assertSame(['category_id'], $indexes->get('idx_products_category_id')['columns']);
        $this->assertSame(['is_active'], $indexes->get('idx_products_is_active')['columns']);
    }

    public function test_product_fillable_contract_contains_only_foundation_fields(): void
    {
        $this->assertSame([
            'category_id',
            'name',
            'sku',
            'description',
            'price',
            'image',
            'is_active',
        ], (new Product)->getFillable());
    }

    public function test_required_product_fields_reject_null_values_at_database_level(): void
    {
        $category = Category::factory()->create();
        $requiredFields = ['category_id', 'name', 'slug', 'sku', 'price', 'is_active'];
        $rejectedFields = [];

        foreach ($requiredFields as $field) {
            $attributes = [
                'category_id' => $category->getKey(),
                'name' => 'Required field '.$field,
                'slug' => 'required-'.str_replace('_', '-', $field),
                'sku' => 'REQ-'.strtoupper($field),
                'price' => 10.25,
                'is_active' => true,
            ];
            $attributes[$field] = null;

            try {
                DB::table('products')->insert($attributes);
            } catch (QueryException) {
                $rejectedFields[] = $field;
            }
        }

        $this->assertSame($requiredFields, $rejectedFields);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_sku_must_be_unique(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        Product::factory()->create(['sku' => $product->sku]);
    }

    public function test_product_can_be_created_by_factory_with_valid_values(): void
    {
        $product = Product::factory()->create();

        $this->assertModelExists($product);
        $this->assertModelExists($product->category);
        $this->assertLessThanOrEqual(50, strlen($product->sku));
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $product->price);
        $this->assertTrue($product->is_active);
        $this->assertNull($product->deleted_at);
    }

    public function test_product_belongs_to_category_and_category_has_many_products(): void
    {
        $category = Category::factory()->create();
        $products = Product::factory()->count(2)->for($category)->create();

        $this->assertTrue($products->first()->category->is($category));
        $this->assertCount(2, $category->products);
        $this->assertTrue($category->products->contains($products->first()));
        $this->assertTrue($category->products->contains($products->last()));
    }

    public function test_defaults_nullable_fields_and_casts_are_applied(): void
    {
        $category = Category::factory()->create();
        $product = Product::query()->create([
            'category_id' => $category->getKey(),
            'name' => 'Product with defaults',
            'sku' => 'DEFAULT-001',
            'price' => '12.30',
        ])->refresh();

        $this->assertNull($product->description);
        $this->assertNull($product->image);
        $this->assertNull($product->deleted_at);
        $this->assertSame('12.30', $product->price);
        $this->assertIsString($product->price);
        $this->assertTrue($product->is_active);
        $this->assertIsBool($product->is_active);
    }

    public function test_invalid_category_id_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Product::factory()->create(['category_id' => 999999]);
    }

    public function test_product_uses_soft_deletes_and_remains_recoverable_with_trashed(): void
    {
        $product = Product::factory()->create();

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertNull(Product::query()->find($product->getKey()));

        $trashedProduct = Product::withTrashed()->findOrFail($product->getKey());

        $this->assertTrue($trashedProduct->trashed());
        $this->assertNotNull($trashedProduct->deleted_at);
    }

    public function test_category_relation_excludes_trashed_products_by_default_and_can_include_them(): void
    {
        $category = Category::factory()->create();
        $activeProduct = Product::factory()->for($category)->create();
        $trashedProduct = Product::factory()->for($category)->create();
        $trashedProduct->delete();

        $this->assertCount(1, $category->products()->get());
        $this->assertTrue($category->products()->first()->is($activeProduct));
        $this->assertCount(2, $category->products()->withTrashed()->get());
    }

    public function test_foreign_key_restricts_category_deletion_even_for_a_trashed_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $product->delete();

        try {
            $category->delete();
            $this->fail('The category foreign key should restrict deletion while a product row exists.');
        } catch (QueryException) {
            $this->assertDatabaseHas('categories', ['id' => $category->getKey()]);
            $this->assertSoftDeleted($product);
            $this->assertNotNull(Product::withTrashed()->find($product->getKey()));
        }
    }

    public function test_factory_generates_repeated_valid_products_with_unique_skus(): void
    {
        $products = Product::factory()->count(3)->create();

        $this->assertCount(3, $products);
        $this->assertCount(3, $products->pluck('sku')->unique());

        foreach ($products as $product) {
            $this->assertModelExists($product);
            $this->assertModelExists($product->category);
            $this->assertGreaterThan(0, (float) $product->price);
        }
    }

    public function test_factory_active_and_inactive_states_match_the_boolean_cast(): void
    {
        $active = Product::factory()->active()->create();
        $inactive = Product::factory()->inactive()->create();

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);
        $this->assertIsBool($active->is_active);
        $this->assertIsBool($inactive->is_active);
    }
}
