<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_and_order_items_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('order_items'));

        foreach ([
            'user_id',
            'reference',
            'checkout_idempotency_key',
            'customer_name',
            'customer_email',
            'customer_phone',
            'delivery_method',
            'province',
            'city',
            'address',
            'delivery_reference',
            'notes',
            'subtotal',
            'shipping_cost',
            'total',
            'status',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column), "Missing orders.{$column}");
        }

        foreach ([
            'order_id',
            'product_id',
            'product_name',
            'product_sku',
            'quantity',
            'unit_price',
            'subtotal',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('order_items', $column), "Missing order_items.{$column}");
        }
    }

    public function test_order_models_persist_decimal_snapshots(): void
    {
        $order = Order::query()->create([
            'reference' => '01JTESTORDER00000000000001',
            'checkout_idempotency_key' => 'checkout-token-001',
            'customer_name' => 'Cliente Demo',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '20.50',
            'shipping_cost' => '0.00',
            'total' => '20.50',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $order->items()->create([
            'product_id' => null,
            'product_name' => 'Producto histórico',
            'product_sku' => 'SKU-001',
            'quantity' => 2,
            'unit_price' => '10.25',
            'subtotal' => '20.50',
        ]);

        $fresh = Order::query()->with('items')->firstOrFail();

        $this->assertSame('20.50', $fresh->subtotal);
        $this->assertSame('20.50', $fresh->total);
        $this->assertSame('20.50', $fresh->items->first()->subtotal);
        $this->assertSame('reference', $fresh->getRouteKeyName());
    }

    public function test_order_item_snapshots_remain_when_product_is_deleted(): void
    {
        $order = Order::query()->create([
            'reference' => '01JTESTORDER00000000000002',
            'checkout_idempotency_key' => 'checkout-token-002',
            'customer_name' => 'Cliente Demo',
            'customer_email' => 'cliente@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_HOME,
            'province' => 'Pichincha',
            'city' => 'Quito',
            'address' => 'Av. Demo 123',
            'subtotal' => '15.00',
            'shipping_cost' => '0.00',
            'total' => '15.00',
            'status' => Order::STATUS_PENDING_PAYMENT,
        ]);

        $item = $order->items()->create([
            'product_id' => null,
            'product_name' => 'Producto eliminado',
            'product_sku' => 'SKU-DEL',
            'quantity' => 1,
            'unit_price' => '15.00',
            'subtotal' => '15.00',
        ]);

        $this->assertInstanceOf(OrderItem::class, OrderItem::query()->findOrFail($item->id));
        $this->assertSame('Producto eliminado', $item->fresh()->product_name);
    }
}
