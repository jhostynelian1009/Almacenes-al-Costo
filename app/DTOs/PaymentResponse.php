<?php

namespace App\DTOs;

final class PaymentResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    public static function pending(
        ?string $transactionId = null,
        ?string $message = null,
        array $payload = [],
    ): self {
        return new self(
            status: 'pending',
            transactionId: $transactionId,
            message: $message,
            payload: $payload,
        );
    }

    public static function completed(
        ?string $transactionId = null,
        ?string $message = null,
        array $payload = [],
    ): self {
        return new self(
            status: 'completed',
            transactionId: $transactionId,
            message: $message,
            payload: $payload,
        );
    }

    public static function failed(
        ?string $message = null,
        array $payload = [],
    ): self {
        return new self(
            status: 'failed',
            message: $message,
            payload: $payload,
        );
    }

    public static function withRedirect(
        string $redirectUrl,
        ?string $transactionId = null,
        ?string $message = null,
        array $payload = [],
    ): self {
        return new self(
            status: 'processing',
            transactionId: $transactionId,
            redirectUrl: $redirectUrl,
            message: $message,
            payload: $payload,
        );
    }
}
