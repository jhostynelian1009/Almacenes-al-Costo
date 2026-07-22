<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(ProductImageService $imageService): View
    {
        $products = Product::query()
            ->with('category:id,name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
        $imageUrls = $products->getCollection()->mapWithKeys(
            fn (Product $product): array => [$product->getKey() => $imageService->url($product->image)]
        );

        return view('admin.products.index', compact('products', 'imageUrls'));
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'categoryOptions' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreProductRequest $request, ProductImageService $imageService): RedirectResponse
    {
        $attributes = $request->safe()->except(['image']);
        $image = $request->file('image');
        $newPath = null;

        try {
            if ($image instanceof UploadedFile) {
                $newPath = $imageService->store($image);
                $attributes['image'] = $newPath;
            }

            $product = Product::query()->create($attributes);
        } catch (Throwable $exception) {
            $this->cleanupNewImage($imageService, $newPath, $exception);
        }

        return to_route('admin.products.show', $product)
            ->with('success', 'Producto creado correctamente.');
    }

    public function show(Product $product, ProductImageService $imageService): View
    {
        $product->load('category:id,name');

        return view('admin.products.show', [
            'product' => $product,
            'imageUrl' => $imageService->url($product->image),
        ]);
    }

    public function edit(Product $product, ProductImageService $imageService): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'categoryOptions' => $this->categoryOptions(),
            'imageUrl' => $imageService->url($product->image),
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        ProductImageService $imageService,
    ): RedirectResponse {
        $attributes = $request->safe()->except(['image', 'remove_image']);
        $image = $request->file('image');
        $currentPath = $product->image;
        $newPath = null;

        try {
            if ($image instanceof UploadedFile) {
                $newPath = $imageService->store($image);
                $attributes['image'] = $newPath;
            } elseif ($request->boolean('remove_image')) {
                $attributes['image'] = null;
            }

            $product->update($attributes);
        } catch (Throwable $exception) {
            $this->cleanupNewImage($imageService, $newPath, $exception);
        }

        if (($newPath !== null || $request->boolean('remove_image')) && $currentPath !== null) {
            $imageService->delete($currentPath);
        }

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

    private function cleanupNewImage(
        ProductImageService $imageService,
        ?string $newPath,
        Throwable $originalException,
    ): never {
        try {
            $imageService->delete($newPath);
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }

        throw $originalException;
    }
}
