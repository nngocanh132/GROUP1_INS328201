<?php
/**
 * ContractController
 *
 * Contract listing and detail pages
 */

class ContractController
{
    /**
     * Contract listing page
     */
    public static function index()
    {
        require_login();
        $user = current_user();

        // Filters
        $q             = trim($_GET['q']      ?? '');
        $filter_status = trim($_GET['status'] ?? '');
        $filter_type   = (int)($_GET['type']  ?? 0);
        $page          = max(1, (int)($_GET['page'] ?? 1));

        $allowed_statuses = ['Draft','PendingReview','PendingApproval','RevisionRequested','Approved','Active','Closed'];
        if (!in_array($filter_status, $allowed_statuses)) $filter_status = '';

        $filters = [
            'q' => $q,
            'status' => $filter_status,
            'type' => $filter_type,
        ];

        // Query contracts
        $result = ContractModel::getFiltered($user, $filters, $page, DEFAULT_PAGE_SIZE);
        $contracts = $result['rows'];

        // Get contract types for filter
        $contract_types = get_contract_types();

        // Display view
        require __DIR__ . '/../Views/contracts/index.php';
    }

    /**
     * Contract detail page
     */
    public static function show($contract_id)
    {
        require_login();
        $user = current_user();

        $id = (int)$contract_id;
        if (!$id) {
            redirect_with_flash(BASE_URL . '/pages/contracts.php', 'danger', 'No contract ID specified.');
        }

        // Handle POST workflow actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handleWorkflowActions($id, $user);
        }

        $contract = ContractModel::getById($id);
        if (!$contract) {
            redirect_with_flash(BASE_URL . '/pages/contracts.php', 'danger', 'Contract not found.');
        }

        // Access control for external users
        if ($user['role'] === 'external') {
            $ext_cp_id = (int) db_query(
                "SELECT c.CounterpartyID FROM Contact c
                 JOIN Account a ON a.AccountID = c.AccountID
                 WHERE c.AccountID = ? AND a.Status = 'Active' LIMIT 1",
                [$user['account_id']]
            )->fetchColumn();

            if (!$ext_cp_id || (int)$ext_cp_id !== (int)$contract['CounterpartyID']) {
                error_log(sprintf(
                    'External access denied: account=%d tried contract=%d (cp=%d, allowed=%d)',
                    $user['account_id'], $id, $contract['CounterpartyID'], $ext_cp_id
                ));
                redirect_with_flash(BASE_URL . '/pages/contracts.php', 'danger', 'Access denied.');
            }
        }

