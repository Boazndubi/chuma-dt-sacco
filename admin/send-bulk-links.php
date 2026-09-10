<?php
/**
 * POST admin/send-bulk-links.php   { loan_ids: [12,45,...], channel: 'sms'|'whatsapp'|'both', csrf_token }
 * Sends a reminder to every loan_id in the list, using the same per-loan
 * logic as the single "Send" button.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reminders.php';

$admin = require_admin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verify_csrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session, please refresh the page and try again.']);
    exit;
}

$loanIds = array_filter(array_map('intval', $input['loan_ids'] ?? []));
$channel = $input['channel'] ?? '';

if (empty($loanIds) || !in_array($channel, ['sms', 'whatsapp', 'both'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing or invalid loan_ids/channel.']);
    exit;
}

// Hard cap so a stray "select all" on a huge filtered list can't fire
// hundreds of provider calls in one request.
if (count($loanIds) > 200) {
    http_response_code(422);
    echo json_encode(['error' => 'Too many recipients in one batch (max 200). Narrow your filter and try again.']);
    exit;
}

$results = [];
$sentCount = 0;
$failedCount = 0;

foreach ($loanIds as $loanId) {
    $loan = get_loan_by_id($pdo, $loanId);
    if (!$loan) {
        $results[] = ['loan_id' => $loanId, 'ok' => false, 'error' => 'Loan not found.'];
        $failedCount++;
        continue;
    }

    $result = send_loan_reminder($pdo, $loan, $channel, $admin['id']);

    $results[] = [
        'loan_id'  => $loanId,
        'ok'       => $result['ok'],
        'sent_via' => $result['sent_via'],
        'error'    => $result['ok'] ? null : implode('; ', $result['errors']),
    ];

    $result['ok'] ? $sentCount++ : $failedCount++;
}

echo json_encode([
    'ok'      => $sentCount > 0,
    'sent'    => $sentCount,
    'failed'  => $failedCount,
    'results' => $results,
]);