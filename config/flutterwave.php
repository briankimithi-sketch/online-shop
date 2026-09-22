<?php

use Flutterwave\Payments\Services\Modal;
use Flutterwave\Payments\Services\Transactions;

return [
    'publicKey' => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secretKey' => env('FLUTTERWAVE_SECRET_KEY'),
    'encryptionKey' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    'secretHash' => env('FLUTTERWAVE_SECRET_HASH'),
    'redirectUrl' => env('FLUTTERWAVE_REDIRECT_URL'),
    'successUrl' => env('FLUTTERWAVE_SUCCESS_URL'),
    'cancelUrl' => env('FLUTTERWAVE_CANCEL_URL'),
    'webhookUrl' => env('FLUTTERWAVE_WEBHOOK_URL'),
    'title' => env('FLUTTERWAVE_TITLE', 'Online Shop'),
    'description' => env('FLUTTERWAVE_DESCRIPTION', 'Payment for your order'),
    'logo' => env('FLUTTERWAVE_LOGO', ''),
    'country' => env('FLUTTERWAVE_COUNTRY', 'KE'),
    'currency' => env('FLUTTERWAVE_CURRENCY', 'KES'),
    'paymentType' => ['card', 'mobilemoney', 'ussd', 'banktransfer', 'qr'],
    'transactionPrefix' => env('FLUTTERWAVE_TRANSACTION_PREFIX', 'OS_'),
    'env' => env('FLUTTERWAVE_ENV', 'staging'),
    'businessName' => env('FLUTTERWAVE_BUSINESS_NAME', 'Online Shop'),
    'services' => [
        'modals' => Modal::class,
        'transactions' => Transactions::class,
    ],
];
