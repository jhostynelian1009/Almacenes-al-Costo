<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMinimumStockRequest;
use App\Models\Inventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InventoryMinimumStockController extends Controller
{
    public function update(UpdateMinimumStockRequest $request, Inventory $inventory): RedirectResponse
    {
        $minimumStock = (int) $request->validated('min_stock');

        DB::transaction(function () use ($inventory, $minimumStock): void {
            $lockedInventory = Inventory::query()
                ->whereKey($inventory->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedInventory->product()->firstOrFail()->trashed(), 404);

            $lockedInventory->update([
                'min_stock' => $minimumStock,
            ]);
        });

        return to_route('admin.inventory.show', $inventory)
            ->with('success', 'Stock mínimo actualizado correctamente.');
    }
}
