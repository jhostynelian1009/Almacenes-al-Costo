<?php

namespace App\Http\Controllers;

use App\DTOs\PaymentResponse;
use App\Exceptions\PaymentOperationException;
use App\Http\Requests\DatafastResultRequest;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DatafastPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function widget(string $orderReference, string $paymentReference): View|RedirectResponse
    {
        $order = Order::query()
            ->where('reference', $orderReference)
            ->firstOrFail();

        try {
            $widget = $this->paymentService->datafastWidgetData($order, $paymentReference);
        } catch (PaymentOperationException $exception) {
            Log::info('Datafast widget unavailable', [
                'gateway' => 'datafast',
                'order_reference' => $orderReference,
                'payment_reference' => $paymentReference,
                'failure_category' => 'widget_unavailable',
            ]);

            return redirect()
                ->route('orders.payment.show', ['orderReference' => $orderReference])
                ->with('error', $exception->getMessage());
        }

        return view('public.orders.datafast-widget', [
            'order' => $order,
            'widget' => $widget,
        ]);
    }

    public function result(
        DatafastResultRequest $request,
        string $orderReference,
        string $paymentReference,
    ): View {
        $order = Order::query()
            ->where('reference', $orderReference)
            ->firstOrFail();

        try {
            $paymentResponse = $this->paymentService->handleDatafastResult(
                $order,
                $paymentReference,
                (string) $request->validated('resourcePath'),
            );
        } catch (PaymentOperationException $exception) {
            Log::warning('Datafast result could not be verified safely', [
                'gateway' => 'datafast',
                'order_reference' => $orderReference,
                'payment_reference' => $paymentReference,
                'failure_category' => 'result_verification',
            ]);

            $paymentResponse = PaymentResponse::pending(
                transactionId: $paymentReference,
                message: $exception->getMessage(),
                payload: ['gateway' => 'datafast'],
            );
        }

        return view('public.orders.datafast-result', [
            'order' => $order,
            'paymentResponse' => $paymentResponse,
        ]);
    }
}
