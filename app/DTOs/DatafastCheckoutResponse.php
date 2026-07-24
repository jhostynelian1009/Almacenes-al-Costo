<?php

namespace App\DTOs;

final class DatafastCheckoutResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $checkoutId,
        public readonly string $merchantTransactionId,
        public readonly string $resultCode,
        public readonly ?string $resultDescription = null,
        public readonly array $payload = [],
    ) {}
}
