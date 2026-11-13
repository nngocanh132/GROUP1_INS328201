<?php
/**
 * Approvals / Review Workflow (MVC Entry Point)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/ApprovalModel.php';
require_once __DIR__ . '/../app/Controllers/ApprovalController.php';

ApprovalController::index();
