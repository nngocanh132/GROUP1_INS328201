<?php
/**
 * SettingsController
 *
 * Admin settings and user account management
 */

class SettingsController
{
    public static function index()
    {
        require_role('admin');
        $user = current_user();

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
            self::handlePostAction($user);
        }

        // Load all accounts
        $accounts = SettingsModel::getAllAccounts();
        $pending_count = SettingsModel::countPending($accounts);

        // Display view
        require __DIR__ . '/../Views/settings/index.php';
    }

    private static function handlePostAction($user)
    {
        $action    = trim($_POST['action']     ?? '');
        $target_id = (int)($_POST['account_id'] ?? 0);

        // Cannot modify self or invalid ID
        if (!$target_id || $target_id === (int)$user['account_id']) {
            return;
        }

        $target = SettingsModel::getAccountById($target_id);
        if (!$target) {
            return;
        }

        // Process actions
        if ($action === 'activate' && in_array($target['Status'], ['Pending', 'Inactive'])) {
            SettingsModel::activateAccount($target_id);
            audit('ACTIVATE_ACCOUNT', 'Account', $target_id, ['email' => $target['Email']]);
            redirect_with_flash(BASE_URL . '/pages/settings.php', 'success',
                "Account for {$target['Email']} has been activated.");

        } elseif ($action === 'deactivate' && $target['Status'] === 'Active') {
            SettingsModel::deactivateAccount($target_id);
            audit('DEACTIVATE_ACCOUNT', 'Account', $target_id, ['email' => $target['Email']]);
            redirect_with_flash(BASE_URL . '/pages/settings.php', 'success',
                "Account for {$target['Email']} has been deactivated.");

        } elseif ($action === 'reject_pending' && $target['Status'] === 'Pending') {
            // Mark account inactive — the counterparty remains Rejected (set separately)
            SettingsModel::deactivateAccount($target_id);
            audit('REJECT_ACCOUNT', 'Account', $target_id, ['email' => $target['Email']]);
            redirect_with_flash(BASE_URL . '/pages/settings.php', 'success',
                "Pending account for {$target['Email']} has been rejected.");
        }
    }
}
