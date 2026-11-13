<?php
/**
 * Download Document (MVC Entry Point)
 *
 * Serves contract documents securely with access control.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/DocumentModel.php';
require_once __DIR__ . '/../app/Controllers/DocumentController.php';

DocumentController::download();
