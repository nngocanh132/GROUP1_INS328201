<?php
/**
 * RequestController
 *
 * Handles both tabs:
 *   Tab 1 — Contract Proposals (ContractProposal table)
 *   Tab 2 — Workflow Requests  (ContractRequest table)
 */
class RequestController
{
    public static function index()
    {
        require_login();
        $user = current_user();

        if ($user['role'] === 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
        }

        $proposals_enabled = RequestModel::tableExists('ContractProposal');

        $errors   = [];
        $old      = [];
        $show_form = isset($_GET['new']) && $user['role'] === 'staff';

        // ----------------------------------------------------------------
        // Handle POST actions
        // ----------------------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                $errors[] = 'Security token mismatch. Please refresh and try again.';
                $show_form = true;
            } elseif (!$proposals_enabled) {
                redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger',
                    'The Contract Proposals feature requires a database migration. Please ask your admin to run vinamilk_clm_migration_001.sql in phpMyAdmin.');
            } else {
                $post_action = trim($_POST['post_action'] ?? '');

                // Admin: approve / reject proposal
                if ($post_action === 'admin_approve' || $post_action === 'admin_reject') {
                    self::handleAdminProposalAction($post_action, $user);

                // Staff: withdraw proposal
                } elseif ($post_action === 'withdraw') {
                    self::handleWithdraw($user);

                // Staff: create new proposal
                } elseif ($post_action === 'create_proposal') {
                    if ($user['role'] !== 'staff') {
                        redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger', 'Only staff can create proposals.');
                    }
                    [$errors, $old, $show_form] = self::handleCreateProposal($user);
                }
            }
        }

        // ----------------------------------------------------------------
        // Load display data
        // ----------------------------------------------------------------
        $tab = trim($_GET['tab'] ?? 'proposals');

        // Tab 1: Proposals
        $proposals          = [];
        $pending_admin      = 0;
        $prop_status_filter = trim($_GET['pstatus'] ?? '');
        $prop_allowed = ['Pending','PartnerApproved','PartnerRejected','AdminApproved','AdminRejected','ContractCreated','Withdrawn'];
        if (!in_array($prop_status_filter, $prop_allowed)) $prop_status_filter = '';

        if ($proposals_enabled) {
            $pw = ['1=1'];
            $pp = [];
            if ($user['role'] === 'staff') { $pw[] = 'p.ProposedByStaffID = ?'; $pp[] = $user['staff_id']; }
            if ($prop_status_filter)       { $pw[] = 'p.Status = ?';             $pp[] = $prop_status_filter; }

            $proposals = db_query(
                "SELECT p.*, ct.TypeName, cp.CompanyName AS CounterpartyName, cp.CounterpartyCode,
                        d.DepartmentName, s.FullName AS ProposerName
                 FROM ContractProposal p
                 JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
                 JOIN Counterparty cp ON cp.CounterpartyID = p.CounterpartyID
                 JOIN Department d   ON d.DepartmentID    = p.DepartmentID
                 JOIN Staff s        ON s.StaffID         = p.ProposedByStaffID
                 WHERE " . implode(' AND ', $pw) . "
                 ORDER BY CASE p.Status WHEN 'PartnerApproved' THEN 0 WHEN 'Pending' THEN 1 ELSE 2 END,
                          p.CreatedAt DESC",
                $pp
            )->fetchAll();

            if ($user['role'] === 'admin') {
                $pending_admin = (int) db_query(
                    "SELECT COUNT(*) FROM ContractProposal WHERE Status = 'PartnerApproved'"
                )->fetchColumn();
            }
        }

        // Tab 2: Workflow Requests
        $req_q    = trim($_GET['q']      ?? '');
        $req_st   = trim($_GET['status'] ?? '');
        $req_type = trim($_GET['type']   ?? '');
        $req_page = max(1, (int)($_GET['page'] ?? 1));
        $req_allowed_st   = ['Pending','InProgress','Completed','Cancelled'];
        $req_allowed_type = ['Revision','Supplement','ExternalReview','InternalApproval'];
        if (!in_array($req_st,   $req_allowed_st))   $req_st   = '';
        if (!in_array($req_type, $req_allowed_type)) $req_type = '';

        $rw = ['1=1'];
        $rp = [];
        if ($req_q)    { $rw[] = '(c.ContractTitle LIKE ? OR c.ContractCode LIKE ?)'; $rp[] = "%$req_q%"; $rp[] = "%$req_q%"; }
        if ($req_st)   { $rw[] = 'cr.Status = ?';      $rp[] = $req_st; }
        if ($req_type) { $rw[] = 'cr.RequestType = ?'; $rp[] = $req_type; }

        $req_result = paginate(
            "SELECT cr.*, c.ContractCode, c.ContractTitle, c.Status AS ContractStatus,
                    COALESCE(s.FullName, a.Email) AS RequesterName
             FROM ContractRequest cr
             JOIN Contract c ON c.ContractID = cr.ContractID
             JOIN Account a  ON a.AccountID  = cr.RequestedBy
             LEFT JOIN Staff s ON s.AccountID = cr.RequestedBy
             WHERE " . implode(' AND ', $rw) . "
             ORDER BY cr.RequestedAt DESC",
            $rp, $req_page, DEFAULT_PAGE_SIZE
        );

        // Form data for new proposal form
        $contract_types = get_contract_types();
        $departments    = get_departments();
        $counterparties = get_counterparties();

        require __DIR__ . '/../Views/requests/index.php';
    }

    // ----------------------------------------------------------------
    // Admin: approve or reject a PartnerApproved proposal
    // ----------------------------------------------------------------
    private static function handleAdminProposalAction(string $post_action, array $user): void
    {
        if ($user['role'] !== 'admin') {
            redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger', 'Only admins can perform this action.');
        }
        $prop_id = (int)($_POST['proposal_id'] ?? 0);
        $note    = trim($_POST['admin_note']   ?? '');
        $prop    = db_query("SELECT * FROM ContractProposal WHERE ProposalID = ?", [$prop_id])->fetch();

        if (!$prop || $prop['Status'] !== 'PartnerApproved') {
            redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'danger',
                'Proposal not found or not in the correct state for admin review.');
        }
        if ($post_action === 'admin_reject' && $note === '') {
            redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'danger',
                'A note is required when rejecting a proposal.');
        }

        $new_status = ($post_action === 'admin_approve') ? 'AdminApproved' : 'AdminRejected';
        db_query(
            "UPDATE ContractProposal SET Status = ?, AdminReviewNote = ?, AdminReviewedBy = ?, AdminReviewedAt = NOW() WHERE ProposalID = ?",
            [$new_status, $note ?: null, $user['account_id'], $prop_id]
        );

        $staff_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID = ?", [$prop['ProposedByStaffID']])->fetchColumn();
        if ($staff_acc) {
            $msg = ($post_action === 'admin_approve')
                ? "Your proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) was approved by Admin. You can now create the contract."
                : "Your proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) was rejected by Admin." . ($note ? " Reason: $note" : '');
            db_query(
                "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?, ?, ?, 'Info', 0, NOW())",
                [$staff_acc, 'Proposal ' . ($post_action === 'admin_approve' ? 'approved' : 'rejected') . ' by Admin', $msg]
            );
        }

        audit('PROPOSAL_' . ($post_action === 'admin_approve' ? 'APPROVED' : 'REJECTED'), 'ContractProposal', $prop_id, ['note' => $note]);
        redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'success',
            $post_action === 'admin_approve'
                ? "Proposal \"{$prop['ProposalTitle']}\" approved."
                : "Proposal \"{$prop['ProposalTitle']}\" rejected.");
    }

    // ----------------------------------------------------------------
    // Staff: withdraw a proposal
    // ----------------------------------------------------------------
    private static function handleWithdraw(array $user): void
    {
        $prop_id = (int)($_POST['proposal_id'] ?? 0);
        $prop    = db_query("SELECT * FROM ContractProposal WHERE ProposalID = ?", [$prop_id])->fetch();
        if (!$prop || !in_array($prop['Status'], ['Pending', 'PartnerApproved'])) {
            redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'danger',
                'Proposal cannot be withdrawn at this stage.');
        }
        $proposer_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID = ?", [$prop['ProposedByStaffID']])->fetchColumn();
        if ((int)$proposer_acc !== (int)$user['account_id'] && $user['role'] !== 'admin') {
            redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger', 'Access denied.');
        }
        db_query("UPDATE ContractProposal SET Status = 'Withdrawn' WHERE ProposalID = ?", [$prop_id]);
        audit('PROPOSAL_WITHDRAWN', 'ContractProposal', $prop_id, []);
        redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'success',
            "Proposal \"{$prop['ProposalTitle']}\" withdrawn.");
    }

    // ----------------------------------------------------------------
    // Staff: create new proposal — returns [$errors, $old, $show_form]
    // ----------------------------------------------------------------
    private static function handleCreateProposal(array $user): array
    {
        $show_form = true;
        $old = [
            'ProposalTitle'     => trim($_POST['ProposalTitle']     ?? ''),
            'ContractTypeID'    => (int)($_POST['ContractTypeID']   ?? 0),
            'DepartmentID'      => (int)($_POST['DepartmentID']     ?? 0),
            'CounterpartyID'    => (int)($_POST['CounterpartyID']   ?? 0),
            'Summary'           => trim($_POST['Summary']           ?? ''),
            'EstimatedValue'    => trim($_POST['EstimatedValue']    ?? ''),
            'Currency'          => trim($_POST['Currency']          ?? 'VND'),
            'ProposedStartDate' => trim($_POST['ProposedStartDate'] ?? ''),
            'ProposedEndDate'   => trim($_POST['ProposedEndDate']   ?? ''),
        ];
        $errors = [];

        if ($old['ProposalTitle'] === '')  $errors['ProposalTitle']  = 'Proposal title is required.';
        if (!$old['ContractTypeID'])       $errors['ContractTypeID'] = 'Please select a contract type.';
        if (!$old['DepartmentID'])         $errors['DepartmentID']   = 'Please select a department.';
        if (!$old['CounterpartyID'])       $errors['CounterpartyID'] = 'Please select a counterparty.';
        if ($old['Summary'] === '')        $errors['Summary']        = 'Business purpose is required.';
        if (!in_array($old['Currency'], ['VND','USD'])) $errors['Currency'] = 'Invalid currency.';

        $estimated_value = null;
        if ($old['EstimatedValue'] !== '') {
            if (!is_numeric($old['EstimatedValue']) || (float)$old['EstimatedValue'] < 0) {
                $errors['EstimatedValue'] = 'Estimated value must be a non-negative number.';
            } else {
                $estimated_value = (float)$old['EstimatedValue'];
            }
        }
        if ($old['ProposedStartDate'] && $old['ProposedEndDate']
            && strtotime($old['ProposedEndDate']) < strtotime($old['ProposedStartDate'])) {
            $errors['ProposedEndDate'] = 'End date must be on or after start date.';
        }
        if ($old['CounterpartyID'] && !isset($errors['CounterpartyID'])) {
            if (!db_query("SELECT 1 FROM Counterparty WHERE CounterpartyID = ? AND ApprovalStatus = 'Active'",
                    [$old['CounterpartyID']])->fetchColumn()) {
                $errors['CounterpartyID'] = 'Selected counterparty is not active.';
            }
        }

        if (empty($errors)) {
            $code = generate_proposal_code();
            $db   = get_db();
            $db->beginTransaction();
            try {
                db_query(
                    "INSERT INTO ContractProposal
                         (ProposalCode, ProposalTitle, ContractTypeID, DepartmentID,
                          CounterpartyID, ProposedByStaffID, Summary, EstimatedValue,
                          Currency, ProposedStartDate, ProposedEndDate, Status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,'Pending')",
                    [
                        $code, $old['ProposalTitle'], $old['ContractTypeID'],
                        $old['DepartmentID'], $old['CounterpartyID'], $user['staff_id'],
                        $old['Summary'], $estimated_value, $old['Currency'],
                        $old['ProposedStartDate'] ?: null, $old['ProposedEndDate'] ?: null,
                    ]
                );
                $prop_id = (int)$db->lastInsertId();

                $partner_accs = db_query(
                    "SELECT a.AccountID FROM Contact c JOIN Account a ON a.AccountID=c.AccountID
                     WHERE c.CounterpartyID = ? AND a.Status = 'Active'",
                    [$old['CounterpartyID']]
                )->fetchAll(PDO::FETCH_COLUMN);
                foreach ($partner_accs as $pid) {
                    db_query(
                        "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?, ?, ?, 'Info', 0, NOW())",
                        [$pid, 'New contract proposal for your review',
                         "Vinamilk submitted proposal \"{$old['ProposalTitle']}\" ({$code}) for your review."]
                    );
                }

                $db->commit();
                audit('CREATE', 'ContractProposal', $prop_id, ['code' => $code]);
                redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'success',
                    "Proposal \"{$old['ProposalTitle']}\" ({$code}) submitted. Awaiting partner review.");
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('create_proposal error: ' . $e->getMessage());
                $errors[] = 'An error occurred: ' . $e->getMessage();
            }
        }
        return [$errors, $old, $show_form];
    }

    // ----------------------------------------------------------------
    // Create new contract form + POST handler (Staff only)
    // Accessed via pages/create_request.php
    // ----------------------------------------------------------------
    public static function create()
    {
        require_login();
        $user = current_user();

        if ($user['role'] === 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'You do not have permission to create contracts.');
        }
        if ($user['role'] === 'admin') {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger',
                'Admins review and approve contracts — contract creation is a Staff function.');
        }

        $page_title  = 'Create New Contract';
        $active_nav  = 'contracts';
        $breadcrumbs = [
            ['label' => 'Contracts', 'url' => BASE_URL . '/pages/contracts.php'],
            ['label' => 'Create New Contract'],
        ];

        $contract_types = get_contract_types();
        $departments    = get_departments();
        $counterparties = get_counterparties();
        $internal_staff = get_internal_staff();

        $errors = [];
        $old    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                $errors[] = 'Security token mismatch. Please refresh and try again.';
            } else {
                $old = [
                    'ContractTitle'     => trim($_POST['ContractTitle']     ?? ''),
                    'ContractTypeID'    => (int)($_POST['ContractTypeID']   ?? 0),
                    'DepartmentID'      => (int)($_POST['DepartmentID']     ?? 0),
                    'CounterpartyID'    => (int)($_POST['CounterpartyID']   ?? 0),
                    'OwnerStaffID'      => trim($_POST['OwnerStaffID']      ?? ''),
                    'ContractValue'     => trim($_POST['ContractValue']     ?? ''),
                    'Currency'          => trim($_POST['Currency']          ?? 'VND'),
                    'StartDate'         => trim($_POST['StartDate']         ?? ''),
                    'EndDate'           => trim($_POST['EndDate']           ?? ''),
                    'RenewalReviewDate' => trim($_POST['RenewalReviewDate'] ?? ''),
                    'AutoRenewalAlert'  => isset($_POST['AutoRenewalAlert']) ? 1 : 0,
                    'Summary'           => trim($_POST['Summary']           ?? ''),
                ];
                $action   = trim($_POST['action_type'] ?? 'submit');
                $is_draft = ($action === 'draft');

                if ($old['ContractTitle'] === '') $errors['ContractTitle']  = 'Contract title is required.';
                if (!$old['ContractTypeID'])      $errors['ContractTypeID'] = 'Please select a contract type.';
                if (!$old['DepartmentID'])        $errors['DepartmentID']   = 'Please select a department.';
                if (!$old['CounterpartyID'])      $errors['CounterpartyID'] = 'Please select a counterparty.';
                if ($old['OwnerStaffID'] === '')  $errors['OwnerStaffID']   = 'Please select a contract owner.';

                if (!$is_draft) {
                    if ($old['StartDate'] === '') $errors['StartDate'] = 'Start date is required.';
                    if ($old['EndDate']   === '') $errors['EndDate']   = 'End date is required.';
                    if ($old['Summary']   === '') $errors['Summary']   = 'Summary / business purpose is required.';
                    if (!isset($errors['StartDate'], $errors['EndDate']) && $old['StartDate'] && $old['EndDate']) {
                        if (strtotime($old['EndDate']) < strtotime($old['StartDate'])) {
                            $errors['EndDate'] = 'End date must be on or after start date.';
                        }
                    }
                }

                if ($old['CounterpartyID'] && !isset($errors['CounterpartyID'])) {
                    if (!db_query("SELECT 1 FROM Counterparty WHERE CounterpartyID=? AND ApprovalStatus='Active'",
                            [$old['CounterpartyID']])->fetchColumn()) {
                        $errors['CounterpartyID'] = 'Selected counterparty is not active.';
                    }
                }

                $contract_value = null;
                if ($old['ContractValue'] !== '') {
                    if (!is_numeric($old['ContractValue']) || (float)$old['ContractValue'] < 0) {
                        $errors['ContractValue'] = 'Contract value must be a non-negative number.';
                    } else {
                        $contract_value = (float)$old['ContractValue'];
                    }
                }

                if (empty($errors)) {
                    $status     = $is_draft ? 'Draft' : 'PendingReview';
                    $start_date = $old['StartDate'] ?: null;
                    $end_date   = $old['EndDate']   ?: null;
                    if ($is_draft && !$start_date) $start_date = date('Y-m-d');
                    if ($is_draft && !$end_date)   $end_date   = date('Y-m-d', strtotime('+1 year'));

                    $max_attempts = 3;
                    $attempt = 0;
                    $saved   = false;

                    while (!$saved && $attempt < $max_attempts) {
                        $attempt++;
                        $contract_code = generate_contract_code();
                        $db = get_db();
                        $db->beginTransaction();
                        try {
                            $db->prepare("
                                INSERT INTO Contract
                                    (ContractCode, ContractTitle, ContractTypeID, DepartmentID, CounterpartyID,
                                     OwnerStaffID, Summary, ContractValue, Currency, StartDate, EndDate,
                                     RenewalReviewDate, AutoRenewalAlert, Status)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                            ")->execute([
                                $contract_code, $old['ContractTitle'], $old['ContractTypeID'],
                                $old['DepartmentID'], $old['CounterpartyID'], $old['OwnerStaffID'],
                                $old['Summary'] ?: null, $contract_value, $old['Currency'],
                                $start_date, $end_date, $old['RenewalReviewDate'] ?: null,
                                $old['AutoRenewalAlert'], $status,
                            ]);
                            $contract_id = (int)$db->lastInsertId();
                            record_status_change($contract_id, $user['account_id'], null, $status,
                                $is_draft ? 'Contract created as Draft' : 'Contract submitted for legal review');

                            if (!$is_draft) {
                                $legal_reviewer = db_query(
                                    "SELECT a.AccountID FROM Staff s
                                     JOIN Account a ON a.AccountID=s.AccountID
                                     JOIN Role r ON r.RoleID=a.RoleID
                                     JOIN Department d ON d.DepartmentID=s.DepartmentID
                                     WHERE d.DepartmentName='Legal' AND a.Status='Active'
                                       AND r.RoleName IN ('Manager/Admin','Staff') AND s.StaffID!=?
                                     ORDER BY r.RoleID DESC, s.StaffID ASC LIMIT 1",
                                    [$old['OwnerStaffID']]
                                )->fetchColumn();
                                if (!$legal_reviewer) {
                                    $legal_reviewer = db_query(
                                        "SELECT a.AccountID FROM Account a JOIN Role r ON r.RoleID=a.RoleID
                                         WHERE r.RoleName='Manager/Admin' AND a.Status='Active' LIMIT 1"
                                    )->fetchColumn();
                                }
                                if ($legal_reviewer) {
                                    db_query("INSERT INTO Review (ContractID, RequestID, ReviewerAccountID, ReviewStatus, SubmittedAt) VALUES (?,NULL,?,'Pending',NOW())",
                                        [$contract_id, $legal_reviewer]);
                                }
                                db_query("INSERT INTO ContractRequest (ContractID, RequestedBy, RequestType, AssignedSide, Status, Description, RequestedAt) VALUES (?,?,'InternalApproval','Internal','InProgress',?,NOW())",
                                    [$contract_id, $user['account_id'], 'Contract submitted for review: ' . $old['ContractTitle']]);
                                if ($legal_reviewer) {
                                    db_query("INSERT INTO Notification (AccountID, ContractID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,?,'Info',0,NOW())",
                                        [$legal_reviewer, $contract_id, 'New contract for legal review',
                                         "Contract $contract_code has been submitted for legal review."]);
                                }
                            }

                            $db->commit();
                            $saved = true;
                            audit('CREATE', 'Contract', $contract_id, ['contractCode' => $contract_code, 'status' => $status]);
                            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $contract_id, 'success',
                                $is_draft ? "Draft saved. Code: $contract_code" : "Contract submitted for review. Code: $contract_code");
                        } catch (Throwable $e) {
                            $db->rollBack();
                            if ($e->getCode() === '23000' && $attempt < $max_attempts) continue;
                            error_log('create_contract error: ' . $e->getMessage());
                            $errors[] = 'An unexpected error occurred: ' . $e->getMessage();
                            break;
                        }
                    }
                    if (!$saved && empty($errors)) {
                        $errors[] = 'Could not generate a unique contract code. Please try again.';
                    }
                }
            }
        } else {
            $old['OwnerStaffID']     = $user['staff_id'];
            $old['Currency']         = 'VND';
            $old['AutoRenewalAlert'] = 1;
            $old['DepartmentID']     = '';
            if ($user['dept'] ?? false) {
                $dept_row = db_query("SELECT DepartmentID FROM Department WHERE DepartmentName=? LIMIT 1",
                    [$user['dept']])->fetch();
                if ($dept_row) $old['DepartmentID'] = $dept_row['DepartmentID'];
            }
        }

        require __DIR__ . '/../Views/requests/create.php';
    }
}
