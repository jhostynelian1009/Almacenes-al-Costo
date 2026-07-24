<?php

namespace Tests\Feature;

use App\DTOs\PaymentResponse;
use App\Events\OrderApproved;
use App\Events\PaymentInitiated;
use App\Exceptions\InventoryOperationException;
use App\Exceptions\PaymentOperationException;
use App\Jobs\ReleaseReservedStock;
use App\Jobs\UpdateInventoryOnPayment;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────── initialize ──────────────────────────────

    public function test_initialize_creates_payment_with_amount_from_order(): void
    {
        Event::fake();

        $order = $this->makeOrder('10.99');
        $service = app(PaymentService::class);

        $response = $service->initialize($order, 'manual', 'transfer');

        $this->assertSame('pending', $response->status);
        $this->assertNull($response->redirectUrl);

        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('10.99', (string) $payment->amount);
    }

    public function test_initialize_does_not_accept_amount_from_client(): void
    {
        Event::fake();

        $order = $this->makeOrder('10.00');
        $service = app(PaymentService::class);

        $service->initialize($order, 'manual', 'transfer', ['amount' => '999.99']);

        $payment = Payment::query()->where('order_id', $order->id)->first();
        $this->assertSame('10.00', (string) $payment->amount);
    }

    public function test_initialize_is_idempotent_for_same_order_and_method(): void
    {
        Event::fake();

        $order = $this->makeOrder();
        $service = app(PaymentService::class);

        $service->initialize($order, 'manual', 'transfer');
        $service->initialize($order, 'manual', 'transfer');

        $count = Payment::query()->where('order_id', $order->id)->count();
        $this->assertSame(1, $count);
    }

    public function test_initialize_rejects_non_payable_order(): void
    {
        $order = $this->makeOrder('10.00', Order::STATUS_VALIDATING);
        $service = app(PaymentService::class);

        $this->expectException(PaymentOperationException::class);
        $service->initialize($order, 'manual', 'transfer');
    }

    public function test_initialize_creates_payment_transaction_records(): void
    {
        Event::fake();

        $order = $this->makeOrder();
        $service = app(PaymentService::class);

        $service->initialize($order, 'manual', 'transfer');

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $payment->transactions()->count()); // request + response
    }

    public function test_initialize_dispatches_payment_initiated_event(): void
    {
        Event::fake([PaymentInitiated::class]);

        $order = $this->makeOrder();
        app(PaymentService::class)->initialize($order, 'manual', 'transfer');

        Event::assertDispatched(PaymentInitiated::class, function (PaymentInitiated $event) use ($order) {
            return $event->order->is($order);
        });
    }

    public function test_initialize_dispatches_payment_initiated_event_only_once_on_idempotent_call(): void
    {
        Event::fake([PaymentInitiated::class]);

        $order = $this->makeOrder();
        $service = app(PaymentService::class);

        $service->initialize($order, 'manual', 'transfer');
        $service->initialize($order, 'manual', 'transfer');

        Event::assertDispatchedTimes(PaymentInitiated::class, 1);
    }

    // ────────────────────────────── approveManualPayment ──────────────────────────────

    public function test_approve_manual_payment_transitions_order_to_approved(): void
    {
        Event::fake();

        $order = $this->makeOrder('10.00', Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);
        $service = app(PaymentService::class);

        $service->approveManualPayment($order, $payment);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_APPROVED, $order->status);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
    }

    public function test_approve_dispatches_order_approved_event(): void
    {
        Event::fake([OrderApproved::class]);

        $order = $this->makeOrder('10.00', Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        app(PaymentService::class)->approveManualPayment($order, $payment);

        Event::assertDispatched(OrderApproved::class);
    }

    public function test_approve_rejects_non_validating_order(): void
    {
        $order = $this->makeOrder('10.00', Order::STATUS_PENDING_PAYMENT);
        $payment = $this->makePayment($order);
        $service = app(PaymentService::class);

        $this->expectException(PaymentOperationException::class);
        $service->approveManualPayment($order, $payment);
    }

    public function test_approve_is_idempotent_on_repeated_call(): void
    {
        Event::fake([OrderApproved::class]);

        $order = $this->makeOrder('10.00', Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);
        $service = app(PaymentService::class);

        $service->approveManualPayment($order, $payment);

        // Second call should throw (order is now approved, not validating)
        $this->expectException(PaymentOperationException::class);
        $service->approveManualPayment($order->fresh(), $payment->fresh());
    }

    // ────────────────────────────── rejectManualPayment ──────────────────────────────

    public function test_reject_manual_payment_returns_order_to_pending_payment(): void
    {
        Event::fake();

        $order = $this->makeOrder('10.00', Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);
        $service = app(PaymentService::class);

        $service->rejectManualPayment($order, $payment, 'Comprobante ilegible');

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
    }

    public function test_reject_stores_rejection_reason_on_receipt(): void
    {
        Event::fake();

        $order = $this->makeOrder('10.00', Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        PaymentReceipt::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'file_path' => 'comprobantes/test.pdf',
            'payment_method' => 'transfer',
            'uploaded_at' => now(),
        ]);

        app(PaymentService::class)->rejectManualPayment($order, $payment, 'Monto incorrecto');

        $order->refresh()->load('receipt');
        $this->assertSame('Monto incorrecto', $order->receipt->rejection_reason);
    }

    public function test_reject_rejects_non_validating_order(): void
    {
        $order = $this->makeOrder('10.00', Order::STATUS_PENDING_PAYMENT);
        $payment = $this->makePayment($order);

        $this->expectException(PaymentOperationException::class);
        app(PaymentService::class)->rejectManualPayment($order, $payment, 'reason');
    }

    // ────────────────────────────── Inventory Settlement ──────────────────────────────

    public function test_physical_stock_2_reserved_stock_2_quantity_2_settles_successfully(): void
    {
        $order = $this->makeOrderWithInventory(2, 2, 2);

        $job = new UpdateInventoryOnPayment($order);
        $job->handle(app(InventoryService::class));

        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(0, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
    }

    public function test_physical_stock_10_reserved_stock_3_quantity_3_settles_correctly(): void
    {
        $order = $this->makeOrderWithInventory(10, 3, 3);

        $job = new UpdateInventoryOnPayment($order);
        $job->handle(app(InventoryService::class));

        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(7, $inventory->stock);
        $this->assertSame(0, $inventory->reserved_stock);
    }

    public function test_physical_and_reserved_balances_are_both_reduced_exactly_once(): void
    {
        $order = $this->makeOrderWithInventory(5, 5, 2);

        $job = new UpdateInventoryOnPayment($order);
        $job->handle(app(InventoryService::class));

        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(3, $inventory->stock);
        $this->assertSame(3, $inventory->reserved_stock);

        $this->assertSame(2, $inventory->movements()->count());
        $this->assertSame(1, $inventory->movements()->where('type', 'release')->count());
        $this->assertSame(1, $inventory->movements()->where('type', 'exit')->count());
    }

    public function test_insufficient_reserved_stock_fails_without_changing_either_balance(): void
    {
        $order = $this->makeOrderWithInventory(5, 1, 2);

        $job = new UpdateInventoryOnPayment($order);

        try {
            $job->handle(app(InventoryService::class));
            $this->fail('Should have failed due to insufficient reserved stock');
        } catch (InventoryOperationException $e) {
            // Expected
        }

        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(5, $inventory->stock);
        $this->assertSame(1, $inventory->reserved_stock);
    }

    public function test_failure_during_settlement_rolls_back_every_change(): void
    {
        $order = $this->makeOrderWithInventory(5, 2, 2);

        $eventName = 'eloquent.creating: '.InventoryMovement::class;
        $count = 0;
        Event::listen($eventName, function () use (&$count) {
            $count++;
            if ($count === 2) {
                throw new \RuntimeException('Forced movement failure on exit.');
            }
        });

        $job = new UpdateInventoryOnPayment($order);
        try {
            $job->handle(app(InventoryService::class));
            $this->fail('Should have failed due to forced exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('Forced movement failure on exit.', $e->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(5, $inventory->stock);
        $this->assertSame(2, $inventory->reserved_stock);
        $this->assertSame(0, $inventory->movements()->count());
    }

    public function test_retrying_the_same_job_creates_no_duplicate_inventory_effects(): void
    {
        $order = $this->makeOrderWithInventory(5, 5, 2);
        $inventory = $order->items->first()->product->inventory;

        $job = new UpdateInventoryOnPayment($order);
        $job->handle(app(InventoryService::class));
        $job->handle(app(InventoryService::class)); // Retry

        $inventory->refresh();
        $this->assertSame(3, $inventory->stock);
        $this->assertSame(3, $inventory->reserved_stock);
        $this->assertSame(2, $inventory->movements()->count());
    }

    public function test_duplicate_payment_webhook_creates_no_duplicate_inventory_effect(): void
    {
        $order = $this->makeOrderWithInventory(5, 5, 2);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $response = new PaymentResponse(status: 'completed', transactionId: 'tx_123');

        $service = app(PaymentService::class);
        $method = new \ReflectionMethod($service, 'applyWebhookResult');
        $method->invoke($service, $payment, $response, 'evt_1');

        // Sync queue triggers the job
        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(3, $inventory->stock);

        // Duplicate webhook
        $method->invoke($service, $payment, $response, 'evt_1');
        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(3, $inventory->stock);
    }

    public function test_repeated_manual_approval_creates_no_duplicate_inventory_effect(): void
    {
        $order = $this->makeOrderWithInventory(5, 5, 2);
        $order->update(['status' => Order::STATUS_VALIDATING]);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $service = app(PaymentService::class);
        $service->approveManualPayment($order, $payment);

        $inventory = $order->items->first()->product->inventory->fresh();
        $this->assertSame(3, $inventory->stock);

        try {
            $service->approveManualPayment($order, $payment);
        } catch (PaymentOperationException $e) {
            // Already approved
        }
        $this->assertSame(3, $order->items->first()->product->inventory->fresh()->stock);
    }

    public function test_manual_approval_dispatches_only_one_inventory_settlement_job(): void
    {
        Queue::fake();

        $order = $this->makeOrderWithInventory(5, 5, 2);
        $order->update(['status' => Order::STATUS_VALIDATING]);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        app(PaymentService::class)->approveManualPayment($order, $payment);

        Queue::assertPushed(UpdateInventoryOnPayment::class, 1);
        Queue::assertNotPushed(ReleaseReservedStock::class);
    }

    public function test_gateway_completion_dispatches_only_one_inventory_settlement_job(): void
    {
        Queue::fake();

        $order = $this->makeOrderWithInventory(5, 5, 2);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $service = app(PaymentService::class);
        $method = new \ReflectionMethod($service, 'applyWebhookResult');
        $response = new PaymentResponse(status: 'completed', transactionId: 'tx_123');
        $method->invoke($service, $payment, $response, 'evt_1');

        Queue::assertPushed(UpdateInventoryOnPayment::class, 1);
        Queue::assertNotPushed(ReleaseReservedStock::class);
    }

    public function test_failed_or_expired_payment_releases_the_reservation_once(): void
    {
        Queue::fake();

        $order = $this->makeOrderWithInventory(5, 5, 2);
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $service = app(PaymentService::class);
        $method = new \ReflectionMethod($service, 'applyWebhookResult');
        $response = new PaymentResponse(status: 'failed');
        $method->invoke($service, $payment, $response, 'evt_2');

        Queue::assertPushed(ReleaseReservedStock::class, 1);
        Queue::assertNotPushed(UpdateInventoryOnPayment::class);
    }

    public function test_completed_payment_cannot_release_its_reservation_through_a_failure_event(): void
    {
        Queue::fake();

        $order = $this->makeOrderWithInventory(5, 5, 2);
        $order->update(['status' => Order::STATUS_PAID]);
        $payment = $this->makePayment($order, Payment::STATUS_COMPLETED);

        $service = app(PaymentService::class);
        $method = new \ReflectionMethod($service, 'applyWebhookResult');

        // Attacker sends failed webhook for already completed payment
        $response = new PaymentResponse(status: 'failed');
        $method->invoke($service, $payment, $response, 'evt_3');

        // Should be ignored
        Queue::assertNotPushed(ReleaseReservedStock::class);
    }

    // ────────────────────────────── Helpers ──────────────────────────────

    private function makeOrderWithInventory(int $stock, int $reserved, int $quantity): Order
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create(['is_active' => true]);
        $product->inventory()->update([
            'stock' => $stock,
            'reserved_stock' => $reserved,
        ]);

        $order = $this->makeOrder('10.00', Order::STATUS_PENDING_PAYMENT);
        $order->items()->create([
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => '10.00',
            'subtotal' => '10.00',
        ]);

        return $order;
    }

    private function makeOrder(
        string $total = '10.00',
        string $status = Order::STATUS_PENDING_PAYMENT,
    ): Order {
        return Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => $total,
            'shipping_cost' => '0.00',
            'total' => $total,
            'status' => $status,
        ]);
    }

    private function makePayment(Order $order, string $status = Payment::STATUS_PENDING): Payment
    {
        return Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'manual',
            'payment_method' => 'transfer',
            'amount' => $order->total,
            'status' => $status,
        ]);
    }
}
