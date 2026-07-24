<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentOperationException extends RuntimeException
{
    public static function unsupportedGateway(string $gateway): self
    {
        return new self("Método de pago no soportado: {$gateway}");
    }

    public static function orderNotPayable(): self
    {
        return new self('Este pedido no puede ser procesado en este momento.');
    }

    public static function duplicatePayment(): self
    {
        return new self('Ya existe un pago en proceso para este pedido.');
    }

    public static function invalidReceipt(string $reason = ''): self
    {
        $message = 'El comprobante enviado no es válido.';
        if ($reason !== '') {
            $message .= " {$reason}";
        }

        return new self($message);
    }

    public static function invalidSignature(): self
    {
        return new self('La firma del webhook no es válida.');
    }

    public static function invalidStateTransition(string $from, string $to): self
    {
        return new self("Transición de estado inválida: {$from} → {$to}");
    }

    public static function providerTimeout(): self
    {
        return new self('La pasarela de pago no respondió en el tiempo esperado.');
    }

    public static function providerError(string $safeMessage = ''): self
    {
        $message = 'Ocurrió un error al procesar el pago con la pasarela.';
        if ($safeMessage !== '') {
            $message .= " {$safeMessage}";
        }

        return new self($message);
    }

    public static function duplicateReceipt(): self
    {
        return new self('Ya se ha enviado un comprobante para este pedido.');
    }
}
