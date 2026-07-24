<?php

namespace Tests\Feature\Admin;

use App\Exceptions\InventoryOperationException;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryEpicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_completes_the_full_inventory_lifecycle_with_exact_snapshots(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administradora integral']);
        $product = Product::factory()->create([
            'name' => 'Producto del flujo integral',
            'sku' => 'INV-E2E-001',
        ]);
        $inventory = $product->inventory;

        $this->assertDatabaseCount('inventories', 1);
        $this->assertSame($product->getKey(), $inventory->product_id);
        $this->assertDatabaseCount('inventory_movements', 0);

        $operations = [
            [InventoryMovement::TYPE_ENTRY, 20, null, 'Recepción inicial', 'ep008-entry'],
            [InventoryMovement::TYPE_RESERVE, 6, null, 'Reserva comercial', 'ep008-reserve'],
            [InventoryMovement::TYPE_EXIT, 5, null, 'Despacho disponible', 'ep008-exit'],
            [InventoryMovement::TYPE_RELEASE, 2, null, 'Liberación parcial', 'ep008-release'],
            [InventoryMovement::TYPE_ADJUSTMENT, null, 8, 'Conteo físico final', 'ep008-adjustment'],
        ];

        foreach ($operations as $index => [$type, $quantity, $newStock, $reason, $key]) {
            $this->postMovement($admin, $inventory, [
                'type' => $type,
                'quantity' => $quantity,
                'new_stock' => $newStock,
                'reason' => $reason,
                'idempotency_key' => $key,
            ])->assertRedirect(route('admin.inventory.show', $inventory))
                ->assertSessionHas('success', 'Movimiento de inventario registrado correctamente.');

            $this->assertSame($index + 1, $inventory->movements()->count());
        }

        $inventory = $inventory->fresh();
        $this->assertSame(8, $inventory->stock);
        $this->assertSame(4, $inventory->reserved_stock);
        $this->assertSame(4, $inventory->available_stock);
        $this->assertSame(0, $inventory->min_stock);
        $this->assertSame(Inventory::STOCK_STATUS_SUFFICIENT, $inventory->stock_status);

        $movementCount = $inventory->movements()->count();
        $this->actingAs($admin)
            ->patch(route('admin.inventory.minimum-stock.update', $inventory), [
                'min_stock' => 4,
                'stock' => 999,
                'reserved_stock' => 999,
                'product_id' => Product::factory()->create()->getKey(),
            ])
            ->assertRedirect(route('admin.inventory.show', $inventory))
            ->assertSessionHas('success', 'Stock mínimo actualizado correctamente.');

        $inventory = $inventory->fresh();
        $this->assertSame(8, $inventory->stock);
        $this->assertSame(4, $inventory->reserved_stock);
        $this->assertSame(4, $inventory->available_stock);
        $this->assertSame(4, $inventory->min_stock);
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $inventory->stock_status);
        $this->assertSame($movementCount, $inventory->movements()->count());

        $expectedSnapshots = [
            [InventoryMovement::TYPE_ENTRY, 20, 0, 0, 20, 0, 0, 'Recepción inicial'],
            [InventoryMovement::TYPE_RESERVE, 0, 6, 20, 20, 0, 6, 'Reserva comercial'],
            [InventoryMovement::TYPE_EXIT, -5, 0, 20, 15, 6, 6, 'Despacho disponible'],
            [InventoryMovement::TYPE_RELEASE, 0, -2, 15, 15, 6, 4, 'Liberación parcial'],
            [InventoryMovement::TYPE_ADJUSTMENT, -7, 0, 15, 8, 4, 4, 'Conteo físico final'],
        ];
        $movements = $inventory->movements()->orderBy('id')->get();

        foreach ($movements as $index => $movement) {
            $this->assertSame($expectedSnapshots[$index], [
                $movement->type,
                $movement->stock_delta,
                $movement->reserved_delta,
                $movement->stock_before,
                $movement->stock_after,
                $movement->reserved_before,
                $movement->reserved_after,
                $movement->reason,
            ]);
            $this->assertSame($admin->getKey(), $movement->created_by);
            $this->assertNull($movement->reference_type);
            $this->assertNull($movement->reference_id);
        }

        $alerts = $this->actingAs($admin)->get(route('admin.inventory.alerts'));
        $alerts->assertOk()
            ->assertSee('Producto del flujo integral')
            ->assertSee('Stock bajo')
            ->assertSee(route('admin.inventory.show', $inventory));

        $historyResponse = $this->get(route('admin.inventory.show', $inventory))->assertOk();
        $history = $historyResponse->viewData('movements');

        $this->assertSame(
            $movements->sortByDesc('id')->pluck('id')->values()->all(),
            $history->getCollection()->pluck('id')->all(),
        );
        $historyResponse->assertSeeInOrder([
            'Conteo físico final',
            'Liberación parcial',
            'Despacho disponible',
            'Reserva comercial',
            'Recepción inicial',
        ])->assertSee('Administradora integral');
    }

    public function test_idempotency_and_invalid_operations_preserve_the_complete_state_and_safe_messages(): void
    {
        $admin = User::factory()->admin()->create();
        $otherInventory = Product::factory()->create()->inventory;
        $inventory = Product::factory()->create()->inventory;
        $maliciousActor = User::factory()->create();
        $entryPayload = [
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 10,
            'new_stock' => null,
            'reason' => 'Entrada idempotente integral',
            'idempotency_key' => 'ep008-shared-entry',
            'stock' => 999,
            'reserved_stock' => 999,
            'product_id' => $otherInventory->product_id,
            'inventory_id' => $otherInventory->getKey(),
            'created_by' => $maliciousActor->getKey(),
            'stock_before' => 999,
            'stock_after' => 999,
            'reserved_before' => 999,
            'reserved_after' => 999,
            'stock_delta' => 999,
            'reserved_delta' => 999,
            'reference_type' => 'purchase',
            'reference_id' => 999,
            'deleted_at' => now(),
        ];

        $this->postMovement($admin, $inventory, $entryPayload)
            ->assertRedirect(route('admin.inventory.show', $inventory));
        $this->postMovement($admin, $inventory, $entryPayload)
            ->assertRedirect(route('admin.inventory.show', $inventory));

        $entry = InventoryMovement::query()->sole();
        $this->assertSame(10, $inventory->fresh()->stock);
        $this->assertSame(0, $inventory->fresh()->reserved_stock);
        $this->assertSame(0, $otherInventory->fresh()->stock);
        $this->assertSame($inventory->getKey(), $entry->inventory_id);
        $this->assertSame($admin->getKey(), $entry->created_by);
        $this->assertSame(0, $entry->stock_before);
        $this->assertSame(10, $entry->stock_after);
        $this->assertSame(10, $entry->stock_delta);
        $this->assertNull($entry->reference_type);
        $this->assertNull($entry->reference_id);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->postMovement($admin, $inventory, [
            'type' => InventoryMovement::TYPE_RESERVE,
            'quantity' => 4,
            'new_stock' => null,
            'reason' => 'Reserva válida previa',
            'idempotency_key' => 'ep008-valid-reserve',
        ])->assertRedirect(route('admin.inventory.show', $inventory));

        $invalidOperations = [
            [InventoryMovement::TYPE_EXIT, 7, null, 'No existe stock disponible suficiente'],
            [InventoryMovement::TYPE_RESERVE, 7, null, 'No existe stock disponible suficiente'],
            [InventoryMovement::TYPE_RELEASE, 5, null, 'La cantidad a liberar supera el stock reservado'],
            [InventoryMovement::TYPE_ADJUSTMENT, null, 3, 'El nuevo saldo de inventario no es válido'],
        ];

        foreach ($invalidOperations as $index => [$type, $quantity, $newStock, $safeMessage]) {
            $formUrl = route('admin.inventory.movements.create', [$inventory, 'type' => $type]);

            $this->actingAs($admin)
                ->from($formUrl)
                ->post(route('admin.inventory.movements.store', $inventory), [
                    'type' => $type,
                    'quantity' => $quantity,
                    'new_stock' => $newStock,
                    'reason' => 'Operación inválida integral',
                    'idempotency_key' => 'ep008-invalid-'.$index,
                ])
                ->assertRedirect($formUrl)
                ->assertSessionHas('error', fn (string $message): bool => str_contains($message, $safeMessage)
                    && ! str_contains($message, 'SQLSTATE')
                    && ! str_contains($message, 'constraint'));

            $this->assertSame(10, $inventory->fresh()->stock);
            $this->assertSame(4, $inventory->fresh()->reserved_stock);
            $this->assertSame(6, $inventory->fresh()->available_stock);
            $this->assertDatabaseCount('inventory_movements', 2);
        }
    }

    public function test_soft_deleted_product_retains_history_but_is_excluded_and_rejects_every_mutation(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'name' => 'Producto histórico integral',
            'sku' => 'INV-DELETED-001',
        ]);
        $inventory = $product->inventory;
        $service = app(InventoryService::class);

        $service->recordEntry($inventory, 5, 'Entrada histórica del sistema');
        $this->postMovement($admin, $inventory, [
            'type' => InventoryMovement::TYPE_RESERVE,
            'quantity' => 2,
            'new_stock' => null,
            'reason' => 'Reserva histórica administrativa',
            'idempotency_key' => 'ep008-deleted-reserve',
        ])->assertRedirect(route('admin.inventory.show', $inventory));
        $product->delete();

        $this->actingAs($reviewer)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertDontSee('Producto histórico integral')
            ->assertDontSee('INV-DELETED-001');
        $this->get(route('admin.inventory.alerts'))
            ->assertOk()
            ->assertDontSee('Producto histórico integral')
            ->assertDontSee('INV-DELETED-001');
        $this->get(route('admin.inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Producto eliminado')
            ->assertSee('Entrada histórica del sistema')
            ->assertSee('Reserva histórica administrativa')
            ->assertDontSee('Registrar movimiento')
            ->assertDontSee('Configurar stock mínimo');

        $this->get(route('admin.inventory.movements.create', $inventory))->assertNotFound();
        $this->from(route('admin.inventory.show', $inventory))
            ->post(route('admin.inventory.movements.store', $inventory), [
                'type' => InventoryMovement::TYPE_ENTRY,
                'quantity' => 1,
                'new_stock' => null,
                'reason' => 'Operación prohibida',
                'idempotency_key' => 'ep008-deleted-entry',
            ])
            ->assertRedirect(route('admin.inventory.show', $inventory))
            ->assertSessionHas('error', 'No se puede modificar el inventario de un producto eliminado.');
        $this->patch(route('admin.inventory.minimum-stock.update', $inventory), ['min_stock' => 20])
            ->assertNotFound();

        $this->assertSame(5, $inventory->fresh()->stock);
        $this->assertSame(2, $inventory->fresh()->reserved_stock);
        $this->assertSame(0, $inventory->fresh()->min_stock);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertModelExists($inventory->fresh());

        $physicalDeletionWasRestricted = false;

        try {
            Product::withTrashed()->findOrFail($product->getKey())->forceDelete();
        } catch (QueryException) {
            $physicalDeletionWasRestricted = true;
        }

        $this->assertTrue($physicalDeletionWasRestricted);
        $this->assertNotNull(Product::withTrashed()->find($product->getKey()));
        $this->assertModelExists($inventory->fresh());
    }

    public function test_actor_history_and_movement_immutability_survive_related_deletions(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $otherInventory = Product::factory()->create()->inventory;
        $service = app(InventoryService::class);
        $systemMovement = $service->recordEntry($inventory, 4, 'Movimiento del sistema');
        $adminMovement = $service->reserve($inventory, 1, 'Movimiento administrativo', $admin);

        $this->assertNull($systemMovement->created_by);
        $this->assertSame($admin->getKey(), $adminMovement->created_by);
        $admin->delete();
        $this->assertNull($adminMovement->fresh()->created_by);
        $this->assertModelExists($adminMovement->fresh());

        $attempts = [
            function () use ($adminMovement): void {
                $adminMovement->fresh()->update(['reason' => 'Alterado']);
            },
            function () use ($adminMovement): void {
                $adminMovement->fresh()->delete();
            },
            function () use ($adminMovement, $otherInventory): void {
                $movement = $adminMovement->fresh();
                $movement->inventory_id = $otherInventory->getKey();
                $movement->save();
            },
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('El movimiento permitió una mutación prohibida.');
            } catch (InventoryOperationException $exception) {
                $this->assertSame('Los movimientos de inventario son inmutables.', $exception->getMessage());
            }
        }

        $adminMovement = $adminMovement->fresh();
        $this->assertSame($inventory->getKey(), $adminMovement->inventory_id);
        $this->assertSame('Movimiento administrativo', $adminMovement->reason);
        $this->assertNull($adminMovement->created_by);
        $this->assertModelExists($systemMovement->fresh());
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_every_inventory_route_keeps_its_authorization_and_closed_surface(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $requests = [
            ['GET', route('admin.inventory.index'), []],
            ['GET', route('admin.inventory.alerts'), []],
            ['GET', route('admin.inventory.show', $inventory), []],
            ['GET', route('admin.inventory.movements.create', $inventory), []],
            ['PATCH', route('admin.inventory.minimum-stock.update', $inventory), ['min_stock' => 1]],
            [
                'POST',
                route('admin.inventory.movements.store', $inventory),
                [
                    'type' => InventoryMovement::TYPE_ENTRY,
                    'quantity' => 1,
                    'new_stock' => null,
                    'reason' => 'Operación autorizada',
                    'idempotency_key' => 'ep008-authorized-entry',
                ],
            ],
        ];

        foreach ($requests as [$method, $url, $parameters]) {
            $this->call($method, $url, $parameters)->assertRedirect(route('login'));
        }

        $employee = User::factory()->create();
        foreach ($requests as [$method, $url, $parameters]) {
            $this->actingAs($employee)->call($method, $url, $parameters)->assertForbidden();
        }

        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        foreach ($requests as [$method, $url, $parameters]) {
            $this->actingAs($inactiveAdmin)->call($method, $url, $parameters)->assertRedirect(route('login'));
        }

        $admin = User::factory()->admin()->create();
        foreach (array_slice($requests, 0, 4) as [$method, $url, $parameters]) {
            $this->actingAs($admin)->call($method, $url, $parameters)->assertOk();
        }
        foreach (array_slice($requests, 4) as [$method, $url, $parameters]) {
            $this->actingAs($admin)
                ->call($method, $url, $parameters)
                ->assertRedirect(route('admin.inventory.show', $inventory));
        }

        $this->assertSame(1, $inventory->fresh()->stock);
        $this->assertSame(1, $inventory->fresh()->min_stock);
        $this->assertDatabaseCount('inventory_movements', 1);

        $expectedRoutes = [
            'admin.inventory.alerts',
            'admin.inventory.index',
            'admin.inventory.minimum-stock.update',
            'admin.inventory.movements.create',
            'admin.inventory.movements.store',
            'admin.inventory.show',
        ];
        $actualRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.inventory.'))
            ->sort()
            ->values()
            ->all();
        sort($expectedRoutes);

        $this->assertSame($expectedRoutes, $actualRoutes);
        $this->assertCount(57, Route::getRoutes());

        foreach ($expectedRoutes as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('active', $route->gatherMiddleware());
            $this->assertContains('admin', $route->gatherMiddleware());
        }

        foreach ([
            'admin.inventory.create',
            'admin.inventory.store',
            'admin.inventory.edit',
            'admin.inventory.update',
            'admin.inventory.destroy',
            'admin.inventory.restore',
            'admin.inventory.force-delete',
            'admin.inventory.movements.edit',
            'admin.inventory.movements.update',
            'admin.inventory.movements.destroy',
            'admin.inventory.movements.restore',
            'admin.inventory.movements.force-delete',
            'admin.inventory.alerts.dismiss',
        ] as $forbiddenRoute) {
            $this->assertFalse(Route::has($forbiddenRoute));
        }

        $publicInventoryRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'inventory'))
            ->reject(fn ($route): bool => str_starts_with($route->uri(), 'admin/'));
        $this->assertCount(0, $publicInventoryRoutes);
    }

    public function test_all_inventory_pages_keep_contractual_pagination_order_and_bounded_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(3)->create();
        $historyInventory = null;

        foreach (range(16, 1) as $number) {
            $product = Product::factory()
                ->for($categories[($number - 1) % $categories->count()])
                ->create(['name' => sprintf('Producto integración %02d', $number)]);

            if ($number === 16) {
                $historyInventory = $product->inventory;
            }
        }

        $this->assertInstanceOf(Inventory::class, $historyInventory);
        $historyInventory->update(['min_stock' => 100]);
        $service = app(InventoryService::class);

        foreach (range(1, 21) as $number) {
            $service->recordEntry($historyInventory, 1, 'Movimiento integral '.$number);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $indexFirstPage = $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->viewData('inventories');
        $indexQueries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $indexSecondPage = $this->get(route('admin.inventory.index', ['page' => 2]))
            ->assertOk()
            ->viewData('inventories');

        $this->assertInstanceOf(LengthAwarePaginator::class, $indexFirstPage);
        $this->assertSame(15, $indexFirstPage->perPage());
        $this->assertSame(16, $indexFirstPage->total());
        $this->assertSame(
            collect(range(1, 15))->map(fn (int $number): string => sprintf('Producto integración %02d', $number))->all(),
            $indexFirstPage->getCollection()->map(fn (Inventory $item): string => $item->product->name)->all(),
        );
        $this->assertSame('Producto integración 16', $indexSecondPage->first()->product->name);
        $this->assertLessThanOrEqual(
            3,
            $indexQueries->filter(fn (string $query): bool => str_contains($query, 'products'))->count(),
        );
        $this->assertLessThanOrEqual(
            1,
            $indexQueries->filter(fn (string $query): bool => str_contains($query, 'categories'))->count(),
        );

        DB::flushQueryLog();
        $alertsFirstPage = $this->get(route('admin.inventory.alerts'))
            ->assertOk()
            ->viewData('inventories');
        $alertQueries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $alertsSecondPage = $this->get(route('admin.inventory.alerts', ['page' => 2]))
            ->assertOk()
            ->viewData('inventories');

        $this->assertSame(15, $alertsFirstPage->perPage());
        $this->assertSame(16, $alertsFirstPage->total());
        $this->assertTrue($alertsFirstPage->getCollection()->every(
            fn (Inventory $item): bool => $item->stock_status === Inventory::STOCK_STATUS_OUT_OF_STOCK
        ));
        $this->assertSame($historyInventory->getKey(), $alertsSecondPage->first()->getKey());
        $this->assertSame(Inventory::STOCK_STATUS_LOW, $alertsSecondPage->first()->stock_status);
        $this->assertLessThanOrEqual(
            3,
            $alertQueries->filter(fn (string $query): bool => str_contains($query, 'products'))->count(),
        );
        $this->assertLessThanOrEqual(
            1,
            $alertQueries->filter(fn (string $query): bool => str_contains($query, 'categories'))->count(),
        );

        DB::flushQueryLog();
        $historyFirstPage = $this->get(route('admin.inventory.show', $historyInventory))
            ->assertOk()
            ->viewData('movements');
        $historyQueries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $historySecondPage = $this->get(route('admin.inventory.show', [
            'inventory' => $historyInventory,
            'page' => 2,
        ]))->assertOk()->viewData('movements');

        $this->assertSame(20, $historyFirstPage->perPage());
        $this->assertSame(21, $historyFirstPage->total());
        $this->assertSame(
            $historyInventory->movements()->orderByDesc('created_at')->orderByDesc('id')->limit(20)->pluck('id')->all(),
            $historyFirstPage->getCollection()->pluck('id')->all(),
        );
        $this->assertCount(1, $historySecondPage->items());
        $this->assertLessThanOrEqual(
            1,
            $historyQueries->filter(fn (string $query): bool => str_contains($query, 'users'))->count(),
        );
        $this->assertTrue($historyFirstPage->getCollection()->every(
            fn (InventoryMovement $movement): bool => $movement->relationLoaded('creator')
        ));
    }

    public function test_inventory_architecture_keeps_atomicity_locking_and_no_future_surface(): void
    {
        $observer = file_get_contents(app_path('Observers/ProductObserver.php'));
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $service = file_get_contents(app_path('Services/InventoryService.php'));
        $movementController = file_get_contents(app_path('Http/Controllers/Admin/InventoryMovementController.php'));
        $minimumController = file_get_contents(app_path('Http/Controllers/Admin/InventoryMinimumStockController.php'));
        $alertController = file_get_contents(app_path('Http/Controllers/Admin/InventoryAlertController.php'));
        $inventoryModel = file_get_contents(app_path('Models/Inventory.php'));

        foreach ([
            $observer,
            $provider,
            $service,
            $movementController,
            $minimumController,
            $alertController,
            $inventoryModel,
        ] as $source) {
            $this->assertIsString($source);
        }

        $this->assertStringContainsString('public function created(Product $product)', $observer);
        $this->assertStringContainsString('->firstOrCreate(', $observer);
        $this->assertSame(1, substr_count($provider, 'Product::observe(ProductObserver::class)'));

        $transactionPosition = strpos($service, 'DB::transaction(');
        $lockPosition = strpos($service, '->lockForUpdate()');
        $balancePosition = strpos($service, '$this->calculateBalances(');
        $inventoryUpdatePosition = strpos($service, '$lockedInventory->update([');
        $movementPosition = strpos($service, '$lockedInventory->movements()->create([');

        $this->assertIsInt($transactionPosition);
        $this->assertIsInt($lockPosition);
        $this->assertIsInt($balancePosition);
        $this->assertIsInt($inventoryUpdatePosition);
        $this->assertIsInt($movementPosition);
        $this->assertTrue($transactionPosition < $lockPosition);
        $this->assertTrue($lockPosition < $balancePosition);
        $this->assertTrue($balancePosition < $inventoryUpdatePosition);
        $this->assertTrue($inventoryUpdatePosition < $movementPosition);

        $this->assertStringContainsString('InventoryService $inventoryService', $movementController);
        $this->assertStringContainsString('->recordEntry(', $movementController);
        $this->assertStringContainsString('->recordExit(', $movementController);
        $this->assertStringContainsString('->adjustStock(', $movementController);
        $this->assertStringContainsString('->reserve(', $movementController);
        $this->assertStringContainsString('->release(', $movementController);
        $this->assertStringNotContainsString("'stock' =>", $movementController);
        $this->assertStringNotContainsString("'reserved_stock' =>", $movementController);

        $this->assertStringContainsString('DB::transaction(', $minimumController);
        $this->assertStringContainsString('->lockForUpdate()', $minimumController);
        $this->assertStringContainsString("'min_stock' => \$minimumStock", $minimumController);
        $this->assertStringNotContainsString("'stock' =>", $minimumController);
        $this->assertStringNotContainsString("'reserved_stock' =>", $minimumController);
        $this->assertStringNotContainsString('InventoryService', $minimumController);
        $this->assertStringNotContainsString('InventoryMovement', $minimumController);

        $this->assertStringContainsString('->alerting()', $alertController);
        $this->assertStringContainsString('->paginate(15)', $alertController);
        $this->assertStringNotContainsString('->get()', $alertController);
        $this->assertStringContainsString('(inventories.stock - inventories.reserved_stock)', $inventoryModel);
        $this->assertStringContainsString('static::saving(', $inventoryModel);
        $this->assertStringContainsString("if (\$quantities['reserved_stock'] > \$quantities['stock'])", $inventoryModel);

        $this->assertFalse(Schema::hasTable('alerts'));
        $this->assertFalse(Schema::hasColumn('inventories', 'stock_status'));
        $this->assertCount(57, Route::getRoutes());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postMovement(User $admin, Inventory $inventory, array $payload)
    {
        return $this->actingAs($admin)
            ->post(route('admin.inventory.movements.store', $inventory), $payload);
    }
}
