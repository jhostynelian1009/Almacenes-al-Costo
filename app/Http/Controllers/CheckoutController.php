<?php

namespace App\Http\Controllers;

use App\Exceptions\CartOperationException;
use App\Exceptions\CheckoutOperationException;
use App\Http\Requests\CheckoutFormRequest;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
    ) {}

    public function create(): View|RedirectResponse
    {
        if ($this->cartService->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('warning', 'Agrega productos al carrito antes de continuar.');
        }

        $errors = $this->cartService->checkoutValidationErrors();

        if ($errors !== []) {
            return redirect()
                ->route('cart.index')
                ->with('error', $errors[0]);
        }

        return view('public.checkout.create', [
            'cart' => $this->cartService->summary(),
        ]);
    }

    public function review(CheckoutFormRequest $request): View|RedirectResponse
    {
        if ($this->cartService->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('warning', 'Agrega productos al carrito antes de continuar.');
        }

        try {
            $this->cartService->assertReadyForCheckout();
            $this->checkoutService->storeCheckoutData($request->validated());
            $summary = $this->checkoutService->buildReviewSummary();
        } catch (CartOperationException|CheckoutOperationException $exception) {
            return redirect()
                ->route('cart.index')
                ->with('error', $exception->getMessage());
        }

        return view('public.checkout.review', [
            'review' => $summary,
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $order = $this->checkoutService->placeOrder(auth()->user());
        } catch (CheckoutOperationException $exception) {
            return redirect()
                ->route('cart.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('orders.confirmation', $order->reference)
            ->with('success', 'Tu pedido fue registrado correctamente.');
    }
}
