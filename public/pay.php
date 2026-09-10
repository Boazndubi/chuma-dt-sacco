<?php
/**
 * Entry point for links like:
 *   https://chunadtsacco.co.ke/pay.php?token=<64-char-token>
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
$loan  = $token !== '' ? get_loan_by_token($pdo, $token) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chuna DT Sacco — Loan Payment</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (!$loan): ?>

  <main class="card">
    <img src="assets/img/logo.png" alt="Chuna DT Sacco Ltd" class="logo-img">
    <h1 class="error-title">This link isn't valid</h1>
    <p class="muted">
      It may have expired, or the address was copied incorrectly.
      If you received this by SMS or WhatsApp, please contact the Sacco
      office and we'll send you a fresh payment link.
    </p>
  </main>

<?php else: ?>

  <main class="card">
    <img src="assets/img/logo.png" alt="Chuna DT Sacco Ltd" class="logo-img">

    <p class="member-line">Hello, <?= h($loan['full_name']) ?></p>
    <p class="muted">Loan <?= h($loan['loan_no']) ?> · Member <?= h($loan['member_no']) ?></p>

    <p class="balance-label">Outstanding balance</p>
    <p class="balance">KSh <?= format_money((float) $loan['balance']) ?></p>
    <p class="due-date">Due <?= h(date('j F Y', strtotime($loan['due_date']))) ?></p>

    <form id="pay-form">
      <input type="hidden" name="token" value="<?= h($token) ?>">

      <label for="phone">M-Pesa number to pay from</label>
      <input
        type="tel"
        id="phone"
        name="phone"
        placeholder="07XX XXX XXX"
        value="<?= h($loan['phone']) ?>"
        required
      >

      <button type="submit" id="pay-button">Pay KSh <?= format_money((float) $loan['balance']) ?></button>
      <p id="pay-status" class="status" aria-live="polite"></p>
    </form>

    <p class="footnote">Already paid? Please disregard this message.</p>
  </main>

<?php endif; ?>

<script src="assets/js/pay.js"></script>
</body>
</html>