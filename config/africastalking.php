<?php
/**
 * Africa's Talking SMS configuration.
 * Get credentials from https://account.africastalking.com (sandbox app
 * works for testing without paying for real SMS credits).
 */

require_once __DIR__ . '/env.php';

function at_config(): array
{
    return [
        'username'  => env('AT_USERNAME', 'sandbox'), // 'sandbox' for testing
        'api_key'   => env('AT_API_KEY'),
        'sender_id' => env('AT_SENDER_ID', ''),        // optional registered short/alphanumeric code
        'base_url'  => env('AT_USERNAME', 'sandbox') === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging',
    ];
}
