<?php
/**
 * PartnerPortalController
 *
 * External partner portal — shows proposals, contract summary, and handles
 * partner_approve / partner_revise POST actions on ContractProposals.
 */
class PartnerPortalController
{
    public static function index()
    {
        require_login();
        $user = current_user();

        if ($user['role'] !== 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'This page is for partner company users only.');
        }

        // Resolve user's counterparty from Contact — ground-truth binding
        $my_contact = db_query(
            "SELECT c.ContactID, c.CounterpartyID, c.FullName, c.JobTitle, c.IsPrimary,
                    cp.CompanyName, cp.CounterpartyCode, cp.CounterpartyType,
                    cp.ApprovalStatus AS CompanyStatus, cp.LegalRepresentative
             FROM Contact c
             JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
             WHERE c.AccountID = ?
             LIMIT 1",
            [$user['account_id']]
        )->fetch();

        if (!$my_contact) {
            include __DIR__ . '/../../includes/layout_head.php';
            echo '<div class="alert alert-danger" style="margin:32px auto;max-width:540px;">'
               . icon_svg('alert-circle')
               . '<span>Your account is not linked to any partner company. Please contact the Vinamilk team.</span></div>';
            include __DIR__ . '/../../includes/layout_foot.php';
            exit;
        }

        $my_cp_id          = (int)$my_contact['CounterpartyID'];
        $proposals_enabled = db_table_exists('ContractProposal');

