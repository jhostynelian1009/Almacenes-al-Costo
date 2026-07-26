<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Payments\DatafastRequestBuilder;
use App\Services\Payments\PaymentService;
use App\Support\Money;
use Illuminate\Console\Command;

class DatafastCheckCommand extends Command
{
    protected $signature = 'payments:datafast-check {--order= : Secure public order reference to validate}';

    protected $description = 'Diagnostico de solo lectura de la preparacion de Datafast.';

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $config = config('payment.datafast', []);
        $builder = new DatafastRequestBuilder(is_array($config) ? $config : []);

        $this->line('Datafast - diagnostico de preparacion');
        $this->line('=====================================');
        $this->newLine();

        $this->line('Configuracion general');
        $generalReady = $this->reportGeneralConfiguration($builder, is_array($config) ? $config : []);
        $this->newLine();

        $orderReady = true;
        $orderReference = trim((string) $this->option('order'));

        if ($orderReference !== '') {
            $this->line('Pedido');

            if ($this->isInternalOrderId($orderReference)) {
                $this->errorLine('La referencia del pedido debe ser publica, no un ID interno.');

                return self::INVALID;
            }

            $order = Order::query()
                ->with('items')
                ->where('reference', $orderReference)
                ->first();

            if ($order === null) {
                $this->errorLine('Pedido no encontrado.');

                return self::INVALID;
            }

            $this->okLine('Referencia publica encontrada.');

            $readiness = $this->paymentService->datafastReadiness($order);
            $orderReady = $this->reportOrderDiagnostics($order, $readiness);
            $this->newLine();
        }

        $this->line('Resultado');

        $isReady = $generalReady && $orderReady;
        $this->line($isReady ? 'LISTO PARA DATAFAST' : 'NO LISTO PARA DATAFAST');

        return $isReady ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function reportGeneralConfiguration(DatafastRequestBuilder $builder, array $config): bool
    {
        $ready = true;

        if ((bool) ($config['enabled'] ?? false)) {
            $this->okLine('Datafast habilitado.');
        } else {
            $this->warnLine('Datafast se encuentra desactivado.');
            $ready = false;
        }

        $environment = $builder->environment();
        if ($environment === '') {
            $this->errorLine('Entorno no configurado.');
            $ready = false;
        } else {
            $this->okLine('Entorno configurado: '.$environment);
        }

        $ready = $this->reportUrl($config['base_url'] ?? '', 'API base URL', $ready);
        $ready = $this->reportUrl($config['widget_url'] ?? '', 'URL del widget', $ready);

        foreach ([
            'entity_id' => 'Entity ID',
            'authorization' => 'Credencial de autorizacion',
            'mid' => 'MID',
            'tid' => 'TID',
            'eci' => 'ECI',
            'pserv' => 'PSERV',
            'risk_name' => 'Parametro de riesgo',
            'brands' => 'Brands',
        ] as $key => $label) {
            $ready = $this->reportPresence($config[$key] ?? '', $label, $ready);
        }

        $currency = trim((string) ($config['currency'] ?? ''));
        if ($currency === '') {
            $this->errorLine('Moneda no configurada.');
            $ready = false;
        } elseif (strcasecmp($currency, 'USD') === 0) {
            $this->okLine('Moneda configurada: '.$currency);
        } else {
            $this->errorLine('Moneda no soportada.');
            $ready = false;
        }

        $paymentType = trim((string) ($config['payment_type'] ?? ''));
        if ($paymentType === '') {
            $this->errorLine('Tipo de pago no configurado.');
            $ready = false;
        } else {
            $this->okLine('Tipo de pago configurado.');
        }

        $ready = $this->reportTimeout($config['connect_timeout'] ?? null, 'Connect timeout', $ready);
        $ready = $this->reportTimeout($config['timeout'] ?? null, 'Timeout', $ready);

        return $ready;
    }

    private function reportUrl(mixed $value, string $label, bool $ready): bool
    {
        $url = trim((string) $value);

        if ($url === '') {
            $this->errorLine($label.' no configurada.');

            return false;
        }

        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        if ($scheme === 'https' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $this->okLine($label.' configurada.');

            return $ready;
        }

        $this->errorLine($label.' debe usar HTTPS.');

        return false;
    }

    private function reportPresence(mixed $value, string $label, bool $ready): bool
    {
        if (trim((string) $value) === '') {
            $this->errorLine($label.' no configurado.');

            return false;
        }

        $this->okLine($label.' configurado.');

        return $ready;
    }

    private function reportTimeout(mixed $value, string $label, bool $ready): bool
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            $this->errorLine($label.' no configurado.');

