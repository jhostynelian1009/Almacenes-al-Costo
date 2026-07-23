<?php

namespace App\Http\Controllers;

use App\Exceptions\CartOperationException;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Product;
use App\Services\CartService;
use App\Services\ProductVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductVisibilityService $visibilityService,
    ) {}

    public function index(): View
    {
        return view('public.cart.index', [
            'cart' => $this->cartService->summary(),
        ]);
    }

    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        $product = Product::query()
            ->where('slug', $request->validated('product'))
            ->firstOrFail();

        $product->loadMissing('inventory:id,product_id,stock,reserved_stock');

        if (! $this->visibilityService->isPubliclyVisible($product)) {
            abort(404);
        }

        try {
            $this->cartService->add($product, $request->quantity());
        } catch (CartOperationException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Producto agregado al carrito.');
    }

    public function update(UpdateCartItemRequest $request, Product $product): RedirectResponse
    {
        $product->loadMissing('inventory:id,product_id,stock,reserved_stock');

        if (! $this->visibilityService->isPubliclyVisible($product)) {
            abort(404);
        }

        try {
            $this->cartService->update($product, (int) $request->validated('quantity'));
        } catch (CartOperationException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Cantidad actualizada.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->cartService->remove($product);

        return back()->with('success', 'Producto eliminado del carrito.');
    }

    public function clear(): RedirectResponse
    {
        $this->cartService->clear();

        return redirect()
            ->route('cart.index')
            ->with('success', 'Carrito vaciado.');
    }
}
