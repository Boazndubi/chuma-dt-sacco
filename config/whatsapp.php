<?php
/**
 * Meta WhatsApp Cloud API configuration.
 * Get these from https://developers.facebook.com -> your app -> WhatsApp -> API Setup:
 *   - WHATSAPP_PHONE_NUMBER_ID: the "Phone number ID" (not the phone number itself)
 *   - WHATSAPP_TOKEN: a permanent access token (system user token, not the 24h temp one)
 *   - WHATSAPP_TEMPLATE_NAME: an approved message template name for the first contact
 *     (WhatsApp requires an approved template to message someone outside a 24h window —
 *     you cannot just send free text as the very first message)
 */

require_once __DIR__ . '/env.php';

function whatsapp_config(): array
{
    return [
        'token'           => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template_name'   => env('WHATSAPP_TEMPLATE_NAME', 'loan_payment_reminder'),
        'template_lang'   => env('WHATSAPP_TEMPLATE_LANG', 'en'),
        'api_version'     => env('WHATSAPP_API_VERSION', 'v20.0'),
    ];
}