        // Load related data
        $documents = db_query("
            SELECT doc.*,
                   cv.VersionNumber, cv.FilePath, cv.FileSize, cv.UploadedAt,
                   COALESCE(s.FullName, a.Email) AS UploaderName
            FROM Document doc
            LEFT JOIN ContractVersion cv ON cv.DocumentID=doc.DocumentID AND cv.IsCurrent=1
            LEFT JOIN Account a ON a.AccountID=cv.UploadedBy
            LEFT JOIN Staff s   ON s.AccountID=cv.UploadedBy
            WHERE doc.ContractID=? ORDER BY doc.DocumentID
        ", [$id])->fetchAll();

        $history = db_query("
            SELECT sh.*, COALESCE(s.FullName, a.Email) AS ActorName
            FROM StatusHistory sh
            JOIN Account a ON a.AccountID=sh.ChangedBy
            LEFT JOIN Staff s ON s.AccountID=sh.ChangedBy
            WHERE sh.ContractID=? ORDER BY sh.ChangedAt ASC
        ", [$id])->fetchAll();

        $reviews = db_query("
            SELECT r.*, COALESCE(s.FullName, a.Email) AS ReviewerName
            FROM Review r
            JOIN Account a ON a.AccountID=r.ReviewerAccountID
            LEFT JOIN Staff s ON s.AccountID=r.ReviewerAccountID
            WHERE r.ContractID=? ORDER BY r.SubmittedAt DESC
        ", [$id])->fetchAll();

        

        $approvals = db_query("
            SELECT ap.*, COALESCE(s.FullName, a.Email) AS ApproverName
            FROM Approval ap
            JOIN Account a ON a.AccountID=ap.ApproverAccountID
            LEFT JOIN Staff s ON s.AccountID=ap.ApproverAccountID
            WHERE ap.ContractID=? ORDER BY ap.SubmittedAt DESC
        ", [$id])->fetchAll();

        // Determine action button visibility
        $owner_account_id = db_query(
            "SELECT AccountID FROM Staff WHERE StaffID=?",
            [$contract['OwnerStaffID']]
        )->fetchColumn();

        $is_owner = (int)$owner_account_id === (int)$user['account_id'];

        $my_pending_review = db_query(
            "SELECT ReviewID FROM Review WHERE ContractID=? AND ReviewerAccountID=? AND ReviewStatus='Pending'",
            [$id, $user['account_id']]
        )->fetch();

        $pending_approval = db_query(
            "SELECT ApprovalID FROM Approval WHERE ContractID=? AND ApprovalStatus='Pending'",
            [$id]
        )->fetch();

        $show_submit_for_review = ($is_owner || $user['role'] === 'admin')
                                  && in_array($contract['Status'], ['Draft', 'RevisionRequested']);
        $show_complete_review   = ($my_pending_review || $user['role'] === 'admin')
                                  && $contract['Status'] === 'PendingReview';
        $show_approve_link      = $user['role'] === 'admin'
                                  && in_array($contract['Status'], ['PendingReview', 'PendingApproval']);
        $show_set_active        = $user['role'] === 'admin' && $contract['Status'] === 'Approved';

        $page_title  = $contract['ContractCode'];
        $active_nav  = 'contracts';
        $breadcrumbs = [
            ['label' => 'Contracts', 'url' => BASE_URL . '/pages/contracts.php'],
            ['label' => $contract['ContractCode']],
        ];

        require __DIR__ . '/../Views/contracts/show.php';
    }

    /**
     * Handle workflow POST actions for a contract
     */
    private static function handleWorkflowActions($id, $user)
    {
        if (!verify_csrf()) {
            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger', 'Security token mismatch.');
        }

        $post_action = trim($_POST['post_action'] ?? '');

        // Staff: submit draft for review
        if ($post_action === 'submit_for_review') {
            $contract = db_query("SELECT * FROM Contract WHERE ContractID=?", [$id])->fetch();
            if (!$contract || !in_array($contract['Status'], ['Draft', 'RevisionRequested'])) {
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger',
                    'Contract cannot be submitted for review at this stage.');
            }
            $owner_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID=?", [$contract['OwnerStaffID']])->fetchColumn();
            if ($user['role'] !== 'admin' && (int)$owner_acc !== (int)$user['account_id']) {
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger', 'Access denied.');
            }
            if (!$contract['StartDate'] || !$contract['EndDate']) {
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger',
                    'Contract must have Start Date and End Date before it can be submitted for review.');
            }
            if (!$contract['Summary']) {
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger',
                    'Contract must have a Summary before it can be submitted for review.');
            }

            $db = get_db();
            $db->beginTransaction();
            try {
                $old_status = $contract['Status'];
                db_query("UPDATE Contract SET Status='PendingReview', UpdatedAt=NOW() WHERE ContractID=?", [$id]);
                record_status_change($id, $user['account_id'], $old_status, 'PendingReview', 'Submitted for legal review');

                $legal_reviewer = db_query(
                    "SELECT a.AccountID FROM Staff s
                     JOIN Account a ON a.AccountID=s.AccountID
                     JOIN Role r ON r.RoleID=a.RoleID
                     JOIN Department d ON d.DepartmentID=s.DepartmentID
                     WHERE d.DepartmentName='Legal' AND a.Status='Active'
                       AND r.RoleName IN ('Manager/Admin','Staff') AND s.StaffID!=?
                     ORDER BY r.RoleID DESC, s.StaffID ASC LIMIT 1",
                    [$contract['OwnerStaffID']]
                )->fetchColumn();

                if (!$legal_reviewer) {
                    $legal_reviewer = db_query(
                        "SELECT a.AccountID FROM Account a JOIN Role r ON r.RoleID=a.RoleID
                         WHERE r.RoleName='Manager/Admin' AND a.Status='Active' LIMIT 1"
                    )->fetchColumn();
                }

                db_query("UPDATE Review SET ReviewStatus='Rejected', Comments='Superseded by resubmission', ReviewedAt=NOW()
                          WHERE ContractID=? AND ReviewStatus='Pending'", [$id]);

                if ($legal_reviewer) {
                    db_query("INSERT INTO Review (ContractID, ReviewerAccountID, ReviewStatus, SubmittedAt) VALUES (?,?,'Pending',NOW())",
                        [$id, $legal_reviewer]);
                    db_query("INSERT INTO Notification (AccountID, ContractID, Title, Message, NotificationType, IsRead, CreatedAt)
                              VALUES (?,?,?,?,'Info',0,NOW())",
                        [$legal_reviewer, $id, 'Contract submitted for legal review',
                         "Contract {$contract['ContractCode']} has been submitted for legal review."]);
                }

                $existing_req = db_query(
                    "SELECT RequestID FROM ContractRequest WHERE ContractID=? AND RequestType='InternalApproval' AND Status='InProgress'",
                    [$id]
                )->fetchColumn();
                if (!$existing_req) {
                    db_query(
                        "INSERT INTO ContractRequest (ContractID, RequestedBy, RequestType, AssignedSide, Status, Description, RequestedAt)
                         VALUES (?,?,'InternalApproval','Internal','InProgress',?,NOW())",
                        [$id, $user['account_id'], 'Contract submitted for review and approval: ' . $contract['ContractTitle']]
                    );
                }

                $db->commit();
                audit('SUBMIT_FOR_REVIEW', 'Contract', $id, ['from' => $old_status]);
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'success',
                    'Contract submitted for legal review.');
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('submit_for_review error: ' . $e->getMessage());
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger',
                    'An error occurred: ' . $e->getMessage());
            }
        }

        // Admin: set contract Active
        elseif ($post_action === 'set_active') {
            if ($user['role'] !== 'admin') {
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger', 'Access denied.');
            }
            $contract = db_query("SELECT Status FROM Contract WHERE ContractID=?", [$id])->fetch();
            if (!$contract || $contract['Status'] !== 'Approved') {
                redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger',
                    'Only Approved contracts can be set to Active.');
            }
            db_query("UPDATE Contract SET Status='Active', UpdatedAt=NOW() WHERE ContractID=?", [$id]);
            record_status_change($id, $user['account_id'], 'Approved', 'Active', 'Manually activated by admin');
            audit('SET_ACTIVE', 'Contract', $id, []);
            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'success', 'Contract set to Active.');
        }
    }
}
