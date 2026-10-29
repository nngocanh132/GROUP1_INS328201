<?php
/**
 * RequestModel
 *
 * Contract request data access (if ContractProposal table exists)
 */

class RequestModel
{
    /**
     * Check if ContractProposal table exists
     */
    public static function tableExists()
    {
        return db_table_exists('ContractProposal');
    }

    /**
     * Get requests with filters
     */
    public static function getFiltered($user, $filters, $page, $page_size)
    {
        if (!self::tableExists()) {
            return ['rows' => [], 'total' => 0, 'pages' => 0];
        }

        $where  = ['1=1'];
        $params = [];

        // Role-based filtering
        if ($user['role'] === 'staff') {
            $where[]  = 'p.ProposedByStaffID = ?';
            $params[] = $user['staff_id'];
        }

        // Search
        if (!empty($filters['q'])) {
            $where[]  = '(p.ProposalTitle LIKE ? OR p.ProposalCode LIKE ?)';
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[]  = 'p.Status = ?';
            $params[] = $filters['status'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT p.*, ct.TypeName, cp.CompanyName,
                   s.FullName AS ProposerName
            FROM ContractProposal p
            JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
            JOIN Counterparty cp ON cp.CounterpartyID = p.CounterpartyID
            JOIN Staff s         ON s.StaffID         = p.ProposedByStaffID
            WHERE $where_sql
            ORDER BY p.CreatedAt DESC
        ";

        return paginate($sql, $params, $page, $page_size);
    }
}
