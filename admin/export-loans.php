<?php
/**
 * Export filtered loans as a CSV file.
 * Uses the same query parameters as admin/dashboard.php.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loans.php';

require_admin();

$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$search = mb_substr($search, 0, 100);

$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';
if (!in_array($status, ['all', 'active', 'overdue', 'cleared'], true)) {
    $status = 'all';
}

$lastSent = is_string($_GET['last_sent'] ?? null) ? $_GET['last_sent'] : 'all';
if (!in_array($lastSent, ['all', 'sent', 'never'], true)) {
    $lastSent = 'all';
}

$dueFrom = is_string($_GET['due_from'] ?? null) ? $_GET['due_from'] : '';
$dueTo = is_string($_GET['due_to'] ?? null) ? $_GET['due_to'] : '';
if (!valid_export_date($dueFrom)) {
    $dueFrom = '';
}
if (!valid_export_date($dueTo)) {
    $dueTo = '';
}

$loans = get_loans_for_export($pdo, [
    'search' => $search,
    'status' => $status,
    'last_sent' => $lastSent,
    'due_from' => $dueFrom,
    'due_to' => $dueTo,
]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="chuma-loans-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Loan No.',
    'Member No.',
    'Member Name',
    'Phone',
    'Balance',
    'Due Date',
    'Status',
    'Last Sent At',
    'Last Sent Via',
]);

foreach ($loans as $loan) {
    fputcsv($output, [
        $loan['loan_no'],
        $loan['member_no'],
        $loan['full_name'],
        $loan['phone'],
        $loan['balance'],
        $loan['due_date'],
        $loan['status'],
        $loan['last_sent_at'] ?? '',
        $loan['last_sent_via'] ?? '',
    ]);
}

fclose($output);

function valid_export_date(string $value): bool
{
    if ($value === '') {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}
