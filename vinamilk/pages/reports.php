<?php
/**
 * pages/reports.php — MVC entry-point stub
 *
 * The original code was moved to:
 *   app/Models/ReportModel.php
 *   app/Controllers/ReportController.php
 *   app/Views/reports/index.php
 *
 * This file loads the shared infrastructure, registers the MVC classes,
 * then delegates to ReportController.  The URL and every visible behaviour
 * remain identical to the original.
 *
 * To roll back, restore pages/reports.php.orig over this file.
 */

// ── 1. Shared infrastructure (same order as original) ──────────
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// ── 2. MVC classes ─────────────────────────────────────────────
require_once __DIR__ . '/../app/Models/ReportModel.php';
require_once __DIR__ . '/../app/Controllers/ReportController.php';

// ── 3. Dispatch ────────────────────────────────────────────────
(new ReportController())->index();
