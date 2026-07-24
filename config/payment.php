<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Métodos de pago habilitados
    |--------------------------------------------------------------------------
    | 'transfer' => Transferencia bancaria manual (comprobante)
    | 'deuna'    => Pago con QR Deuna
    */
    'enabled_methods' => array_filter(
        explode(',', (string) env('PAYMENT_ENABLED_METHODS', 'transfer,deuna'))
    ),

    /*
    |--------------------------------------------------------------------------
    | Datos bancarios para transferencia manual
    | Solo se muestran al cliente — nunca en logs
    |--------------------------------------------------------------------------
    */
    'bank' => [
        'name' => env('PAYMENT_BANK_NAME', ''),
        'account_holder' => env('PAYMENT_BANK_ACCOUNT_HOLDER', ''),
        'account_number' => env('PAYMENT_BANK_ACCOUNT_NUMBER', ''),
        'account_type' => env('PAYMENT_BANK_ACCOUNT_TYPE', ''),
        'identification' => env('PAYMENT_BANK_IDENTIFICATION', ''),
        'reference_instructions' => env('PAYMENT_BANK_REFERENCE_INSTRUCTIONS', 'Usar el número de pedido como referencia.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deuna — configuración del adaptador
    | La integración live está PENDIENTE: sin contrato oficial en el SPEC.
    | En tests se usa Http::fake(). No se realizan llamadas reales.
    |--------------------------------------------------------------------------
    */
    'deuna' => [
        'base_url' => env('DEUNA_BASE_URL', ''),
        'merchant_id' => env('DEUNA_MERCHANT_ID', ''),
        'api_key' => env('DEUNA_API_KEY', ''),
        'webhook_secret' => env('DEUNA_WEBHOOK_SECRET', ''),
        'timeout' => (int) env('DEUNA_TIMEOUT', 30),
        'qr_image_url' => env('DEUNA_QR_IMAGE_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tolerancia de timestamp para webhooks (segundos)
    | Los webhooks con timestamp mayor a este valor se descartan silenciosamente.
    |--------------------------------------------------------------------------
    */
    'webhook_timestamp_tolerance' => (int) env('PAYMENT_WEBHOOK_TIMESTAMP_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Límite de tamaño de comprobante (bytes) — 4 MB
    |--------------------------------------------------------------------------
    */
    'receipt_max_size_bytes' => (int) env('PAYMENT_RECEIPT_MAX_SIZE_BYTES', 4 * 1024 * 1024),
];
