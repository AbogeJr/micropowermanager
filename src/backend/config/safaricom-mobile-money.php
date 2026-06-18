<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Safaricom M-PESA (Daraja v3) configuration
    |--------------------------------------------------------------------------
    |
    | Per-environment base URLs and the well-known sandbox test values
    | Daraja publishes for STK Push. Sandbox uses a shared shortcode/passkey
    | so operators can test without having a production paybill yet; for
    | production the operator must supply their own values via the
    | credentials form.
    |
    | Sandbox defaults source:
    |   https://developer.safaricom.co.ke/Documentation -> M-Pesa Express
    |
    */

    'api' => [
        'sandbox_url' => env('SAFARICOM_SANDBOX_URL', 'https://sandbox.safaricom.co.ke'),
        'production_url' => env('SAFARICOM_PRODUCTION_URL', 'https://api.safaricom.co.ke'),
    ],

    'sandbox' => [
        'shortcode' => env('SAFARICOM_SANDBOX_SHORTCODE', '174379'),
        'passkey' => env('SAFARICOM_SANDBOX_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),
    ],

    'transaction' => [
        'timeout' => env('SAFARICOM_TRANSACTION_TIMEOUT', 60),
        'max_amount' => env('SAFARICOM_MAX_AMOUNT', 150000),
        'min_amount' => env('SAFARICOM_MIN_AMOUNT', 1),
    ],
];
