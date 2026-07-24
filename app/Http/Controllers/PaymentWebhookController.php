<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentOperationException;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Receive and process a provider webhook notification.
     *
     * Security order (mandatory, per SPEC):
     * 1. Read raw body
     * 2. Validate HMAC signature — respond 401 on failure
     * 3. Validate timestamp — respond 200 silently on stale
     * 4. Check event_id idempotency
     * 5. Process and mark as processed
     * 6. Always respond 200 unless signature/gateway is invalid
     */
    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $response = $this->paymentService->handleWebhook($request, $gateway);

            return response()->json(['status' => 'ok', 'message' => $response->message ?? 'processed'], 200);
        } catch (PaymentOperationException $e) {
            if (str_contains($e->getMessage(), 'firma')) {
                Log::warning('Webhook invalid signature rejected', [
                    'gateway' => $gateway,
                    'ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Firma inválida.'], 401);
            }

            // Unknown gateway
            Log::warning('Webhook unknown gateway', ['gateway' => $gateway]);

            return response()->json(['error' => 'Pasarela no reconocida.'], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Pasarela no reconocida.'], 400);
        } catch (\Throwable $e) {
            // All internal processing errors respond 200 to prevent provider retries
            Log::error('Webhook processing error — internal', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'ok'], 200);
        }
    }
}
