<?php

namespace App\DTOs;

final class DatafastPaymentResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $status,
        public readonly string $merchantTransactionId,
        public readonly string $checkoutId,
        public readonly ?string $providerPaymentId = null,
        public readonly ?string $resultCode = null,
        public readonly ?string $resultDescription = null,
        public readonly ?string $amount = null,
        public readonly ?string $currency = null,
        public readonly array $payload = [],
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
