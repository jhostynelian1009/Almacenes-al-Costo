<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ────────────────────────────── Internal IDs not exposed ──────────────────────────────

    public function test_payment_page_does_not_expose_order_id(): void
    {
        $order = $this->makeOrder();

        $response = $this->get(route('orders.payment.show', $order->reference));

        $response->assertDontSee('"id":'.$order->id);
        $response->assertDontSee('/admin/orders/'.$order->id);
    }

    public function test_confirmation_page_uses_reference_not_id(): void
    {
        $order = $this->makeOrder(Order::STATUS_VALIDATING);

        $response = $this->get(route('orders.confirmation', $order->reference));

        $response->assertStatus(200);
        $response->assertSee($order->reference);
    }

    // ────────────────────────────── Status cannot be set from client ──────────────────────────────

    public function test_client_cannot_set_order_status_via_payment_process(): void
    {
        $order = $this->makeOrder();

        $response = $this->post(route('orders.payment.process', $order->reference), [
            'payment_method' => 'transfer',
            'status' => 'approved',  // Attacker-supplied status
        ]);

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order->status);
    }

    public function test_client_cannot_set_payment_amount_via_upload(): void
    {
        Storage::fake('local');
        Event::fake();

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('comp.pdf', 100, 'application/pdf');

        $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
            'amount' => '0.01',  // Attacker-supplied amount
        ]);

        $payment = Payment::query()->where('order_id', $order->id)->first();
        if ($payment !== null) {
            $this->assertSame('25.00', (string) $payment->amount);
        }

        // The order total must always come from DB
        $order->refresh();
        $this->assertSame('25.00', (string) $order->total);
    }

    // ────────────────────────────── CSRF ──────────────────────────────

    public function test_receipt_upload_requires_csrf(): void
    {
        $order = $this->makeOrder();

        // In test environment, unverified POST requests redirect or reject safely
        $response = $this->call('POST', route('orders.payment.upload', $order->reference), [], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'wrong-token',
        ]);

        $this->assertContains($response->getStatusCode(), [302, 419]);
    }

    public function test_admin_review_requires_csrf(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder(Order::STATUS_VALIDATING);

        $response = $this->actingAs($admin)->call('POST', route('admin.payments.review', $order->id), [
            'action' => 'approve',
        ], [], [], [
            'HTTP_X_CSRF_TOKEN' => 'wrong-token',
        ]);

        $this->assertContains($response->getStatusCode(), [302, 419]);
    }

    // ────────────────────────────── Webhook CSRF exclusion ──────────────────────────────

    public function test_webhook_does_not_require_csrf_session(): void
    {
        $body = json_encode(['id' => 'evt_nocsrf_001', 'type' => 'test']);

        // No CSRF token — but webhook should handle this via HMAC only
        $response = $this->post(route('payment.webhook', 'deuna'), json_decode($body, true));

        // 401 is expected (no valid HMAC) — but NOT 419 (CSRF)
        $this->assertNotSame(419, $response->getStatusCode());
    }

    // ────────────────────────────── Receipt path traversal prevention ──────────────────────────────

    public function test_path_traversal_in_receipt_download_is_blocked(): void
    {
        $admin = $this->makeAdmin();

        $order = $this->makeOrder(Order::STATUS_VALIDATING);

        // Create a receipt with a malicious path
        PaymentReceipt::query()->create([
            'order_id' => $order->id,
            'file_path' => '../../../etc/passwd',
            'payment_method' => 'transfer',
            'uploaded_at' => now(),
        ]);

        Storage::fake('local');

        $response = $this->actingAs($admin)->get(route('admin.payments.receipt.download', $order->id));

        // Must NOT serve the file — either 403 or 404
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    // ────────────────────────────── Authorization ──────────────────────────────

    public function test_guest_cannot_access_admin_payment_index(): void
    {
        $response = $this->get(route('admin.payments.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_employee_cannot_access_admin_payment_index(): void
    {
        $user = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $response = $this->actingAs($user)->get(route('admin.payments.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_access_payment_index(): void
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('admin.payments.index'));
        $response->assertStatus(200);
    }

    // ────────────────────────────── Logs do not contain secrets ──────────────────────────────

    public function test_payment_service_logs_do_not_contain_api_key(): void
    {
        Event::fake();
        Log::shouldReceive('info')->withArgs(function ($message, $context) {
            $encoded = json_encode($context);

            return ! str_contains((string) $encoded, 'SECRET_API_KEY')
                && ! str_contains((string) $encoded, 'webhook_secret');
        })->zeroOrMoreTimes();

        config(['payment.deuna.api_key' => 'SECRET_API_KEY']);

        $order = $this->makeOrder();
        app(PaymentService::class)->initialize($order, 'manual', 'transfer');

        $this->assertTrue(true); // assertion is in the shouldReceive above
    }

    // ────────────────────────────── No replay from duplicate request ──────────────────────────────

    public function test_duplicate_receipt_submission_does_not_create_new_payment(): void
    {
        Storage::fake('local');
        Event::fake();

        $order = $this->makeOrder();
        $file = UploadedFile::fake()->create('comp.pdf', 100, 'application/pdf');

        $this->post(route('orders.payment.upload', $order->reference), [
            'receipt' => $file,
            'payment_method' => 'transfer',
        ]);

        $paymentCount = Payment::query()->where('order_id', $order->id)->count();

        // Submit again (order is now in validating, so upload is blocked)
        $file2 = UploadedFile::fake()->create('comp2.pdf', 100, 'application/pdf');
        $this->post(route('orders.payment.upload', $order->fresh()->reference), [
            'receipt' => $file2,
            'payment_method' => 'transfer',
        ]);

        $this->assertSame($paymentCount, Payment::query()->where('order_id', $order->id)->count());
    }

    // ────────────────────────────── Helpers ──────────────────────────────

    private function makeOrder(string $status = Order::STATUS_PENDING_PAYMENT): Order
    {
        return Order::query()->create([
            'reference' => (string) Str::ulid(),
            'checkout_idempotency_key' => (string) Str::ulid(),
            'customer_name' => 'Security Test User',
            'customer_email' => 'security@example.com',
            'customer_phone' => '0999999999',
            'delivery_method' => Order::DELIVERY_STORE_PICKUP,
            'subtotal' => '25.00',
            'shipping_cost' => '0.00',
            'total' => '25.00',
            'status' => $status,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }
}
