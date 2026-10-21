<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

session_start_safe();
if (is_logged_in()) {
    $user = current_user();
    audit('LOGOUT', 'Account', $user['account_id']);
}
logout_user();
header('Location: ' . BASE_URL . '/login.php');
exit;
