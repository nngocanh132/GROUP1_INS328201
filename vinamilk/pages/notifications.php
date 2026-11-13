<?php
/**
 * pages/notifications.php — MVC entry-point stub
 *
 * The original code was moved to:
 *   app/Models/NotificationModel.php
 *   app/Controllers/NotificationController.php
 *   app/Views/notifications/index.php
 *
 * This file loads the shared infrastructure, registers the MVC classes,
 * then delegates to NotificationController. The URL and every visible
 * behaviour remain identical to the original.
 *
 * To roll back, restore pages/notifications.php.orig over this file.
 */

// ── 1. Shared infrastructure (same order as original) ──────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// ── 2. MVC classes ─────────────────────────────────────────────
require_once __DIR__ . '/../app/Models/NotificationModel.php';
require_once __DIR__ . '/../app/Controllers/NotificationController.php';

// ── 3. Dispatch ────────────────────────────────────────────────
(new NotificationController())->index();
