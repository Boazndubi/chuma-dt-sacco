<?php
/**
 * Sends an SMS via Africa's Talking. Returns ['ok' => bool, 'error' => ?string].
 * Never throws — the caller (admin/send-link.php) decides how to report failure.
 */

require_once __DIR__ . '/../config/africastalking.php';

function send_sms(string $phone, string $message): array
{
    $cfg = at_config();

    if (!$cfg['api_key']) {
        return ['ok' => false, 'error' => 'AT_API_KEY is not set in .env'];
    }

    $payload = [
        'username' => $cfg['username'],
        'to'       => $phone,          // expects 2547XXXXXXXX
        'message'  => $message,
    ];
    if ($cfg['sender_id'] !== '') {
        $payload['from'] = $cfg['sender_id'];
    }

    $ch = curl_init($cfg['base_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_HTTPHEADER     => [
            'apiKey: ' . $cfg['api_key'],
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[sms] curl error: ' . $curlError);
        return ['ok' => false, 'error' => 'Network error contacting Africa\'s Talking.'];
    }

    $data = json_decode($response, true);
    $recipients = $data['SMSMessageData']['Recipients'] ?? [];
    $first = $recipients[0] ?? null;

    // Africa's Talking returns HTTP 200/201 with per-recipient status codes even on failure.
    if ($httpCode >= 200 && $httpCode < 300 && $first && (int) ($first['statusCode'] ?? 0) === 101) {
        return ['ok' => true, 'error' => null];
    }

    $errorMsg = $first['status'] ?? ($data['SMSMessageData']['Message'] ?? 'Unknown SMS gateway error.');
    error_log('[sms] failed: ' . json_encode($data));
    return ['ok' => false, 'error' => $errorMsg];
}
