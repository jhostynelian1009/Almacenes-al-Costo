<?php

namespace App\Services;

use App\Exceptions\CheckoutOperationException;
use App\Exceptions\InventoryOperationException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    private const CHECKOUT_DATA_KEY = 'checkout.data';

    private const CHECKOUT_TOKEN_KEY = 'checkout.idempotency_token';

    private const CHECKOUT_COMPLETED_REFERENCE_KEY = 'checkout.completed_reference';

    public function __construct(
        private readonly Session $session,
        private readonly CartService $cartService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function storeCheckoutData(array $validated): string
    {
        $token = (string) Str::ulid();

        $this->session->put(self::CHECKOUT_DATA_KEY, $validated);
        $this->session->put(self::CHECKOUT_TOKEN_KEY, $token);
        $this->session->forget(self::CHECKOUT_COMPLETED_REFERENCE_KEY);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function checkoutData(): array
    {
        $data = $this->session->get(self::CHECKOUT_DATA_KEY, []);

        return is_array($data) ? $data : [];
    }

    public function idempotencyToken(): ?string
    {
        $token = $this->session->get(self::CHECKOUT_TOKEN_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function shippingCost(string $deliveryMethod): string
    {
        return '0.00';
    }

    public function shippingLabel(string $deliveryMethod): string
    {
        return $deliveryMethod === Order::DELIVERY_HOME
            ? 'Costo por confirmar'
            : '$ 0.00';
    }

    /**
     * @return array{
     *     items: list<array{
     *         product: Product,
     *         quantity: int,
     *         unit_price: string,
     *         line_subtotal: string
     *     }>,
     *     subtotal: string,
     *     shipping_cost: string,
     *     shipping_label: string,
     *     total: string,
     *     checkout_data: array<string, mixed>
     * }
     */
    public function buildReviewSummary(): array
    {
        $checkoutData = $this->checkoutData();

        if ($checkoutData === [] || $this->idempotencyToken() === null) {
            throw CheckoutOperationException::missingCheckoutSession();
        }

        $cartSummary = $this->cartService->summary();
        $deliveryMethod = (string) ($checkoutData['delivery_method'] ?? Order::DELIVERY_STORE_PICKUP);
        $shippingCost = $this->shippingCost($deliveryMethod);
        $total = Money::add($cartSummary['subtotal'], $shippingCost);

        return [
            'items' => collect($cartSummary['items'])
                ->map(fn (array $item): array => [
                    'product' => $item['product'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_subtotal' => $item['line_subtotal'],
                ])
                ->values()
                ->all(),
            'subtotal' => $cartSummary['subtotal'],
            'shipping_cost' => $shippingCost,
            'shipping_label' => $this->shippingLabel($deliveryMethod),
            'total' => $total,
            'checkout_data' => $checkoutData,
        ];
    }

    public function placeOrder(?User $actor = null): Order
    {
        $completedReference = $this->session->get(self::CHECKOUT_COMPLETED_REFERENCE_KEY);

        if (is_string($completedReference) && $completedReference !== '') {
            return Order::query()
                ->where('reference', $completedReference)
                ->firstOrFail();
        }

        $checkoutData = $this->checkoutData();
        $idempotencyToken = $this->idempotencyToken();

        if ($checkoutData === [] || $idempotencyToken === null) {
            throw CheckoutOperationException::missingCheckoutSession();
        }

        $existingOrder = Order::query()
            ->where('checkout_idempotency_key', $idempotencyToken)
            ->first();

        if ($existingOrder !== null) {
            $this->session->put(self::CHECKOUT_COMPLETED_REFERENCE_KEY, $existingOrder->reference);

            return $existingOrder;
        }

        if ($this->cartService->isEmpty()) {
            throw CheckoutOperationException::emptyCart();
        }

        $this->cartService->assertReadyForCheckout();

        $cartSummary = $this->cartService->summary();
        $deliveryMethod = (string) ($checkoutData['delivery_method'] ?? Order::DELIVERY_STORE_PICKUP);
        $shippingCost = $this->shippingCost($deliveryMethod);
        $subtotal = $cartSummary['subtotal'];
        $total = Money::add($subtotal, $shippingCost);
        $reference = (string) Str::ulid();

        $sortedItems = collect($cartSummary['items'])
            ->sortBy(fn (array $item): int => $item['product']->getKey())
            ->values();

        try {
            $order = DB::transaction(function () use (
                $sortedItems,
                $checkoutData,
                $deliveryMethod,
                $shippingCost,
                $subtotal,
                $total,
                $reference,
                $idempotencyToken,
                $actor,
            ): Order {
                foreach ($sortedItems as $item) {
                    $product = $item['product'];
                    $product->loadMissing('inventory');

                    if (($product->inventory?->available_stock ?? 0) < $item['quantity']) {
                        throw CheckoutOperationException::invalidCart();
                    }
                }

                $order = Order::query()->create([
                    'user_id' => $actor?->getKey(),
                    'reference' => $reference,
                    'checkout_idempotency_key' => $idempotencyToken,
                    'customer_name' => (string) $checkoutData['customer_name'],
                    'customer_email' => (string) $checkoutData['customer_email'],
                    'customer_phone' => (string) $checkoutData['customer_phone'],
                    'delivery_method' => $deliveryMethod,
                    'province' => $checkoutData['province'] ?? null,
                    'city' => $checkoutData['city'] ?? null,
                    'address' => $checkoutData['address'] ?? null,
                    'delivery_reference' => $checkoutData['delivery_reference'] ?? null,
                    'notes' => $checkoutData['notes'] ?? null,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $total,
                    'status' => Order::STATUS_PENDING_PAYMENT,
                ]);

                foreach ($sortedItems as $item) {
                    /** @var Product $product */
                    $product = $item['product'];
                    $inventory = $product->inventory;

                    if ($inventory === null) {
                        throw CheckoutOperationException::invalidCart();
                    }

                    $order->items()->create([
                        'product_id' => $product->getKey(),
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $item['line_subtotal'],
                    ]);

                    $this->inventoryService->reserve(
                        $inventory,
                        $item['quantity'],
                        reason: "Reserva por pedido {$order->reference}",
                        actor: $actor,
                        idempotencyKey: $this->reservationIdempotencyKey($order->reference, $product->getKey()),
                        referenceType: 'order',
                        referenceId: $order->getKey(),
                    );
                }

                return $order->load('items');
            });
        } catch (InventoryOperationException|CheckoutOperationException $exception) {
            throw $exception instanceof CheckoutOperationException
                ? $exception
                : CheckoutOperationException::reservationFailed();
        }

        $this->cartService->clear();
        $this->session->put(self::CHECKOUT_COMPLETED_REFERENCE_KEY, $order->reference);
        $this->session->forget(self::CHECKOUT_DATA_KEY);
        $this->session->forget(self::CHECKOUT_TOKEN_KEY);

        return $order;
    }

    private function reservationIdempotencyKey(string $orderReference, int $productId): string
    {
        return "order:{$orderReference}:product:{$productId}:reserve";
    }
}
