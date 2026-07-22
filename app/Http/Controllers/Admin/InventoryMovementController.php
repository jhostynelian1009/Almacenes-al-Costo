<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InventoryOperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryMovementRequest;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    private const TYPE_OPTIONS = [
        InventoryMovement::TYPE_ENTRY => [
            'label' => 'Entrada',
            'description' => 'Aumenta el stock total sin modificar las reservas.',
        ],
        InventoryMovement::TYPE_EXIT => [
            'label' => 'Salida',
            'description' => 'Reduce únicamente el stock disponible.',
        ],
        InventoryMovement::TYPE_ADJUSTMENT => [
            'label' => 'Ajuste',
            'description' => 'Establece un nuevo saldo total después de una verificación.',
        ],
        InventoryMovement::TYPE_RESERVE => [
            'label' => 'Reserva',
            'description' => 'Traslada stock disponible a stock reservado.',
        ],
        InventoryMovement::TYPE_RELEASE => [
            'label' => 'Liberación',
            'description' => 'Devuelve stock reservado al saldo disponible.',
        ],
    ];

    public function create(Request $request, Inventory $inventory): View
    {
        $selectedType = $request->query('type', InventoryMovement::TYPE_ENTRY);

        abort_unless(
            is_string($selectedType) && array_key_exists($selectedType, self::TYPE_OPTIONS),
            404,
        );

        $inventory->load([
            'product:id,category_id,name,sku,is_active,deleted_at',
            'product.category:id,name',
        ]);

        abort_if($inventory->product->trashed(), 404);

        return view('admin.inventory.movements.create', [
            'inventory' => $inventory,
            'selectedType' => $selectedType,
            'typeOptions' => self::TYPE_OPTIONS,
            'idempotencyKey' => Str::uuid()->toString(),
        ]);
    }

    public function store(
        StoreInventoryMovementRequest $request,
        Inventory $inventory,
        InventoryService $inventoryService,
    ): RedirectResponse {
        $validated = $request->validated();
        /** @var User $actor */
        $actor = $request->user();

        try {
            match ($validated['type']) {
                InventoryMovement::TYPE_ENTRY => $inventoryService->recordEntry(
                    inventory: $inventory,
                    quantity: $validated['quantity'],
                    reason: $validated['reason'],
                    actor: $actor,
                    idempotencyKey: $validated['idempotency_key'],
                    referenceType: null,
                    referenceId: null,
                ),
                InventoryMovement::TYPE_EXIT => $inventoryService->recordExit(
                    inventory: $inventory,
                    quantity: $validated['quantity'],
                    reason: $validated['reason'],
                    actor: $actor,
                    idempotencyKey: $validated['idempotency_key'],
                    referenceType: null,
                    referenceId: null,
                ),
                InventoryMovement::TYPE_ADJUSTMENT => $inventoryService->adjustStock(
                    inventory: $inventory,
                    newStock: $validated['new_stock'],
                    reason: $validated['reason'],
                    actor: $actor,
                    idempotencyKey: $validated['idempotency_key'],
                    referenceType: null,
                    referenceId: null,
                ),
                InventoryMovement::TYPE_RESERVE => $inventoryService->reserve(
                    inventory: $inventory,
                    quantity: $validated['quantity'],
                    reason: $validated['reason'],
                    actor: $actor,
                    idempotencyKey: $validated['idempotency_key'],
                    referenceType: null,
                    referenceId: null,
                ),
                InventoryMovement::TYPE_RELEASE => $inventoryService->release(
                    inventory: $inventory,
                    quantity: $validated['quantity'],
                    reason: $validated['reason'],
                    actor: $actor,
                    idempotencyKey: $validated['idempotency_key'],
                    referenceType: null,
                    referenceId: null,
                ),
            };
        } catch (InventoryOperationException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return to_route('admin.inventory.show', $inventory)
            ->with('success', 'Movimiento de inventario registrado correctamente.');
    }
}
