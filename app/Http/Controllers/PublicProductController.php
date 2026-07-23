<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProductController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'parent_id']);
        $categoriesByParent = $categories->groupBy(
            fn (Category $category): string => (string) $category->parent_id,
        );
        $visibleCategoryIds = [];
        $visitedCategoryIds = [];
        $pendingCategoryIds = $categories
            ->whereNull('parent_id')
            ->pluck('id')
            ->all();

        while ($pendingCategoryIds !== []) {
            $categoryId = (int) array_shift($pendingCategoryIds);

            if (isset($visitedCategoryIds[$categoryId])) {
                continue;
            }

            $visitedCategoryIds[$categoryId] = true;
            $visibleCategoryIds[] = $categoryId;

            foreach ($categoriesByParent->get((string) $categoryId, collect()) as $child) {
                $pendingCategoryIds[] = $child->getKey();
            }
        }

        $visibleCategories = $categories
            ->whereIn('id', $visibleCategoryIds)
            ->values();
        $categorySlug = $request->query('category');

        if ($categorySlug === '') {
            $categorySlug = null;
        }

        $selectedCategory = null;
        $productCategoryIds = $visibleCategoryIds;

        if ($categorySlug !== null) {
            abort_if(
                ! is_string($categorySlug)
                || $categorySlug === ''
                || mb_strlen($categorySlug) > 255,
                404,
            );

            $selectedCategory = $visibleCategories->firstWhere('slug', $categorySlug);
            abort_if($selectedCategory === null, 404);

            $productCategoryIds = [];
            $visitedSubtreeIds = [];
            $pendingSubtreeIds = [$selectedCategory->getKey()];

            while ($pendingSubtreeIds !== []) {
                $categoryId = (int) array_shift($pendingSubtreeIds);

                if (isset($visitedSubtreeIds[$categoryId])) {
                    continue;
                }

                $visitedSubtreeIds[$categoryId] = true;
                $productCategoryIds[] = $categoryId;

                foreach ($categoriesByParent->get((string) $categoryId, collect()) as $child) {
                    $pendingSubtreeIds[] = $child->getKey();
                }
            }
        }

        $products = Product::query()
            ->select(['id', 'category_id', 'name', 'description', 'price', 'image'])
            ->where('is_active', true)
            ->whereIn('category_id', $productCategoryIds)
            ->whereHas('inventory')
            ->with([
                'category:id,name',
                'inventory:id,product_id,stock,reserved_stock',
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);

        if ($selectedCategory !== null) {
            $products->appends(['category' => $selectedCategory->slug]);
        }

        return view('public.products.index', [
            'products' => $products,
            'publicCategories' => $visibleCategories,
            'selectedCategory' => $selectedCategory,
        ]);
    }
}
