<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    public const GATEWAY_MANUAL = 'manual';

    public const GATEWAY_DEUNA = 'deuna';

    public const GATEWAY_DATAFAST = 'datafast';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_DEUNA = 'deuna';

    public const METHOD_CARD = 'card';

    protected $fillable = [
        'order_id',
        'gateway',
        'payment_method',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'gateway_response_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_REFUNDED,
        ], true);
    }
}
