<?php
/**
 * logout.php — MVC entry-point stub
 *
 * Moved to: app/Controllers/LogoutController.php
 * To roll back, restore logout.php.orig over this file.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

require_once __DIR__ . '/app/Controllers/LogoutController.php';

(new LogoutController())->logout();
