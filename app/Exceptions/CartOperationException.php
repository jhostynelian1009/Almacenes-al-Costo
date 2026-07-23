<?php

namespace App\Exceptions;

use RuntimeException;

class CartOperationException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Tu carrito está vacío.');
    }

    public static function invalidQuantity(): self
    {
        return new self('La cantidad debe ser un número entero mayor o igual a 1.');
    }

    public static function productUnavailable(): self
    {
        return new self('Uno o más productos ya no están disponibles.');
    }

    public static function insufficientStock(int $available): self
    {
        return new self("Solo hay {$available} unidad(es) disponible(s) para este producto.");
    }

    public static function soldOut(): self
    {
        return new self('Este producto está agotado.');
    }

    public static function checkoutBlocked(string $message): self
    {
        return new self($message);
    }
}
