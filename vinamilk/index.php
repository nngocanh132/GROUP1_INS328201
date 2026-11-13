<?php
/**
 * Dashboard (MVC Entry Point)
 *
 * Renders role-based dashboards: external partner or internal staff/admin.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

require_once __DIR__ . '/app/Models/DashboardModel.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';

require_login();

DashboardController::index();
