<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Métodos de pago habilitados
    |--------------------------------------------------------------------------
    | 'transfer' => Transferencia bancaria manual (comprobante)
    | 'deuna'    => Pago con QR Deuna
    | 'datafast' => Datafast Dataweb para tarjetas
    */
    'enabled_methods' => array_filter(
        explode(',', (string) env('PAYMENT_ENABLED_METHODS', 'transfer,deuna,datafast'))
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
    | Datafast Dataweb
    |--------------------------------------------------------------------------
    | Deshabilitado por defecto. No se deben configurar credenciales reales
    | hasta contar con activacion Dataweb, credenciales de sandbox y
    | certificacion Datafast.
    */
    'datafast' => [
        'enabled' => (bool) env('DATAFAST_ENABLED', false),
        'environment' => env('DATAFAST_ENVIRONMENT', 'sandbox'),
        'base_url' => env('DATAFAST_BASE_URL', ''),
        'widget_url' => env('DATAFAST_WIDGET_URL', ''),
        'entity_id' => env('DATAFAST_ENTITY_ID', ''),
        'authorization' => env('DATAFAST_AUTHORIZATION', ''),
        'mid' => env('DATAFAST_MID', ''),
        'tid' => env('DATAFAST_TID', ''),
        'eci' => env('DATAFAST_ECI', ''),
        'pserv' => env('DATAFAST_PSERV', ''),
        'risk_name' => env('DATAFAST_RISK_NAME', ''),
        'version' => env('DATAFAST_VERSION', '2'),
        'currency' => 'USD',
        'payment_type' => 'DB',
        'test_mode' => env('DATAFAST_TEST_MODE', 'EXTERNAL'),
        'brands' => env('DATAFAST_BRANDS', 'VISA MASTER AMEX DISCOVER'),
        'connect_timeout' => (int) env('DATAFAST_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('DATAFAST_TIMEOUT', 15),
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
