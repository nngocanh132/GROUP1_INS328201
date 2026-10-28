<?php
/**
 * DashboardController
 *
 * Routes to either external (partner) or internal (staff/admin) dashboard view.
 */

class DashboardController
{
    public static function index()
    {
        $user = current_user();

        // Route based on role
        if ($user['role'] === 'external') {
            self::showExternalDashboard($user);
        } else {
            self::showInternalDashboard($user);
        }
    }

    // ================================================================
    //  EXTERNAL PARTNER DASHBOARD
    // ================================================================

    private static function showExternalDashboard($user)
    {
        // Fetch external contact info
        $ext_contact = DashboardModel::getExternalContact($user['account_id']);
        $ext_cp_id = $ext_contact ? (int)$ext_contact['CounterpartyID'] : 0;

        // Initialize empty data
        $ext_pending_proposals = [];
        $ext_all_proposals     = [];
        $ext_contracts         = [];
        $ext_contract_stats    = [];
        $ext_notifications     = [];

        // If counterparty ID exists, fetch data
        if ($ext_cp_id) {
            $ext_pending_proposals = DashboardModel::getExternalPendingProposals($ext_cp_id);
            $ext_all_proposals     = DashboardModel::getExternalAllProposals($ext_cp_id);
            $ext_contracts         = DashboardModel::getExternalContracts($ext_cp_id);
            $ext_contract_stats    = DashboardModel::getExternalContractStats($ext_cp_id);
            $ext_notifications     = DashboardModel::getExternalNotifications($user['account_id']);

            // Mark all notifications as read on dashboard visit
            DashboardModel::markAllNotificationsRead($user['account_id']);
        }

        // Calculate summary stats
        $ext_total_contracts  = array_sum($ext_contract_stats);
        $ext_active_contracts = (int)($ext_contract_stats['Active'] ?? 0);
        $ext_pending_count    = count($ext_pending_proposals);
        $ext_approved_count   = (int)($ext_all_proposals['AdminApproved'] ?? 0)
                              + (int)($ext_all_proposals['ContractCreated'] ?? 0);

        // Pass data to view
        require __DIR__ . '/../Views/dashboard/external.php';
    }

    // ================================================================
    //  INTERNAL DASHBOARD (staff/admin)
    // ================================================================

    private static function showInternalDashboard($user)
    {
        // Fetch all metrics and data
        $metrics           = DashboardModel::getInternalMetrics();
        $expiring_soon     = DashboardModel::getExpiringSoonCount();
        $pending_approvals = DashboardModel::getPendingApprovalsCount();

        // Chart data
        $by_type_rows      = DashboardModel::getContractsByType();
        $monthly_rows      = DashboardModel::getMonthlyContractTrend();

        // Recent data
        $recent_contracts  = DashboardModel::getRecentContracts();
        $activity          = DashboardModel::getRecentActivity();

        // Prepare chart JSON arrays
        $status_labels = ['Active','Pending Review','Pending Approval','Revision','Approved','Draft','Closed'];
        $status_values = [
            (int)$metrics['active_count'],
            (int)$metrics['pending_review'],
            (int)$metrics['pending_approval'],
            (int)$metrics['revision'],
            (int)$metrics['approved'],
            (int)$metrics['draft'],
            (int)$metrics['closed'],
        ];
        $status_colors = ['#1761a8','#d97706','#ea6f1a','#7c3aed','#12a150','#c8d3e0','#8b9db5'];

        $type_labels  = array_column($by_type_rows, 'TypeName');
        $type_values  = array_map('intval', array_column($by_type_rows, 'cnt'));
        $month_labels = array_column($monthly_rows, 'month_label');
        $month_values = array_map('intval', array_column($monthly_rows, 'cnt'));

        // Pass data to view
        require __DIR__ . '/../Views/dashboard/internal.php';
    }
}
