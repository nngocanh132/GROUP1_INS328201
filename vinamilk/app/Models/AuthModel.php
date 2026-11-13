<?php
/**
 * AuthModel
 *
 * Handles database operations for authentication.
 * All queries extracted verbatim from login.php.
 */
class AuthModel
{
    /**
     * Find an account by email address with joined role/staff/department data.
     *
     * @param string $email
     * @return array|false  Account row or false if not found
     */
    public function findByEmail(string $email): array|false
    {
        $stmt = db_query("
            SELECT
                a.AccountID,
                a.Email,
                a.PasswordHash,
                a.Status,
                r.RoleName,
                s.StaffID,
                s.FullName,
                s.Position,
                d.DepartmentName
            FROM Account a
            JOIN Role r ON r.RoleID = a.RoleID
            LEFT JOIN Staff s ON s.AccountID = a.AccountID
            LEFT JOIN Department d ON d.DepartmentID = s.DepartmentID
            WHERE a.Email = ?
            LIMIT 1
        ", [$email]);

        return $stmt->fetch();
    }

    /**
     * Update last login timestamp for an account.
     *
     * @param int $account_id
     * @return void
     */
    public function updateLastLogin(int $account_id): void
    {
        db_query(
            "UPDATE Account SET LastLoginAt = NOW() WHERE AccountID = ?",
            [$account_id]
        );
    }
}
