<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentOperationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentReviewController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * List all orders with pending receipt review (status = validating).
     */
    public function index(Request $request): View
    {
        $query = Order::query()
            ->with(['payments' => fn ($q) => $q->latest()])
            ->withCount('items')
            ->where('status', Order::STATUS_VALIDATING)
            ->latest();

        if ($request->has('status') && $request->get('status') !== '') {
            $query->where('status', $request->get('status'));
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.payments.index', ['orders' => $orders]);
    }

    /**
     * Show the payment review detail page for an order.
     */
    public function show(int $id): View
    {
        $order = Order::query()
            ->with(['items', 'payments.transactions', 'receipt'])
            ->findOrFail($id);

        $pendingPayment = $order->payments()
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->first();

        return view('admin.payments.show', [
            'order' => $order,
            'pendingPayment' => $pendingPayment,
        ]);
    }

    /**
     * Approve or reject a manual payment receipt.
     */
    public function review(ReviewPaymentRequest $request, int $id): RedirectResponse
    {
        $order = Order::query()
            ->with(['payments', 'receipt'])
            ->findOrFail($id);

        $payment = $order->payments()
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
            ->first();

        if ($payment === null) {
            return back()->with('error', 'No se encontró un pago pendiente para este pedido.');
        }

        $action = $request->validated('action');
        $reason = $request->validated('reason');

        try {
            if ($action === 'approve') {
                $this->paymentService->approveManualPayment($order, $payment);

                Log::info('Admin approved manual payment', [
                    'order_reference' => $order->reference,
                    'admin_id' => $request->user()?->id,
                ]);

                return redirect()
                    ->route('admin.payments.index')
                    ->with('success', "Pago aprobado para el pedido {$order->reference}.");
            }

            // reject
            $this->paymentService->rejectManualPayment($order, $payment, (string) $reason);

            Log::info('Admin rejected manual payment', [
                'order_reference' => $order->reference,
                'admin_id' => $request->user()?->id,
            ]);

            return redirect()
                ->route('admin.payments.show', $id)
                ->with('success', 'Comprobante rechazado. El cliente puede volver a subir su comprobante.');
        } catch (PaymentOperationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
