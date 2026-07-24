<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'gateway',
        'event_type',
        'event_id',
        'payload',
        'signature_verified',
        'processed',
        'processing_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_verified' => 'boolean',
            'processed' => 'boolean',
        ];
    }
}
