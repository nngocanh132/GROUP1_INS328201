<?php
/**
 * Create Partner Company (MVC Entry Point)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/CounterpartyModel.php';
require_once __DIR__ . '/../app/Controllers/CounterpartyController.php';

CounterpartyController::create();
