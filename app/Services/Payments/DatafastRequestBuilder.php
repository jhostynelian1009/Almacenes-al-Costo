<?php

namespace App\Services\Payments;

use App\Exceptions\DatafastOperationException;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatafastRequestBuilder
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @return array{enabled: bool, ready: bool, message: string, missing_configuration: list<string>}
     */
    public function readiness(Order $order): array
    {
        if (! $this->enabled()) {
            return [
                'enabled' => false,
                'ready' => false,
                'message' => 'Pago con tarjeta no disponible en este momento.',
                'missing_configuration' => [],
            ];
        }

        $missing = $this->missingConfigurationKeys();
        if ($missing !== []) {
            return [
                'enabled' => true,
                'ready' => false,
                'message' => 'Pago con tarjeta pendiente de configuracion.',
                'missing_configuration' => $missing,
            ];
        }

        $reason = $this->firstOrderReadinessFailure($order);
        if ($reason !== null) {
            return [
                'enabled' => true,
                'ready' => false,
                'message' => $reason,
                'missing_configuration' => [],
            ];
        }

        return [
            'enabled' => true,
            'ready' => true,
            'message' => 'Pago con tarjeta disponible.',
            'missing_configuration' => [],
        ];
    }

    public function assertReadyForCheckout(Order $order): void
    {
        if (! $this->enabled()) {
            throw DatafastOperationException::disabled();
        }

        $missing = $this->missingConfigurationKeys();
        if ($missing !== []) {
            Log::warning('Datafast configuration incomplete', [
                'gateway' => 'datafast',
                'missing_configuration' => $missing,
            ]);

            throw DatafastOperationException::configurationIncomplete($missing);
        }

        $reason = $this->firstOrderReadinessFailure($order);
        if ($reason !== null) {
            throw DatafastOperationException::orderNotReady($reason);
        }
    }

    /**
     * @return array<string, string>
     */
    public function buildCheckoutPayload(Order $order, string $merchantTransactionId, string $shopperResultUrl): array
    {
        $this->assertReadyForCheckout($order);

        $order->loadMissing('items');
        [$givenName, $surname] = $this->splitCustomerName((string) $order->customer_name);

        $payload = [
            'entityId' => $this->stringConfig('entity_id'),
            'amount' => $this->requiredMoney($order->total),
            'currency' => $this->stringConfig('currency'),
            'paymentType' => $this->stringConfig('payment_type'),
            'merchantTransactionId' => $merchantTransactionId,
            'customParameters[SHOPPER_MID]' => $this->stringConfig('mid'),
            'customParameters[SHOPPER_TID]' => $this->stringConfig('tid'),
            'customParameters[SHOPPER_ECI]' => $this->stringConfig('eci'),
            'customParameters[SHOPPER_PSERV]' => $this->stringConfig('pserv'),
            'customParameters[SHOPPER_VERSIONDF]' => $this->stringConfig('version'),
            'customParameters[SHOPPER_VAL_BASE0]' => $this->requiredMoney($order->tax_base_zero),
            'customParameters[SHOPPER_VAL_BASEIMP]' => $this->requiredMoney($order->tax_base_taxable),
            'customParameters[SHOPPER_VAL_IVA]' => $this->requiredMoney($order->tax_amount),
            'risk.parameters[USER_DATA2]' => $this->stringConfig('risk_name'),
            'customer.givenName' => $givenName,
            'customer.surname' => $surname,
            'customer.email' => (string) $order->customer_email,
            'customer.mobile' => (string) $order->customer_phone,
            'customer.identificationDocId' => (string) $order->customer_identification,
            'billing.street1' => $this->cleanText((string) $order->billing_address),
            'billing.city' => $this->cleanText((string) $order->billing_city),
            'billing.state' => $this->cleanText((string) $order->billing_province),
            'shipping.street1' => $this->cleanText((string) $order->address),
            'shipping.city' => $this->cleanText((string) $order->city),
            'shipping.state' => $this->cleanText((string) $order->province),
            'shopperResultUrl' => $shopperResultUrl,
        ];

        foreach ($order->items as $index => $item) {
            $prefix = "cart.items[{$index}]";
            $payload["{$prefix}.name"] = $this->cleanText((string) $item->product_name);
            $payload["{$prefix}.sku"] = $this->cleanText((string) $item->product_sku, 50);
            $payload["{$prefix}.quantity"] = (string) $item->quantity;
            $payload["{$prefix}.price"] = $this->requiredMoney($item->unit_price);
            $payload["{$prefix}.totalAmount"] = $this->requiredMoney($item->subtotal);
        }

        if ($this->isSandbox() && $this->stringConfig('test_mode') !== '') {
            $payload['testMode'] = $this->stringConfig('test_mode');
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public function missingConfigurationKeys(): array
    {
        $required = [
            'base_url',
            'widget_url',
            'entity_id',
            'authorization',
            'mid',
            'tid',
            'eci',
            'pserv',
            'risk_name',
            'version',
            'currency',
            'payment_type',
            'brands',
        ];

        if ($this->isSandbox()) {
            $required[] = 'test_mode';
        }

        return array_values(array_filter($required, fn (string $key): bool => $this->stringConfig($key) === ''));
    }

    public function checkoutEndpoint(): string
    {
        return rtrim($this->stringConfig('base_url'), '/').'/v1/checkouts';
    }

    public function resourceUrl(string $resourcePath): string
    {
        return rtrim($this->stringConfig('base_url'), '/').$resourcePath;
    }

    public function widgetScriptUrl(string $checkoutId): string
    {
        return rtrim($this->stringConfig('widget_url'), '/').'/v1/paymentWidgets.js?'.http_build_query([
            'checkoutId' => $checkoutId,
        ]);
    }

    public function authorizationToken(): string
    {
        $authorization = $this->stringConfig('authorization');

        if (str_starts_with(strtolower($authorization), 'bearer ')) {
            return trim(substr($authorization, 7));
        }

        return $authorization;
    }

    public function connectTimeout(): int
    {
        return max(1, (int) ($this->config['connect_timeout'] ?? 5));
    }

    public function timeout(): int
    {
        return max(1, (int) ($this->config['timeout'] ?? 15));
    }

    public function currency(): string
    {
        return $this->stringConfig('currency');
    }

    public function brands(): string
    {
        return $this->stringConfig('brands');
    }

    public function environment(): string
    {
        return strtolower($this->stringConfig('environment'));
    }

    public function entityId(): string
    {
        return $this->stringConfig('entity_id');
    }

    public function isSandbox(): bool
    {
        return $this->environment() !== 'production';
    }

    public function isCheckoutCreationCode(?string $code): bool
    {
        return $code === '000.200.100';
    }

    public function isApprovedPaymentCode(?string $code): bool
    {
        if ($code === '000.000.000') {
            return true;
        }

        return $this->isSandbox() && in_array($code, ['000.100.110', '000.100.112'], true);
    }

    public function isFailedPaymentCode(?string $code): bool
    {
        return in_array($code, [
            '100.396.101',
            '100.396.102',
            '800.100.100',
            '800.100.151',
            '800.100.152',
        ], true);
    }

    public function normalizeMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        if (preg_match('/\A-?\d+(\.\d{1,2})?\z/', $value) !== 1) {
            return null;
        }

        return Money::normalize($value);
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    private function firstOrderReadinessFailure(Order $order): ?string
    {
        $order->loadMissing('items');

        if (! $order->isPayable()) {
            return 'el pedido no esta pendiente de pago.';
        }

        $total = $this->normalizeMoney($order->total);
        if ($total === null || bccomp($total, '0.00', 2) <= 0) {
            return 'el total del pedido debe ser mayor a cero.';
        }

        if ($this->stringConfig('currency') !== 'USD') {
            return 'la moneda configurada no esta soportada.';
        }

        if ($this->missingCustomerFields($order) !== []) {
            return 'faltan datos del cliente requeridos para Datafast.';
        }

        if ($this->splitCustomerName((string) $order->customer_name) === null) {
            return 'el nombre del cliente debe incluir nombre y apellido.';
        }

        if ($this->missingBillingFields($order) !== []) {
            return 'faltan datos de facturacion requeridos para Datafast.';
        }

        if ($this->missingShippingFields($order) !== []) {
            return 'faltan datos de envio requeridos para Datafast.';
        }

        if ($order->items->isEmpty()) {
            return 'el pedido no tiene items para enviar a Datafast.';
        }

        foreach ($order->items as $item) {
            if (
                trim((string) $item->product_name) === ''
                || trim((string) $item->product_sku) === ''
                || (int) $item->quantity <= 0
                || $this->normalizeMoney($item->unit_price) === null
                || $this->normalizeMoney($item->subtotal) === null
            ) {
                return 'los items del pedido no tienen snapshots completos.';
            }

            $expectedSubtotal = Money::multiply((string) $item->unit_price, (int) $item->quantity);
            if (bccomp($expectedSubtotal, Money::normalize((string) $item->subtotal), 2) !== 0) {
                return 'los subtotales de items no coinciden con precio y cantidad.';
            }
        }

        $baseZero = $this->normalizeMoney($order->tax_base_zero);
        $baseTaxable = $this->normalizeMoney($order->tax_base_taxable);
        $taxAmount = $this->normalizeMoney($order->tax_amount);

        if ($baseZero === null || $baseTaxable === null || $taxAmount === null) {
            return 'faltan bases imponibles e IVA autorizados para Datafast.';
        }

        foreach ([$baseZero, $baseTaxable, $taxAmount] as $amount) {
            if (bccomp($amount, '0.00', 2) < 0) {
                return 'las bases imponibles e IVA no pueden ser negativos.';
            }
        }

        if (bccomp(Money::add($baseZero, $baseTaxable, $taxAmount), $total, 2) !== 0) {
            return 'las bases imponibles mas IVA no coinciden con el total.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function missingCustomerFields(Order $order): array
    {
        $fields = [
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_identification',
        ];

        return array_values(array_filter($fields, function (string $field) use ($order): bool {
            $value = $order->getAttribute($field);

            if (! is_string($value) || trim($value) === '') {
                return true;
            }

            return $field === 'customer_email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false;
        }));
    }

    /**
     * @return list<string>
     */
    private function missingBillingFields(Order $order): array
    {
        return $this->missingStringAttributes($order, [
            'billing_province',
            'billing_city',
            'billing_address',
        ]);
    }

    /**
     * @return list<string>
     */
    private function missingShippingFields(Order $order): array
    {
        return $this->missingStringAttributes($order, [
            'province',
            'city',
            'address',
        ]);
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function missingStringAttributes(Order $order, array $fields): array
    {
        return array_values(array_filter($fields, function (string $field) use ($order): bool {
            $value = $order->getAttribute($field);

            return ! is_string($value) || trim($value) === '';
        }));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function splitCustomerName(string $name): ?array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        if (! is_array($parts) || count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
            return null;
        }

        return [
            $this->cleanText($parts[0], 48),
            $this->cleanText($parts[1], 80),
        ];
    }

    private function requiredMoney(mixed $value): string
    {
        $normalized = $this->normalizeMoney($value);

        if ($normalized === null) {
            throw DatafastOperationException::orderNotReady('un valor monetario requerido no esta disponible.');
        }

        return $normalized;
    }

    private function cleanText(string $value, int $maxLength = 255): string
    {
        $ascii = Str::ascii($value);
        $clean = preg_replace('/[^A-Za-z0-9 .,_#\/-]/', ' ', $ascii);
        $clean = trim(preg_replace('/\s+/', ' ', (string) $clean));

        return Str::limit($clean, $maxLength, '');
    }

    private function stringConfig(string $key): string
    {
        $value = $this->config[$key] ?? '';

        return is_string($value) ? trim($value) : (string) $value;
    }
}
