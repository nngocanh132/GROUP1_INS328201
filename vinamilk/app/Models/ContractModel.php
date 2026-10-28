<?php
/**
 * ContractModel
 *
 * Contract data access
 */

class ContractModel
{
    /**
     * Get contracts with filters and pagination
     */
    public static function getFiltered($user, $filters, $page, $page_size)
    {
        $where  = ['1=1'];
        $params = [];

        // External user restriction
        if ($user['role'] === 'external') {
            $ext_counterparty_id = (int) db_query(
                "SELECT CounterpartyID FROM Contact WHERE AccountID = ? LIMIT 1",
                [$user['account_id']]
            )->fetchColumn();

            if (!$ext_counterparty_id) {
                $where[] = '0=1'; // No counterparty = no contracts
            } else {
                $where[]  = 'c.CounterpartyID = ?';
                $params[] = $ext_counterparty_id;
            }
        }

        // Search query
        if (!empty($filters['q'])) {
            $where[]  = '(c.ContractTitle LIKE ? OR c.ContractCode LIKE ?)';
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[]  = 'c.Status = ?';
            $params[] = $filters['status'];
        }

        // Type filter
        if (!empty($filters['type'])) {
            $where[]  = 'c.ContractTypeID = ?';
            $params[] = $filters['type'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
                   c.ContractValue, c.Currency, c.StartDate, c.EndDate,
                   c.CreatedAt, c.UpdatedAt,
                   ct.TypeName, cp.CompanyName AS CounterpartyName,
                   s.FullName AS OwnerName, d2.DepartmentName
            FROM Contract c
            JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
            JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
            JOIN Staff s          ON s.StaffID         = c.OwnerStaffID
            JOIN Department d2    ON d2.DepartmentID   = c.DepartmentID
            WHERE $where_sql
            ORDER BY c.UpdatedAt DESC
        ";

        return paginate($sql, $params, $page, $page_size);
    }

    /**
     * Get contract by ID (will be extended for detail page)
     */
    public static function getById($contract_id)
    {
        return db_query(
            "SELECT c.*, ct.TypeName, cp.CompanyName AS CounterpartyName,
                    s.FullName AS OwnerName, d.DepartmentName
             FROM Contract c
             JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
             JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
             JOIN Staff s         ON s.StaffID         = c.OwnerStaffID
             JOIN Department d    ON d.DepartmentID    = c.DepartmentID
             WHERE c.ContractID = ?",
            [$contract_id]
        )->fetch();
    }
}
