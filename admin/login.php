<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

start_admin_session();
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (attempt_login($pdo, $username, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — Chuna DT Sacco</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-page">
  <main class="login-card">
    <img src="assets/img/logo.png" alt="Chuna DT Sacco Ltd" class="logo-img">
    <p class="tagline">Admin</p>

    <?php if ($error): ?>
      <p class="alert-error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autofocus>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <button type="submit">Sign in</button>
    </form>
  </main>
</body>
</html>