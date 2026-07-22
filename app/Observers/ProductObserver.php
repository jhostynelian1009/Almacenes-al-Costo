<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        $product->inventory()->firstOrCreate([], [
            'stock' => 0,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ]);
    }
}
