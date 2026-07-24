<?php

namespace Tests\Feature;

use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\PaymentTransaction;
use App\Models\WebhookLog;
use App\Payments\ManualPaymentAdapter;
use App\Services\Payments\PaymentFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────── Schema & Constants ──────────────────────────────

    public function test_payments_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('payments')
        );
    }

    public function test_payment_receipts_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('payment_receipts')
        );
    }

    public function test_payment_transactions_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('payment_transactions')
        );
    }

    public function test_webhook_logs_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('webhook_logs')
        );
    }

    public function test_payment_statuses_are_defined(): void
    {
        $this->assertSame('pending', Payment::STATUS_PENDING);
        $this->assertSame('processing', Payment::STATUS_PROCESSING);
        $this->assertSame('completed', Payment::STATUS_COMPLETED);
        $this->assertSame('failed', Payment::STATUS_FAILED);
        $this->assertSame('expired', Payment::STATUS_EXPIRED);
        $this->assertSame('refunded', Payment::STATUS_REFUNDED);
    }

    public function test_order_payment_status_constants_are_defined(): void
    {
        $this->assertSame('pending_payment', Order::STATUS_PENDING_PAYMENT);
        $this->assertSame('validating', Order::STATUS_VALIDATING);
        $this->assertSame('paid', Order::STATUS_PAID);
        $this->assertSame('approved', Order::STATUS_APPROVED);
        $this->assertSame('canceled', Order::STATUS_CANCELED);
    }

    public function test_payment_gateway_constants_are_defined(): void
    {
        $this->assertSame('manual', Payment::GATEWAY_MANUAL);
        $this->assertSame('deuna', Payment::GATEWAY_DEUNA);
        $this->assertSame('transfer', Payment::METHOD_TRANSFER);
        $this->assertSame('deuna', Payment::METHOD_DEUNA);
    }

    public function test_payment_amount_is_cast_to_decimal(): void
    {
        $order = $this->makeOrder();

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'manual',
            'payment_method' => 'transfer',
            'amount' => '123.456',
            'status' => Payment::STATUS_PENDING,
        ]);

        $payment->refresh();

        $this->assertIsString($payment->amount);
        $this->assertStringContainsString('123.46', (string) $payment->amount);
    }

    public function test_payment_transaction_payload_is_cast_to_array(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        $tx = PaymentTransaction::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'request',
            'payload' => ['key' => 'value'],
        ]);

        $tx->refresh();
        $this->assertIsArray($tx->payload);
        $this->assertSame('value', $tx->payload['key']);
    }

    public function test_webhook_log_payload_is_cast_to_array(): void
    {
        $log = WebhookLog::query()->create([
            'gateway' => 'deuna',
            'event_id' => 'evt_test_001',
            'payload' => ['event' => 'payment.completed'],
            'signature_verified' => true,
            'processed' => false,
        ]);

        $log->refresh();
        $this->assertIsArray($log->payload);
        $this->assertTrue($log->signature_verified);
    }

    // ────────────────────────────── Relationships ──────────────────────────────

    public function test_order_has_many_payments(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        $this->assertTrue($order->payments->contains($payment));
    }

    public function test_payment_belongs_to_order(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        $this->assertTrue($payment->order->is($order));
    }

    public function test_payment_has_many_transactions(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        PaymentTransaction::query()->create([
            'payment_id' => $payment->id,
            'event_type' => 'request',
            'payload' => [],
        ]);

        $payment->refresh()->load('transactions');
        $this->assertCount(1, $payment->transactions);
    }

    public function test_order_has_one_receipt(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order);

        PaymentReceipt::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'file_path' => 'comprobantes/test.pdf',
            'payment_method' => 'transfer',
            'uploaded_at' => now(),
        ]);

        $order->refresh()->load('receipt');
        $this->assertNotNull($order->receipt);
    }

    // ────────────────────────────── Status helpers ──────────────────────────────

    public function test_payment_is_pending(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order, Payment::STATUS_PENDING);

        $this->assertTrue($payment->isPending());
        $this->assertFalse($payment->isCompleted());
    }

    public function test_payment_is_completed(): void
    {
        $order = $this->makeOrder();
        $payment = $this->makePayment($order, Payment::STATUS_COMPLETED);

        $this->assertTrue($payment->isCompleted());
        $this->assertFalse($payment->isPending());
    }

    public function test_payment_terminal_status_detection(): void
    {
        $order = $this->makeOrder();

        foreach ([Payment::STATUS_COMPLETED, Payment::STATUS_FAILED, Payment::STATUS_EXPIRED, Payment::STATUS_REFUNDED] as $status) {
            $payment = $this->makePayment($order, $status);
            $this->assertTrue($payment->isTerminal(), "Expected {$status} to be terminal");
        }

        $notTerminal = $this->makePayment($order, Payment::STATUS_PENDING);
        $this->assertFalse($notTerminal->isTerminal());
    }

    public function test_order_status_helpers(): void
    {
        $order = $this->makeOrder(Order::STATUS_PENDING_PAYMENT);
        $this->assertTrue($order->isPendingPayment());
        $this->assertTrue($order->isPayable());

        $order->update(['status' => Order::STATUS_VALIDATING]);
        $order->refresh();
        $this->assertTrue($order->isValidating());
        $this->assertFalse($order->isPayable());
    }

    // ────────────────────────────── Unique constraints ──────────────────────────────

    public function test_webhook_log_event_id_must_be_unique(): void
    {
        WebhookLog::query()->create([
            'gateway' => 'deuna',
            'event_id' => 'unique_event_001',
            'payload' => [],
            'signature_verified' => true,
        ]);

        $this->expectException(QueryException::class);

        WebhookLog::query()->create([
            'gateway' => 'deuna',
            'event_id' => 'unique_event_001',
            'payload' => [],
            'signature_verified' => true,
        ]);
    }

    // ────────────────────────────── Factory support ──────────────────────────────

    public function test_factory_returns_manual_adapter_for_transfer(): void
    {
        $factory = new PaymentFactory;
        $adapter = $factory->make('manual');

        $this->assertInstanceOf(ManualPaymentAdapter::class, $adapter);
    }

    public function test_factory_returns_manual_adapter_for_transfer_alias(): void
    {
        $factory = new PaymentFactory;
        $adapter = $factory->make('transfer');

        $this->assertInstanceOf(ManualPaymentAdapter::class, $adapter);
    }

    public function test_factory_rejects_unsupported_gateway(): void
    {
        $factory = new PaymentFactory;

        $this->expectException(PaymentOperationException::class);
        $factory->make('stripe');
    }

    public function test_factory_rejects_empty_gateway(): void
    {
        $factory = new PaymentFactory;

        $this->expectException(PaymentOperationException::class);
        $factory->make('');
    }

    // ────────────────────────────── Helpers ──────────────────────────────

    private function makeOrder(string $status = Order::STATUS_PENDING_PAYMENT): Order
    {
        return Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '10.00',
            'shipping_cost' => '0.00',
            'total' => '10.00',
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
