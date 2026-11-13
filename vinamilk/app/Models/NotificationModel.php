<?php
/**
 * NotificationModel
 *
 * Handles all database operations for the Notification table.
 * All queries extracted verbatim from pages/notifications.php.
 *
 * Security: every query filters by AccountID to enforce user isolation.
 */
class NotificationModel
{
    /**
     * Fetch notifications for a specific user, with optional filter.
     *
     * @param int $account_id  Current user's AccountID
     * @param string $filter   'all' or 'unread'
     * @return array           Notification rows with joined Contract data
     */
    public function getByAccountId(int $account_id, string $filter = 'all'): array
    {
        $where  = 'AccountID = ?';
        $params = [$account_id];

        if ($filter === 'unread') {
            $where .= ' AND IsRead = 0';
        }

        return db_query(
            "SELECT n.*,
                    c.ContractCode, c.ContractTitle
             FROM Notification n
             LEFT JOIN Contract c ON c.ContractID = n.ContractID
             WHERE $where
             ORDER BY n.CreatedAt DESC
             LIMIT 100",
            $params
        )->fetchAll();
    }

    /**
     * Count unread notifications for a user.
     *
     * @param int $account_id
     * @return int
     */
    public function countUnread(int $account_id): int
    {
        return (int) db_query(
            "SELECT COUNT(*)
             FROM Notification
             WHERE AccountID = ? AND IsRead = 0",
            [$account_id]
        )->fetchColumn();
    }

    /**
     * Mark a single notification as read.
     *
     * Only marks if it belongs to the given account (prevents hijacking).
     *
     * @param int $notification_id
     * @param int $account_id
     * @return void
     */
    public function markAsRead(int $notification_id, int $account_id): void
    {
        db_query(
            "UPDATE Notification
             SET IsRead = 1, ReadAt = NOW()
             WHERE NotificationID = ? AND AccountID = ?",
            [$notification_id, $account_id]
        );
    }

    /**
     * Mark ALL unread notifications as read for a user.
     *
     * @param int $account_id
     * @return void
     */
    public function markAllAsRead(int $account_id): void
    {
        db_query(
            "UPDATE Notification
             SET IsRead = 1, ReadAt = NOW()
             WHERE AccountID = ? AND IsRead = 0",
            [$account_id]
        );
    }
}
