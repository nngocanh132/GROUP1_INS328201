<?php
/**
 * CounterpartyModel
 *
 * Counterparty (partner company) management
 */

class CounterpartyModel
{
    /**
     * Get counterparty by ID with relations
     */
    public static function getById($cp_id)
    {
        return db_query(
            "SELECT cp.*, s_prop.FullName AS ProposerFullName, s_prop.Position AS ProposerPosition,
                    dp.DepartmentName AS ProposerDept,
                    s_dec.FullName AS DeciderFullName
             FROM Counterparty cp
             LEFT JOIN Staff s_prop ON s_prop.StaffID = cp.ProposedBy
             LEFT JOIN Department dp ON dp.DepartmentID = s_prop.DepartmentID
             LEFT JOIN Staff s_dec  ON s_dec.StaffID  = cp.DecidedBy
             WHERE cp.CounterpartyID = ?",
            [$cp_id]
        )->fetch();
    }

    /**
     * Get contacts for counterparty
     */
    public static function getContacts($cp_id)
    {
        return db_query(
            "SELECT c.*, a.Email, a.Status AS AccountStatus, a.CreatedAt AS AccountCreated
             FROM Contact c
             JOIN Account a ON a.AccountID = c.AccountID
             WHERE c.CounterpartyID = ?
             ORDER BY c.IsPrimary DESC, c.ContactID ASC",
            [$cp_id]
        )->fetchAll();
    }

    /**
     * Count linked contracts
     */
    public static function countLinkedContracts($cp_id)
    {
        return (int) db_query(
            "SELECT COUNT(*) FROM Contract WHERE CounterpartyID = ?",
            [$cp_id]
        )->fetchColumn();
    }

    /**
     * Count pending counterparties
     */
    public static function countPending()
    {
        return (int) db_query(
            "SELECT COUNT(*) FROM Counterparty WHERE ApprovalStatus = 'Pending'"
        )->fetchColumn();
    }

    /**
     * Get counterparties with filters and pagination
     */
    public static function getFiltered($filters, $page, $page_size)
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[]  = '(cp.CompanyName LIKE ? OR cp.CounterpartyCode LIKE ? OR cp.TaxCode LIKE ?)';
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }
        if (!empty($filters['status'])) {
            $where[]  = 'cp.ApprovalStatus = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'cp.CounterpartyType = ?';
            $params[] = $filters['type'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT cp.*,
                   s.FullName  AS ProposerName,
                   ds.FullName AS DeciderName,
                   (SELECT COUNT(*) FROM Contact ct WHERE ct.CounterpartyID = cp.CounterpartyID) AS ContactCount,
                   (SELECT COUNT(*) FROM Contract cc WHERE cc.CounterpartyID = cp.CounterpartyID) AS ContractCount
            FROM Counterparty cp
            LEFT JOIN Staff s  ON s.StaffID  = cp.ProposedBy
            LEFT JOIN Staff ds ON ds.StaffID = cp.DecidedBy
            WHERE $where_sql
            ORDER BY
                CASE cp.ApprovalStatus WHEN 'Pending' THEN 0 ELSE 1 END,
                cp.ProposedAt DESC
        ";

        return paginate($sql, $params, $page, $page_size);
    }

    /**
     * Approve counterparty
     */
    public static function approve($cp_id, $staff_id)
    {
        db_query(
            "UPDATE Counterparty
             SET ApprovalStatus = 'Active', DecidedBy = ?, DecidedAt = NOW()
             WHERE CounterpartyID = ?",
            [$staff_id, $cp_id]
        );
        // Activate pending contact accounts
        db_query(
            "UPDATE Account a
             JOIN Contact c ON c.AccountID = a.AccountID
             SET a.Status = 'Active'
             WHERE c.CounterpartyID = ? AND a.Status = 'Pending'",
            [$cp_id]
        );
    }

    /**
     * Reject counterparty
     */
    public static function reject($cp_id, $staff_id, $reason)
    {
        db_query(
            "UPDATE Counterparty
             SET ApprovalStatus = 'Rejected', RejectionReason = ?,
                 DecidedBy = ?, DecidedAt = NOW()
             WHERE CounterpartyID = ?",
            [$reason, $staff_id, $cp_id]
        );
        // Deactivate linked pending contact accounts
        db_query(
            "UPDATE Account a
             JOIN Contact c ON c.AccountID = a.AccountID
             SET a.Status = 'Inactive'
             WHERE c.CounterpartyID = ? AND a.Status = 'Pending'",
            [$cp_id]
        );
    }

    /**
     * Suspend counterparty
     */
    public static function suspend($cp_id)
    {
        db_query(
            "UPDATE Counterparty SET ApprovalStatus = 'Suspended' WHERE CounterpartyID = ?",
            [$cp_id]
        );
        // Deactivate linked active contact accounts
        db_query(
            "UPDATE Account a
             JOIN Contact c ON c.AccountID = a.AccountID
             SET a.Status = 'Inactive'
             WHERE c.CounterpartyID = ? AND a.Status = 'Active'",
            [$cp_id]
        );
    }

    /**
     * Reactivate counterparty
     */
    public static function reactivate($cp_id)
    {
        db_query(
            "UPDATE Counterparty SET ApprovalStatus = 'Active' WHERE CounterpartyID = ?",
            [$cp_id]
        );
        // Re-activate contact accounts
        db_query(
            "UPDATE Account a
             JOIN Contact c ON c.AccountID = a.AccountID
             SET a.Status = 'Active'
             WHERE c.CounterpartyID = ? AND a.Status = 'Inactive'",
            [$cp_id]
        );
    }

    /**
     * Notify proposer
     */
    public static function notifyProposer($proposer_staff_id, $cp_id, $title, $message, $type)
    {
        $proposer_acc = db_query(
            "SELECT AccountID FROM Staff WHERE StaffID = ?",
            [$proposer_staff_id]
        )->fetchColumn();

        if ($proposer_acc) {
            db_query(
                "INSERT INTO Notification (AccountID, CounterpartyID, Title, Message, NotificationType, IsRead, CreatedAt)
                 VALUES (?, ?, ?, ?, ?, 0, NOW())",
                [$proposer_acc, $cp_id, $title, $message, $type]
            );
        }
    }
}
