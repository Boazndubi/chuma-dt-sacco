<?php
/**
 * POST admin/send-link.php   { loan_id, channel: 'sms'|'whatsapp'|'both', csrf_token }
 * Called via fetch() from the dashboard's "Send" buttons.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loans.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../config/env.php';

$admin = require_admin(); // redirects to login if not authed; fine to leave as-is for an AJAX 302 too,
                           // since the JS treats any non-JSON response as an error.

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verify_csrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session, please refresh the page and try again.']);
    exit;
}

$loanId  = (int) ($input['loan_id'] ?? 0);
$channel = $input['channel'] ?? '';

if (!$loanId || !in_array($channel, ['sms', 'whatsapp', 'both'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing or invalid loan_id/channel.']);
    exit;
}

$loan = get_loan_by_id($pdo, $loanId);
if (!$loan) {
    http_response_code(404);
    echo json_encode(['error' => 'Loan not found.']);
    exit;
}

$phone = normalize_phone($loan['phone']);
if (!$phone) {
    http_response_code(422);
    echo json_encode(['error' => 'This member has no valid phone number on file.']);
    exit;
}

$link = get_or_create_payment_link($pdo, $loanId);
$baseUrl = env('APP_BASE_URL', 'https://chunadtsacco.co.ke');
$payUrl = "{$baseUrl}/pay.php?token={$link['token']}";

$firstName = strtok($loan['full_name'], ' '); // first name reads friendlier in a text than the full name
$balance = format_money((float) $loan['balance']);
$dueDate = date('j M Y', strtotime($loan['due_date']));

$smsMessage = "Hi {$firstName}, your Chuma DT Sacco loan {$loan['loan_no']} has a balance of "
            . "KSh {$balance}, due {$dueDate}. Pay now: {$payUrl}";

$results = [];

if ($channel === 'sms' || $channel === 'both') {
    $results['sms'] = send_sms($phone, $smsMessage);
}

if ($channel === 'whatsapp' || $channel === 'both') {
    $results['whatsapp'] = send_whatsapp($phone, [$firstName, $loan['loan_no'], $balance, $dueDate, $payUrl]);
}

$anySucceeded = false;
$errors = [];
foreach ($results as $via => $result) {
    if ($result['ok']) {
        $anySucceeded = true;
    } else {
        $errors[] = ucfirst($via) . ': ' . $result['error'];
    }
}

if ($anySucceeded) {
    // Record what actually went out — if "both" was requested but only one
    // channel succeeded, record the one that worked, not the request.
    $sentVia = ($channel === 'both' && count(array_filter($results, fn($r) => $r['ok'])) === 2)
        ? 'both'
        : array_key_first(array_filter($results, fn($r) => $r['ok']));
    mark_payment_link_sent($pdo, $link['id'], $sentVia, $admin['id']);
}

// pay_url always comes back, even on failure — lets you copy the link and
// test pay.php by hand while SMS/WhatsApp credentials aren't wired up yet.
if (empty($errors)) {
    echo json_encode(['ok' => true, 'sent_via' => $sentVia ?? $channel, 'pay_url' => $payUrl]);
} elseif ($anySucceeded) {
    http_response_code(207); // partial success, e.g. "both" requested but only SMS went through
    echo json_encode(['ok' => true, 'partial' => true, 'errors' => $errors, 'pay_url' => $payUrl]);
} else {
    http_response_code(502);
    echo json_encode(['ok' => false, 'errors' => $errors, 'pay_url' => $payUrl]);
}
