<?php

namespace App\Services;

use App\Http\Controllers\PublicProductController;
use App\Models\Product;

class ProductVisibilityService
{
    public function isPubliclyVisible(Product $product): bool
    {
        if (! $product->is_active || $product->trashed()) {
            return false;
        }

        if (! $product->relationLoaded('inventory') && ! $product->inventory()->exists()) {
            return false;
        }

        if ($product->relationLoaded('inventory') && $product->inventory === null) {
            return false;
        }

        return in_array(
            $product->category_id,
            PublicProductController::resolveVisibleCategoryIds(),
            true,
        );
    }
}
