<?php
/**
 * SettingsModel
 *
 * User account management for admin
 */

class SettingsModel
{
    /**
     * Get account by ID
     */
    public static function getAccountById($account_id)
    {
        return db_query(
            "SELECT a.AccountID, a.Email, a.Status, r.RoleName
             FROM Account a JOIN Role r ON r.RoleID = a.RoleID
             WHERE a.AccountID = ?",
            [$account_id]
        )->fetch();
    }

    /**
     * Get all accounts with staff/partner info
     */
    public static function getAllAccounts()
    {
        return db_query("
            SELECT a.AccountID, a.Email, a.Status, a.LastLoginAt, a.CreatedAt,
                   r.RoleName,
                   s.StaffID, s.FullName, s.Position,
                   d.DepartmentName,
                   c.CounterpartyID, cp.CompanyName AS PartnerCompany
            FROM Account a
            JOIN Role r ON r.RoleID = a.RoleID
            LEFT JOIN Staff   s  ON s.AccountID  = a.AccountID
            LEFT JOIN Department d ON d.DepartmentID = s.DepartmentID
            LEFT JOIN Contact  c  ON c.AccountID  = a.AccountID
            LEFT JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
            ORDER BY
                CASE a.Status WHEN 'Pending' THEN 0 ELSE 1 END,
                r.RoleID ASC,
                COALESCE(s.FullName, a.Email)
        ")->fetchAll();
    }

    /**
     * Activate account
     */
    public static function activateAccount($account_id)
    {
        db_query("UPDATE Account SET Status = 'Active' WHERE AccountID = ?", [$account_id]);
    }

    /**
     * Deactivate account
     */
    public static function deactivateAccount($account_id)
    {
        db_query("UPDATE Account SET Status = 'Inactive' WHERE AccountID = ?", [$account_id]);
    }

    /**
     * Count pending accounts
     */
    public static function countPending($accounts)
    {
        return count(array_filter($accounts, fn($a) => $a['Status'] === 'Pending'));
    }
}
