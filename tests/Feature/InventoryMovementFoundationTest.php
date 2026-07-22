<?php

namespace Tests\Feature;

use App\Exceptions\InventoryOperationException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class InventoryMovementFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_movements_schema_matches_the_contract_exactly(): void
    {
        $this->assertTrue(Schema::hasTable('inventory_movements'));
        $this->assertSame([
            'id',
            'inventory_id',
            'type',
            'stock_delta',
            'reserved_delta',
            'stock_before',
            'stock_after',
            'reserved_before',
            'reserved_after',
            'reason',
            'created_by',
            'reference_type',
            'reference_id',
            'idempotency_key',
            'created_at',
        ], Schema::getColumnListing('inventory_movements'));
        $this->assertFalse(Schema::hasColumn('inventory_movements', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('inventory_movements', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('inventory_movements', 'product_id'));
        $this->assertSame(
            Schema::getColumnType('inventories', 'id'),
            Schema::getColumnType('inventory_movements', 'inventory_id'),
        );

        $indexes = collect(Schema::getIndexes('inventory_movements'))->keyBy('name');

        $this->assertTrue($indexes->get('inventory_movements_idempotency_key_unique')['unique']);
        $this->assertSame(
            ['inventory_id', 'created_at'],
            $indexes->get('inventory_movements_inventory_id_created_at_index')['columns'],
        );
        $this->assertSame(['type'], $indexes->get('inventory_movements_type_index')['columns']);
        $this->assertSame(['created_by'], $indexes->get('inventory_movements_created_by_index')['columns']);
        $this->assertSame(
            ['reference_type', 'reference_id'],
            $indexes->get('inventory_movements_reference_type_reference_id_index')['columns'],
        );

        $migration = file_get_contents(database_path(
            'migrations/2026_07_22_000003_create_inventory_movements_table.php'
        ));

        $this->assertIsString($migration);
        $this->assertStringContainsString("->on('inventories')", $migration);
        $this->assertStringContainsString('->restrictOnDelete();', $migration);
        $this->assertStringContainsString("->on('users')", $migration);
        $this->assertStringContainsString('->nullOnDelete();', $migration);
    }

    public function test_model_configuration_types_relations_and_integer_casts_are_contractual(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $movement = $this->service()->recordEntry($inventory, 8);

        $this->assertSame('inventory_movements', $movement->getTable());
        $this->assertTrue($movement->usesTimestamps());
        $this->assertSame('created_at', InventoryMovement::CREATED_AT);
        $this->assertNull(InventoryMovement::UPDATED_AT);
        $this->assertSame([
            'inventory_id',
            'type',
            'stock_delta',
            'reserved_delta',
            'stock_before',
            'stock_after',
            'reserved_before',
            'reserved_after',
            'reason',
            'created_by',
            'reference_type',
            'reference_id',
            'idempotency_key',
        ], $movement->getFillable());
        $this->assertSame([
            'entry',
            'exit',
            'adjustment',
            'reserve',
            'release',
        ], InventoryMovement::validTypes());
        $this->assertTrue($movement->inventory->is($inventory));
        $this->assertTrue($inventory->movements()->firstOrFail()->is($movement));
        $this->assertNull($movement->creator);
        $this->assertIsInt($movement->stock_delta);
        $this->assertIsInt($movement->reserved_delta);
        $this->assertIsInt($movement->stock_before);
        $this->assertIsInt($movement->stock_after);
        $this->assertIsInt($movement->reserved_before);
        $this->assertIsInt($movement->reserved_after);
        $this->assertNotNull($movement->created_at);
    }

    public function test_product_creation_does_not_create_an_initial_movement(): void
    {
        $product = Product::factory()->create();

        $this->assertModelExists($product->inventory);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_foreign_keys_reject_unknown_inventory_and_restrict_inventory_deletion(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $movement = $this->service()->recordEntry($inventory, 2);

        try {
            $inventory->delete();
            $this->fail('An inventory with movements cannot be deleted.');
        } catch (QueryException) {
            $this->assertModelExists($inventory);
            $this->assertModelExists($movement);
        }

        $this->expectException(QueryException::class);

        DB::table('inventory_movements')->insert([
            ...$this->rawMovementAttributes(),
            'inventory_id' => 999999,
        ]);
    }

    public function test_actor_and_complete_reference_are_recorded_and_actor_deletion_sets_null(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $actor = User::factory()->create();
        $movement = $this->service()->recordEntry(
            $inventory,
            4,
            reason: 'Recepción verificada',
            actor: $actor,
            referenceType: 'external_document',
            referenceId: 123,
        );

        $this->assertTrue($movement->creator->is($actor));
        $this->assertSame('external_document', $movement->reference_type);
        $this->assertSame(123, $movement->reference_id);
        $this->assertSame('Recepción verificada', $movement->reason);

        $actor->delete();

        $movement->refresh();
        $this->assertNull($movement->created_by);
        $this->assertNull($movement->creator);
        $this->assertModelExists($movement);
    }

    public function test_entry_updates_stock_and_records_exact_snapshots(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $movement = $this->service()->recordEntry($inventory, 10);

        $this->assertSame(10, $inventory->fresh()->stock);
        $this->assertSame(0, $inventory->fresh()->reserved_stock);
        $this->assertSame(10, $inventory->fresh()->available_stock);
        $this->assertSame(InventoryMovement::TYPE_ENTRY, $movement->type);
        $this->assertSame(10, $movement->stock_delta);
        $this->assertSame(0, $movement->reserved_delta);
        $this->assertSame(0, $movement->stock_before);
        $this->assertSame(10, $movement->stock_after);
        $this->assertSame(0, $movement->reserved_before);
        $this->assertSame(0, $movement->reserved_after);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_exit_uses_only_available_stock_and_failure_is_atomic(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();
        $service->recordEntry($inventory, 10);
        $service->reserve($inventory, 4);
        $beforeCount = InventoryMovement::query()->count();

        try {
            $service->recordExit($inventory, 7);
            $this->fail('An exit cannot consume reserved stock.');
        } catch (InventoryOperationException $exception) {
            $this->assertSame(
                'No existe stock disponible suficiente para completar la operación.',
                $exception->getMessage(),
            );
        }

        $inventory->refresh();
        $this->assertSame(10, $inventory->stock);
        $this->assertSame(4, $inventory->reserved_stock);
        $this->assertSame($beforeCount, InventoryMovement::query()->count());

        $movement = $service->recordExit($inventory, 3);

        $this->assertSame(-3, $movement->stock_delta);
        $this->assertSame(0, $movement->reserved_delta);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(7, $movement->stock_after);
        $this->assertSame(4, $movement->reserved_before);
        $this->assertSame(4, $movement->reserved_after);
    }

    public function test_adjustment_sets_absolute_stock_and_calculates_delta(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();
        $service->recordEntry($inventory, 5);

        $movement = $service->adjustStock($inventory, 9, 'Conteo físico');

        $this->assertSame(4, $movement->stock_delta);
        $this->assertSame(5, $movement->stock_before);
        $this->assertSame(9, $movement->stock_after);
        $this->assertSame('Conteo físico', $movement->reason);
        $this->assertSame(9, $inventory->fresh()->stock);
    }

    public function test_adjustment_requires_reason_change_and_respects_reserved_stock(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();
        $service->recordEntry($inventory, 10);
        $service->reserve($inventory, 5);
        $beforeCount = InventoryMovement::query()->count();

        foreach (
            [
                [10, null],
                [10, 'Sin cambio'],
                [4, 'Debajo de la reserva'],
            ] as [$newStock, $reason]
        ) {
            try {
                $service->adjustStock($inventory, $newStock, $reason);
                $this->fail('The invalid adjustment should be rejected.');
            } catch (InventoryOperationException) {
                $inventory->refresh();
            }
        }

        $this->assertSame(10, $inventory->stock);
        $this->assertSame(5, $inventory->reserved_stock);
        $this->assertSame($beforeCount, InventoryMovement::query()->count());
    }

    public function test_reserve_and_release_change_only_reserved_stock(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();
        $service->recordEntry($inventory, 10);

        $reservation = $service->reserve($inventory, 6);

        $this->assertSame(0, $reservation->stock_delta);
        $this->assertSame(6, $reservation->reserved_delta);
        $this->assertSame(10, $reservation->stock_before);
        $this->assertSame(10, $reservation->stock_after);
        $this->assertSame(0, $reservation->reserved_before);
        $this->assertSame(6, $reservation->reserved_after);

        $release = $service->release($inventory, 2);

        $this->assertSame(0, $release->stock_delta);
        $this->assertSame(-2, $release->reserved_delta);
        $this->assertSame(6, $release->reserved_before);
        $this->assertSame(4, $release->reserved_after);
        $this->assertSame(10, $inventory->fresh()->stock);
        $this->assertSame(4, $inventory->fresh()->reserved_stock);
        $this->assertSame(6, $inventory->fresh()->available_stock);
    }

    public function test_excessive_reservation_and_release_are_rejected_without_changes(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();
        $service->recordEntry($inventory, 5);

        try {
            $service->reserve($inventory, 6);
            $this->fail('A reservation cannot exceed available stock.');
        } catch (InventoryOperationException) {
            $this->assertSame(0, $inventory->fresh()->reserved_stock);
        }

        $service->reserve($inventory, 3);
        $beforeCount = InventoryMovement::query()->count();

        try {
            $service->release($inventory, 4);
            $this->fail('A release cannot exceed reserved stock.');
        } catch (InventoryOperationException $exception) {
            $this->assertSame('La cantidad a liberar supera el stock reservado.', $exception->getMessage());
        }

        $this->assertSame(5, $inventory->fresh()->stock);
        $this->assertSame(3, $inventory->fresh()->reserved_stock);
        $this->assertSame($beforeCount, InventoryMovement::query()->count());
    }

    public function test_non_positive_decimal_and_string_quantities_are_rejected(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();

        foreach ([0, -1, 1.5, '1'] as $quantity) {
            foreach (['recordEntry', 'recordExit', 'reserve', 'release'] as $method) {
                try {
                    $service->{$method}($inventory, $quantity);
                    $this->fail("{$method} should reject the invalid quantity.");
                } catch (InventoryOperationException $exception) {
                    $this->assertStringContainsString('cantidad', $exception->getMessage());
                }
            }
        }

        foreach ([-1, 1.5, '1'] as $newStock) {
            try {
                $service->adjustStock($inventory, $newStock, 'Conteo');
                $this->fail('Adjustment should reject an invalid absolute stock.');
            } catch (InventoryOperationException) {
                $this->assertSame(0, $inventory->fresh()->stock);
            }
        }

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_idempotency_key_is_normalized_and_retries_return_the_same_movement(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();

        $first = $service->recordEntry($inventory, 7, idempotencyKey: ' retry-entry ');
        $second = $service->recordEntry($inventory, 7, idempotencyKey: 'retry-entry');

        $this->assertTrue($first->is($second));
        $this->assertSame('retry-entry', $first->idempotency_key);
        $this->assertSame(7, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_idempotency_key_cannot_be_reused_for_another_inventory_type_or_payload(): void
    {
        $firstInventory = Product::factory()->create()->inventory;
        $secondInventory = Product::factory()->create()->inventory;
        $service = $this->service();
        $service->recordEntry($firstInventory, 5, idempotencyKey: 'shared-key');

        foreach (
            [
                fn () => $service->recordEntry($secondInventory, 5, idempotencyKey: 'shared-key'),
                fn () => $service->reserve($firstInventory, 5, idempotencyKey: 'shared-key'),
                fn () => $service->recordEntry($firstInventory, 6, idempotencyKey: 'shared-key'),
            ] as $operation
        ) {
            try {
                $operation();
                $this->fail('The idempotency key reuse should be rejected.');
            } catch (InventoryOperationException $exception) {
                $this->assertStringContainsString('otra operación', $exception->getMessage());
            }
        }

        $this->assertSame(5, $firstInventory->fresh()->stock);
        $this->assertSame(0, $secondInventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_empty_and_oversized_idempotency_keys_are_rejected(): void
    {
        $inventory = Product::factory()->create()->inventory;

        foreach (['  ', str_repeat('a', 101)] as $key) {
            try {
                $this->service()->recordEntry($inventory, 1, idempotencyKey: $key);
                $this->fail('The invalid idempotency key should be rejected.');
            } catch (InventoryOperationException $exception) {
                $this->assertSame('La clave de idempotencia no es válida.', $exception->getMessage());
            }
        }

        $this->assertSame(0, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_incomplete_references_are_rejected(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $service = $this->service();

        foreach (
            [
                fn () => $service->recordEntry($inventory, 1, referenceType: 'document'),
                fn () => $service->recordEntry($inventory, 1, referenceId: 20),
            ] as $operation
        ) {
            try {
                $operation();
                $this->fail('An incomplete reference should be rejected.');
            } catch (InventoryOperationException $exception) {
                $this->assertStringContainsString('tipo e identificador', $exception->getMessage());
            }
        }

        $this->assertSame(0, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_movements_cannot_be_updated_deleted_or_reassigned(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $otherInventory = Product::factory()->create()->inventory;
        $movement = $this->service()->recordEntry($inventory, 3, reason: 'Original');

        foreach (
            [
                fn () => $movement->update(['reason' => 'Alterado']),
                fn () => $movement->update(['inventory_id' => $otherInventory->getKey()]),
                fn () => $movement->delete(),
            ] as $operation
        ) {
            try {
                $operation();
                $this->fail('Inventory movements must be immutable.');
            } catch (InventoryOperationException $exception) {
                $this->assertSame('Los movimientos de inventario son inmutables.', $exception->getMessage());
                $movement->refresh();
            }
        }

        $this->assertTrue($movement->inventory->is($inventory));
        $this->assertSame('Original', $movement->reason);
        $this->assertModelExists($movement);
    }

    public function test_movement_creation_failure_rolls_back_balance_and_history(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $eventName = 'eloquent.creating: '.InventoryMovement::class;
        Event::listen($eventName, fn (): never => throw new RuntimeException('Forced movement failure.'));

        try {
            $this->service()->recordEntry($inventory, 5);
            $this->fail('The forced movement failure should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced movement failure.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $inventory->refresh();
        $this->assertSame(0, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_soft_deleted_product_keeps_history_but_rejects_new_operations(): void
    {
        $product = Product::factory()->create();
        $inventory = $product->inventory;
        $movement = $this->service()->recordEntry($inventory, 5);

        $product->delete();

        $this->assertTrue(Product::withTrashed()->findOrFail($product->getKey())->inventory->is($inventory));
        $this->assertTrue($inventory->fresh()->movements()->firstOrFail()->is($movement));

        try {
            $this->service()->recordEntry($inventory, 1);
            $this->fail('A deleted product cannot receive inventory operations.');
        } catch (InventoryOperationException $exception) {
            $this->assertSame(
                'No se puede modificar el inventario de un producto eliminado.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(5, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_service_reloads_and_locks_inventory_instead_of_using_stale_values(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $staleInventory = Inventory::query()->findOrFail($inventory->getKey());
        $service = $this->service();

        $service->recordEntry($inventory, 5);
        $movement = $service->recordEntry($staleInventory, 2);

        $this->assertSame(5, $movement->stock_before);
        $this->assertSame(7, $movement->stock_after);
        $this->assertSame(7, $inventory->fresh()->stock);

        $source = file_get_contents(app_path('Services/InventoryService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('DB::transaction(', $source);
        $this->assertStringContainsString('->lockForUpdate()', $source);
        $this->assertStringContainsString('->whereKey($inventoryId)', $source);
    }

    public function test_inventory_movements_add_no_routes_or_future_tables(): void
    {
        $this->assertCount(38, Route::getRoutes());
        $this->assertFalse(Route::has('admin.inventory-movements.index'));
        $this->assertFalse(Route::has('admin.inventory-movements.store'));
        $this->assertFalse(Schema::hasTable('stock_movements'));
        $this->assertFalse(Schema::hasTable('purchases'));
        $this->assertFalse(Schema::hasTable('sales'));
    }

    private function service(): InventoryService
    {
        return app(InventoryService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function rawMovementAttributes(): array
    {
        return [
            'type' => InventoryMovement::TYPE_ENTRY,
            'stock_delta' => 1,
            'reserved_delta' => 0,
            'stock_before' => 0,
            'stock_after' => 1,
            'reserved_before' => 0,
            'reserved_after' => 0,
            'reason' => null,
            'created_by' => null,
            'reference_type' => null,
            'reference_id' => null,
            'idempotency_key' => null,
            'created_at' => now(),
        ];
    }
}
