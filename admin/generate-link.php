<?php
/**
 * CLI fallback, useful for testing without the admin UI:
 *   php admin/generate-link.php LN-2026-0091
 *
 * The admin dashboard's "Send" buttons cover normal day-to-day use now —
 * this stays around for scripting/testing.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/env.php';

$loanNo = $argv[1] ?? null;

if (!$loanNo) {
    fwrite(STDERR, "Usage: php admin/generate-link.php <loan_no>\n");
    exit(1);
}

$stmt = $pdo->prepare("SELECT id FROM loans WHERE loan_no = :loan_no");
$stmt->execute(['loan_no' => $loanNo]);
$loan = $stmt->fetch();

if (!$loan) {
    fwrite(STDERR, "No loan found with number {$loanNo}\n");
    exit(1);
}

$token = generate_payment_token();

$insert = $pdo->prepare(
    "INSERT INTO payment_links (token, loan_id, expires_at) VALUES (:token, :loan_id, :expires_at)"
);
$insert->execute([
    'token'      => $token,
    'loan_id'    => $loan['id'],
    'expires_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
]);

$baseUrl = env('APP_BASE_URL', 'https://chunadtsacco.co.ke');
echo "Payment link created:\n{$baseUrl}/pay.php?token={$token}\n";
