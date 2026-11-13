<?php
/**
 * Shared utility / helper functions
 * Adapted for vinamilk_clm schema.
 */

function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float|int|null $amount, string $currency = 'VND'): string {
    if ($amount === null) return '—';
    if ($currency === 'VND') {
        return number_format($amount, 0, '.', ',') . ' ₫';
    }
    return number_format($amount, 2, '.', ',') . ' ' . strtoupper($currency);
}

function fmt_date(?string $d): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d M Y'); }
    catch (Exception) { return $d; }
}

function fmt_datetime(?string $d): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d M Y, H:i'); }
    catch (Exception) { return $d; }
}

/**
 * Status badge — maps Contract.Status and Review/Approval statuses
 * from the vinamilk_clm schema.
 */
function status_badge(string $status): string {
    $map = [
        // Contract statuses (PascalCase from schema)
        'Draft'              => ['label' => 'Draft',              'class' => 'badge-draft'],
        'PendingReview'      => ['label' => 'Pending Review',     'class' => 'badge-review'],
        'PendingApproval'    => ['label' => 'Pending Approval',   'class' => 'badge-pending'],
        'RevisionRequested'  => ['label' => 'Revision Requested', 'class' => 'badge-cancelled'],
        'Approved'           => ['label' => 'Approved',           'class' => 'badge-approved'],
        'Active'             => ['label' => 'Active',             'class' => 'badge-active'],
        'Closed'             => ['label' => 'Closed',             'class' => 'badge-draft'],
        // Review / Approval statuses
        'Pending'            => ['label' => 'Pending',            'class' => 'badge-pending'],
        'InProgress'         => ['label' => 'In Progress',        'class' => 'badge-review'],
        'Completed'          => ['label' => 'Completed',          'class' => 'badge-approved'],
        'Cancelled'          => ['label' => 'Cancelled',          'class' => 'badge-cancelled'],
        'Rejected'           => ['label' => 'Rejected',           'class' => 'badge-rejected'],
        'RevisionRequired'   => ['label' => 'Revision Required',  'class' => 'badge-cancelled'],
        // Counterparty statuses
        'Suspended'          => ['label' => 'Suspended',          'class' => 'badge-rejected'],
    ];
    $info = $map[$status] ?? ['label' => $status, 'class' => 'badge-draft'];
    return '<span class="badge ' . $info['class'] . '">' . e($info['label']) . '</span>';
}

/**
 * Return a user-facing label for a ContractType TypeName (already a plain string in the DB).
 */
function contract_type_label(string $type): string {
    return $type; // TypeName is already readable in vinamilk_clm
}

/**
 * Return a display label for the normalised role key used in the session.
 */
function role_label(string $role): string {
    return match($role) {
        'admin'    => 'Manager / Admin',
        'staff'    => 'Staff',
        'external' => 'External (Partner)',
        default    => ucfirst($role),
    };
}

/**
 * Generate a unique ContractCode.
 * Format: VM-YYYY-NNN  (matches the existing VM-2026-001 pattern)
 *
 * Root cause of the previous duplicate-key bug:
 *   The old implementation used COUNT(*) to determine the next
 *   sequence number.  COUNT only tells you how many rows exist, NOT
 *   what the highest existing suffix is.  If the seed data already
 *   contains VM-2026-006 and there are 9 total contracts, COUNT+1=10
 *   generates VM-2026-010 — but VM-2026-006 was already allocated
 *   to an earlier contract.  On other calls the count could produce
 *   a number that matches an already-existing code, causing the
 *   1062 Duplicate entry error.
 *
 * Fix:
 *   1. Extract the MAX numeric suffix from existing codes for the year.
 *   2. Try MAX+1, MAX+2, … until a code that does not yet exist is found.
 *   3. Wrap the final INSERT in a transaction; if a concurrent process
 *      races us and takes the same code first, the outer catch block
 *      in create_request.php retries the whole transaction (the unique
 *      constraint on ContractCode is the ultimate safety net).
 */
function generate_contract_code(): string {
    $year   = date('Y');
    $prefix = 'VM-' . $year . '-';

    // Find the highest numeric suffix already used this year.
    // REGEXP_SUBSTR requires MySQL 8.0+; fall back to a PHP-side approach.
    $existing = db_query(
        "SELECT ContractCode FROM Contract
         WHERE ContractCode LIKE ?
         ORDER BY ContractCode DESC",
        [$prefix . '%']
    )->fetchAll(PDO::FETCH_COLUMN);

    $max_num = 0;
    foreach ($existing as $code) {
        $suffix = substr($code, strlen($prefix));
        if (is_numeric($suffix)) {
            $max_num = max($max_num, (int)$suffix);
        }
    }

    // Walk forward from MAX+1 until we find a code that does not exist.
    $next = $max_num + 1;
    do {
        $candidate = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
        $taken     = db_query(
            "SELECT 1 FROM Contract WHERE ContractCode = ?",
            [$candidate]
        )->fetchColumn();
        if (!$taken) {
            return $candidate;
        }
        $next++;
    } while ($next <= 9999); // hard upper bound to prevent infinite loops

    // Fallback (should never be reached in practice)
    return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
}

