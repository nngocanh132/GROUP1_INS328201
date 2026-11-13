<?php
/**
 * Documents page (MVC Entry Point)
 *
 * Document + ContractVersion tables listing
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/DocumentModel.php';
require_once __DIR__ . '/../app/Controllers/DocumentController.php';

DocumentController::index();
