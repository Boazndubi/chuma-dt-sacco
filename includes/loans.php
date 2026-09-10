<?php
/**
 * Queries used by the admin dashboard: listing loans+members, and
 * getting-or-creating a payment link for a loan.
 */

require_once __DIR__ . '/functions.php';

/**
 * All loans with their member info, filterable by search text and status,
 * sortable, and paginated. Returns ['loans' => [...], 'total' => int].
 *
 * $options:
 *   search   string  free-text match on name/loan_no/member_no/phone
 *   status   string  'all' | 'active' | 'overdue' | 'cleared'
 *   last_sent string  'all' | 'sent' | 'never'
 *   due_from string   inclusive due date lower bound, YYYY-MM-DD
 *   due_to   string   inclusive due date upper bound, YYYY-MM-DD
 *   sort     string  one of: full_name, balance, due_date, status, last_sent_at
 *   dir      string  'ASC' | 'DESC'
 *   page     int
 *   per_page int
 */
function get_loans_for_admin(PDO $pdo, array $options = []): array
{
    $search = trim($options['search'] ?? '');
    $status = $options['status'] ?? 'all';
    $lastSent = $options['last_sent'] ?? 'all';
    $dueFrom = $options['due_from'] ?? '';
    $dueTo = $options['due_to'] ?? '';

    $sortColumnMap = [
        'full_name'    => 'members.full_name',
        'balance'      => 'loans.balance',
        'due_date'     => 'loans.due_date',
        'status'       => 'loans.status',
        'last_sent_at' => 'last_sent_at',
    ];
    $sortColumn = $sortColumnMap[$options['sort'] ?? 'due_date'] ?? 'loans.due_date';
    $sortDir = strtoupper($options['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

    $perPage = max(1, (int) ($options['per_page'] ?? 25));
    $page = max(1, (int) ($options['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];

    $validStatuses = ['active', 'overdue', 'cleared'];
    if (in_array($status, $validStatuses, true)) {
        $where[] = "loans.status = :status";
        $params['status'] = $status;
    }

    if ($lastSent === 'sent') {
        $where[] = "(SELECT latest_link.sent_at FROM payment_links latest_link
                     WHERE latest_link.loan_id = loans.id
                     ORDER BY latest_link.created_at DESC LIMIT 1) IS NOT NULL";
    } elseif ($lastSent === 'never') {
        $where[] = "(SELECT latest_link.sent_at FROM payment_links latest_link
                     WHERE latest_link.loan_id = loans.id
                     ORDER BY latest_link.created_at DESC LIMIT 1) IS NULL";
    }

    if ($dueFrom !== '') {
        $where[] = 'loans.due_date >= :due_from';
        $params['due_from'] = $dueFrom;
    }
    if ($dueTo !== '') {
        $where[] = 'loans.due_date <= :due_to';
        $params['due_to'] = $dueTo;
    }
    // status === 'all' -> no filter, show every status

    if ($search !== '') {
        $where[] = "(members.full_name ILIKE :q OR loans.loan_no ILIKE :q
                     OR members.member_no ILIKE :q OR members.phone ILIKE :q)";
        $params['q'] = "%{$search}%";
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $fromSql  = "FROM loans JOIN members ON members.id = loans.member_id {$whereSql}";

    // Total count (for pagination)
    $countStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    // Page of results
    $sql = "SELECT
                loans.id AS loan_id, loans.loan_no, loans.balance, loans.due_date, loans.status,
                members.full_name, members.member_no, members.phone,
                (SELECT pl.sent_at FROM payment_links pl
                 WHERE pl.loan_id = loans.id ORDER BY pl.created_at DESC LIMIT 1) AS last_sent_at,
                (SELECT pl.sent_via FROM payment_links pl
                 WHERE pl.loan_id = loans.id ORDER BY pl.created_at DESC LIMIT 1) AS last_sent_via
            {$fromSql}
            ORDER BY {$sortColumn} {$sortDir}
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'loans' => $stmt->fetchAll(),
        'total' => $total,
    ];
}

/**
 * Reuses a still-valid (unexpired) payment link for this loan if one exists,
 * otherwise creates a fresh one. Keeps us from generating a new token — and
 * invalidating the old one — every single time an admin clicks "send".
 */
function get_or_create_payment_link(PDO $pdo, int $loanId, int $expiryDays = 14): array
{
    $stmt = $pdo->prepare(
        "SELECT id, token FROM payment_links
         WHERE loan_id = :loan_id AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute(['loan_id' => $loanId]);
    $existing = $stmt->fetch();

    if ($existing) {
        return $existing;
    }

    $token = generate_payment_token();
    $insert = $pdo->prepare(
        "INSERT INTO payment_links (token, loan_id, expires_at) VALUES (:token, :loan_id, :expires_at)"
    );
    $insert->execute([
        'token'      => $token,
        'loan_id'    => $loanId,
        'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiryDays} days")),
    ]);

    return ['id' => (int) $pdo->lastInsertId(), 'token' => $token];
}

function mark_payment_link_sent(PDO $pdo, int $paymentLinkId, string $via, int $adminId): void
{
    $stmt = $pdo->prepare(
        "UPDATE payment_links SET sent_via = :via, sent_at = NOW(), sent_by = :admin_id WHERE id = :id"
    );
    $stmt->execute(['via' => $via, 'admin_id' => $adminId, 'id' => $paymentLinkId]);
}

/** Full loan+member row by loan_id, for building the SMS/WhatsApp message. */
function get_loan_by_id(PDO $pdo, int $loanId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT loans.id AS loan_id, loans.loan_no, loans.balance, loans.due_date,
                members.full_name, members.phone
         FROM loans JOIN members ON members.id = loans.member_id
         WHERE loans.id = :id"
    );
    $stmt->execute(['id' => $loanId]);
    $row = $stmt->fetch();
    return $row ?: null;
}