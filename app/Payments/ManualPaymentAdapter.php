<?php

namespace App\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\DTOs\PaymentResponse;
use App\Models\Order;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * ManualPaymentAdapter handles bank transfer payments.
 *
 * initializePayment() returns a pending response with no redirect URL.
 * Webhook and callback are not applicable for manual payments.
 */
class ManualPaymentAdapter implements PaymentGatewayInterface
{
    public function initializePayment(Order $order, array $options = []): PaymentResponse
    {
        // Manual payment: no external call, no redirect URL.
        return PaymentResponse::pending(
            message: 'Por favor cargue su comprobante de transferencia bancaria.',
            payload: ['gateway' => 'manual'],
        );
    }

    public function handleCallback(Request $request): PaymentResponse
    {
        throw new RuntimeException(
            'handleCallback no aplica para pagos manuales (transferencia bancaria).'
        );
    }

    public function handleWebhook(Request $request): PaymentResponse
    {
        throw new RuntimeException(
            'handleWebhook no aplica para pagos manuales (transferencia bancaria).'
        );
    }

    public function validateSignature(Request $request): bool
    {
        throw new RuntimeException(
            'validateSignature no aplica para pagos manuales (transferencia bancaria).'
        );
    }
}
