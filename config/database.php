<?php
/**
 * Returns a single, shared PDO connection to Postgres (Neon).
 * Neon requires SSL — sslmode=require is set below by default.
 */

require_once __DIR__ . '/env.php';

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST');                  // e.g. ep-xxxx-xxxx.us-east-2.aws.neon.tech
    $port = env('DB_PORT', '5432');
    $name = env('DB_NAME', 'chuma_dt_sacco');
    $user = env('DB_USER');
    $pass = env('DB_PASS');
    $sslmode = env('DB_SSLMODE', 'require'); // Neon requires SSL

    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
