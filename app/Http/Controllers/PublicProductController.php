<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicProductController extends Controller
{
    private const SEARCH_MAX_LENGTH = 100;

    public function index(Request $request): View
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'parent_id']);
        $visibleCategoryIds = self::resolveVisibleCategoryIds($categories);
        $visibleCategories = $categories
            ->whereIn('id', $visibleCategoryIds)
            ->values();
        $categoryContext = $this->resolveCategoryFilter(
            $request->query('category'),
            $visibleCategories,
            $visibleCategoryIds,
            $categories,
        );
        $searchQuery = $this->normalizeSearchQuery($request->query('q'));

        $products = $this->publicProductsQuery($categoryContext['product_category_ids'])
            ->when($searchQuery !== null, fn (Builder $query): Builder => $this->applySearch($query, $searchQuery))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);

        $appends = array_filter([
            'category' => $categoryContext['selected_category']?->slug,
            'q' => $searchQuery,
        ], fn (?string $value): bool => filled($value));

        if ($appends !== []) {
            $products->appends($appends);
        }

        return view('public.products.index', [
            'products' => $products,
            'publicCategories' => $visibleCategories,
            'selectedCategory' => $categoryContext['selected_category'],
            'searchQuery' => $searchQuery,
        ]);
    }

    public function show(Product $product): View
    {
        if (! $this->isPubliclyVisible($product)) {
            abort(404);
        }

        $product->load([
            'category:id,name,slug',
            'inventory:id,product_id,stock,reserved_stock',
        ]);

        return view('public.products.show', [
            'product' => $product,
        ]);
    }

    /**
     * @return array<int, int>
     */
    public static function resolveVisibleCategoryIds(?Collection $categories = null): array
    {
        $categories ??= Category::query()
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

        return $visibleCategoryIds;
    }

    private function isPubliclyVisible(Product $product): bool
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
            self::resolveVisibleCategoryIds(),
            true,
        );
    }

    /**
     * @param  array<int, int>  $visibleCategoryIds
     * @return array{selected_category: ?Category, product_category_ids: array<int, int>}
     */
    private function resolveCategoryFilter(
        mixed $categorySlug,
        Collection $visibleCategories,
        array $visibleCategoryIds,
        Collection $categories,
    ): array {
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

            $productCategoryIds = $this->resolveSubtreeCategoryIds(
                $selectedCategory,
                $categories,
            );
        }

        return [
            'selected_category' => $selectedCategory,
            'product_category_ids' => $productCategoryIds,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function resolveSubtreeCategoryIds(Category $selectedCategory, Collection $categories): array
    {
        $categoriesByParent = $categories->groupBy(
            fn (Category $category): string => (string) $category->parent_id,
        );
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

        return $productCategoryIds;
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function publicProductsQuery(array $categoryIds): Builder
    {
        return Product::query()
            ->select(['id', 'category_id', 'name', 'slug', 'description', 'price', 'image'])
            ->where('is_active', true)
            ->whereIn('category_id', $categoryIds)
            ->whereHas('inventory')
            ->with([
                'category:id,name,slug',
                'inventory:id,product_id,stock,reserved_stock',
            ]);
    }

    private function normalizeSearchQuery(mixed $searchQuery): ?string
    {
        if (! is_string($searchQuery)) {
            return null;
        }

        $searchQuery = trim($searchQuery);

        if ($searchQuery === '') {
            return null;
        }

        if (mb_strlen($searchQuery) > self::SEARCH_MAX_LENGTH) {
            $searchQuery = mb_substr($searchQuery, 0, self::SEARCH_MAX_LENGTH);
        }

        return $searchQuery;
    }

    private function applySearch(Builder $query, string $searchQuery): Builder
    {
        $likeTerm = '%'.$this->escapeLikeTerm($searchQuery).'%';

        return $query->where(function (Builder $builder) use ($likeTerm): void {
            $builder
                ->where('name', 'like', $likeTerm)
                ->orWhere('description', 'like', $likeTerm);
        });
    }

    private function escapeLikeTerm(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
