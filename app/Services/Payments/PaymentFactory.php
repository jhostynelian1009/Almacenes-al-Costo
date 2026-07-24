<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentOperationException;
use App\Payments\DeunaGateway;
use App\Payments\ManualPaymentAdapter;

class PaymentFactory
{
    /**
     * Supported gateway identifiers.
     */
    public const SUPPORTED_GATEWAYS = ['manual', 'deuna'];

    /**
     * Resolve and return the correct payment adapter for the given gateway identifier.
     *
     * @throws PaymentOperationException when gateway is not supported
     */
    public function make(string $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            'manual', 'transfer' => new ManualPaymentAdapter,
            'deuna' => $this->buildDeunaGateway(),
            default => throw PaymentOperationException::unsupportedGateway($gateway),
        };
    }

    private function buildDeunaGateway(): DeunaGateway
    {
        /** @var array<string, mixed> $config */
        $config = config('payment.deuna', []);

        return new DeunaGateway(
            baseUrl: (string) ($config['base_url'] ?? ''),
            merchantId: (string) ($config['merchant_id'] ?? ''),
            apiKey: (string) ($config['api_key'] ?? ''),
            webhookSecret: (string) ($config['webhook_secret'] ?? ''),
            timeoutSeconds: (int) ($config['timeout'] ?? 30),
        );
    }
}
