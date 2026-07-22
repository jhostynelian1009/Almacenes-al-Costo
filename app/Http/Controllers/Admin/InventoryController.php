<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $inventories = Inventory::query()
            ->select('inventories.*')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereNull('products.deleted_at')
            ->with([
                'product:id,category_id,name,sku,is_active,deleted_at',
                'product.category:id,name',
            ])
            ->orderBy('products.name')
            ->orderBy('inventories.id')
            ->paginate(15);

        return view('admin.inventory.index', [
            'inventories' => $inventories,
        ]);
    }

    public function show(Inventory $inventory): View
    {
        $inventory->load([
            'product:id,category_id,name,sku,is_active,deleted_at',
            'product.category:id,name',
        ]);
        $movements = $inventory->movements()
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.inventory.show', [
            'inventory' => $inventory,
            'movements' => $movements,
        ]);
    }
}
