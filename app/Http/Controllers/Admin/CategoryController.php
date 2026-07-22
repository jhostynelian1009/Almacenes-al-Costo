<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()->with('parent')->ordered()->get();

        return view('admin.categories.index', compact('categories'));
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
