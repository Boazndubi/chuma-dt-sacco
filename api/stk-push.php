<?php
/**
 * POST /api/stk-push.php   { token, product_code, member_no, phone }
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/mpesa.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$token = $input['token'] ?? '';
$productCode = strtoupper(trim((string) ($input['product_code'] ?? '')));
$memberNo = trim((string) ($input['member_no'] ?? ''));
$rawPhone = $input['phone'] ?? '';

$loan = $token !== '' ? get_loan_by_token($pdo, $token) : null;
if (!$loan) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid or expired payment link.']);
    exit;
}

$productCodes = loan_product_codes();
if (!isset($productCodes[$productCode])) {
    http_response_code(422);
    echo json_encode(['error' => 'Select a valid loan or product code.']);
    exit;
}

if ($productCode !== strtoupper((string) $loan['product_code'])) {
    http_response_code(422);
    echo json_encode(['error' => 'The loan code does not match this payment link.']);
    exit;
}

if ($memberNo === '' || !hash_equals((string) $loan['member_no'], $memberNo)) {
    http_response_code(422);
    echo json_encode(['error' => 'The member number does not match this payment link.']);
    exit;
}

$phone = normalize_phone($rawPhone);
if (!$phone) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a valid Safaricom number, e.g. 07XX XXX XXX.']);
    exit;
}

$amount = (float) $loan['balance'];
if ($amount <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'This loan has no outstanding balance.']);
    exit;
}

try {
    $cfg = mpesa_config();
    $accessToken = mpesa_get_access_token($cfg);
    $accountReference = $productCode . $memberNo;
    $stk = mpesa_stk_push($cfg, $accessToken, $phone, $amount, $accountReference);

    if (($stk['ResponseCode'] ?? null) !== '0') {
        http_response_code(502);
        echo json_encode(['error' => $stk['errorMessage'] ?? 'Safaricom rejected the request.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO payments
            (loan_id, payment_link_id, phone, amount, checkout_request_id, merchant_request_id, status)
         VALUES (:loan_id, :payment_link_id, :phone, :amount, :checkout_id, :merchant_id, 'pending')"
    );
    $stmt->execute([
        'loan_id'         => $loan['loan_id'],
        'payment_link_id' => $loan['payment_link_id'],
        'phone'           => $phone,
        'amount'          => $amount,
        'checkout_id'     => $stk['CheckoutRequestID'],
        'merchant_id'     => $stk['MerchantRequestID'],
    ]);

    echo json_encode(['checkout_request_id' => $stk['CheckoutRequestID']]);
} catch (Throwable $e) {
    error_log('[stk-push] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Something went wrong starting the payment. Please try again.']);
}

function mpesa_get_access_token(array $cfg): string
{
    $url = $cfg['base_url'] . '/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $cfg['consumer_key'] . ':' . $cfg['consumer_secret'],
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        throw new RuntimeException('Could not obtain M-Pesa access token.');
    }

    return $data['access_token'];
}

function mpesa_stk_push(array $cfg, string $accessToken, string $phone, float $amount, string $accountRef): array
{
    $timestamp = date('YmdHis');
    $password = base64_encode($cfg['shortcode'] . $cfg['passkey'] . $timestamp);

    $payload = [
        'BusinessShortCode' => $cfg['shortcode'],
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int) ceil($amount),
        'PartyA'             => $phone,
        'PartyB'             => $cfg['shortcode'],
        'PhoneNumber'        => $phone,
        'CallBackURL'        => $cfg['callback_url'],
        'AccountReference'   => $accountRef,
        'TransactionDesc'    => 'Chuma DT Sacco loan repayment',
    ];

    $ch = curl_init($cfg['base_url'] . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true) ?? [];
}
