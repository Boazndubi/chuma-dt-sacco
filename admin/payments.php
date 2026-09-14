<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loans.php';

$admin = require_admin();
$payments = get_payment_log($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments — Chuna DT Sacco Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<header class="topbar">
  <a href="dashboard.php" class="brand-logo" aria-label="Chuna DT Sacco admin dashboard">
    <img src="assets/img/logo.png" alt="Chuna DT Sacco Ltd - The University Sacco">
  </a>
  <div class="topbar-right">
    <a href="dashboard.php" class="logout-link">Dashboard</a>
    <a href="activity-log.php" class="logout-link">Activity log</a>
    <span class="admin-name"><?= h($admin['full_name']) ?></span>
    <a href="logout.php" class="logout-link">Sign out</a>
  </div>
</header>

<main class="dashboard">
  <div class="dashboard-header">
    <div>
      <h1>Payments</h1>
      <p class="dashboard-count">Recent M-Pesa payment attempts</p>
    </div>
  </div>

  <?php if (empty($payments)): ?>
    <div class="empty-state">
      <p>No M-Pesa payments have been recorded yet.</p>
    </div>
  <?php else: ?>
    <div class="table-scroll">
      <table class="loans-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Member</th>
            <th>Loan No.</th>
            <th>Phone</th>
            <th>Amount</th>
            <th>Status</th>
            <th>M-Pesa receipt</th>
            <th>Result</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
          <tr>
            <td data-label="Date"><?= h(date('j M Y, g:ia', strtotime($payment['created_at']))) ?></td>
            <td data-label="Member"><?= h($payment['full_name']) ?></td>
            <td data-label="Loan No."><?= h($payment['loan_no']) ?></td>
            <td data-label="Phone"><?= h($payment['phone']) ?></td>
            <td data-label="Amount">KSh <?= format_money((float) $payment['amount']) ?></td>
            <td data-label="Status"><span class="status-pill status-<?= h($payment['status']) ?>"><?= h($payment['status']) ?></span></td>
            <td data-label="M-Pesa receipt"><?= h($payment['mpesa_receipt'] ?? '-') ?></td>
            <td data-label="Result"><?= h($payment['result_desc'] ?? '-') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
