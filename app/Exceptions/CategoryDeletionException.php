<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class CategoryDeletionException extends RuntimeException
{
    public static function hasSubcategories(): self
    {
        return new self('No se puede eliminar la categoría porque tiene subcategorías asociadas.');
    }

    public static function hasProducts(): self
    {
        return new self('No se puede eliminar la categoría porque tiene productos asociados.');
    }

    public static function hasRelatedRecords(?Throwable $previous = null): self
    {
        return new self(
            'No se puede eliminar la categoría porque tiene relaciones asociadas.',
            previous: $previous,
        );
    }
}
