<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const DELIVERY_STORE_PICKUP = 'store_pickup';

    public const DELIVERY_HOME = 'home_delivery';

    protected $fillable = [
        'user_id',
        'reference',
        'checkout_idempotency_key',
        'customer_name',
        'customer_email',
        'customer_phone',
        'delivery_method',
        'province',
        'city',
        'address',
        'delivery_reference',
        'notes',
        'subtotal',
        'shipping_cost',
        'total',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function isHomeDelivery(): bool
    {
        return $this->delivery_method === self::DELIVERY_HOME;
    }

    public function isStorePickup(): bool
    {
        return $this->delivery_method === self::DELIVERY_STORE_PICKUP;
    }
}
