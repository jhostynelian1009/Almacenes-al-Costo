<?php

namespace App\Models;

use App\Exceptions\InventoryOperationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    public const TYPE_ENTRY = 'entry';

    public const TYPE_EXIT = 'exit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_RESERVE = 'reserve';

    public const TYPE_RELEASE = 'release';

    public const TYPES = [
        self::TYPE_ENTRY,
        self::TYPE_EXIT,
        self::TYPE_ADJUSTMENT,
        self::TYPE_RESERVE,
        self::TYPE_RELEASE,
    ];

    protected $fillable = [
        'inventory_id',
        'type',
        'stock_delta',
        'reserved_delta',
        'stock_before',
        'stock_after',
        'reserved_before',
        'reserved_after',
        'reason',
        'created_by',
        'reference_type',
        'reference_id',
        'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw InventoryOperationException::immutableMovement());
        static::deleting(fn (): never => throw InventoryOperationException::immutableMovement());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_delta' => 'integer',
            'reserved_delta' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'reserved_before' => 'integer',
            'reserved_after' => 'integer',
            'reference_id' => 'integer',
        ];
    }

    public static function validTypes(): array
    {
        return self::TYPES;
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
