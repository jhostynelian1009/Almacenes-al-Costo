<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_routes_have_the_expected_names(): void
    {
        $expected = [
            'admin.inventory.alerts' => ['GET', 'HEAD'],
            'admin.inventory.index' => ['GET', 'HEAD'],
            'admin.inventory.minimum-stock.update' => ['PATCH'],
            'admin.inventory.show' => ['GET', 'HEAD'],
            'admin.inventory.movements.create' => ['GET', 'HEAD'],
            'admin.inventory.movements.store' => ['POST'],
        ];
        $actualNames = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.inventory.'))
            ->sort()
            ->values()
            ->all();
        $expectedNames = array_keys($expected);
        sort($expectedNames);

        $this->assertSame($expectedNames, $actualNames);
        $this->assertCount(48, Route::getRoutes());

        foreach ($expected as $name => $methods) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertSame($methods, $route->methods());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('active', $route->gatherMiddleware());
            $this->assertContains('admin', $route->gatherMiddleware());
            $this->assertStringStartsWith('admin/inventory', $route->uri());
        }

        $this->assertFalse(Route::has('admin.inventory.update'));
        $this->assertFalse(Route::has('admin.inventory.destroy'));
        $this->assertFalse(Route::has('admin.inventory.movements.edit'));
        $this->assertFalse(Route::has('admin.inventory.movements.update'));
        $this->assertFalse(Route::has('admin.inventory.movements.destroy'));

        $publicInventoryRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_contains($route->uri(), 'inventory'))
            ->reject(fn ($route): bool => str_starts_with($route->uri(), 'admin/'));

        $this->assertCount(0, $publicInventoryRoutes);
    }

    public function test_only_active_administrators_can_access_inventory_routes(): void
    {
        $inventory = Product::factory()->create()->inventory;
        $routes = [
            route('admin.inventory.index'),
            route('admin.inventory.show', $inventory),
            route('admin.inventory.movements.create', $inventory),
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect(route('login'));
        }
        $this->post(route('admin.inventory.movements.store', $inventory))->assertRedirect(route('login'));

        $employee = User::factory()->create();
        foreach ($routes as $route) {
            $this->actingAs($employee)->get($route)->assertForbidden();
        }
        $this->post(route('admin.inventory.movements.store', $inventory))->assertForbidden();

        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        foreach ($routes as $route) {
            $this->actingAs($inactiveAdmin)->get($route)->assertRedirect(route('login'));
        }

        $this->assertSame(0, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_admin_index_uses_layout_navigation_and_accessible_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.inventory.index'));
        $content = $response->getContent();

        $response->assertOk()
            ->assertViewIs('admin.inventory.index')
            ->assertSee('No hay inventarios disponibles')
            ->assertSee('se crean automáticamente')
            ->assertSee('role="status"', false)
            ->assertSee('href="'.route('admin.inventory.index').'"', false)
            ->assertDontSee('Crear inventario');
        $this->assertSame(1, substr_count($content, '<h1'));
    }

    public function test_index_displays_balances_product_category_and_status_but_excludes_deleted_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Herramientas']);
        $product = Product::factory()->for($category)->inactive()->create([
            'name' => 'Taladro visible',
            'sku' => 'TAL-001',
        ]);
        $product->inventory->update([
            'stock' => 12,
            'reserved_stock' => 4,
            'min_stock' => 3,
        ]);
        $deleted = Product::factory()->for($category)->create([
            'name' => 'Producto eliminado',
            'sku' => 'DEL-001',
        ]);
        $deleted->delete();

        $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSeeInOrder(['Producto', 'SKU', 'Categoría', 'Stock total', 'Reservado', 'Disponible', 'Mínimo'])
            ->assertSee('Taladro visible')
            ->assertSee('TAL-001')
            ->assertSee('Herramientas')
            ->assertSee('Inactivo')
            ->assertSee(route('admin.inventory.show', $product->inventory))
            ->assertDontSee('Producto eliminado')
            ->assertDontSee('DEL-001')
            ->assertDontSee('deleted_at')
            ->assertDontSee('idempotency_key');
    }

    public function test_index_paginates_fifteen_and_orders_by_product_name_then_inventory_id(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        foreach (range(16, 1) as $number) {
            Product::factory()->for($category)->create([
                'name' => sprintf('Producto %02d', $number),
            ]);
        }
        $duplicateProducts = Product::factory()->count(2)->for($category)->create(['name' => 'Producto 17']);

        $firstPage = $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->viewData('inventories');
        $secondPage = $this->get(route('admin.inventory.index', ['page' => 2]))
            ->assertOk()
            ->viewData('inventories');

        $this->assertInstanceOf(LengthAwarePaginator::class, $firstPage);
        $this->assertSame(15, $firstPage->perPage());
        $this->assertSame(18, $firstPage->total());
        $this->assertSame(
            collect(range(1, 15))->map(fn (int $number): string => sprintf('Producto %02d', $number))->all(),
            $firstPage->getCollection()->map(fn (Inventory $inventory): string => $inventory->product->name)->all(),
        );
        $this->assertSame(
            [
                'Producto 16',
                'Producto 17',
                'Producto 17',
            ],
            $secondPage->getCollection()->map(fn (Inventory $inventory): string => $inventory->product->name)->all(),
        );
        $this->assertSame(
            $duplicateProducts->pluck('inventory.id')->sort()->values()->all(),
            $secondPage->getCollection()->filter(
                fn (Inventory $inventory): bool => $inventory->product->name === 'Producto 17'
            )->pluck('id')->all(),
        );
    }

    public function test_index_eager_loads_products_and_categories_with_bounded_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $categories = Category::factory()->count(3)->create();

        foreach ($categories as $category) {
            Product::factory()->count(3)->for($category)->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $inventories = $this->actingAs($admin)
            ->get(route('admin.inventory.index'))
            ->assertOk()
            ->viewData('inventories');
        $queries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $productQueries = $queries->filter(fn (string $query): bool => str_contains($query, 'products'));
        $categoryQueries = $queries->filter(fn (string $query): bool => str_contains($query, 'categories'));

        $this->assertLessThanOrEqual(3, $productQueries->count());
        $this->assertLessThanOrEqual(1, $categoryQueries->count());
        $this->assertTrue($inventories->getCollection()->every(
            fn (Inventory $inventory): bool => $inventory->relationLoaded('product')
                && $inventory->product->relationLoaded('category')
        ));
    }

    public function test_detail_displays_balances_history_actor_and_system(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administradora de inventario']);
        $product = Product::factory()->create([
            'name' => 'Producto con historial',
            'sku' => 'HIST-001',
        ]);
        $inventory = $product->inventory;
        $service = app(InventoryService::class);
        $service->recordEntry($inventory, 10, 'Carga del sistema');
        $service->reserve($inventory, 3, 'Reserva administrativa', $admin);

        $response = $this->actingAs($admin)->get(route('admin.inventory.show', $inventory));
        $content = $response->getContent();

        $response->assertOk()
            ->assertViewIs('admin.inventory.show')
            ->assertSee('Producto con historial')
            ->assertSee('HIST-001')
            ->assertSee($product->category->name)
            ->assertSee('Stock total')
            ->assertSee('Stock reservado')
            ->assertSee('Stock disponible')
            ->assertSee('Carga del sistema')
            ->assertSee('Reserva administrativa')
            ->assertSee('Sistema')
            ->assertSee('Administradora de inventario')
            ->assertSee('+10')
            ->assertSee('+3')
            ->assertDontSee('idempotency_key');
        $this->assertSame(1, substr_count($content, '<h1'));
    }

    public function test_history_is_paginated_ordered_and_eager_loads_creator(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $service = app(InventoryService::class);
        $baseTime = now()->startOfSecond();

        foreach (range(1, 21) as $number) {
            Carbon::setTestNow($baseTime->copy()->addMinutes($number));
            $service->recordEntry($inventory, 1, "Movimiento {$number}", $admin);
        }
        Carbon::setTestNow();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $movements = $this->actingAs($admin)
            ->get(route('admin.inventory.show', $inventory))
            ->assertOk()
            ->viewData('movements');
        $queries = collect(DB::getQueryLog())->pluck('query')->map('strtolower');
        $userQueries = $queries->filter(fn (string $query): bool => str_contains($query, 'from "users"'));

        $this->assertSame(20, $movements->perPage());
        $this->assertSame(21, $movements->total());
        $this->assertSame('Movimiento 21', $movements->first()->reason);
        $this->assertSame('Movimiento 2', $movements->last()->reason);
        $this->assertLessThanOrEqual(1, $userQueries->count());
        $this->assertTrue($movements->getCollection()->every(
            fn (InventoryMovement $movement): bool => $movement->relationLoaded('creator')
        ));
    }

    public function test_soft_deleted_product_detail_and_history_remain_visible_without_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['name' => 'Producto histórico']);
        $inventory = $product->inventory;
        app(InventoryService::class)->recordEntry($inventory, 4, 'Movimiento previo');
        $product->delete();

        $this->actingAs($admin)
            ->get(route('admin.inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Producto eliminado')
            ->assertSee('Movimiento previo')
            ->assertDontSee('Registrar movimiento')
            ->assertDontSee(route('admin.inventory.movements.create', $inventory));
    }

    public function test_create_form_defaults_to_entry_accepts_all_types_and_generates_uuid_without_persisting(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $types = InventoryMovement::validTypes();

        foreach ($types as $type) {
            $parameters = $type === InventoryMovement::TYPE_ENTRY
                ? [$inventory]
                : [$inventory, 'type' => $type];
            $response = $this->actingAs($admin)
                ->get(route('admin.inventory.movements.create', $parameters))
                ->assertOk()
                ->assertViewIs('admin.inventory.movements.create')
                ->assertViewHas('selectedType', $type)
                ->assertViewHas('idempotencyKey', fn (string $key): bool => Str::isUuid($key));

            $response->assertSee('value="'.$type.'" selected', false);
        }

        $this->assertSame(0, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_invalid_form_type_is_not_found(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;

        $this->actingAs($admin)
            ->get(route('admin.inventory.movements.create', [$inventory, 'type' => 'transfer']))
            ->assertNotFound();
    }

    public function test_form_is_accessible_and_contains_only_allowed_inputs(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;

        $response = $this->actingAs($admin)
            ->get(route('admin.inventory.movements.create', $inventory))
            ->assertOk();
        $content = $response->getContent();

        $response->assertSee('<form method="POST"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="type"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee('name="new_stock"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('for="type"', false)
            ->assertSee('for="quantity"', false)
            ->assertSee('for="new_stock"', false)
            ->assertSee('for="reason"', false)
            ->assertSee('aria-describedby=', false)
            ->assertDontSee('name="inventory_id"', false)
            ->assertDontSee('name="created_by"', false)
            ->assertDontSee('name="stock_before"', false)
            ->assertDontSee('name="stock_after"', false)
            ->assertDontSee('name="reserved_before"', false)
            ->assertDontSee('name="reserved_after"', false)
            ->assertDontSee('name="stock_delta"', false)
            ->assertDontSee('name="reference_type"', false)
            ->assertDontSee('name="reference_id"', false);
        $this->assertSame(1, substr_count($content, '<h1'));
    }

    public function test_request_validates_type_reason_conditional_quantities_and_idempotency_key(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $route = route('admin.inventory.movements.store', $inventory);

        foreach (['entry', 'exit', 'reserve', 'release'] as $type) {
            $this->actingAs($admin)
                ->post($route, [
                    'type' => $type,
                    'reason' => 'Motivo',
                    'idempotency_key' => (string) Str::uuid(),
                ])
                ->assertSessionHasErrors('quantity');
        }

        $this->post($route, [
            'type' => 'adjustment',
            'reason' => 'Motivo',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('new_stock');

        foreach ([
            $this->payload(['type' => 'transfer']),
            $this->payload(['reason' => '   ']),
            $this->payload(['quantity' => 0]),
            $this->payload(['quantity' => '1.5']),
            $this->payload(['idempotency_key' => '   ']),
            $this->payload(['idempotency_key' => str_repeat('a', 101)]),
        ] as $payload) {
            $this->post($route, $payload)->assertSessionHasErrors();
        }

        $this->post($route, $this->payload([
            'type' => 'adjustment',
            'quantity' => null,
            'new_stock' => -1,
        ]))->assertSessionHasErrors('new_stock');
        $this->assertSame(0, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_entry_records_actor_reason_snapshots_and_ignores_manipulated_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $otherInventory = Product::factory()->create()->inventory;
        $key = (string) Str::uuid();

        $response = $this->actingAs($admin)->post(
            route('admin.inventory.movements.store', $inventory),
            $this->payload([
                'quantity' => '5',
                'reason' => '  Recepción manual  ',
                'idempotency_key' => " {$key} ",
                'inventory_id' => $otherInventory->getKey(),
                'created_by' => User::factory()->create()->getKey(),
                'stock_before' => 900,
                'stock_after' => 999,
                'reference_type' => 'purchase',
                'reference_id' => 10,
            ]),
        );

        $movement = InventoryMovement::query()->sole();

        $response->assertRedirect(route('admin.inventory.show', $inventory))
            ->assertSessionHas('success', 'Movimiento de inventario registrado correctamente.');
        $this->assertSame(5, $inventory->fresh()->stock);
        $this->assertSame(0, $otherInventory->fresh()->stock);
        $this->assertSame($inventory->getKey(), $movement->inventory_id);
        $this->assertSame($admin->getKey(), $movement->created_by);
        $this->assertSame('Recepción manual', $movement->reason);
        $this->assertSame($key, $movement->idempotency_key);
        $this->assertSame(0, $movement->stock_before);
        $this->assertSame(5, $movement->stock_after);
        $this->assertSame(5, $movement->stock_delta);
        $this->assertNull($movement->reference_type);
        $this->assertNull($movement->reference_id);
    }

    public function test_valid_exit_adjustment_reserve_and_release_use_domain_service(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $service = app(InventoryService::class);
        $service->recordEntry($inventory, 10, 'Saldo inicial');

        $this->actingAs($admin)->post(
            route('admin.inventory.movements.store', $inventory),
            $this->payload(['type' => 'exit', 'quantity' => 2, 'reason' => 'Salida manual']),
        )->assertRedirect(route('admin.inventory.show', $inventory));
        $this->assertSame(8, $inventory->fresh()->stock);

        $this->post(
            route('admin.inventory.movements.store', $inventory),
            $this->payload([
                'type' => 'adjustment',
                'quantity' => null,
                'new_stock' => 12,
                'reason' => 'Conteo físico',
            ]),
        )->assertRedirect(route('admin.inventory.show', $inventory));
        $this->assertSame(12, $inventory->fresh()->stock);

        $this->post(
            route('admin.inventory.movements.store', $inventory),
            $this->payload(['type' => 'reserve', 'quantity' => 4, 'reason' => 'Reserva manual']),
        )->assertRedirect(route('admin.inventory.show', $inventory));
        $this->assertSame(4, $inventory->fresh()->reserved_stock);

        $this->post(
            route('admin.inventory.movements.store', $inventory),
            $this->payload(['type' => 'release', 'quantity' => 3, 'reason' => 'Liberación manual']),
        )->assertRedirect(route('admin.inventory.show', $inventory));

        $this->assertSame(12, $inventory->fresh()->stock);
        $this->assertSame(1, $inventory->fresh()->reserved_stock);
        $this->assertSame(11, $inventory->fresh()->available_stock);
        $this->assertDatabaseCount('inventory_movements', 5);
        $this->assertSame(4, InventoryMovement::query()->where('created_by', $admin->getKey())->count());
    }

    public function test_invalid_domain_operations_return_safe_errors_and_preserve_input_and_balances(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $service = app(InventoryService::class);
        $service->recordEntry($inventory, 5, 'Saldo inicial');
        $service->reserve($inventory, 3, 'Reserva inicial');

        $cases = [
            ['exit', 3, null, 'No existe stock disponible suficiente'],
            ['adjustment', null, 2, 'El nuevo saldo de inventario no es válido'],
            ['reserve', 3, null, 'No existe stock disponible suficiente'],
            ['release', 4, null, 'La cantidad a liberar supera el stock reservado'],
        ];

        foreach ($cases as [$type, $quantity, $newStock, $message]) {
            $formRoute = route('admin.inventory.movements.create', [$inventory, 'type' => $type]);
            $beforeCount = InventoryMovement::query()->count();

            $this->actingAs($admin)
                ->from($formRoute)
                ->post(route('admin.inventory.movements.store', $inventory), $this->payload([
                    'type' => $type,
                    'quantity' => $quantity,
                    'new_stock' => $newStock,
                    'reason' => 'Operación inválida conservada',
                ]))
                ->assertRedirect($formRoute)
                ->assertSessionHas('error', fn (string $error): bool => str_contains($error, $message)
                    && ! str_contains($error, 'SQLSTATE')
                    && ! str_contains($error, 'constraint'))
                ->assertSessionHasInput('reason', 'Operación inválida conservada');

            $this->assertSame(5, $inventory->fresh()->stock);
            $this->assertSame(3, $inventory->fresh()->reserved_stock);
            $this->assertSame($beforeCount, InventoryMovement::query()->count());
        }
    }

    public function test_repeated_post_with_same_key_is_idempotent_and_redirects_both_times(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $payload = $this->payload([
            'quantity' => 6,
            'reason' => 'Entrada idempotente',
            'idempotency_key' => 'manual-entry-key',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.inventory.movements.store', $inventory), $payload)
            ->assertRedirect(route('admin.inventory.show', $inventory));
        $this->post(route('admin.inventory.movements.store', $inventory), $payload)
            ->assertRedirect(route('admin.inventory.show', $inventory));

        $this->assertSame(6, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_incompatible_idempotency_key_is_rejected_by_service(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $key = 'manual-shared-key';

        $this->actingAs($admin)->post(
            route('admin.inventory.movements.store', $inventory),
            $this->payload(['quantity' => 5, 'idempotency_key' => $key]),
        )->assertRedirect(route('admin.inventory.show', $inventory));

        $this->from(route('admin.inventory.movements.create', $inventory))
            ->post(
                route('admin.inventory.movements.store', $inventory),
                $this->payload([
                    'type' => 'reserve',
                    'quantity' => 2,
                    'idempotency_key' => $key,
                ]),
            )
            ->assertSessionHas('error', 'La clave de idempotencia ya fue utilizada para otra operación.');

        $this->assertSame(5, $inventory->fresh()->stock);
        $this->assertSame(0, $inventory->fresh()->reserved_stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_soft_deleted_product_rejects_create_and_store_but_keeps_history(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $inventory = $product->inventory;
        app(InventoryService::class)->recordEntry($inventory, 3, 'Historial conservado');
        $product->delete();

        $this->actingAs($admin)
            ->get(route('admin.inventory.movements.create', $inventory))
            ->assertNotFound();

        $this->from(route('admin.inventory.show', $inventory))
            ->post(
                route('admin.inventory.movements.store', $inventory),
                $this->payload(['quantity' => 1]),
            )
            ->assertRedirect(route('admin.inventory.show', $inventory))
            ->assertSessionHas('error', 'No se puede modificar el inventario de un producto eliminado.');

        $this->assertSame(3, $inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->get(route('admin.inventory.show', $inventory))
            ->assertOk()
            ->assertSee('Historial conservado');
    }

    public function test_movements_cannot_be_edited_or_deleted_through_http(): void
    {
        $admin = User::factory()->admin()->create();
        $inventory = Product::factory()->create()->inventory;
        $movement = app(InventoryService::class)->recordEntry($inventory, 2, 'Inmutable');
        $uri = 'admin/inventory/'.$inventory->getKey().'/movements/'.$movement->getKey();

        $this->actingAs($admin)->put($uri, ['reason' => 'Alterado'])->assertNotFound();
        $this->delete($uri)->assertNotFound();

        $this->assertSame('Inmutable', $movement->fresh()->reason);
        $this->assertModelExists($movement);
    }

    public function test_controllers_delegate_mutations_exclusively_to_inventory_service(): void
    {
        $inventoryController = file_get_contents(app_path('Http/Controllers/Admin/InventoryController.php'));
        $movementController = file_get_contents(app_path('Http/Controllers/Admin/InventoryMovementController.php'));

        $this->assertIsString($inventoryController);
        $this->assertIsString($movementController);
        $this->assertStringNotContainsString('->update(', $inventoryController);
        $this->assertStringNotContainsString('->save(', $inventoryController);
        $this->assertStringNotContainsString('->increment(', $movementController);
        $this->assertStringNotContainsString('->decrement(', $movementController);
        $this->assertStringNotContainsString('$inventory->update(', $movementController);
        $this->assertStringContainsString('InventoryService $inventoryService', $movementController);
        $this->assertStringContainsString('->recordEntry(', $movementController);
        $this->assertStringContainsString('->recordExit(', $movementController);
        $this->assertStringContainsString('->adjustStock(', $movementController);
        $this->assertStringContainsString('->reserve(', $movementController);
        $this->assertStringContainsString('->release(', $movementController);
        $this->assertStringContainsString('catch (InventoryOperationException $exception)', $movementController);
        $this->assertStringNotContainsString('catch (Throwable', $movementController);
        $this->assertStringNotContainsString('catch (QueryException', $movementController);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => InventoryMovement::TYPE_ENTRY,
            'quantity' => 1,
            'new_stock' => null,
            'reason' => 'Operación manual',
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
    }
}
