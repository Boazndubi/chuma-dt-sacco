<?php
/**
 * Safaricom Daraja (M-Pesa) configuration.
 */

require_once __DIR__ . '/env.php';

function mpesa_config(): array
{
    $env = env('MPESA_ENV', 'sandbox');
    $callbackUrl = env('MPESA_CALLBACK_URL');
    $callbackSecret = env('MPESA_CALLBACK_SECRET');

    // Append the shared secret as a query param on the callback URL Daraja
    // is told to hit. Without this, anyone who finds the callback URL could
    // POST a fake "payment succeeded" body and mark a loan as paid for free —
    // Safaricom doesn't sign these requests by default.
    if ($callbackUrl && $callbackSecret) {
        $separator = str_contains($callbackUrl, '?') ? '&' : '?';
        $callbackUrl .= $separator . 'key=' . urlencode($callbackSecret);
    }

    return [
        'env'             => $env,
        'base_url'        => $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke',
        'consumer_key'    => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode'       => env('MPESA_SHORTCODE'),
        'passkey'         => env('MPESA_PASSKEY'),
        'callback_url'    => $callbackUrl,
        'callback_secret' => $callbackSecret,
    ];
}
