<?php
/**
 * ApprovalModel
 *
 * Approval workflow data access
 */

class ApprovalModel
{
    /**
     * Get pending approvals for current user
     */
    public static function getPendingForUser($user)
    {
        if ($user['role'] !== 'admin') {
            return [];
        }

        return db_query("
            SELECT a.*, c.ContractCode, c.ContractTitle, c.Status AS ContractStatus,
                   s.FullName AS RequesterName
            FROM Approval a
            JOIN Contract c ON c.ContractID = a.ContractID
            LEFT JOIN Staff s ON s.StaffID = a.RequestedBy
            WHERE a.ApprovalStatus = 'Pending'
            ORDER BY a.RequestedAt DESC
        ")->fetchAll();
    }

    /**
     * Get approval by ID
     */
    public static function getById($approval_id)
    {
        return db_query(
            "SELECT a.*, c.ContractCode, c.ContractTitle, c.Status AS ContractStatus
             FROM Approval a
             JOIN Contract c ON c.ContractID = a.ContractID
             WHERE a.ApprovalID = ?",
            [$approval_id]
        )->fetch();
    }

    /**
     * Approve
     */
    public static function approve($approval_id, $staff_id, $comments = null)
    {
        db_query(
            "UPDATE Approval
             SET ApprovalStatus = 'Approved', DecidedBy = ?, DecidedAt = NOW(), Comments = ?
             WHERE ApprovalID = ?",
            [$staff_id, $comments, $approval_id]
        );
    }

    /**
     * Reject
     */
    public static function reject($approval_id, $staff_id, $comments)
    {
        db_query(
            "UPDATE Approval
             SET ApprovalStatus = 'Rejected', DecidedBy = ?, DecidedAt = NOW(), Comments = ?
             WHERE ApprovalID = ?",
            [$staff_id, $comments, $approval_id]
        );
    }
}
