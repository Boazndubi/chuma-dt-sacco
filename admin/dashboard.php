<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/loans.php';

$admin = require_admin();

// --- Input handling (whitelisted, bounded) -------------------------------

$search = trim($_GET['q'] ?? '');
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$validStatuses = ['all', 'active', 'overdue', 'cleared'];
$status = $_GET['status'] ?? 'all';
if (!in_array($status, $validStatuses, true)) {
    $status = 'all';
}

$lastSentOptions = ['all', 'sent', 'never'];
$lastSent = $_GET['last_sent'] ?? 'all';
if (!in_array($lastSent, $lastSentOptions, true)) {
  $lastSent = 'all';
}

$dueFrom = is_string($_GET['due_from'] ?? null) ? $_GET['due_from'] : '';
$dueTo = is_string($_GET['due_to'] ?? null) ? $_GET['due_to'] : '';
if (!is_valid_date_input($dueFrom)) {
  $dueFrom = '';
}
if (!is_valid_date_input($dueTo)) {
  $dueTo = '';
}

$sortableColumns = [
    'name'     => 'full_name',
    'balance'  => 'balance',
    'due'      => 'due_date',
    'status'   => 'status',
    'lastsent' => 'last_sent_at',
];
$sortKey = $_GET['sort'] ?? 'due';
if (!array_key_exists($sortKey, $sortableColumns)) {
    $sortKey = 'due';
}
$sortDir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$perPage = (int) ($_GET['per_page'] ?? 25);
$perPage = max(10, min($perPage, 100)); // clamp 10..100

$page = (int) ($_GET['page'] ?? 1);
$page = max(1, $page);

// get_loans_for_admin() must accept this options array and return
// ['loans' => [...], 'total' => int]
$result = get_loans_for_admin($pdo, [
    'search'   => $search,
    'status'   => $status,
    'last_sent'=> $lastSent,
    'due_from' => $dueFrom,
    'due_to'   => $dueTo,
    'sort'     => $sortableColumns[$sortKey],
    'dir'      => $sortDir,
    'page'     => $page,
    'per_page' => $perPage,
]);

$loans      = $result['loans'];
$totalLoans = $result['total'];
$totalPages = max(1, (int) ceil($totalLoans / $perPage));
$page       = min($page, $totalPages); // don't overshoot on stale links

$csrfToken = csrf_token();

/** Build a query string with one or more params overridden, keeping the rest. */
function build_query(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}

function is_valid_date_input(string $value): bool
{
  $date = DateTime::createFromFormat('!Y-m-d', $value);
  return $date !== false && $date->format('Y-m-d') === $value;
}