/**
 * Log an audit entry to AuditLog table.
 * AuditLog schema: AccountID, Action, EntityName, EntityID, Details (JSON), IPAddress
 */
function audit(string $action, string $entity_name, ?int $entity_id, ?array $details = null): void {
    try {
        $user = current_user();
        db_query(
            "INSERT INTO AuditLog (AccountID, Action, EntityName, EntityID, Details, IPAddress)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $user['account_id'] ?: null,
                $action,
                $entity_name,
                $entity_id,
                $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        );
    } catch (Throwable) {
        // Non-fatal
    }
}

/**
 * Redirect with a one-time flash message.
 */
function redirect_with_flash(string $url, string $type, string $message): never {
    session_start_safe();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    header('Location: ' . $url);
    exit;
}

function consume_flash(): ?array {
    session_start_safe();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Return JSON response and exit.
 */
function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Paginate a query — returns ['rows', 'total', 'pages', 'current', 'per_page']
 */
function paginate(string $sql, array $params, int $page, int $per_page): array {
    $count_sql = 'SELECT COUNT(*) FROM (' . $sql . ') AS _pagination_count';
    $total     = (int) db_query($count_sql, $params)->fetchColumn();
    $pages     = max(1, (int) ceil($total / $per_page));
    $page      = max(1, min($page, $pages));
    $offset    = ($page - 1) * $per_page;
    $rows      = db_query($sql . ' LIMIT ' . (int)$per_page . ' OFFSET ' . (int)$offset, $params)->fetchAll();
    return [
        'rows'    => $rows,
        'total'   => $total,
        'pages'   => $pages,
        'current' => $page,
        'per_page'=> $per_page,
    ];
}

/**
 * Fetch all active ContractTypes for form selects.
 */
function get_contract_types(): array {
    return db_query(
        "SELECT ContractTypeID, TypeName FROM ContractType WHERE IsActive = 1 ORDER BY ContractTypeID"
    )->fetchAll();
}

/**
 * Fetch all Departments for form selects.
 */
function get_departments(): array {
    return db_query(
        "SELECT DepartmentID, DepartmentName FROM Department ORDER BY DepartmentName"
    )->fetchAll();
}

/**
 * Fetch all approved Counterparties for form selects.
 */
function get_counterparties(): array {
    return db_query(
        "SELECT CounterpartyID, CounterpartyCode, CompanyName FROM Counterparty
         WHERE ApprovalStatus = 'Active' ORDER BY CompanyName"
    )->fetchAll();
}

/**
 * Fetch internal staff (Staff role) for owner dropdowns.
 */
function get_internal_staff(): array {
    return db_query(
        "SELECT s.StaffID, s.FullName, s.Position, d.DepartmentName
         FROM Staff s
         JOIN Account a ON a.AccountID = s.AccountID
         JOIN Role r ON r.RoleID = a.RoleID
         JOIN Department d ON d.DepartmentID = s.DepartmentID
         WHERE a.Status = 'Active' AND r.RoleName IN ('Manager/Admin','Staff')
         ORDER BY s.FullName"
    )->fetchAll();
}

/**
 * Record a StatusHistory entry for a contract.
 */
function record_status_change(
    int $contract_id, int $account_id,
    ?string $old_status, string $new_status, ?string $reason = null
): void {
    try {
        db_query(
            "INSERT INTO StatusHistory (ContractID, ChangedBy, OldStatus, NewStatus, ChangeReason)
             VALUES (?, ?, ?, ?, ?)",
            [$contract_id, $account_id, $old_status, $new_status, $reason]
        );
    } catch (Throwable) {
        // Non-fatal
    }
}

/**
 * Check whether a table exists in the current database.
 * Used to gracefully degrade when the migration hasn't been run yet.
 */
function db_table_exists(string $table): bool {
    try {
        $row = db_query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        )->fetchColumn();
        return (int)$row > 0;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Status badge for ContractProposal.Status values.
 * Defined here (not in individual pages) so it's available everywhere.
 */
function proposal_badge(string $status): string {
    $map = [
        'Pending'         => ['Pending Partner Review', 'badge-pending'],
        'PartnerApproved' => ['Partner Approved',       'badge-review'],
        'PartnerRejected' => ['Revision Requested',     'badge-cancelled'],
        'AdminApproved'   => ['Admin Approved',         'badge-approved'],
        'AdminRejected'   => ['Admin Rejected',         'badge-rejected'],
        'ContractCreated' => ['Contract Created',       'badge-active'],
        'Withdrawn'       => ['Withdrawn',              'badge-draft'],
    ];
    [$label, $class] = $map[$status] ?? [$status, 'badge-draft'];
    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES) . '</span>';
}

/**
 * Generate a unique ContractProposal code. Format: CP-YYYY-NNN
 */
function generate_proposal_code(): string {
    $year  = date('Y');
    $count = (int) db_query(
        "SELECT COUNT(*) FROM ContractProposal WHERE YEAR(CreatedAt) = ?",
        [$year]
    )->fetchColumn();
    $code = 'CP-' . $year . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    while (db_query("SELECT 1 FROM ContractProposal WHERE ProposalCode = ?", [$code])->fetchColumn()) {
        $count++;
        $code = 'CP-' . $year . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
    return $code;
}
