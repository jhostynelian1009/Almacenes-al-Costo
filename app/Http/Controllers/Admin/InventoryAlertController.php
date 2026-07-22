<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\View\View;

class InventoryAlertController extends Controller
{
    public function index(): View
    {
        $inventories = Inventory::query()
            ->select('inventories.*')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereNull('products.deleted_at')
            ->alerting()
            ->with([
                'product:id,category_id,name,sku,is_active,deleted_at',
                'product.category:id,name',
            ])
            ->orderByRaw(
                'CASE WHEN (inventories.stock - inventories.reserved_stock) = 0 THEN 0 ELSE 1 END'
            )
            ->orderByRaw('(inventories.stock - inventories.reserved_stock) ASC')
            ->orderBy('products.name')
            ->orderBy('inventories.id')
            ->paginate(15);

        return view('admin.inventory.alerts', [
            'inventories' => $inventories,
        ]);
    }
}
