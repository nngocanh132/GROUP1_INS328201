<?php
/**
 * Contract Detail (MVC Entry Point)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/ContractModel.php';
require_once __DIR__ . '/../app/Controllers/ContractController.php';

$contract_id = (int)($_GET['id'] ?? 0);
ContractController::show($contract_id);
