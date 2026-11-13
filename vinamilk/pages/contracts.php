<?php
/**
 * Contracts Listing (MVC Entry Point)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/ContractModel.php';
require_once __DIR__ . '/../app/Controllers/ContractController.php';

ContractController::index();
