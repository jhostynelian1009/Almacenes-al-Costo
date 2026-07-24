<?php

namespace App\Contracts;

use App\DTOs\PaymentResponse;
use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment transaction with the provider.
     *
     * @param  array<string, mixed>  $options
     */
    public function initializePayment(Order $order, array $options = []): PaymentResponse;

    /**
     * Handle the synchronous browser callback from the provider after the customer
     * returns from the payment portal. MUST NOT update payment state — only reads.
     */
    public function handleCallback(Request $request): PaymentResponse;

    /**
     * Parse and validate the asynchronous server-to-server webhook notification.
     * Includes signature verification via validateSignature().
     */
    public function handleWebhook(Request $request): PaymentResponse;

    /**
     * Verify the HMAC or provider-specific signature of a webhook notification.
     * Must be called with the raw request body before any parsing.
     */
    public function validateSignature(Request $request): bool;
}
