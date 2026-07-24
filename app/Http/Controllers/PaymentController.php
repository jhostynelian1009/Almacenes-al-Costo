<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentOperationException;
use App\Http\Requests\UploadReceiptRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\ReceiptUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly ReceiptUploadService $receiptUploadService,
    ) {}

    /**
     * Show payment method selection and instructions for an order.
     */
    public function show(string $orderReference): View|RedirectResponse
    {
        $order = Order::query()
            ->with(['payments', 'receipt'])
            ->where('reference', $orderReference)
            ->firstOrFail();

        // If already paid/approved, redirect to confirmation
        if (in_array($order->status, [Order::STATUS_PAID, Order::STATUS_APPROVED], true)) {
            return redirect()->route('orders.confirmation', ['orderReference' => $orderReference])
                ->with('info', 'Este pedido ya fue pagado.');
        }

        if ($order->status === Order::STATUS_VALIDATING) {
            return redirect()->route('orders.confirmation', ['orderReference' => $orderReference])
                ->with('info', 'Tu comprobante está siendo revisado.');
        }

        $bankConfig = config('payment.bank', []);
        $enabledMethods = config('payment.enabled_methods', ['transfer']);

        return view('public.orders.payment', [
            'order' => $order,
            'bankConfig' => $bankConfig,
            'enabledMethods' => $enabledMethods,
        ]);
    }

    /**
     * Initialize a payment for a given method (transfer or deuna).
     */
    public function process(Request $request, string $orderReference): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:transfer,deuna'],
        ]);

        $order = Order::query()
            ->where('reference', $orderReference)
            ->firstOrFail();

        if (! $order->isPayable()) {
            return back()->with('error', 'Este pedido no puede procesarse en este momento.');
        }

        $method = $validated['payment_method'];
        $gateway = $method === 'deuna' ? 'deuna' : 'manual';

        try {
            $this->paymentService->initialize($order, $gateway, $method);
        } catch (PaymentOperationException $e) {
            Log::info('Payment initialization rejected', [
                'order_reference' => $orderReference,
                'reason' => $e->getMessage(),
            ]);

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('orders.payment.show', ['orderReference' => $orderReference]);
    }

    /**
     * Upload a receipt for a bank transfer or Deuna payment.
     */
    public function uploadReceipt(UploadReceiptRequest $request, string $orderReference): RedirectResponse
    {
        $order = Order::query()
            ->with(['payments'])
            ->where('reference', $orderReference)
            ->firstOrFail();

        if (! $order->isPayable()) {
            return back()->with('error', 'No se puede adjuntar un comprobante a este pedido en su estado actual.');
        }

        $file = $request->file('receipt');
        $paymentMethod = $request->validated('payment_method');
        $reference = $request->validated('transaction_reference');

        $payment = $order->payments()
            ->where('payment_method', $paymentMethod)
            ->where('status', Payment::STATUS_PENDING)
            ->first();

        if ($payment === null) {
            try {
                $this->paymentService->initialize($order, 'manual', $paymentMethod);
                $order->refresh();
                $payment = $order->payments()->where('payment_method', $paymentMethod)->first();
            } catch (PaymentOperationException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        try {
            $this->receiptUploadService->validateFile($file);
            $this->receiptUploadService->store($file, $order, $paymentMethod, $reference, $payment);
            $order->update(['status' => Order::STATUS_VALIDATING]);

            Log::info('Receipt submitted, order moved to validating', [
                'order_reference' => $orderReference,
            ]);
        } catch (PaymentOperationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('orders.confirmation', ['orderReference' => $orderReference])
            ->with('success', 'Comprobante recibido. Tu pedido está siendo revisado por el equipo.');
    }

    /**
     * Handle a payment gateway return/callback.
     * MUST NOT update payment status — only reads current DB state.
     */
    public function callback(Request $request, string $gateway): View
    {
        $orderReference = $request->query('order_reference', '');

        $order = null;
        if ($orderReference !== '') {
            $order = Order::query()->where('reference', $orderReference)->first();
        }

        $status = $order?->status ?? 'unknown';

        return view('public.orders.payment-callback', [
            'order' => $order,
            'status' => $status,
            'gateway' => $gateway,
        ]);
    }
}
