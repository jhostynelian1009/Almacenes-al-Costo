<?php

namespace Tests\Feature;

use App\Events\OrderApproved;
use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\ReceiptUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────── Public payment page access ──────────────────────────────

    public function test_payment_page_accessible_for_pending_payment_order(): void
    {
        $order = $this->makeOrder();

        $response = $this->get(route('orders.payment.show', $order->reference));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
    }

    public function test_payment_page_redirects_if_order_already_approved(): void
    {
        $order = $this->makeOrder(Order::STATUS_APPROVED);

        $response = $this->get(route('orders.payment.show', $order->reference));

        $response->assertRedirect(route('orders.confirmation', $order->reference));
    }

    public function test_payment_page_redirects_if_order_validating(): void
    {
        $order = $this->makeOrder(Order::STATUS_VALIDATING);

        $response = $this->get(route('orders.payment.show', $order->reference));

        $response->assertRedirect(route('orders.confirmation', $order->reference));
    }

    public function test_payment_page_does_not_expose_internal_ids(): void
    {
        $order = $this->makeOrder();

        $response = $this->get(route('orders.payment.show', $order->reference));

        // Ensure internal numeric order ID is not in the page response
        $response->assertDontSee('"id":'.$order->id, false);
    }

    // ────────────────────────────── Receipt upload ──────────────────────────────

    public function test_valid_receipt_upload_moves_order_to_validating(): void
    {
        Storage::fake('local');
        Event::fake();

        $order = $this->makeOrder();

        $file = UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $response = $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $response->assertRedirect(route('orders.confirmation', $order->reference));

        $order->refresh();
        $this->assertSame(Order::STATUS_VALIDATING, $order->status);
    }

    public function test_receipt_is_stored_in_private_storage(): void
    {
        Storage::fake('local');
        Event::fake();

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $receipt = PaymentReceipt::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($receipt);
        $this->assertStringStartsWith('comprobantes/', $receipt->file_path);

        // Storage path must NOT be in public disk
        Storage::disk('public')->assertMissing($receipt->file_path);
    }

    public function test_receipt_filename_is_generated_not_client_supplied(): void
    {
        Storage::fake('local');
        Event::fake();

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('my_secret_transfer.pdf', 50, 'application/pdf');

        $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $receipt = PaymentReceipt::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($receipt);
        // Generated filename must NOT match the original filename verbatim
        $this->assertStringNotContainsString('my_secret_transfer', $receipt->file_path);
    }

    public function test_invalid_mime_type_is_rejected(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $response->assertSessionHasErrors(['receipt']);
        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
    }

    public function test_file_exceeding_4mb_is_rejected(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('large.pdf', 5000, 'application/pdf'); // 5 MB

        $response = $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $response->assertSessionHasErrors(['receipt']);
    }

    public function test_missing_payment_method_is_rejected(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $response = $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
        ]);

        $response->assertSessionHasErrors(['payment_method']);
    }

    public function test_receipt_upload_rejected_for_non_pending_order(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder(Order::STATUS_APPROVED);
        $file = UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $response = $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $response->assertSessionHas('error');
    }

    // ────────────────────────────── Admin review ──────────────────────────────

    public function test_admin_can_see_payments_index(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder(Order::STATUS_VALIDATING);

        $response = $this->actingAs($admin)->get(route('admin.payments.index'));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
    }

    public function test_guest_cannot_access_admin_payment_review(): void
    {
        $response = $this->get(route('admin.payments.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_admin_payment_review(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('admin.payments.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_approve_validating_payment(): void
    {
        Event::fake([OrderApproved::class]);

        $admin = $this->makeAdmin();
        $order = $this->makeOrder(Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order);

        $this->makeReceipt($order, $payment);

        $response = $this->actingAs($admin)->post(route('admin.payments.review', $order->id), [
            'action' => 'approve',
        ]);

        $response->assertRedirect(route('admin.payments.index'));

        $order->refresh();
        $this->assertSame(Order::STATUS_APPROVED, $order->status);

        Event::assertDispatched(OrderApproved::class);
    }

    public function test_admin_can_reject_with_reason(): void
    {
        Event::fake();

        $admin = $this->makeAdmin();
        $order = $this->makeOrder(Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order);
        $this->makeReceipt($order, $payment);

        $response = $this->actingAs($admin)->post(route('admin.payments.review', $order->id), [
            'action' => 'reject',
            'reason' => 'Comprobante no legible',
        ]);

        $response->assertRedirect(route('admin.payments.show', $order->id));

        $order->refresh()->load('receipt');
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertSame('Comprobante no legible', $order->receipt->rejection_reason);
    }

    public function test_admin_reject_requires_reason(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder(Order::STATUS_VALIDATING);
        $this->makePayment($order);

        $response = $this->actingAs($admin)->post(route('admin.payments.review', $order->id), [
            'action' => 'reject',
            'reason' => '',
        ]);

        $response->assertSessionHasErrors(['reason']);
    }

    public function test_employee_cannot_approve_payment(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $order = $this->makeOrder(Order::STATUS_VALIDATING);
        $this->makePayment($order);

        $response = $this->actingAs($employee)->post(route('admin.payments.review', $order->id), [
            'action' => 'approve',
        ]);

        $response->assertStatus(403);
        $order->refresh();
        $this->assertSame(Order::STATUS_VALIDATING, $order->status);
    }

    // ────────────────────────────── Receipt Download ──────────────────────────────

    public function test_admin_can_download_receipt(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $order = $this->makeOrder(Order::STATUS_VALIDATING);
        $payment = $this->makePayment($order);

        Storage::disk('local')->put('comprobantes/test_receipt.pdf', 'fake-pdf-content');

        PaymentReceipt::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'file_path' => 'comprobantes/test_receipt.pdf',
            'original_filename' => 'comprobante.pdf',
            'payment_method' => 'transfer',
            'uploaded_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.receipt.download', $order->id));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_download_receipt(): void
    {
        $order = $this->makeOrder(Order::STATUS_VALIDATING);

        $response = $this->get(route('admin.payments.receipt.download', $order->id));
        $response->assertRedirect(route('login'));
    }

    // ────────────────────────────── ReceiptUploadService ──────────────────────────────

    public function test_receipt_upload_service_validates_mime(): void
    {
        $service = new ReceiptUploadService;
        $file = UploadedFile::fake()->create('script.php', 10, 'text/plain');

        $this->expectException(PaymentOperationException::class);
        $service->validateFile($file);
    }

    public function test_receipt_upload_service_validates_size(): void
    {
        $service = new ReceiptUploadService;
        $file = UploadedFile::fake()->create('big.pdf', 5000, 'application/pdf'); // 5 MB

        $this->expectException(PaymentOperationException::class);
        $service->validateFile($file);
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
            'subtotal' => '25.00',
            'shipping_cost' => '0.00',
            'total' => '25.00',
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

    private function makeReceipt(Order $order, Payment $payment): PaymentReceipt
    {
        return PaymentReceipt::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'file_path' => 'comprobantes/fake.pdf',
            'payment_method' => 'transfer',
            'uploaded_at' => now(),
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
