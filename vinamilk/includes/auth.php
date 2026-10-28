<?php
/**
 * Authentication & Session helpers
 * Adapted for vinamilk_clm schema:
 *   Account (AccountID, RoleID, Email, PasswordHash, Status)
 *   Staff   (StaffID, AccountID, DepartmentID, FullName, Position)
 *   Role    (RoleID, RoleName)
 *   Department (DepartmentID, DepartmentName)
 */
require_once __DIR__ . '/config.php';

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    session_start_safe();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_role(string ...$roles): void {
    require_login();
    $user_role = $_SESSION['user_role'] ?? '';
    if (!in_array($user_role, $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../pages/403.php';
        exit;
    }
}

function current_user(): array {
    session_start_safe();
    return [
        'id'        => $_SESSION['user_id']       ?? 0,
        'account_id'=> $_SESSION['account_id']    ?? 0,
        'staff_id'  => $_SESSION['staff_id']      ?? '',
        'name'      => $_SESSION['user_name']      ?? '',
        'email'     => $_SESSION['user_email']     ?? '',
        'role'      => $_SESSION['user_role']      ?? '',
        'role_name' => $_SESSION['user_role_name'] ?? '',
        'dept'      => $_SESSION['user_dept']      ?? '',
        'position'  => $_SESSION['user_position']  ?? '',
        'initials'  => $_SESSION['user_initials']  ?? 'U',
        'is_internal' => $_SESSION['is_internal']  ?? false,
    ];
}

/**
 * Populate the session from a joined Account+Staff+Role+Department row.
 * The query that produces $row is in login.php.
 *
 * Role semantics:
 *   RoleName = 'Manager/Admin'  → internal, can approve
 *   RoleName = 'Staff'          → internal, creates contracts
 *   RoleName = 'External'       → counterparty portal user (read-only in this app)
 */
function login_user(array $row): void {
    session_start_safe();
    session_regenerate_id(true);

    $role_name = $row['RoleName'] ?? '';

    // Normalised role key used throughout the app for permission checks
    $role_key = match($role_name) {
        'Manager/Admin' => 'admin',
        'Staff'         => 'staff',
        'External'      => 'external',
        default         => 'staff',
    };

    // Build initials from FullName (Staff) or from email prefix (External/no staff)
    $full_name = $row['FullName'] ?? '';
    if ($full_name) {
        $parts = explode(' ', $full_name);
        $initials = mb_strtoupper(
            mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1)
        );
    } else {
        $initials = mb_strtoupper(mb_substr($row['Email'] ?? 'U', 0, 2));
    }

    $_SESSION['user_id']       = $row['AccountID'];   // primary key used everywhere
    $_SESSION['account_id']    = $row['AccountID'];
    $_SESSION['staff_id']      = $row['StaffID'] ?? '';
    $_SESSION['user_name']     = $full_name ?: ($row['Email'] ?? '');
    $_SESSION['user_email']    = $row['Email'] ?? '';
    $_SESSION['user_role']     = $role_key;
    $_SESSION['user_role_name']= $role_name;
    $_SESSION['user_dept']     = $row['DepartmentName'] ?? '';
    $_SESSION['user_position'] = $row['Position'] ?? '';
    $_SESSION['user_initials'] = $initials;
    $_SESSION['is_internal']   = in_array($role_key, ['admin', 'staff']);
}

function logout_user(): void {
    session_start_safe();
    $_SESSION = [];
    session_destroy();
}

// ---------------------------------------------------------------
// CSRF helpers
// ---------------------------------------------------------------
function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): bool {
    $token = $_POST[CSRF_TOKEN_NAME] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $valid = isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    if ($valid) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
    }
    return $valid;
}
