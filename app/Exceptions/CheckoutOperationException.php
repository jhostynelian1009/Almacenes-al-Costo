<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutOperationException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('No hay productos en el carrito para confirmar el pedido.');
    }

    public static function invalidCart(): self
    {
        return new self('Tu carrito contiene productos no disponibles. Revisa las cantidades antes de continuar.');
    }

    public static function reservationFailed(): self
    {
        return new self('No fue posible confirmar el pedido por falta de stock. Revisa tu carrito e inténtalo nuevamente.');
    }

    public static function missingCheckoutSession(): self
    {
        return new self('Tu sesión de checkout expiró. Completa nuevamente tus datos.');
    }
}
