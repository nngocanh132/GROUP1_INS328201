<?php
/**
 * login.php — MVC entry-point stub
 *
 * The original code was moved to:
 *   app/Models/AuthModel.php
 *   app/Controllers/AuthController.php
 *   app/Views/auth/login.php
 *
 * This file loads the shared infrastructure, registers the MVC classes,
 * then delegates to AuthController. The URL and every visible behaviour
 * remain identical to the original.
 *
 * To roll back, restore login.php.orig over this file.
 */

// ── 1. Shared infrastructure ─────────────────────────────────
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// ── 2. MVC classes ───────────────────────────────────────────
require_once __DIR__ . '/app/Models/AuthModel.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';

// ── 3. Dispatch ──────────────────────────────────────────────
(new AuthController())->login();
