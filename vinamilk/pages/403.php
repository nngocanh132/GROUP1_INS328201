<?php
/**
 * 403 Access Denied Page (MVC Entry Point)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../app/Controllers/ErrorController.php';

ErrorController::error403();
