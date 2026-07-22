<?php

namespace App\Services;

use App\Exceptions\CategoryDeletionException;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CategoryDeletionService
{
    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $lockedCategory = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCategory->children()->exists()) {
                throw CategoryDeletionException::hasSubcategories();
            }

            if ($lockedCategory->products()->withTrashed()->exists()) {
                throw CategoryDeletionException::hasProducts();
            }

            try {
                $lockedCategory->delete();
            } catch (QueryException $exception) {
                if (! $this->isIntegrityConstraintViolation($exception)) {
                    throw $exception;
                }

                throw CategoryDeletionException::hasRelatedRecords($exception);
            }
        });
    }

    private function isIntegrityConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($sqlState, ['23000', '23503'], true)
            || in_array($driverCode, [19, 787, 1451], true);
    }
}
