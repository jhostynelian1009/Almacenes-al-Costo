<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'description', 'parent_id']);

        return view('public.categories.index', [
            'rootCategories' => $categories->whereNull('parent_id')->values(),
            'categoriesByParent' => $categories->groupBy(
                fn (Category $category): string => (string) $category->parent_id,
            ),
            'maxDepth' => max($categories->count(), 1),
        ]);
    }
}
