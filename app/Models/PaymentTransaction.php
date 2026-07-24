<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_REQUEST = 'request';

    public const EVENT_RESPONSE = 'response';

    public const EVENT_WEBHOOK = 'webhook_received';

    public const EVENT_CALLBACK = 'callback_received';

    public const EVENT_ERROR = 'error';

    protected $fillable = [
        'payment_id',
        'event_type',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
