<?php

namespace App\Services\Payments;

use App\Exceptions\DatafastOperationException;

class DatafastResourcePathValidator
{
    public function validate(string $resourcePath, string $checkoutId): string
    {
        $resourcePath = trim($resourcePath);

        if ($resourcePath === '' || ! $this->isValidCheckoutId($checkoutId)) {
            throw DatafastOperationException::invalidResourcePath();
        }

        $decoded = rawurldecode($resourcePath);

        if ($this->containsUnsafeCharacters($resourcePath) || $this->containsUnsafeCharacters($decoded)) {
            throw DatafastOperationException::invalidResourcePath();
        }

        if (
            str_contains($resourcePath, '://')
            || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $resourcePath) === 1
            || str_starts_with($resourcePath, '//')
            || str_contains($resourcePath, '?')
            || str_contains($resourcePath, '#')
            || str_contains($resourcePath, '@')
            || str_contains($resourcePath, '\\')
            || str_contains($decoded, '\\')
            || str_contains($decoded, '..')
        ) {
            throw DatafastOperationException::invalidResourcePath();
        }

        $expected = "/v1/checkouts/{$checkoutId}/payment";

        if (! hash_equals($expected, $resourcePath)) {
            throw DatafastOperationException::invalidResourcePath();
        }

        return $resourcePath;
    }

    public function isValidCheckoutId(string $checkoutId): bool
    {
        return preg_match('/\A[A-Za-z0-9._-]{6,128}\z/', $checkoutId) === 1;
    }

    private function containsUnsafeCharacters(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }
}
