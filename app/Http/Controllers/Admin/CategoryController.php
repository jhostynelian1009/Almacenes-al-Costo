<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexCategoryRequest;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryStatusRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(IndexCategoryRequest $request): View
    {
        $filters = $request->validated();
        $query = Category::query()->with('parent');

        if (($search = $filters['search'] ?? null) !== null) {
            $escapedSearch = addcslashes($search, '\\%_');
            $query->where('name', 'like', "%{$escapedSearch}%");
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        if ($parentId = $filters['parent_id'] ?? null) {
            $query->where('parent_id', $parentId);
        }

        if (($filters['level'] ?? null) === 'main') {
            $query->whereNull('parent_id');
        } elseif (($filters['level'] ?? null) === 'child') {
            $query->whereNotNull('parent_id');
        }

        $categories = $query->ordered()->paginate(15)->withQueryString();
        $parentCategories = Category::query()->ordered()->get(['id', 'name']);

        return view('admin.categories.index', [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
            'filters' => $filters,
            'hasCategories' => $parentCategories->isNotEmpty(),
            'hasActiveFilters' => collect($filters)
                ->except('page')
                ->contains(fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'parentCategories' => Category::query()->ordered()->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create($request->validated());

        return to_route('admin.categories.show', $category)
            ->with('success', 'Categoría creada correctamente.');
    }

    public function show(Category $category): View
    {
        $category->load('parent');

        return view('admin.categories.show', compact('category'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            'parentCategories' => Category::query()
                ->whereKeyNot($category->getKey())
                ->ordered()
                ->get(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('admin.categories.show', $category)
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function updateStatus(UpdateCategoryStatusRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            'is_active' => $request->validated('is_active'),
        ]);

        return to_route('admin.categories.index')
            ->with('success', $category->is_active
                ? 'Categoría activada correctamente.'
                : 'Categoría desactivada correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return to_route('admin.categories.index')
                ->with('error', 'No se puede eliminar la categoría porque tiene subcategorías asociadas.');
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return to_route('admin.categories.index')
                ->with('error', 'No se puede eliminar la categoría porque tiene relaciones asociadas.');
        }

        return to_route('admin.categories.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }
}
