<?php
/**
 * Small, focused helpers shared by the public payment page, the API
 * endpoints, and the admin area.
 */

function generate_payment_token(): string
{
    return bin2hex(random_bytes(32));
}

function get_loan_by_token(PDO $pdo, string $token): ?array
{
    $sql = "SELECT
                loans.id            AS loan_id,
                loans.loan_no,
                loans.balance,
                loans.due_date,
                loans.status,
                members.full_name,
                members.member_no,
                members.phone,
                payment_links.id    AS payment_link_id,
                payment_links.expires_at
            FROM payment_links
            JOIN loans   ON loans.id = payment_links.loan_id
            JOIN members ON members.id = loans.member_id
            WHERE payment_links.token = :token
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        return null;
    }

    return $row;
}

function format_money(float $amount): string
{
    return number_format($amount, 2);
}

function normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D/', '', $phone);

    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '254' . substr($digits, 1);
    }
    if (str_starts_with($digits, '254') && strlen($digits) === 12) {
        return $digits;
    }
    if (strlen($digits) === 9) {
        return '254' . $digits;
    }

    return null;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
