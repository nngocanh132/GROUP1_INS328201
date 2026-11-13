<?php
/**
 * Settings / Administration (MVC Entry Point)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/SettingsModel.php';
require_once __DIR__ . '/../app/Controllers/SettingsController.php';

SettingsController::index();
