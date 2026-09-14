<?php
/**
 * Queries used by the admin dashboard: listing loans+members (with search,
 * filters, sorting, and pagination), and getting-or-creating a payment link.
 */

require_once __DIR__ . '/functions.php';

/** Builds the WHERE clause + bound params shared by the dashboard list, the
 *  CSV export, and the summary cards, so filtering logic can't drift between them. */
function build_loan_filter(array $options): array
{
    $search   = $options['search'] ?? '';
    $status   = $options['status'] ?? 'all';
    $lastSent = $options['last_sent'] ?? 'all';
    $dueFrom  = $options['due_from'] ?? '';
    $dueTo    = $options['due_to'] ?? '';

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = "(members.full_name ILIKE :q OR loans.loan_no ILIKE :q
                      OR members.member_no ILIKE :q OR members.phone ILIKE :q)";
        $params['q'] = "%{$search}%";
    }

    if (in_array($status, ['active', 'overdue', 'cleared'], true)) {
        $where[] = 'loans.status = :status';
        $params['status'] = $status;
    }

    if ($lastSent === 'sent') {
        $where[] = 'EXISTS (SELECT 1 FROM payment_links pl WHERE pl.loan_id = loans.id AND pl.sent_at IS NOT NULL)';
    } elseif ($lastSent === 'never') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM payment_links pl WHERE pl.loan_id = loans.id AND pl.sent_at IS NOT NULL)';
    }

    if ($dueFrom !== '') {
        $where[] = 'loans.due_date >= :due_from';
        $params['due_from'] = $dueFrom;
    }
    if ($dueTo !== '') {
        $where[] = 'loans.due_date <= :due_to';
        $params['due_to'] = $dueTo;
    }

    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

const LOAN_LIST_SELECT = "
    loans.id AS loan_id, loans.loan_no, loans.balance, loans.due_date, loans.status,
    members.full_name, members.member_no, members.phone,
    (SELECT pl.sent_at FROM payment_links pl
     WHERE pl.loan_id = loans.id ORDER BY pl.created_at DESC LIMIT 1) AS last_sent_at,
    (SELECT pl.sent_via FROM payment_links pl
     WHERE pl.loan_id = loans.id ORDER BY pl.created_at DESC LIMIT 1) AS last_sent_via
";

/**
 * Options:
 *   search    string  matched against name/loan_no/member_no/phone
 *   status    'all'|'active'|'overdue'|'cleared'
 *   last_sent 'all'|'sent'|'never'
 *   due_from  'YYYY-MM-DD' or ''
 *   due_to    'YYYY-MM-DD' or ''
 *   sort      one of: full_name, balance, due_date, status, last_sent_at
 *   dir       'asc'|'desc'
 *   page      1-based page number
 *   per_page  rows per page
 *
 * Returns ['loans' => [...], 'total' => int].
 */
function get_loans_for_admin(PDO $pdo, array $options): array
{
    $sort     = $options['sort'] ?? 'due_date';
    $dir      = strtolower($options['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    $page     = max(1, (int) ($options['page'] ?? 1));
    $perPage  = max(1, min(100, (int) ($options['per_page'] ?? 25)));

    // Defensive whitelist even though the caller already validates this —
    // $sort/$dir get interpolated directly into the query below, so they
    // must never come from unvalidated input.
    $sortColumns = [
        'full_name'    => 'members.full_name',
        'balance'      => 'loans.balance',
        'due_date'     => 'loans.due_date',
        'status'       => 'loans.status',
        'last_sent_at' => 'last_sent_at',
    ];
    $orderBy = $sortColumns[$sort] ?? 'loans.due_date';

    $filter = build_loan_filter($options);
    $whereSql = $filter['sql'];
    $params = $filter['params'];

    // Total count for pagination, against the same filters.
    $countSql = "SELECT COUNT(*) FROM loans JOIN members ON members.id = loans.member_id WHERE {$whereSql}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;

    $sql = "SELECT " . LOAN_LIST_SELECT . "
            FROM loans
            JOIN members ON members.id = loans.member_id
            WHERE {$whereSql}
            ORDER BY {$orderBy} {$dir}
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['loans' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Same filters as get_loans_for_admin(), no pagination — every matching row,
 * for the CSV export. Capped at 5000 rows as a sanity limit.
 */
function get_loans_for_export(PDO $pdo, array $options): array
{
    $filter = build_loan_filter($options);

    $sql = "SELECT " . LOAN_LIST_SELECT . "
            FROM loans
            JOIN members ON members.id = loans.member_id
            WHERE {$filter['sql']}
            ORDER BY loans.due_date ASC
            LIMIT 5000";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($filter['params']);
    return $stmt->fetchAll();
}

/** Total outstanding balance, overdue count, and reminders sent in the last 7 days. */
function get_dashboard_summary(PDO $pdo): array
{
    $totalOutstanding = $pdo->query(
        "SELECT COALESCE(SUM(balance), 0) FROM loans WHERE status != 'cleared'"
    )->fetchColumn();

    $overdueCount = $pdo->query(
        "SELECT COUNT(*) FROM loans WHERE status = 'overdue'"
    )->fetchColumn();

    $sentThisWeek = $pdo->query(
        "SELECT COUNT(*) FROM payment_links WHERE sent_at >= NOW() - INTERVAL '7 days'"
    )->fetchColumn();

    return [
        'total_outstanding' => (float) $totalOutstanding,
        'overdue_count'     => (int) $overdueCount,
        'sent_this_week'    => (int) $sentThisWeek,
    ];
}

/** Every payment link that's ever been sent, most recent first, for the activity log. */
function get_activity_log(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT
            payment_links.sent_at, payment_links.sent_via,
            loans.loan_no, members.full_name, members.phone,
            admins.full_name AS admin_name
         FROM payment_links
         JOIN loans   ON loans.id = payment_links.loan_id
         JOIN members ON members.id = loans.member_id
         LEFT JOIN admins ON admins.id = payment_links.sent_by
         WHERE payment_links.sent_at IS NOT NULL
         ORDER BY payment_links.sent_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Recent M-Pesa payment attempts for the admin payments page. */
function get_payment_log(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT
            payments.created_at, payments.updated_at, payments.phone,
            payments.amount, payments.status, payments.mpesa_receipt,
            payments.result_desc, loans.loan_no, members.full_name
         FROM payments
         JOIN loans ON loans.id = payments.loan_id
         JOIN members ON members.id = loans.member_id
         ORDER BY payments.created_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Reuses a still-valid (unexpired) payment link for this loan if one exists,
 * otherwise creates a fresh one.
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
        "INSERT INTO payment_links (token, loan_id, expires_at)
         VALUES (:token, :loan_id, :expires_at)
         RETURNING id"
    );
    $insert->execute([
        'token'      => $token,
        'loan_id'    => $loanId,
        'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiryDays} days")),
    ]);

    return ['id' => (int) $insert->fetchColumn(), 'token' => $token];
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
