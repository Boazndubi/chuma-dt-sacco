<?php
/**
 * POST target Safaricom hits directly — never called by the browser.
 * Must always respond 200 with {"ResultCode":0} even if something
 * downstream fails, or Daraja will keep retrying it.
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
error_log('[mpesa-callback] raw: ' . $raw);

$data = json_decode($raw, true);
$callback = $data['Body']['stkCallback'] ?? null;

if (!$callback) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Ignored — unexpected payload']);
    exit;
}

$checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
$resultCode = $callback['ResultCode'] ?? null;
$resultDesc = $callback['ResultDesc'] ?? '';

if (!$checkoutRequestId) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Ignored — no CheckoutRequestID']);
    exit;
}

try {
    if ((int) $resultCode === 0) {
        $items = $callback['CallbackMetadata']['Item'] ?? [];
        $meta = [];
        foreach ($items as $item) {
            $meta[$item['Name']] = $item['Value'] ?? null;
        }
        $receipt = $meta['MpesaReceiptNumber'] ?? null;

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "UPDATE payments
             SET status = 'success', mpesa_receipt = :receipt, result_desc = :desc
             WHERE checkout_request_id = :id"
        );
        $stmt->execute(['receipt' => $receipt, 'desc' => $resultDesc, 'id' => $checkoutRequestId]);

        $payment = $pdo->prepare("SELECT loan_id, amount FROM payments WHERE checkout_request_id = :id");
        $payment->execute(['id' => $checkoutRequestId]);
        $row = $payment->fetch();

        if ($row) {
            $update = $pdo->prepare(
                "UPDATE loans
                 SET balance = GREATEST(balance - :amount, 0),
                     status = CASE WHEN balance - :amount <= 0 THEN 'cleared' ELSE status END
                 WHERE id = :loan_id"
            );
            $update->execute(['amount' => $row['amount'], 'loan_id' => $row['loan_id']]);
        }

        $pdo->commit();
    } else {
        $status = (int) $resultCode === 1032 ? 'cancelled' : 'failed';
        $stmt = $pdo->prepare(
            "UPDATE payments SET status = :status, result_desc = :desc WHERE checkout_request_id = :id"
        );
        $stmt->execute(['status' => $status, 'desc' => $resultDesc, 'id' => $checkoutRequestId]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[mpesa-callback] error: ' . $e->getMessage());
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