/** Render a sortable column header link. */
function sort_link(string $label, string $key, string $currentKey, string $currentDir): string
{
    $nextDir = ($key === $currentKey && $currentDir === 'asc') ? 'desc' : 'asc';
    $href = h(build_query(['sort' => $key, 'dir' => $nextDir, 'page' => 1]));
    $indicator = '';
    if ($key === $currentKey) {
        $indicator = $currentDir === 'asc'
            ? '<span class="sort-indicator" aria-hidden="true">&#8593;</span>'
            : '<span class="sort-indicator" aria-hidden="true">&#8595;</span>';
    }
    $ariaSort = $key === $currentKey ? ($currentDir === 'asc' ? 'ascending' : 'descending') : 'none';
    return "<a href=\"{$href}\" class=\"sort-link\" aria-sort=\"{$ariaSort}\">{$label}{$indicator}</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Chuma DT Sacco Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<header class="topbar">
  <p class="brand">Chuma DT Sacco <span class="brand-sub">Admin</span></p>
  <div class="topbar-right">
    <span class="admin-name"><?= h($admin['full_name']) ?></span>
    <a href="logout.php" class="logout-link">Sign out</a>
  </div>
</header>

<main class="dashboard">
  <div class="dashboard-header">
    <div>
      <h1>Outstanding loans</h1>
      <p class="dashboard-count"><?= (int) $totalLoans ?> loan<?= $totalLoans === 1 ? '' : 's' ?> found</p>
    </div>

    <form method="get" class="toolbar" role="search">
      <input type="hidden" name="sort" value="<?= h($sortKey) ?>">
      <input type="hidden" name="dir" value="<?= h($sortDir) ?>">
      <label class="visually-hidden" for="q">Search loans</label>
      <input type="text" id="q" name="q" placeholder="Search name, loan no, phone…" value="<?= h($search) ?>">

      <details class="filter-menu" <?= ($status !== 'all' || $lastSent !== 'all' || $dueFrom !== '' || $dueTo !== '') ? 'open' : '' ?>>
        <summary>Filter</summary>
        <div class="filter-panel">
          <div class="filter-field">
            <label for="status">Status</label>
            <select id="status" name="status">
              <option value="all"      <?= $status === 'all'      ? 'selected' : '' ?>>All statuses</option>
              <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>Active</option>
              <option value="overdue"  <?= $status === 'overdue'  ? 'selected' : '' ?>>Overdue</option>
              <option value="cleared"  <?= $status === 'cleared'  ? 'selected' : '' ?>>Cleared</option>
            </select>
          </div>

          <div class="filter-field">
            <label for="last_sent">Last sent</label>
            <select id="last_sent" name="last_sent">
              <option value="all"   <?= $lastSent === 'all'   ? 'selected' : '' ?>>All sent status</option>
              <option value="sent"  <?= $lastSent === 'sent'  ? 'selected' : '' ?>>Sent</option>
              <option value="never" <?= $lastSent === 'never' ? 'selected' : '' ?>>Never sent</option>
            </select>
          </div>

          <div class="filter-field">
            <label for="due_from">Due from</label>
            <input type="date" id="due_from" name="due_from" value="<?= h($dueFrom) ?>">
          </div>

          <div class="filter-field">
            <label for="due_to">Due to</label>
            <input type="date" id="due_to" name="due_to" value="<?= h($dueTo) ?>">
          </div>

          <div class="filter-actions">
            <a href="?">Clear filters</a>
            <button type="submit">Apply filters</button>
          </div>
        </div>
      </details>

      <label class="visually-hidden" for="per_page">Rows per page</label>
      <select id="per_page" name="per_page">
        <option value="25"  <?= $perPage === 25  ? 'selected' : '' ?>>25 rows</option>
        <option value="50"  <?= $perPage === 50  ? 'selected' : '' ?>>50 rows</option>
        <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 rows</option>
      </select>

      <button type="submit">Search</button>
    </form>
  </div>

  <div class="bulk-toolbar" hidden aria-label="Bulk send actions">
    <span class="bulk-selection-count" aria-live="polite">0 selected</span>
    <span class="bulk-toolbar-label">Send payment link via</span>
    <button type="button" class="bulk-send-btn" data-channel="sms" disabled>SMS</button>
    <button type="button" class="bulk-send-btn" data-channel="whatsapp" disabled>WhatsApp</button>
    <button type="button" class="bulk-send-btn bulk-send-btn-both" data-channel="both" disabled>Both</button>
    <button type="button" class="bulk-clear-btn">Clear selection</button>
    <p class="bulk-result" aria-live="polite"></p>
  </div>

  <?php if (empty($loans)): ?>
    <div class="empty-state">
      <p>No loans match "<?= h($search) ?>"<?= $status !== 'all' ? ' with status ' . h($status) : '' ?>.</p>
      <a href="?">Clear filters</a>
    </div>
  <?php else: ?>
  <div class="table-scroll">
  <table class="loans-table">
    <thead>
      <tr>
        <th class="select-column">
          <input type="checkbox" class="select-all-checkbox" aria-label="Select all loans on this page">
        </th>
        <th><?= sort_link('Member', 'name', $sortKey, $sortDir) ?></th>
        <th>Loan No.</th>
        <th><?= sort_link('Balance', 'balance', $sortKey, $sortDir) ?></th>
        <th><?= sort_link('Due', 'due', $sortKey, $sortDir) ?></th>
        <th><?= sort_link('Status', 'status', $sortKey, $sortDir) ?></th>
        <th><?= sort_link('Last sent', 'lastsent', $sortKey, $sortDir) ?></th>
        <th>Send link</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($loans as $loan): ?>
      <tr data-loan-id="<?= (int) $loan['loan_id'] ?>">
        <td class="select-column">
          <input type="checkbox" class="loan-checkbox" aria-label="Select <?= h($loan['full_name']) ?>">
        </td>
        <td>
          <div class="member-name"><?= h($loan['full_name']) ?></div>
          <div class="member-sub"><?= h($loan['member_no']) ?> · <?= h($loan['phone']) ?></div>
        </td>
        <td><?= h($loan['loan_no']) ?></td>
        <td class="balance-cell">KSh <?= format_money((float) $loan['balance']) ?></td>
        <td><?= h(date('j M Y', strtotime($loan['due_date']))) ?></td>
        <td><span class="status-pill status-<?= h($loan['status']) ?>"><?= h($loan['status']) ?></span></td>
        <td class="last-sent">
          <?php if ($loan['last_sent_at']): ?>
            <?= h(date('j M, g:ia', strtotime($loan['last_sent_at']))) ?>
            <span class="via-tag">via <?= h($loan['last_sent_via']) ?></span>
          <?php else: ?>
            <span class="muted">Never</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="send-controls">
            <button class="send-btn" data-channel="sms">SMS</button>
            <button class="send-btn" data-channel="whatsapp">WhatsApp</button>
            <button class="send-btn send-btn-both" data-channel="both">Both</button>
          </div>
          <p class="send-result" aria-live="polite"></p>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="pagination" aria-label="Pagination">
    <a href="<?= h(build_query(['page' => max(1, $page - 1)])) ?>"
       class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>"
       aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>

    <span class="page-status">Page <?= (int) $page ?> of <?= (int) $totalPages ?></span>

    <a href="<?= h(build_query(['page' => min($totalPages, $page + 1)])) ?>"
       class="page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>"
       aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</main>

<script>window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;</script>
<script src="assets/js/admin.js"></script>
</body>
</html>