        // ----------------------------------------------------------------
        // Handle POST actions
        // ----------------------------------------------------------------
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Security token mismatch.');
            }
            if (!$proposals_enabled) {
                redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
                    'The Contract Proposals feature is not yet available.');
            }

            $post_action = trim($_POST['post_action'] ?? '');
            $prop_id     = (int)($_POST['proposal_id'] ?? 0);
            $note        = trim($_POST['review_note']  ?? '');

            if (!$prop_id) {
                redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Invalid request.');
            }

            // Load proposal and verify it belongs to this user's company
            $prop = db_query(
                "SELECT p.*, ct.TypeName, d.DepartmentName,
                        s.FullName AS ProposerName,
                        sa.AccountID AS ProposerAccountID
                 FROM ContractProposal p
                 JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
                 JOIN Department d   ON d.DepartmentID    = p.DepartmentID
                 JOIN Staff s        ON s.StaffID         = p.ProposedByStaffID
                 JOIN Account sa     ON sa.AccountID      = s.AccountID
                 WHERE p.ProposalID = ?",
                [$prop_id]
            )->fetch();

            if (!$prop || (int)$prop['CounterpartyID'] !== $my_cp_id) {
                error_log(sprintf(
                    'Partner portal access denied: account=%d tried proposal=%d (cp=%d, allowed=%d)',
                    $user['account_id'], $prop_id, (int)($prop['CounterpartyID'] ?? 0), $my_cp_id
                ));
                redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Access denied.');
            }

            if ($prop['Status'] !== 'Pending') {
                redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
                    'This proposal is no longer awaiting your review (current status: ' . $prop['Status'] . ').');
            }

            if ($post_action === 'partner_approve') {
                self::doApprove($prop, $note, $user, $my_contact, $my_cp_id);
            } elseif ($post_action === 'partner_revise') {
                if ($note === '') {
                    redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
                        'Please provide details explaining what revisions are needed.');
                }
                self::doRevise($prop, $note, $user, $my_contact);
            } else {
                redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Unknown action.');
            }
        }

        // Mark notifications as read
        db_query("UPDATE Notification SET IsRead = 1, ReadAt = NOW() WHERE AccountID = ? AND IsRead = 0",
            [$user['account_id']]);

        // ----------------------------------------------------------------
        // Load proposals for this company
        // ----------------------------------------------------------------
        $filter_pstatus    = trim($_GET['pstatus'] ?? '');
        $allowed_pstatuses = ['Pending','PartnerApproved','PartnerRejected','AdminApproved','AdminRejected','ContractCreated','Withdrawn'];
        if (!in_array($filter_pstatus, $allowed_pstatuses)) $filter_pstatus = '';

        $all_proposals = [];
        $pending_count = 0;

        if ($proposals_enabled) {
            $prop_where  = ['p.CounterpartyID = ?'];
            $prop_params = [$my_cp_id];
            if ($filter_pstatus !== '') {
                $prop_where[]  = 'p.Status = ?';
                $prop_params[] = $filter_pstatus;
            }

            $all_proposals = db_query(
                "SELECT p.*, ct.TypeName, d.DepartmentName, s.FullName AS ProposerName
                 FROM ContractProposal p
                 JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
                 JOIN Department d   ON d.DepartmentID    = p.DepartmentID
                 JOIN Staff s        ON s.StaffID         = p.ProposedByStaffID
                 WHERE " . implode(' AND ', $prop_where) . "
                 ORDER BY CASE p.Status WHEN 'Pending' THEN 0 ELSE 1 END, p.CreatedAt DESC",
                $prop_params
            )->fetchAll();

            $pending_count = count(array_filter($all_proposals, fn($p) => $p['Status'] === 'Pending'));
        }

        // ----------------------------------------------------------------
        // Load contracts summary (up to 10)
        // ----------------------------------------------------------------
        $contracts_summary = db_query(
            "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
                    c.ContractValue, c.Currency, c.StartDate, c.EndDate, c.UpdatedAt,
                    ct.TypeName
             FROM Contract c
             JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
             WHERE c.CounterpartyID = ?
             ORDER BY c.UpdatedAt DESC LIMIT 10",
            [$my_cp_id]
        )->fetchAll();

        $contract_counts = db_query(
            "SELECT Status, COUNT(*) AS cnt FROM Contract WHERE CounterpartyID = ? GROUP BY Status",
            [$my_cp_id]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $total_contracts   = array_sum($contract_counts);
        $active_contracts  = (int)($contract_counts['Active'] ?? 0);
        $pending_contracts = (int)(($contract_counts['PendingReview'] ?? 0)
                                  + ($contract_counts['PendingApproval'] ?? 0));

        // ----------------------------------------------------------------
        // Proposal to act on (from ?review=ID)
        // ----------------------------------------------------------------
        $review_proposal = null;
        if ($proposals_enabled && isset($_GET['review'])) {
            $rev_id = (int)$_GET['review'];
            if ($rev_id) {
                $review_proposal = db_query(
                    "SELECT p.*, ct.TypeName, d.DepartmentName, s.FullName AS ProposerName,
                            dep_s.DepartmentName AS ProposerDept
                     FROM ContractProposal p
                     JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
                     JOIN Department d   ON d.DepartmentID    = p.DepartmentID
                     JOIN Staff s        ON s.StaffID         = p.ProposedByStaffID
                     JOIN Department dep_s ON dep_s.DepartmentID = s.DepartmentID
                     WHERE p.ProposalID = ? AND p.CounterpartyID = ?",
                    [$rev_id, $my_cp_id]
                )->fetch();
            }
        }

        require __DIR__ . '/../Views/partner_portal/index.php';
    }

    // ----------------------------------------------------------------
    private static function doApprove(array $prop, string $note, array $user, array $my_contact, int $my_cp_id): void
    {
        $prop_id = $prop['ProposalID'];
        $db = get_db();
        $db->beginTransaction();
        try {
            db_query(
                "UPDATE ContractProposal
                 SET Status='PartnerApproved', PartnerReviewNote=?, PartnerReviewedBy=?, PartnerReviewedAt=NOW()
                 WHERE ProposalID=?",
                [$note ?: null, $user['account_id'], $prop_id]
            );

            $admin_accounts = db_query(
                "SELECT a.AccountID FROM Account a JOIN Role r ON r.RoleID=a.RoleID
                 WHERE r.RoleName='Manager/Admin' AND a.Status='Active'"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($admin_accounts as $admin_id) {
                db_query(
                    "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,'Info',0,NOW())",
                    [$admin_id,
                     'Contract proposal approved by partner',
                     "The proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) has been approved by {$my_contact['CompanyName']} and is now awaiting your review."]
                );
            }

            db_query(
                "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,'Success',0,NOW())",
                [$prop['ProposerAccountID'],
                 'Partner company approved your proposal',
                 "{$my_contact['CompanyName']} has approved the proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}). It is now awaiting Admin approval."]
            );

            $db->commit();
            audit('PARTNER_APPROVE', 'ContractProposal', $prop_id, ['company' => $my_contact['CompanyName'], 'note' => $note]);
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'success',
                "Proposal \"{$prop['ProposalTitle']}\" approved. It is now awaiting Admin review before a contract can be created.");
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('partner_approve error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'An error occurred. Please try again.');
        }
    }

    // ----------------------------------------------------------------
    private static function doRevise(array $prop, string $note, array $user, array $my_contact): void
    {
        $prop_id = $prop['ProposalID'];
        $db = get_db();
        $db->beginTransaction();
        try {
            db_query(
                "UPDATE ContractProposal
                 SET Status='PartnerRejected', PartnerReviewNote=?, PartnerReviewedBy=?, PartnerReviewedAt=NOW()
                 WHERE ProposalID=?",
                [$note, $user['account_id'], $prop_id]
            );

            db_query(
                "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt) VALUES (?,?,?,'Warning',0,NOW())",
                [$prop['ProposerAccountID'],
                 'Partner company requested revisions',
                 "{$my_contact['CompanyName']} has reviewed the proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) and requested revisions: {$note}"]
            );

            $db->commit();
            audit('PARTNER_REVISE', 'ContractProposal', $prop_id, ['company' => $my_contact['CompanyName'], 'note' => $note]);
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'success',
                "Revision request sent for \"{$prop['ProposalTitle']}\". The Vinamilk team has been notified.");
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('partner_revise error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'An error occurred. Please try again.');
        }
    }
}
