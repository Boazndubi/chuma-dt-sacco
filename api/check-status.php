<?php
/**
 * GET /api/check-status.php?checkout_request_id=...
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$checkoutRequestId = $_GET['checkout_request_id'] ?? '';

if ($checkoutRequestId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing checkout_request_id']);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT status, mpesa_receipt, result_desc FROM payments WHERE checkout_request_id = :id"
);
$stmt->execute(['id' => $checkoutRequestId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Unknown payment']);
    exit;
}

echo json_encode($row);
