<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_PAID = 'paid';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELED = 'canceled';

    public const DELIVERY_STORE_PICKUP = 'store_pickup';

    public const DELIVERY_HOME = 'home_delivery';

    protected $fillable = [
        'user_id',
        'reference',
        'checkout_idempotency_key',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_identification',
        'delivery_method',
        'province',
        'city',
        'address',
        'billing_province',
        'billing_city',
        'billing_address',
        'delivery_reference',
        'notes',
        'subtotal',
        'shipping_cost',
        'tax_base_zero',
        'tax_base_taxable',
        'tax_amount',
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
            'tax_base_zero' => 'decimal:2',
            'tax_base_taxable' => 'decimal:2',
            'tax_amount' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    public function isValidating(): bool
    {
        return $this->status === self::STATUS_VALIDATING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPayable(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
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
