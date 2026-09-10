<?php
/**
 * Shared logic for sending a payment reminder for one loan, used by both
 * the single-row "Send" button and the bulk "Send to selected" action.
 */

require_once __DIR__ . '/loans.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/whatsapp.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/env.php';

/**
 * Sends a reminder for a single loan via the given channel(s) and records
 * the send if at least one channel succeeded.
 *
 * Returns:
 *   ok       bool    true if at least one channel succeeded
 *   partial  bool    true if 'both' was requested but only one channel went through
 *   sent_via ?string which channel(s) actually succeeded
 *   errors   array   human-readable errors for any channel that failed
 *   pay_url  string  always returned, even on failure, for manual testing
 */
function send_loan_reminder(PDO $pdo, array $loan, string $channel, int $adminId): array
{
    $loanId = (int) $loan['loan_id'];

    $phone = normalize_phone($loan['phone']);
    if (!$phone) {
        return [
            'ok' => false, 'partial' => false, 'sent_via' => null,
            'errors' => ['This member has no valid phone number on file.'],
            'pay_url' => null,
        ];
    }

    $link = get_or_create_payment_link($pdo, $loanId);
    $baseUrl = env('APP_BASE_URL', 'https://chunadtsacco.co.ke');
    $payUrl = "{$baseUrl}/pay.php?token={$link['token']}";

    $firstName = strtok($loan['full_name'], ' ');
    $balance = format_money((float) $loan['balance']);
    $dueDate = date('j M Y', strtotime($loan['due_date']));

    $smsMessage = "Hi {$firstName}, your Chuna DT Sacco loan {$loan['loan_no']} has a balance of "
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

    $sentVia = null;
    if ($anySucceeded) {
        $succeededChannels = array_keys(array_filter($results, fn($r) => $r['ok']));
        $sentVia = (count($succeededChannels) === 2) ? 'both' : $succeededChannels[0];
        mark_payment_link_sent($pdo, $link['id'], $sentVia, $adminId);
    }

    return [
        'ok'      => $anySucceeded,
        'partial' => $anySucceeded && !empty($errors),
        'sent_via'=> $sentVia,
        'errors'  => $errors,
        'pay_url' => $payUrl,
    ];
}
