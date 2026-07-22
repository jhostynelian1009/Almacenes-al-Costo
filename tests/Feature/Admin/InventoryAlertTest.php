<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_routes_are_administrative_and_authorized(): void
    {
        $alertsRoute = Route::getRoutes()->getByName('admin.inventory.alerts');
        $minimumRoute = Route::getRoutes()->getByName('admin.inventory.minimum-stock.update');

        $this->assertNotNull($alertsRoute);
        $this->assertNotNull($minimumRoute);
        $this->assertSame(['GET', 'HEAD'], $alertsRoute->methods());
        $this->assertSame('admin/inventory/alerts', $alertsRoute->uri());
        $this->assertSame(['PATCH'], $minimumRoute->methods());
        $this->assertSame('admin/inventory/{inventory}/minimum-stock', $minimumRoute->uri());
        $this->assertCount(38, Route::getRoutes());

        foreach ([$alertsRoute, $minimumRoute] as $route) {
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('active', $route->gatherMiddleware());
            $this->assertContains('admin', $route->gatherMiddleware());
        }

        $inventory = Product::factory()->create()->inventory;
        $alertsUrl = route('admin.inventory.alerts');
        $minimumUrl = route('admin.inventory.minimum-stock.update', $inventory);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($alertsUrl)->assertOk();
        $this->actingAs($admin)->patch($minimumUrl, ['min_stock' => 1])
            ->assertRedirect(route('admin.inventory.show', $inventory));

        auth()->logout();
        $this->get($alertsUrl)->assertRedirect(route('login'));
        $this->patch($minimumUrl, ['min_stock' => 2])->assertRedirect(route('login'));

        $employee = User::factory()->create();
        $this->actingAs($employee)->get($alertsUrl)->assertForbidden();
        $this->actingAs($employee)->patch($minimumUrl, ['min_stock' => 2])->assertForbidden();

        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $this->actingAs($inactiveAdmin)->get($alertsUrl)->assertRedirect(route('login'));
        $this->actingAs($inactiveAdmin)->patch($minimumUrl, ['min_stock' => 2])
            ->assertRedirect(route('login'));

        $this->get('/inventory/alerts')->assertNotFound();
        $this->assertFalse(Route::has('admin.inventory.alerts.dismiss'));
        $this->assertFalse(Route::has('admin.inventory.alerts.read'));
        $this->assertFalse(Route::has('admin.inventory.alerts.destroy'));
    }

    public function test_stock_status_classifies_every_contractual_boundary_dynamically(): void
    {
        $outOfStock = $this->inventory(stock: 0, reserved: 0, minimum: 0);
        $equalMinimum = $this->inventory(stock: 5, reserved: 2, minimum: 3);
        $belowMinimum = $this->inventory(stock: 5, reserved: 3, minimum: 3);
        $sufficient = $this->inventory(stock: 4, reserved: 0, minimum: 3);
        $zeroMinimumSufficient = $this->inventory(stock: 1, reserved: 0, minimum: 0);
        $reservationCreatesLowStock = $this->inventory(stock: 5, reserved: 4, minimum: 1);

        $this->assertSame(Inventory::STOCK_STATUS_OUT_OF_STOCK, $outOfStock->stock_status);
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $equalMinimum->stock_status);
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $belowMinimum->stock_status);
        $this->assertSame(Inventory::STOCK_STATUS_SUFFICIENT, $sufficient->stock_status);
        $this->assertSame(Inventory::STOCK_STATUS_SUFFICIENT, $zeroMinimumSufficient->stock_status);
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $reservationCreatesLowStock->stock_status);
        $this->assertSame(0, $outOfStock->available_stock);
        $this->assertSame(1, $reservationCreatesLowStock->available_stock);
        $this->assertFalse(Schema::hasColumn('inventories', 'stock_status'));
        $this->assertFalse(Schema::hasTable('alerts'));
    }

    public function test_alerts_include_only_out_of_stock_and_low_stock_for_non_deleted_products(): void
    {
        $admin = User::factory()->admin()->create();
        $outOfStock = $this->inventory(['name' => 'Agotado'], 0, 0, 0);
        $lowStock = $this->inventory(['name' => 'Bajo'], 5, 3, 2);
        $sufficient = $this->inventory(['name' => 'Suficiente'], 5, 1, 2);
        $deleted = $this->inventory(['name' => 'Eliminado'], 0, 0, 0);
        $deleted->product->delete();

        $ids = $this->alertIds($admin);

        $this->assertContains($outOfStock->id, $ids);
        $this->assertContains($lowStock->id, $ids);
        $this->assertNotContains($sufficient->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
        $this->assertModelExists($deleted->fresh());
    }

    public function test_alerts_have_contractual_stable_order(): void
    {
        $admin = User::factory()->admin()->create();
        $outZeta = $this->inventory(['name' => 'Zeta agotado'], 0, 0, 0);
        $lowTwo = $this->inventory(['name' => 'Charlie bajo'], 4, 2, 3);
        $outAlpha = $this->inventory(['name' => 'Alfa agotado'], 0, 0, 4);
        $lowOne = $this->inventory(['name' => 'Beta bajo'], 3, 2, 2);
        $duplicateFirst = $this->inventory(['name' => 'Duplicado'], 5, 2, 4);
        $duplicateSecond = $this->inventory(['name' => 'Duplicado'], 6, 3, 4);

        $this->assertSame(
            [
                $outAlpha->id,
                $outZeta->id,
                $lowOne->id,
                $lowTwo->id,
                $duplicateFirst->id,
                $duplicateSecond->id,
            ],
            $this->alertIds($admin),
        );
    }

    public function test_alerts_paginate_fifteen_and_render_the_second_page(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 17) as $number) {
            $this->inventory(['name' => sprintf('Producto alerta %02d', $number)], 0, 0, 0);
        }

        $firstPage = $this->actingAs($admin)
            ->get(route('admin.inventory.alerts'))
            ->assertOk()
            ->viewData('inventories');
        $secondPage = $this->get(route('admin.inventory.alerts', ['page' => 2]))
            ->assertOk()
            ->viewData('inventories');

        $this->assertInstanceOf(LengthAwarePaginator::class, $firstPage);
        $this->assertSame(15, $firstPage->perPage());
        $this->assertSame(17, $firstPage->total());
        $this->assertCount(15, $firstPage->items());
        $this->assertCount(2, $secondPage->items());
        $this->assertSame(2, $secondPage->currentPage());
    }

    public function test_minimum_stock_update_changes_only_the_configuration_and_accepts_zero(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = $this->inventory(stock: 10, reserved: 2, minimum: 1);
        app(InventoryService::class)->recordEntry($inventory, 1, 'Movimiento previo');
        $inventory = $inventory->fresh();
        $movementCount = $inventory->movements()->count();

        $this->actingAs($admin)
            ->patch(route('admin.inventory.minimum-stock.update', $inventory), [
                'min_stock' => '5',
                'stock' => 999,
                'reserved_stock' => 999,
                'product_id' => Product::factory()->create()->id,
                'available_stock' => 999,
            ])
            ->assertRedirect(route('admin.inventory.show', $inventory))
            ->assertSessionHas('success', 'Stock mínimo actualizado correctamente.');

        $updated = $inventory->fresh();
        $this->assertSame(5, $updated->min_stock);
        $this->assertSame(11, $updated->stock);
        $this->assertSame(2, $updated->reserved_stock);
        $this->assertSame($inventory->product_id, $updated->product_id);
        $this->assertSame($movementCount, $updated->movements()->count());

        $this->patch(route('admin.inventory.minimum-stock.update', $inventory), ['min_stock' => 0])
            ->assertRedirect(route('admin.inventory.show', $inventory));
        $this->assertSame(0, $inventory->fresh()->min_stock);
        $this->assertSame($movementCount, $inventory->movements()->count());
    }

    public function test_minimum_stock_rejects_missing_negative_decimal_text_and_out_of_range_values(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = $this->inventory(stock: 5, reserved: 0, minimum: 3);
        $url = route('admin.inventory.minimum-stock.update', $inventory);
        $detailUrl = route('admin.inventory.show', $inventory);
        $invalidPayloads = [
            [],
            ['min_stock' => -1],
            ['min_stock' => '1.5'],
            ['min_stock' => 'texto'],
            ['min_stock' => '4294967296'],
        ];

        foreach ($invalidPayloads as $payload) {
            $this->actingAs($admin)
                ->from($detailUrl)
                ->patch($url, $payload)
                ->assertRedirect($detailUrl)
                ->assertSessionHasErrors('min_stock');
            $this->assertSame(3, $inventory->fresh()->min_stock);
        }

        $this->assertSame(5, $inventory->fresh()->stock);
        $this->assertSame(0, $inventory->fresh()->reserved_stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_deleted_product_keeps_inventory_but_rejects_minimum_update_and_hides_form(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = $this->inventory(['name' => 'Producto eliminado'], 4, 1, 2);
        $inventory->product->delete();

        $this->actingAs($admin)
            ->patch(route('admin.inventory.minimum-stock.update', $inventory), ['min_stock' => 8])
            ->assertNotFound();

        $this->assertSame(2, $inventory->fresh()->min_stock);
        $this->assertModelExists($inventory->fresh());
        $this->get(route('admin.inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Producto eliminado')
            ->assertDontSee('Configurar stock mínimo')
            ->assertDontSee(route('admin.inventory.minimum-stock.update', $inventory));
    }

    public function test_minimum_update_uses_a_local_transaction_and_row_lock_without_inventory_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryMinimumStockController.php'));
        $request = file_get_contents(app_path('Http/Requests/Admin/UpdateMinimumStockRequest.php'));

        $this->assertIsString($controller);
        $this->assertIsString($request);
        $this->assertStringContainsString('DB::transaction(', $controller);
        $this->assertStringContainsString('->lockForUpdate()', $controller);
        $this->assertStringContainsString("'min_stock' => \$minimumStock", $controller);
        $this->assertStringNotContainsString('InventoryService', $controller);
        $this->assertStringNotContainsString('InventoryMovement', $controller);
        $this->assertStringContainsString("'required', 'integer', 'min:0', 'max:4294967295'", $request);
        $this->assertStringNotContainsString("'stock' =>", $request);
        $this->assertStringNotContainsString("'reserved_stock' =>", $request);
        $this->assertStringNotContainsString("'product_id' =>", $request);
    }

    public function test_changing_minimum_stock_creates_and_resolves_alerts_dynamically(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = $this->inventory(stock: 5, reserved: 0, minimum: 0);

        $this->assertNotContains($inventory->id, $this->alertIds($admin));

        $this->patch(route('admin.inventory.minimum-stock.update', $inventory), ['min_stock' => 5])
            ->assertRedirect(route('admin.inventory.show', $inventory));
        $this->assertContains($inventory->id, $this->alertIds($admin));

        $this->patch(route('admin.inventory.minimum-stock.update', $inventory), ['min_stock' => 0])
            ->assertRedirect(route('admin.inventory.show', $inventory));
        $this->assertNotContains($inventory->id, $this->alertIds($admin));
        $this->assertFalse(Schema::hasTable('alerts'));
    }

    public function test_inventory_movements_create_and_resolve_alerts_from_current_balances(): void
    {
        $service = app(InventoryService::class);
        $entryInventory = $this->inventory(stock: 0, reserved: 0, minimum: 2);
        $exitInventory = $this->inventory(stock: 5, reserved: 0, minimum: 2);
        $reservationInventory = $this->inventory(stock: 5, reserved: 0, minimum: 2);

        $this->assertSame(Inventory::STOCK_STATUS_OUT_OF_STOCK, $entryInventory->stock_status);
        $service->recordEntry($entryInventory, 3, 'Resuelve alerta');
        $this->assertSame(Inventory::STOCK_STATUS_SUFFICIENT, $entryInventory->fresh()->stock_status);

        $service->recordExit($exitInventory, 3, 'Genera alerta');
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $exitInventory->fresh()->stock_status);

        $service->reserve($reservationInventory, 3, 'Genera alerta por reserva');
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $reservationInventory->fresh()->stock_status);
        $service->release($reservationInventory, 1, 'Resuelve alerta por liberación');
        $this->assertSame(Inventory::STOCK_STATUS_SUFFICIENT, $reservationInventory->fresh()->stock_status);
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->assertFalse(Schema::hasTable('alerts'));
    }

    public function test_inventory_interfaces_show_textual_states_links_form_and_empty_state(): void
    {
        $admin = User::factory()->admin()->create();
        $outOfStock = $this->inventory(['name' => 'Producto agotado'], 0, 0, 0);
        $lowStock = $this->inventory(['name' => 'Producto bajo'], 2, 1, 1);
        $this->inventory(['name' => 'Producto suficiente'], 3, 0, 1);

        $index = $this->actingAs($admin)->get(route('admin.inventory.index'));
        $index->assertOk()
            ->assertSee('Agotado')
            ->assertSee('Stock bajo')
            ->assertSee('Suficiente')
            ->assertSee(route('admin.inventory.alerts'));
        $this->assertSame(1, substr_count($index->getContent(), '<h1'));

        $detail = $this->get(route('admin.inventory.show', $lowStock));
        $detail->assertOk()
            ->assertSee('Estado del stock')
            ->assertSee('Stock bajo')
            ->assertSee('Configurar stock mínimo')
            ->assertSee('La alerta se activa cuando el stock disponible es igual o inferior al mínimo.')
            ->assertSee('name="min_stock"', false)
            ->assertSee('type="number"', false)
            ->assertSee('method="POST"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('name="_token"', false)
            ->assertSee(route('admin.inventory.minimum-stock.update', $lowStock));
        $this->assertSame(1, substr_count($detail->getContent(), '<h1'));

        $alerts = $this->get(route('admin.inventory.alerts'));
        $alerts->assertOk()
            ->assertSee('Producto agotado')
            ->assertSee('Producto bajo')
            ->assertSee('Agotado')
            ->assertSee('Stock bajo')
            ->assertSee(route('admin.inventory.show', $outOfStock))
            ->assertSee(route('admin.inventory.index'));
        $this->assertSame(1, substr_count($alerts->getContent(), '<h1'));

        Inventory::query()->update(['min_stock' => 0]);
        $outOfStock->update(['stock' => 1]);
        $this->get(route('admin.inventory.alerts'))
            ->assertOk()
            ->assertSee('No existen productos con stock bajo o agotado.')
            ->assertSee('role="status"', false)
            ->assertDontSee('Crear inventario');

        $sidebar = file_get_contents(resource_path('views/components/admin/sidebar.blade.php'));
        $this->assertIsString($sidebar);
        $this->assertStringNotContainsString('Inventory::', $sidebar);
        $this->assertStringNotContainsString('alerting()', $sidebar);
    }

    public function test_alert_query_filters_and_paginates_in_the_database_without_n_plus_one(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(3)->create();

        foreach ($categories as $category) {
            foreach (range(1, 3) as $number) {
                $this->inventory([
                    'category_id' => $category->id,
                    'name' => $category->name.' '.$number,
                ], 0, 0, 0);
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $inventories = $this->actingAs($admin)
            ->get(route('admin.inventory.alerts'))
            ->assertOk()
            ->viewData('inventories');
        $queries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $productQueries = $queries->filter(fn (string $query): bool => str_contains($query, 'products'));
        $categoryQueries = $queries->filter(fn (string $query): bool => str_contains($query, 'categories'));

        $this->assertInstanceOf(LengthAwarePaginator::class, $inventories);
        $this->assertSame(15, $inventories->perPage());
        $this->assertCount(9, $inventories->items());
        $this->assertLessThanOrEqual(3, $productQueries->count());
        $this->assertLessThanOrEqual(1, $categoryQueries->count());
        $this->assertTrue($inventories->getCollection()->every(
            fn (Inventory $inventory): bool => $inventory->relationLoaded('product')
                && $inventory->product->relationLoaded('category')
        ));

        $controller = file_get_contents(app_path('Http/Controllers/Admin/InventoryAlertController.php'));
        $this->assertIsString($controller);
        $this->assertStringContainsString('->alerting()', $controller);
        $this->assertStringContainsString('->paginate(15)', $controller);
        $this->assertStringNotContainsString('->get()', $controller);

        DB::flushQueryLog();
        $indexInventories = $this->get(route('admin.inventory.index'))
            ->assertOk()
            ->viewData('inventories');
        $indexQueries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');

        $this->assertLessThanOrEqual(
            3,
            $indexQueries->filter(fn (string $query): bool => str_contains($query, 'products'))->count(),
        );
        $this->assertLessThanOrEqual(
            1,
            $indexQueries->filter(fn (string $query): bool => str_contains($query, 'categories'))->count(),
        );
        $this->assertTrue($indexInventories->getCollection()->every(
            fn (Inventory $inventory): bool => $inventory->relationLoaded('product')
                && $inventory->product->relationLoaded('category')
        ));
    }

    /**
     * @param  array<string, mixed>  $productAttributes
     */
    private function inventory(
        array $productAttributes = [],
        int $stock = 0,
        int $reserved = 0,
        int $minimum = 0,
    ): Inventory {
        $product = Product::factory()->create($productAttributes);
        $inventory = $product->inventory;
        $inventory->update([
            'stock' => $stock,
            'reserved_stock' => $reserved,
            'min_stock' => $minimum,
        ]);

        return $inventory->fresh();
    }

    /**
     * @return array<int, int>
     */
    private function alertIds(User $admin): array
    {
        return $this->actingAs($admin)
            ->get(route('admin.inventory.alerts'))
            ->assertOk()
            ->viewData('inventories')
            ->getCollection()
            ->pluck('id')
            ->all();
    }
}
