<?php

namespace App\Exceptions;

use DomainException;
use Throwable;

class InventoryOperationException extends DomainException
{
    public static function invalidQuantity(): self
    {
        return new self('La cantidad de inventario debe ser un número entero válido.');
    }

    public static function insufficientStock(): self
    {
        return new self('No existe stock disponible suficiente para completar la operación.');
    }

    public static function invalidAdjustment(): self
    {
        return new self('El nuevo saldo de inventario no es válido.');
    }

    public static function adjustmentReasonRequired(): self
    {
        return new self('El ajuste de inventario requiere un motivo.');
    }

    public static function insufficientReservation(): self
    {
        return new self('La cantidad a liberar supera el stock reservado.');
    }

    public static function deletedProduct(): self
    {
        return new self('No se puede modificar el inventario de un producto eliminado.');
    }

    public static function incompleteReference(): self
    {
        return new self('La referencia del movimiento debe incluir tipo e identificador válidos.');
    }

    public static function invalidIdempotencyKey(): self
    {
        return new self('La clave de idempotencia no es válida.');
    }

    public static function reusedIdempotencyKey(?Throwable $previous = null): self
    {
        return new self(
            'La clave de idempotencia ya fue utilizada para otra operación.',
            previous: $previous,
        );
    }

    public static function immutableMovement(): self
    {
        return new self('Los movimientos de inventario son inmutables.');
    }
}
