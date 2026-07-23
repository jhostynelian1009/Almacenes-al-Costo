<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ProductSlugService;

class ProductObserver
{
    public function __construct(private readonly ProductSlugService $slugService) {}

    public function creating(Product $product): void
    {
        $product->slug = $this->slugService->generate((string) $product->name);
    }

    public function created(Product $product): void
    {
        $product->inventory()->firstOrCreate([], [
            'stock' => 0,
            'reserved_stock' => 0,
            'min_stock' => 0,
        ]);
    }
}
