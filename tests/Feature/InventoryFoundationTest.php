<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Observers\ProductObserver;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class InventoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventories_table_matches_the_contract_exactly(): void
    {
        $this->assertTrue(Schema::hasTable('inventories'));
        $this->assertSame([
            'id',
            'product_id',
            'stock',
            'reserved_stock',
            'min_stock',
            'updated_at',
        ], Schema::getColumnListing('inventories'));
        $this->assertFalse(Schema::hasColumn('inventories', 'created_at'));
        $this->assertFalse(Schema::hasColumn('inventories', 'deleted_at'));

        $indexes = collect(Schema::getIndexes('inventories'))->keyBy('name');

        $this->assertTrue($indexes->has('inventories_product_id_unique'));
        $this->assertTrue($indexes->get('inventories_product_id_unique')['unique']);
        $this->assertSame(['product_id'], $indexes->get('inventories_product_id_unique')['columns']);

        $migration = file_get_contents(database_path('migrations/2026_07_22_000002_create_inventories_table.php'));

        $this->assertIsString($migration);
        $this->assertStringContainsString('$table->id();', $migration);
        $this->assertStringContainsString('$table->unsignedInteger(\'product_id\')->unique();', $migration);
        $this->assertStringContainsString('$table->unsignedInteger(\'stock\')->default(0);', $migration);
        $this->assertStringContainsString('$table->unsignedInteger(\'reserved_stock\')->default(0);', $migration);
        $this->assertStringContainsString('$table->unsignedInteger(\'min_stock\')->default(0);', $migration);
        $this->assertStringContainsString('->restrictOnDelete();', $migration);
        $this->assertSame(
            Schema::getColumnType('products', 'id'),
            Schema::getColumnType('inventories', 'product_id'),
        );
    }

    public function test_inventory_model_uses_only_the_contractual_configuration(): void
    {
        $inventory = new Inventory;

        $this->assertSame('inventories', $inventory->getTable());
        $this->assertSame([
            'product_id',
            'stock',
            'reserved_stock',
            'min_stock',
        ], $inventory->getFillable());
        $this->assertTrue($inventory->usesTimestamps());
        $this->assertNull(Inventory::CREATED_AT);
        $this->assertSame('updated_at', Inventory::UPDATED_AT);
    }

    public function test_database_defaults_and_integer_casts_are_applied(): void
    {
        $category = Category::factory()->create();
        $productId = DB::table('products')->insertGetId($this->rawProductAttributes($category));
        $inventory = Inventory::query()->create(['product_id' => $productId])->refresh();

        $this->assertSame(0, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
        $this->assertSame(0, $inventory->min_stock);
        $this->assertIsInt($inventory->stock);
        $this->assertIsInt($inventory->reserved_stock);
        $this->assertIsInt($inventory->min_stock);
        $this->assertSame(0, $inventory->available_stock);
        $this->assertNotNull($inventory->updated_at);
    }

    public function test_product_factory_creates_exactly_one_inventory_idempotently(): void
    {
        $products = Product::factory()->count(5)->create();

        $this->assertDatabaseCount('inventories', 5);

        foreach ($products as $product) {
            $this->assertInstanceOf(Inventory::class, $product->inventory);
            $this->assertSame(1, Inventory::query()->where('product_id', $product->getKey())->count());
            app(ProductObserver::class)->created($product);
            $this->assertSame(1, Inventory::query()->where('product_id', $product->getKey())->count());
        }

        $this->assertDatabaseCount('inventories', 5);
    }

    public function test_product_and_inventory_relations_work_in_both_directions(): void
    {
        $product = Product::factory()->create();
        $inventory = $product->inventory;

        $this->assertTrue($inventory->product->is($product));
        $this->assertTrue($product->inventory()->firstOrFail()->is($inventory));
    }

    public function test_product_id_is_unique_and_rejects_nonexistent_products(): void
    {
        $product = Product::factory()->create();

        try {
            Inventory::query()->create([
                'product_id' => $product->getKey(),
                'stock' => 0,
                'reserved_stock' => 0,
                'min_stock' => 0,
            ]);
            $this->fail('A product cannot have more than one inventory.');
        } catch (QueryException) {
            $this->assertDatabaseCount('inventories', 1);
        }

        $this->expectException(QueryException::class);

        Inventory::query()->create([
            'product_id' => 999999,
            'stock' => 0,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ]);
    }

    public function test_zero_and_fully_reserved_stock_are_valid_and_available_stock_is_calculated(): void
    {
        $inventory = Product::factory()->create()->inventory;

        $inventory->update([
            'stock' => 0,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ]);

        $this->assertSame(0, $inventory->fresh()->available_stock);

        $inventory->update([
            'stock' => 12,
            'reserved_stock' => 12,
            'min_stock' => 0,
        ]);

        $this->assertSame(0, $inventory->fresh()->available_stock);

        $inventory->update([
            'stock' => 12,
            'reserved_stock' => 5,
            'min_stock' => 3,
        ]);

        $this->assertSame(7, $inventory->fresh()->available_stock);
    }

    public function test_reserved_stock_cannot_exceed_physical_stock(): void
    {
        $inventory = Product::factory()->create()->inventory;

        try {
            $inventory->update(['stock' => 4, 'reserved_stock' => 5]);
            $this->fail('Reserved stock greater than physical stock must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('El stock reservado no puede superar el stock físico.', $exception->getMessage());
        }

        $inventory->refresh();
        $this->assertSame(0, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
        $this->assertSame(0, $inventory->available_stock);
    }

    public function test_negative_and_decimal_quantities_are_rejected_by_the_domain(): void
    {
        $inventory = Product::factory()->create()->inventory;

        foreach (['stock', 'reserved_stock', 'min_stock'] as $attribute) {
            try {
                $inventory->update([$attribute => -1]);
                $this->fail("{$attribute} must reject negative values.");
            } catch (DomainException $exception) {
                $this->assertStringContainsString('no pueden ser negativas', $exception->getMessage());
                $inventory->refresh();
            }
        }

        try {
            $inventory->update(['stock' => '1.5']);
            $this->fail('Inventory quantities must reject decimal values.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('números enteros', $exception->getMessage());
        }

        $this->assertSame(0, $inventory->fresh()->stock);
    }

    public function test_soft_deleted_product_keeps_its_inventory_and_inverse_relation(): void
    {
        $product = Product::factory()->create();
        $inventory = $product->inventory;
        $inventory->update(['stock' => 8, 'reserved_stock' => 3, 'min_stock' => 2]);

        $product->delete();

        $trashedProduct = Product::withTrashed()->with('inventory')->findOrFail($product->getKey());

        $this->assertSoftDeleted($trashedProduct);
        $this->assertTrue($trashedProduct->inventory->is($inventory));
        $this->assertSame(8, $trashedProduct->inventory->stock);
        $this->assertSame(3, $trashedProduct->inventory->reserved_stock);
        $this->assertSame(2, $trashedProduct->inventory->min_stock);
        $this->assertTrue($inventory->fresh()->product->is($trashedProduct));
    }

    public function test_physical_product_deletion_is_restricted_without_cascades(): void
    {
        $product = Product::factory()->create();
        $inventory = $product->inventory;

        try {
            $product->forceDelete();
            $this->fail('Physical deletion must be restricted while inventory exists.');
        } catch (QueryException) {
            $this->assertNotNull(Product::withTrashed()->find($product->getKey()));
            $this->assertModelExists($inventory);
        }
    }

    public function test_updated_at_changes_without_a_created_at_column(): void
    {
        $inventory = Product::factory()->create()->inventory->fresh();
        $originalTimestamp = $inventory->updated_at;
        $future = now()->addMinute()->startOfSecond();

        Carbon::setTestNow($future);

        try {
            $inventory->update(['stock' => 1]);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertTrue($inventory->fresh()->updated_at->equalTo($future));
        $this->assertTrue($inventory->updated_at->greaterThan($originalTimestamp));
        $this->assertFalse(Schema::hasColumn('inventories', 'created_at'));
    }

    public function test_migration_backfills_active_and_soft_deleted_existing_products(): void
    {
        $category = Category::factory()->create();
        $activeProductId = DB::table('products')->insertGetId($this->rawProductAttributes($category, [
            'sku' => 'BACKFILL-ACTIVE',
        ]));
        $deletedProductId = DB::table('products')->insertGetId($this->rawProductAttributes($category, [
            'sku' => 'BACKFILL-DELETED',
            'deleted_at' => now(),
        ]));

        $this->assertDatabaseMissing('inventories', ['product_id' => $activeProductId]);
        $this->assertDatabaseMissing('inventories', ['product_id' => $deletedProductId]);

        Schema::drop('inventories');

        $migration = require database_path('migrations/2026_07_22_000002_create_inventories_table.php');
        $migration->up();

        foreach ([$activeProductId, $deletedProductId] as $productId) {
            $this->assertDatabaseHas('inventories', [
                'product_id' => $productId,
                'stock' => 0,
                'reserved_stock' => 0,
                'min_stock' => 0,
            ]);
        }

        $this->assertNotNull(Product::withTrashed()->findOrFail($deletedProductId)->deleted_at);
    }

    public function test_product_store_rolls_back_product_inventory_and_image_together(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $eventName = 'eloquent.created: '.Product::class;
        Event::listen($eventName, fn (): never => throw new RuntimeException('Forced creation failure.'));

        try {
            $this->actingAs($admin)
                ->withoutExceptionHandling()
                ->post(route('admin.products.store'), [
                    'category_id' => $category->getKey(),
                    'name' => 'Producto transaccional',
                    'sku' => 'INVENTORY-TRANSACTION',
                    'description' => 'Debe revertirse por completo.',
                    'price' => '10.00',
                    'is_active' => '1',
                    'image' => $this->fakeImage('transaction.png'),
                ]);

            $this->fail('The forced persistence exception should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced creation failure.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('inventories', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_inventory_foundation_adds_no_routes_or_future_tables(): void
    {
        $this->assertCount(36, Route::getRoutes());
        $this->assertFalse(Route::has('admin.inventories.index'));
        $this->assertFalse(Route::has('admin.inventories.store'));
        $this->assertTrue(Schema::hasTable('inventory_movements'));
        $this->assertFalse(Schema::hasTable('stock_movements'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rawProductAttributes(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->getKey(),
            'name' => 'Producto previo al inventario',
            'sku' => 'RAW-'.fake()->unique()->numerify('########'),
            'description' => null,
            'price' => '10.00',
            'image' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ], $overrides);
    }

    private function fakeImage(string $name): UploadedFile
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        $this->assertIsString($contents);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
