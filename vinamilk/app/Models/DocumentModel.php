<?php
/**
 * DocumentModel
 *
 * Document access and file operations
 */

class DocumentModel
{
    /**
     * Get document with version and contract info
     */
    public static function getDocumentWithContract($doc_id)
    {
        return db_query(
            "SELECT d.DocumentID, d.DocumentName, d.DocumentType, d.ContractID, d.IsVisibleToPartner,
                    c.CounterpartyID, c.ContractCode,
                    cv.FilePath, cv.FileSize, cv.VersionNumber
             FROM Document d
             JOIN Contract c         ON c.ContractID   = d.ContractID
             LEFT JOIN ContractVersion cv ON cv.DocumentID = d.DocumentID AND cv.IsCurrent = 1
             WHERE d.DocumentID = ?",
            [$doc_id]
        )->fetch();
    }

    /**
     * Check if external user has access to document's counterparty
     */
    public static function externalUserHasAccess($account_id, $counterparty_id)
    {
        return (bool) db_query(
            "SELECT 1 FROM Contact WHERE AccountID = ? AND CounterpartyID = ?",
            [$account_id, $counterparty_id]
        )->fetchColumn();
    }

    /**
     * Get available contracts for upload (non-closed)
     */
    public static function getAvailableContracts()
    {
        return db_query(
            "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status
             FROM Contract c
             WHERE c.Status NOT IN ('Closed')
             ORDER BY c.UpdatedAt DESC
             LIMIT 200"
        )->fetchAll();
    }

    /**
     * Get contract by ID
     */
    public static function getContractById($contract_id)
    {
        return db_query(
            "SELECT ContractID, ContractCode, ContractTitle, Status FROM Contract WHERE ContractID = ?",
            [$contract_id]
        )->fetch();
    }

    /**
     * Insert new document
     */
    public static function insertDocument($contract_id, $doc_type, $doc_name, $is_visible)
    {
        db_query(
            "INSERT INTO Document (ContractID, DocumentType, DocumentName, IsVisibleToPartner, CreatedAt)
             VALUES (?, ?, ?, ?, NOW())",
            [$contract_id, $doc_type, $doc_name, $is_visible]
        );
        return (int)get_db()->lastInsertId();
    }

    /**
     * Insert contract version
     */
    public static function insertVersion($document_id, $version_num, $file_path, $file_size, $file_hash, $uploaded_by, $change_summary)
    {
        db_query(
            "INSERT INTO ContractVersion
                 (DocumentID, VersionNumber, FilePath, FileSize, FileHash,
                  UploadedBy, ChangeSummary, IsCurrent, UploadedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [$document_id, $version_num, $file_path, $file_size, $file_hash, $uploaded_by, $change_summary]
        );
    }

    /**
     * Get documents with filters and pagination
     */
    public static function getDocumentsFiltered($user, $filters, $page, $page_size)
    {
        $where  = ['1=1'];
        $params = [];

        // External user restriction
        if ($user['role'] === 'external') {
            $where[]  = 'doc.IsVisibleToPartner = 1';
            $where[]  = 'co.AccountID = ?';
            $params[] = $user['account_id'];
        }

        // Search query
        if (!empty($filters['q'])) {
            $where[]  = '(doc.DocumentName LIKE ? OR c.ContractCode LIKE ? OR c.ContractTitle LIKE ?)';
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
            $params[] = "%{$filters['q']}%";
        }

        // Document type filter
        if (!empty($filters['type'])) {
            $where[]  = 'doc.DocumentType = ?';
            $params[] = $filters['type'];
        }

        // Contract filter
        if (!empty($filters['contract'])) {
            $where[]  = 'doc.ContractID = ?';
            $params[] = $filters['contract'];
        }

        $where_sql = implode(' AND ', $where);
        $join_contact = ($user['role'] === 'external') ? "JOIN Contact co ON co.CounterpartyID = c.CounterpartyID" : "";

        $sql = "
            SELECT doc.DocumentID, doc.DocumentName, doc.DocumentType,
                   doc.IsVisibleToPartner, doc.CreatedAt,
                   c.ContractID, c.ContractCode, c.ContractTitle,
                   cv.VersionNumber, cv.FileSize, cv.FilePath, cv.UploadedAt,
                   COALESCE(s.FullName, a.Email) AS UploaderName
            FROM Document doc
            JOIN Contract c ON c.ContractID = doc.ContractID
            $join_contact
            LEFT JOIN ContractVersion cv ON cv.DocumentID = doc.DocumentID AND cv.IsCurrent = 1
            LEFT JOIN Account a ON a.AccountID = cv.UploadedBy
            LEFT JOIN Staff s ON s.AccountID = cv.UploadedBy
            WHERE $where_sql
            ORDER BY doc.CreatedAt DESC
        ";

        return paginate($sql, $params, $page, $page_size);
    }

    /**
     * Get document type counts
     */
    public static function getTypeCounts($user)
    {
        $join_contact = ($user['role'] === 'external') ? "JOIN Contact co ON co.CounterpartyID = c.CounterpartyID AND co.AccountID = " . (int)$user['account_id'] : "";
        $where = ($user['role'] === 'external') ? "WHERE doc.IsVisibleToPartner = 1" : "";

        $tc_rows = db_query("
            SELECT doc.DocumentType, COUNT(*) AS cnt
            FROM Document doc
            JOIN Contract c ON c.ContractID = doc.ContractID
            $join_contact
            $where
            GROUP BY doc.DocumentType
        ")->fetchAll();

        $counts = [];
        foreach ($tc_rows as $r) {
            $counts[$r['DocumentType']] = (int)$r['cnt'];
        }
        return $counts;
    }
}
