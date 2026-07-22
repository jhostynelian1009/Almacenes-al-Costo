<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::query()
            ->with('category:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::query()->create($request->validated());

        return to_route('admin.products.show', $product)
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product): View
    {
        $product->load('category:id,name');

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return to_route('admin.products.show', $product)
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('admin.products.index')
            ->with('success', 'Producto eliminado correctamente.');
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categoryOptions(): Collection
    {
        $categories = Category::query()
            ->ordered()
            ->get(['id', 'name', 'parent_id']);
        $categoriesByParent = $categories
            ->groupBy(fn (Category $category): string => (string) $category->parent_id);
        $options = collect();

        $appendChildren = function (?int $parentId, int $depth) use (&$appendChildren, $categoriesByParent, $options): void {
            foreach ($categoriesByParent->get((string) $parentId, collect()) as $category) {
                $options->push([
                    'id' => (int) $category->getKey(),
                    'label' => str_repeat('— ', $depth).$category->name,
                ]);

                $appendChildren((int) $category->getKey(), $depth + 1);
            }
        };

        $appendChildren(null, 0);

        return $options;
    }
}
