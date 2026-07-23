<?php

namespace App\Services;

use App\Exceptions\CartOperationException;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';

    public function __construct(
        private readonly Session $session,
        private readonly ProductVisibilityService $visibilityService,
    ) {}

    /**
     * @return array<int, int>
     */
    public function rawItems(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $productId => $quantity) {
            if (! is_int($productId) && ! (is_string($productId) && ctype_digit($productId))) {
                continue;
            }

            if (! is_int($quantity) && ! (is_string($quantity) && ctype_digit($quantity))) {
                continue;
            }

            $normalized[(int) $productId] = (int) $quantity;
        }

        return $normalized;
    }

    public function unitCount(): int
    {
        return array_sum($this->rawItems());
    }

    public function isEmpty(): bool
    {
        return $this->rawItems() === [];
    }

    /**
     * @return array{
     *     items: list<array{
     *         product: Product,
     *         quantity: int,
     *         unit_price: string,
     *         line_subtotal: string,
     *         available_stock: int
     *     }>,
     *     subtotal: string,
     *     total_units: int
     * }
     */
    public function summary(): array
    {
        $rawItems = $this->rawItems();

        if ($rawItems === []) {
            return [
                'items' => [],
                'subtotal' => '0.00',
                'total_units' => 0,
            ];
        }

        $products = Product::query()
            ->whereIn('id', array_keys($rawItems))
            ->with([
                'category:id,name,slug,parent_id,is_active',
                'inventory:id,product_id,stock,reserved_stock',
            ])
            ->get()
            ->keyBy('id');

        $items = [];
        $subtotal = '0.00';
        $totalUnits = 0;

        foreach ($rawItems as $productId => $quantity) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $unitPrice = Money::normalize($product->price);
            $lineSubtotal = Money::multiply($unitPrice, $quantity);
            $availableStock = $product->inventory?->available_stock ?? 0;

            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'available_stock' => $availableStock,
            ];

            $subtotal = Money::add($subtotal, $lineSubtotal);
            $totalUnits += $quantity;
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'total_units' => $totalUnits,
        ];
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $this->assertValidQuantity($quantity);
        $this->assertCanAddProduct($product, $quantity);

        $items = $this->rawItems();
        $productId = $product->getKey();
        $newQuantity = ($items[$productId] ?? 0) + $quantity;

        $this->assertQuantityWithinStock($product, $newQuantity);

        $items[$productId] = $newQuantity;
        $this->session->put(self::SESSION_KEY, $items);
    }

    public function update(Product $product, int $quantity): void
    {
        $this->assertValidQuantity($quantity);
        $this->assertCanAddProduct($product, $quantity);
        $this->assertQuantityWithinStock($product, $quantity);

        $items = $this->rawItems();

        if (! array_key_exists($product->getKey(), $items)) {
            throw CartOperationException::productUnavailable();
        }

        $items[$product->getKey()] = $quantity;
        $this->session->put(self::SESSION_KEY, $items);
    }

    public function remove(Product $product): void
    {
        $items = $this->rawItems();
        unset($items[$product->getKey()]);
        $this->session->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * @return list<string>
     */
    public function checkoutValidationErrors(): array
    {
        if ($this->isEmpty()) {
            return ['Tu carrito está vacío.'];
        }

        $errors = [];

        foreach ($this->summary()['items'] as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];
            $availableStock = $item['available_stock'];

            if (! $this->visibilityService->isPubliclyVisible($product)) {
                $errors[] = "{$product->name} ya no está disponible.";

                continue;
            }

            if ($availableStock <= 0) {
                $errors[] = "{$product->name} está agotado.";

                continue;
            }

            if ($quantity > $availableStock) {
                $errors[] = "{$product->name} solo tiene {$availableStock} unidad(es) disponible(s).";
            }
        }

        $loadedProductIds = collect($this->summary()['items'])
            ->map(fn (array $item): int => $item['product']->getKey())
            ->all();
        $missingProductIds = array_diff(array_keys($this->rawItems()), $loadedProductIds);

        if ($missingProductIds !== []) {
            $errors[] = 'Uno o más productos del carrito ya no existen.';
        }

        return array_values(array_unique($errors));
    }

    public function assertReadyForCheckout(): void
    {
        $errors = $this->checkoutValidationErrors();

        if ($errors !== []) {
            throw CartOperationException::checkoutBlocked($errors[0]);
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function checkoutProducts(): Collection
    {
        $this->assertReadyForCheckout();

        return collect($this->summary()['items'])
            ->sortBy(fn (array $item): int => $item['product']->getKey())
            ->map(fn (array $item): Product => $item['product'])
            ->values();
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw CartOperationException::invalidQuantity();
        }
    }

    private function assertCanAddProduct(Product $product, int $quantity): void
    {
        if (! $this->visibilityService->isPubliclyVisible($product)) {
            throw CartOperationException::productUnavailable();
        }

        $product->loadMissing('inventory:id,product_id,stock,reserved_stock');

        if (($product->inventory?->available_stock ?? 0) <= 0) {
            throw CartOperationException::soldOut();
        }
    }

    private function assertQuantityWithinStock(Product $product, int $quantity): void
    {
        $product->loadMissing('inventory:id,product_id,stock,reserved_stock');
        $availableStock = $product->inventory?->available_stock ?? 0;

        if ($quantity > $availableStock) {
            throw CartOperationException::insufficientStock($availableStock);
        }
    }
}
