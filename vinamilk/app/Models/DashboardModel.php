<?php
/**
 * DashboardModel
 *
 * Data provider for both external (partner) and internal (staff/admin) dashboards.
 */

class DashboardModel
{
    // ================================================================
    //  EXTERNAL PARTNER QUERIES
    // ================================================================

    /**
     * Fetch the external contact's counterparty info
     */
    public static function getExternalContact($account_id)
    {
        return db_query(
            "SELECT c.CounterpartyID, c.FullName, c.JobTitle,
                    cp.CompanyName, cp.CounterpartyCode, cp.CounterpartyType,
                    cp.ApprovalStatus AS CompanyStatus
             FROM Contact c
             JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
             WHERE c.AccountID = ?
             LIMIT 1",
            [$account_id]
        )->fetch();
    }

    /**
     * Pending proposals for this counterparty
     */
    public static function getExternalPendingProposals($counterparty_id)
    {
        if (!db_table_exists('ContractProposal')) {
            return [];
        }
        return db_query(
            "SELECT p.*, ct.TypeName, s.FullName AS ProposerName
             FROM ContractProposal p
             JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
             JOIN Staff s         ON s.StaffID         = p.ProposedByStaffID
             WHERE p.CounterpartyID = ? AND p.Status = 'Pending'
             ORDER BY p.CreatedAt DESC",
            [$counterparty_id]
        )->fetchAll();
    }

    /**
     * All proposal counts by status for this counterparty
     */
    public static function getExternalAllProposals($counterparty_id)
    {
        if (!db_table_exists('ContractProposal')) {
            return [];
        }
        return db_query(
            "SELECT p.Status, COUNT(*) AS cnt
             FROM ContractProposal p
             WHERE p.CounterpartyID = ?
             GROUP BY p.Status",
            [$counterparty_id]
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Recent contracts for this counterparty
     */
    public static function getExternalContracts($counterparty_id)
    {
        return db_query(
            "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
                    c.ContractValue, c.Currency, c.StartDate, c.EndDate, c.UpdatedAt,
                    ct.TypeName
             FROM Contract c
             JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
             WHERE c.CounterpartyID = ?
             ORDER BY c.UpdatedAt DESC
             LIMIT 8",
            [$counterparty_id]
        )->fetchAll();
    }

    /**
     * Contract status distribution for this counterparty
     */
    public static function getExternalContractStats($counterparty_id)
    {
        return db_query(
            "SELECT Status, COUNT(*) AS cnt FROM Contract
             WHERE CounterpartyID = ? GROUP BY Status",
            [$counterparty_id]
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Recent notifications for this account
     */
    public static function getExternalNotifications($account_id)
    {
        return db_query(
            "SELECT Title, Message, NotificationType, CreatedAt, IsRead
             FROM Notification
             WHERE AccountID = ?
             ORDER BY CreatedAt DESC
             LIMIT 8",
            [$account_id]
        )->fetchAll();
    }

    /**
     * Mark all notifications as read
     */
    public static function markAllNotificationsRead($account_id)
    {
        db_query(
            "UPDATE Notification SET IsRead = 1, ReadAt = NOW()
             WHERE AccountID = ? AND IsRead = 0",
            [$account_id]
        );
    }

    // ================================================================
    //  INTERNAL DASHBOARD QUERIES (staff/admin)
    // ================================================================

    /**
     * Overall contract metrics
     */
    public static function getInternalMetrics()
    {
        return db_query("
            SELECT
                COUNT(*)                              AS total,
                SUM(Status = 'Draft')                 AS draft,
                SUM(Status = 'PendingReview')         AS pending_review,
                SUM(Status = 'PendingApproval')       AS pending_approval,
                SUM(Status = 'RevisionRequested')     AS revision,
                SUM(Status = 'Approved')              AS approved,
                SUM(Status = 'Active')                AS active_count,
                SUM(Status = 'Closed')                AS closed
            FROM Contract
        ")->fetch();
    }

    /**
     * Count contracts expiring soon (within 30 days)
     */
    public static function getExpiringSoonCount()
    {
        return (int) db_query("
            SELECT COUNT(*) FROM Contract
            WHERE Status = 'Active'
              AND EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
    }

    /**
     * Count pending approvals
     */
    public static function getPendingApprovalsCount()
    {
        return (int) db_query(
            "SELECT COUNT(*) FROM Approval WHERE ApprovalStatus = 'Pending'"
        )->fetchColumn();
    }

    /**
     * Contracts by type distribution
     */
    public static function getContractsByType()
    {
        return db_query("
            SELECT ct.TypeName, COUNT(c.ContractID) AS cnt
            FROM Contract c
            JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
            GROUP BY ct.TypeName ORDER BY cnt DESC
        ")->fetchAll();
    }

    /**
     * Monthly contract creation trend (last 11 months)
     */
    public static function getMonthlyContractTrend()
    {
        return db_query("
            SELECT DATE_FORMAT(CreatedAt,'%b %Y') AS month_label,
                   DATE_FORMAT(CreatedAt,'%Y-%m') AS month_key,
                   COUNT(*) AS cnt
            FROM Contract
            WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 11 MONTH)
            GROUP BY month_key, month_label ORDER BY month_key ASC
        ")->fetchAll();
    }

    /**
     * Recent contracts (8 most recent)
     */
    public static function getRecentContracts()
    {
        return db_query("
            SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
                   c.EndDate, c.UpdatedAt, ct.TypeName,
                   cp.CompanyName AS CounterpartyName,
                   s.FullName AS OwnerName, d.DepartmentName
            FROM Contract c
            JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
            JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
            JOIN Staff s          ON s.StaffID         = c.OwnerStaffID
            JOIN Department d     ON d.DepartmentID    = c.DepartmentID
            ORDER BY c.UpdatedAt DESC LIMIT 8
        ")->fetchAll();
    }

    /**
     * Recent activity log (5 most recent)
     */
    public static function getRecentActivity()
    {
        return db_query("
            SELECT al.Action, al.EntityName, al.EntityID, al.CreatedAt,
                   COALESCE(s.FullName, a.Email) AS ActorName
            FROM AuditLog al
            LEFT JOIN Account a ON a.AccountID = al.AccountID
            LEFT JOIN Staff s   ON s.AccountID = al.AccountID
            ORDER BY al.CreatedAt DESC LIMIT 5
        ")->fetchAll();
    }
}
