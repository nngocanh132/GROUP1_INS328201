<?php
/**
 * Login page
 * Schema: Account (AccountID, RoleID, Email, PasswordHash, Status)
 *         Staff   (StaffID, AccountID, DepartmentID, FullName, Position)
 *         Role    (RoleID, RoleName)
 *         Department (DepartmentID, DepartmentName)
 *
 * Only 'Active' accounts may log in.
 * External (partner) accounts can log in but see a restricted portal notice.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

session_start_safe();

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email address and password.';
    } else {
        // Fetch Account + Staff + Role + Department in one query.
        // Staff row may not exist for External accounts.
        $stmt = db_query("
            SELECT
                a.AccountID,
                a.Email,
                a.PasswordHash,
                a.Status,
                r.RoleName,
                s.StaffID,
                s.FullName,
                s.Position,
                d.DepartmentName
            FROM Account a
            JOIN Role r ON r.RoleID = a.RoleID
            LEFT JOIN Staff s ON s.AccountID = a.AccountID
            LEFT JOIN Department d ON d.DepartmentID = s.DepartmentID
            WHERE a.Email = ?
            LIMIT 1
        ", [$email]);

        $row = $stmt->fetch();

        if (!$row) {
            $error = 'Invalid email or password. Please try again.';
        } elseif ($row['Status'] !== 'Active') {
            $error = 'Your account is not active. Please contact the system administrator.';
        } elseif (!password_verify($password, $row['PasswordHash'])) {
            $error = 'Invalid email or password. Please try again.';
            audit('LOGIN_FAILED', 'Account', null, ['email' => $email]);
        } else {
            login_user($row);

            // Update LastLoginAt
            db_query(
                "UPDATE Account SET LastLoginAt = NOW() WHERE AccountID = ?",
                [$row['AccountID']]
            );

            audit('LOGIN', 'Account', $row['AccountID'], ['role' => $row['RoleName']]);
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Vinamilk CDM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-header">
      <div class="login-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" width="28" height="28">
          <rect x="2" y="7" width="20" height="14" rx="2"/>
          <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
        </svg>
      </div>
      <h1>Vinamilk CDM</h1>
      <p>Contract &amp; Document Management System</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <span><?= e($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>/login.php" novalidate>
      <div class="form-group" style="margin-bottom:18px;">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email"
               class="form-control"
               value="<?= e($email) ?>"
               placeholder="you@vinamilk.com.vn"
               autocomplete="email"
               required>
      </div>

      <div class="form-group" style="margin-bottom:24px;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               class="form-control"
               placeholder="Enter your password"
               autocomplete="current-password"
               required>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
        Sign in to your account
      </button>
    </form>

  </div>
</div>
</body>
</html>
