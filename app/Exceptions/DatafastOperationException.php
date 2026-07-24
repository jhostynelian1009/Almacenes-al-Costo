<?php

namespace App\Exceptions;

class DatafastOperationException extends PaymentOperationException
{
    /**
     * @param  list<string>  $missingKeys
     */
    public static function configurationIncomplete(array $missingKeys): self
    {
        return new self('Pago con tarjeta no disponible por configuracion pendiente.');
    }

    public static function disabled(): self
    {
        return new self('Pago con tarjeta no disponible en este momento.');
    }

    public static function orderNotReady(string $reason): self
    {
        return new self("No podemos procesar tarjeta para este pedido: {$reason}");
    }

    public static function checkoutCreationFailed(): self
    {
        return new self('No se pudo iniciar el pago con tarjeta. Intenta mas tarde o usa otro metodo.');
    }

    public static function invalidResourcePath(): self
    {
        return new self('No se pudo verificar el pago con tarjeta de forma segura.');
    }

    public static function verificationFailed(): self
    {
        return new self('La respuesta de Datafast no coincide con el pedido. El pago queda pendiente de verificacion.');
    }
}