            return false;
        }

        $this->okLine($label.' configurado.');

        return $ready;
    }

    /**
     * @param  array{enabled: bool, ready: bool, message: string, missing_configuration: list<string>}  $readiness
     */
    private function reportOrderDiagnostics(Order $order, array $readiness): bool
    {
        $ready = $readiness['ready'];

        if (! $order->isPayable()) {
            $this->errorLine('El pedido no esta pendiente de pago.');

            return false;
        }

        if ($this->hasPositiveMoney($order->total)) {
            $this->okLine('Total del pedido valido.');
        } else {
            $this->errorLine('El total del pedido debe ser mayor a cero.');
            $ready = false;
        }

        if ($this->customerReady($order)) {
            $this->okLine('Datos de identificacion completos.');
        } else {
            $this->errorLine('Datos de identificacion incompletos.');
            $ready = false;
        }

        if ($this->billingReady($order)) {
            $this->okLine('Datos de facturacion completos.');
        } else {
            $this->errorLine('Datos de facturacion incompletos.');
            $ready = false;
        }

        if ($this->shippingReady($order)) {
            $this->okLine('Datos de envio completos.');
        } else {
            $this->errorLine('Datos de envio incompletos.');
            $ready = false;
        }

        if ($this->itemsReady($order)) {
            $this->okLine('Items completos y consistentes.');
        } else {
            $this->errorLine('Items incompletos o inconsistentes.');
            $ready = false;
        }

        if ($this->taxSnapshotReady($order)) {
            $this->okLine('Desglose tributario disponible.');
        } else {
            $this->errorLine('Desglose tributario no disponible.');
            $ready = false;
        }

        if ($this->taxEquationMatches($order)) {
            $this->okLine('Bases imponibles e IVA coinciden con el total.');
        } else {
            $this->errorLine('Las bases imponibles mas IVA no coinciden con el total.');
            $ready = false;
        }

        if ($readiness['ready']) {
            $this->okLine('Validacion central de Datafast disponible.');
        } else {
            $this->errorLine($readiness['message']);
        }

        return $ready && $readiness['ready'];
    }

    private function customerReady(Order $order): bool
    {
        foreach ([
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_identification',
        ] as $field) {
            $value = $order->getAttribute($field);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return filter_var((string) $order->customer_email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function billingReady(Order $order): bool
    {
        foreach ([
            'billing_province',
            'billing_city',
            'billing_address',
        ] as $field) {
            $value = $order->getAttribute($field);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function shippingReady(Order $order): bool
    {
        foreach ([
            'province',
            'city',
            'address',
        ] as $field) {
            $value = $order->getAttribute($field);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function itemsReady(Order $order): bool
    {
        if ($order->items->isEmpty()) {
            return false;
        }

        foreach ($order->items as $item) {
            if (
                trim((string) $item->product_name) === ''
                || trim((string) $item->product_sku) === ''
                || ! is_int($item->quantity)
                || $item->quantity <= 0
                || $this->normalizedMoney($item->unit_price) === null
                || $this->normalizedMoney($item->subtotal) === null
            ) {
                return false;
            }

            $expectedSubtotal = Money::multiply((string) $item->unit_price, (int) $item->quantity);
            if (bccomp($expectedSubtotal, Money::normalize((string) $item->subtotal), 2) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function taxSnapshotReady(Order $order): bool
    {
        return $this->normalizedMoney($order->tax_base_zero) !== null
            && $this->normalizedMoney($order->tax_base_taxable) !== null
            && $this->normalizedMoney($order->tax_amount) !== null
            && bccomp((string) $order->tax_base_zero, '0.00', 2) >= 0
            && bccomp((string) $order->tax_base_taxable, '0.00', 2) >= 0
            && bccomp((string) $order->tax_amount, '0.00', 2) >= 0;
    }

    private function taxEquationMatches(Order $order): bool
    {
        if (! $this->taxSnapshotReady($order) || ! $this->hasPositiveMoney($order->total)) {
            return false;
        }

        return bccomp(
            Money::add(
                Money::normalize((string) $order->tax_base_zero),
                Money::normalize((string) $order->tax_base_taxable),
                Money::normalize((string) $order->tax_amount),
            ),
            Money::normalize((string) $order->total),
            2
        ) === 0;
    }

    private function hasPositiveMoney(mixed $value): bool
    {
        $normalized = $this->normalizedMoney($value);

        return $normalized !== null && bccomp($normalized, '0.00', 2) > 0;
    }

    private function normalizedMoney(mixed $value): ?string
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

    private function isInternalOrderId(string $value): bool
    {
        return $value !== '' && ctype_digit($value);
    }

    private function okLine(string $message): void
    {
        $this->line('[OK] '.$message);
    }

    private function warnLine(string $message): void
    {
        $this->line('[AVISO] '.$message);
    }

    private function errorLine(string $message): void
    {
        $this->line('[ERROR] '.$message);
    }
}
