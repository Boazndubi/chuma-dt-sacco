<?php
/**
 * Minimal session-based admin auth. No framework needed for a single
 * admin role — just PHP sessions + password_hash/password_verify.
 */

require_once __DIR__ . '/db.php';

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']), // only send cookie over HTTPS in production
        ]);
        session_start();
    }
}

/** Call at the top of every protected admin page. Redirects to login if not authenticated. */
function require_admin(): array
{
    start_admin_session();

    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    return [
        'id'        => $_SESSION['admin_id'],
        'username'  => $_SESSION['admin_username'],
        'full_name' => $_SESSION['admin_full_name'],
    ];
}

/** Returns true and starts the session on success; false on bad credentials. */
function attempt_login(PDO $pdo, string $username, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name FROM admins WHERE username = :u');
    $stmt->execute(['u' => $username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    start_admin_session();
    session_regenerate_id(true); // prevent session fixation on login
    $_SESSION['admin_id']        = $admin['id'];
    $_SESSION['admin_username']  = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'];

    return true;
}

function logout_admin(): void
{
    start_admin_session();
    $_SESSION = [];
    session_destroy();
}

/** One CSRF token per session, reused across forms/AJAX calls in the admin area. */
function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool
{
    start_admin_session();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
