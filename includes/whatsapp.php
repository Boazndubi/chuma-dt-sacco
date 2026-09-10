<?php
/**
 * Sends a WhatsApp message via Meta's Cloud API. Returns ['ok' => bool, 'error' => ?string].
 *
 * Uses an approved template message rather than free text: WhatsApp requires
 * a pre-approved template for any message that starts a conversation (i.e. the
 * member hasn't messaged the Sacco's WhatsApp number in the last 24h). Set up
 * a template like:
 *
 *   Name: loan_payment_reminder
 *   Body: Hello {{1}}, your Chuma DT Sacco loan {{2}} has a balance of KSh {{3}},
 *         due {{4}}. Pay now: {{5}}
 *
 * and approve it in Meta Business Manager before this will send successfully.
 */

require_once __DIR__ . '/../config/whatsapp.php';

/**
 * @param array $templateParams Ordered list of values to fill {{1}}, {{2}}, ... in the template.
 */
function send_whatsapp(string $phone, array $templateParams): array
{
    $cfg = whatsapp_config();

    if (!$cfg['token'] || !$cfg['phone_number_id']) {
        return ['ok' => false, 'error' => 'WHATSAPP_TOKEN or WHATSAPP_PHONE_NUMBER_ID is not set in .env'];
    }

    $url = "https://graph.facebook.com/{$cfg['api_version']}/{$cfg['phone_number_id']}/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $phone, // expects 2547XXXXXXXX, no leading +
        'type'              => 'template',
        'template'          => [
            'name'     => $cfg['template_name'],
            'language' => ['code' => $cfg['template_lang']],
            'components' => [[
                'type'       => 'body',
                'parameters' => array_map(
                    fn($value) => ['type' => 'text', 'text' => (string) $value],
                    $templateParams
                ),
            ]],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[whatsapp] curl error: ' . $curlError);
        return ['ok' => false, 'error' => 'Network error contacting WhatsApp.'];
    }

    $data = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($data['messages'][0]['id'])) {
        return ['ok' => true, 'error' => null];
    }

    $errorMsg = $data['error']['message'] ?? 'Unknown WhatsApp API error.';
    error_log('[whatsapp] failed: ' . json_encode($data));
    return ['ok' => false, 'error' => $errorMsg];
}
