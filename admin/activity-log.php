<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loans.php';

$admin = require_admin();
$activity = get_activity_log($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Activity Log — Chuna DT Sacco Admin</title>
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
    <a href="payments.php" class="logout-link">Payments</a>
    <span class="admin-name"><?= h($admin['full_name']) ?></span>
    <a href="logout.php" class="logout-link">Sign out</a>
  </div>
</header>

<main class="dashboard">
  <div class="dashboard-header">
    <div>
      <h1>Activity log</h1>
      <p class="dashboard-count">Recent payment-link reminders</p>
    </div>
    <a href="dashboard.php" class="export-link">Back to dashboard</a>
  </div>

  <?php if (empty($activity)): ?>
    <div class="empty-state">
      <p>No payment-link activity has been recorded yet.</p>
    </div>
  <?php else: ?>
    <div class="table-scroll">
      <table class="loans-table">
        <thead>
          <tr>
            <th>Date and time</th>
            <th>Member</th>
            <th>Loan No.</th>
            <th>Phone</th>
            <th>Channel</th>
            <th>Sent by</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activity as $item): ?>
          <tr>
            <td data-label="Date and time"><?= h(date('j M Y, g:ia', strtotime($item['sent_at']))) ?></td>
            <td data-label="Member"><?= h($item['full_name']) ?></td>
            <td data-label="Loan No."><?= h($item['loan_no']) ?></td>
            <td data-label="Phone"><?= h($item['phone']) ?></td>
            <td data-label="Channel"><?= h($item['sent_via']) ?></td>
            <td data-label="Sent by"><?= h($item['admin_name'] ?? 'Unknown admin') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
