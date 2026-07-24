<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_DEUNA = 'deuna';

    protected $fillable = [
        'order_id',
        'payment_id',
        'file_path',
        'original_filename',
        'payment_method',
        'transaction_reference',
        'rejection_reason',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
