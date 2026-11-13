<?php
/**
 * Counterparties (MVC Entry Point)
 *
 * Migrated to MVC. All logic is in:
 *   app/Models/CounterpartyModel.php
 *   app/Controllers/CounterpartyController.php
 *   app/Views/counterparties/index.php
 *
 * The original self-contained implementation is preserved in
 * vinamilk_backup_premvc/pages/counterparties.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_once __DIR__ . '/../app/Models/CounterpartyModel.php';
require_once __DIR__ . '/../app/Controllers/CounterpartyController.php';

CounterpartyController::index();
