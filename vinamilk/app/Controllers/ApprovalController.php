<?php
/**
 * ApprovalController
 *
 * Sections:
 *   1. Pending Counterparty Approvals  (Admin only)
 *   2. Pending Contract Proposals      (Admin: PartnerApproved proposals)
 *   3. Pending Legal Reviews           (Assigned reviewer OR Admin)
 *   4. Pending Approval decisions      (Admin only)
 */
class ApprovalController
{
    public static function index()
    {
        require_login();
        $user = current_user();

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Security token mismatch.');
            }

            $post_action = trim($_POST['post_action'] ?? '');

            if ($post_action === 'approve_counterparty' || $post_action === 'reject_counterparty') {
                self::handleCounterpartyApproval($post_action, $user);
            } elseif ($post_action === 'complete_review') {
                self::handleCompleteReview($user);
            } elseif ($post_action === 'decide_approval') {
                self::handleApprovalDecision($user);
            }
        }

        // ----------------------------------------------------------------
        // Load display data
        // ----------------------------------------------------------------

        // 1. Pending counterparties (admin)
        $pending_counterparties = ($user['role'] === 'admin')
            ? db_query("SELECT cp.*, s.FullName AS ProposerName FROM Counterparty cp
                        LEFT JOIN Staff s ON s.StaffID=cp.ProposedBy
                        WHERE cp.ApprovalStatus='Pending' ORDER BY cp.ProposedAt ASC")->fetchAll()
            : [];

        // 2. Pending proposals (admin: PartnerApproved)
        $pending_proposals = [];
        if ($user['role'] === 'admin' && db_table_exists('ContractProposal')) {
            $pending_proposals = db_query(
                "SELECT p.*, ct.TypeName, cp.CompanyName AS CounterpartyName,
                        d.DepartmentName, s.FullName AS ProposerName
                 FROM ContractProposal p
                 JOIN ContractType ct ON ct.ContractTypeID=p.ContractTypeID
                 JOIN Counterparty cp ON cp.CounterpartyID=p.CounterpartyID
                 JOIN Department d   ON d.DepartmentID=p.DepartmentID
                 JOIN Staff s        ON s.StaffID=p.ProposedByStaffID
                 WHERE p.Status='PartnerApproved' ORDER BY p.UpdatedAt ASC"
            )->fetchAll();
        }

        // 3. Pending reviews
        if ($user['role'] === 'admin') {
            $pending_reviews = db_query(
                "SELECT r.*, c.ContractCode, c.ContractTitle, c.Status AS ContractStatus,
                        COALESCE(s.FullName, a.Email) AS ReviewerName, cp.CompanyName AS CounterpartyName
                 FROM Review r
                 JOIN Contract c    ON c.ContractID=r.ContractID
                 JOIN Account a     ON a.AccountID=r.ReviewerAccountID
                 LEFT JOIN Staff s  ON s.AccountID=r.ReviewerAccountID
                 JOIN Counterparty cp ON cp.CounterpartyID=c.CounterpartyID
                 WHERE r.ReviewStatus='Pending' ORDER BY r.SubmittedAt ASC"
            )->fetchAll();
        } else {
            $pending_reviews = db_query(
                "SELECT r.*, c.ContractCode, c.ContractTitle, c.Status AS ContractStatus,
                        COALESCE(s.FullName, a.Email) AS ReviewerName, cp.CompanyName AS CounterpartyName
                 FROM Review r
                 JOIN Contract c    ON c.ContractID=r.ContractID
                 JOIN Account a     ON a.AccountID=r.ReviewerAccountID
                 LEFT JOIN Staff s  ON s.AccountID=r.ReviewerAccountID
                 JOIN Counterparty cp ON cp.CounterpartyID=c.CounterpartyID
                 WHERE r.ReviewStatus='Pending' AND r.ReviewerAccountID=? ORDER BY r.SubmittedAt ASC",
                [$user['account_id']]
            )->fetchAll();
        }

        // 4. Pending approvals and specific approval (admin)
        $pending_approvals = [];
        $act_approval      = null;

        if ($user['role'] === 'admin') {
            $act_id = (int)($_GET['act'] ?? 0);
            if (!$act_id && isset($_GET['contract_id'])) {
                $act_id = (int) db_query(
                    "SELECT ApprovalID FROM Approval WHERE ContractID=? AND ApprovalStatus='Pending'
                     ORDER BY SubmittedAt DESC LIMIT 1",
                    [(int)$_GET['contract_id']]
                )->fetchColumn();
            }
            if ($act_id) {
                $act_approval = db_query(
                    "SELECT ap.*, c.ContractCode, c.ContractTitle, c.ContractValue, c.Currency,
                            c.StartDate, c.EndDate, cp.CompanyName AS CounterpartyName,
                            s.FullName AS OwnerName, ct.TypeName
                     FROM Approval ap
                     JOIN Contract c      ON c.ContractID=ap.ContractID
                     JOIN ContractType ct ON ct.ContractTypeID=c.ContractTypeID
                     JOIN Counterparty cp ON cp.CounterpartyID=c.CounterpartyID
                     JOIN Staff s         ON s.StaffID=c.OwnerStaffID
                     WHERE ap.ApprovalID=? AND ap.ApprovalStatus='Pending'",
                    [$act_id]
                )->fetch();
            }

            $pending_approvals = db_query(
                "SELECT ap.ApprovalID, ap.ContractID, ap.ApprovalLevel, ap.SubmittedAt,
                        c.ContractCode, c.ContractTitle, c.ContractValue, c.Currency,
                        cp.CompanyName AS CounterpartyName,
                        COALESCE(s.FullName, a.Email) AS ApproverName
                 FROM Approval ap
                 JOIN Contract c      ON c.ContractID=ap.ContractID
                 JOIN Counterparty cp ON cp.CounterpartyID=c.CounterpartyID
                 JOIN Account a       ON a.AccountID=ap.ApproverAccountID
                 LEFT JOIN Staff s    ON s.AccountID=ap.ApproverAccountID
                 WHERE ap.ApprovalStatus='Pending' ORDER BY ap.SubmittedAt ASC"
            )->fetchAll();
        }

        // 5. Recent approval history
        $history = db_query(
            "SELECT ap.ApprovalID, ap.ApprovalStatus, ap.ApprovedAt, ap.Comments, ap.ContractID,
                    c.ContractCode, c.ContractTitle, COALESCE(s.FullName, a.Email) AS ApproverName
             FROM Approval ap
             JOIN Contract c   ON c.ContractID=ap.ContractID
             JOIN Account a    ON a.AccountID=ap.ApproverAccountID
             LEFT JOIN Staff s ON s.AccountID=ap.ApproverAccountID
             WHERE ap.ApprovalStatus != 'Pending'
             ORDER BY ap.ApprovedAt DESC LIMIT 20"
        )->fetchAll();

        $total_pending =
            count($pending_counterparties) +
            count($pending_proposals) +
            count($pending_reviews) +
            count($pending_approvals);

        require __DIR__ . '/../Views/approvals/index.php';
    }

    // ----------------------------------------------------------------
    private static function handleCounterpartyApproval(string $post_action, array $user): void
    {
        if ($user['role'] !== 'admin') {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Only admins can approve counterparties.');
        }
        $cp_id  = (int)($_POST['counterparty_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        $cp     = db_query("SELECT * FROM Counterparty WHERE CounterpartyID=? AND ApprovalStatus='Pending'", [$cp_id])->fetch();

        if (!$cp) {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Counterparty not found or already processed.');
        }
        if ($post_action === 'reject_counterparty' && $reason === '') {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'A rejection reason is required.');
        }

        $db = get_db();
        $db->beginTransaction();
        try {
            if ($post_action === 'approve_counterparty') {
                db_query("UPDATE Counterparty SET ApprovalStatus='Active', DecidedBy=?, DecidedAt=NOW() WHERE CounterpartyID=?",
                    [$user['staff_id'], $cp_id]);
                db_query("UPDATE Account a JOIN Contact c ON c.AccountID=a.AccountID
                          SET a.Status='Active' WHERE c.CounterpartyID=? AND a.Status='Pending'", [$cp_id]);
                $db->commit();
                audit('APPROVE', 'Counterparty', $cp_id, ['company' => $cp['CompanyName']]);
                redirect_with_flash(BASE_URL . '/pages/approvals.php', 'success',
                    "\"{$cp['CompanyName']}\" approved and contact account activated.");
            } else {
                db_query("UPDATE Counterparty SET ApprovalStatus='Rejected', RejectionReason=?, DecidedBy=?, DecidedAt=NOW() WHERE CounterpartyID=?",
                    [$reason, $user['staff_id'], $cp_id]);
                db_query("UPDATE Account a JOIN Contact c ON c.AccountID=a.AccountID
                          SET a.Status='Inactive' WHERE c.CounterpartyID=? AND a.Status='Pending'", [$cp_id]);
                $db->commit();
                audit('REJECT', 'Counterparty', $cp_id, ['reason' => $reason]);
                redirect_with_flash(BASE_URL . '/pages/approvals.php', 'success', "\"{$cp['CompanyName']}\" rejected.");
            }
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('counterparty approval error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'An error occurred. Please try again.');
        }
    }

    // ----------------------------------------------------------------
    private static function handleCompleteReview(array $user): void
    {
        $review_id     = (int)($_POST['review_id']    ?? 0);
        $review_status = trim($_POST['review_status'] ?? '');
        $comments      = trim($_POST['comments']      ?? '');

        $allowed = ['Approved', 'Rejected', 'RevisionRequired'];
        if (!$review_id || !in_array($review_status, $allowed)) {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Invalid review action.');
        }
        if ($review_status !== 'Approved' && $comments === '') {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger',
                'Comments are required when rejecting or requesting revision.');
        }

        $review = db_query(
            "SELECT r.*, c.Status AS ContractStatus, c.ContractCode, c.ContractTitle, c.OwnerStaffID
             FROM Review r JOIN Contract c ON c.ContractID=r.ContractID
             WHERE r.ReviewID=? AND r.ReviewStatus='Pending'",
            [$review_id]
        )->fetch();

        if (!$review) {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Review not found or already processed.');
        }
        if ($user['role'] !== 'admin' && (int)$review['ReviewerAccountID'] !== (int)$user['account_id']) {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'You are not the assigned reviewer.');
        }

        $db = get_db();
        $db->beginTransaction();
        try {
            db_query("UPDATE Review SET ReviewStatus=?, Comments=?, ReviewedAt=NOW() WHERE ReviewID=?",
                [$review_status, $comments ?: null, $review_id]);

            $old_status = $review['ContractStatus'];

            if ($review_status === 'Approved') {
                db_query("UPDATE Contract SET Status='PendingApproval', UpdatedAt=NOW() WHERE ContractID=?", [$review['ContractID']]);
                record_status_change($review['ContractID'], $user['account_id'], $old_status, 'PendingApproval',
                    'Legal review approved: ' . ($comments ?: 'Approved by legal team'));

                $approver_account_id = db_query(
                    "SELECT a.AccountID FROM Account a JOIN Role r ON r.RoleID=a.RoleID
                     WHERE r.RoleName='Manager/Admin' AND a.Status='Active' LIMIT 1"
                )->fetchColumn();

                if ($approver_account_id) {
                    $existing = db_query(
                        "SELECT 1 FROM Approval WHERE ContractID=? AND ApprovalStatus='Pending'",
                        [$review['ContractID']]
                    )->fetchColumn();
                    if (!$existing) {
                        db_query("INSERT INTO Approval (ContractID, ApproverAccountID, ApprovalLevel, ApprovalStatus, SubmittedAt) VALUES (?,?,1,'Pending',NOW())",
                            [$review['ContractID'], $approver_account_id]);
                    }
                    db_query("INSERT INTO Notification (AccountID, ContractID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,?,'Info',0,NOW())",
                        [$approver_account_id, $review['ContractID'],
                         'Contract ready for final approval',
                         "Contract {$review['ContractCode']} has passed legal review and is awaiting your approval."]);
                }
                db_query("UPDATE ContractRequest SET Status='Completed', RespondedBy=?, RespondedAt=NOW(), ResponseNote='Legal review approved'
                          WHERE ContractID=? AND RequestType='InternalApproval' AND Status='InProgress'",
                    [$user['account_id'], $review['ContractID']]);
            } else {
                db_query("UPDATE Contract SET Status='RevisionRequested', UpdatedAt=NOW() WHERE ContractID=?", [$review['ContractID']]);
                $reason_label = $review_status === 'RevisionRequired' ? 'Revision required' : 'Rejected';
                record_status_change($review['ContractID'], $user['account_id'], $old_status, 'RevisionRequested', "$reason_label: $comments");

                $owner_account_id = db_query(
                    "SELECT a.AccountID FROM Staff s JOIN Account a ON a.AccountID=s.AccountID WHERE s.StaffID=?",
                    [$review['OwnerStaffID']]
                )->fetchColumn();
                if ($owner_account_id) {
                    db_query("INSERT INTO Notification (AccountID, ContractID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,?,'Warning',0,NOW())",
                        [$owner_account_id, $review['ContractID'],
                         'Contract revision required',
                         "Contract {$review['ContractCode']}: $reason_label — $comments"]);
                }
            }

            $db->commit();
            audit('REVIEW_' . strtoupper($review_status), 'Contract', $review['ContractID'],
                ['reviewId' => $review_id, 'comments' => $comments]);
            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $review['ContractID'],
                'success', "Review completed: $review_status.");
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('review completion error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'An error occurred: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    private static function handleApprovalDecision(array $user): void
    {
        if ($user['role'] !== 'admin') {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Only Managers/Admins can perform approval actions.');
        }

        $approval_id     = (int)($_POST['approval_id']    ?? 0);
        $approval_action = trim($_POST['approval_action'] ?? '');
        $comments        = trim($_POST['comments']        ?? '');

        if (!$approval_id || !in_array($approval_action, ['Approved', 'Rejected'])) {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Invalid approval action.');
        }
        if ($approval_action === 'Rejected' && $comments === '') {
            redirect_with_flash(BASE_URL . '/pages/approvals.php?act=' . $approval_id,
                'danger', 'Comments are required when rejecting.');
        }

        $ap = db_query("SELECT * FROM Approval WHERE ApprovalID=? AND ApprovalStatus='Pending'", [$approval_id])->fetch();
        if (!$ap) {
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'Approval record not found or already processed.');
        }

        $db = get_db();
        $db->beginTransaction();
        try {
            db_query("UPDATE Approval SET ApprovalStatus=?, Comments=?, ApprovedAt=NOW() WHERE ApprovalID=?",
                [$approval_action, $comments ?: null, $approval_id]);

            $new_status = ($approval_action === 'Approved') ? 'Approved' : 'RevisionRequested';
            $old_row    = db_query("SELECT Status, ContractCode, OwnerStaffID FROM Contract WHERE ContractID=?", [$ap['ContractID']])->fetch();

            db_query("UPDATE Contract SET Status=?, UpdatedAt=NOW() WHERE ContractID=?", [$new_status, $ap['ContractID']]);
            record_status_change($ap['ContractID'], $user['account_id'], $old_row['Status'], $new_status,
                $comments ?: "Approval decision: $approval_action");

            $owner_acc = db_query(
                "SELECT a.AccountID FROM Staff s JOIN Account a ON a.AccountID=s.AccountID WHERE s.StaffID=?",
                [$old_row['OwnerStaffID']]
            )->fetchColumn();
            if ($owner_acc) {
                $msg = $approval_action === 'Approved'
                    ? "Contract {$old_row['ContractCode']} has been approved by management."
                    : "Contract {$old_row['ContractCode']} requires revision: $comments";
                db_query("INSERT INTO Notification (AccountID, ContractID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,?,?,0,NOW())",
                    [$owner_acc, $ap['ContractID'],
                     'Contract ' . ($approval_action === 'Approved' ? 'approved' : 'revision required'),
                     $msg,
                     $approval_action === 'Approved' ? 'Success' : 'Warning']);
            }

            $db->commit();
            audit('APPROVAL_' . strtoupper($approval_action), 'Contract', $ap['ContractID'],
                ['approvalId' => $approval_id, 'comments' => $comments]);
            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $ap['ContractID'],
                'success', "Contract has been $approval_action.");
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('Approval error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/approvals.php', 'danger', 'An error occurred. Please try again.');
        }
    }
}
