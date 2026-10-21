<?php
/**
 * Contract Detail page
 *
 * Workflow action buttons by role and contract status:
 *
 *   Staff (owner of contract):
 *     Draft             → "Submit for Review" button
 *     RevisionRequested → "Resubmit for Review" button
 *
 *   Staff/Admin (Legal reviewer assigned to this contract):
 *     PendingReview     → "Complete Review" button (opens modal)
 *
 *   Admin:
 *     PendingReview or PendingApproval → "Review & Approve" link to approvals.php
 *     PendingApproval  → inline "Approve / Reject" form
 *
 *   All internal:
 *     Approved          → "Set Active" button (admin only)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect_with_flash(BASE_URL . '/pages/contracts.php', 'danger', 'No contract ID specified.');
}

// ---------------------------------------------------------------
// Handle POST workflow actions
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger', 'Security token mismatch.');
    }

    $post_action = trim($_POST['post_action'] ?? '');

    // ---- Staff: submit draft for review ----
    if ($post_action === 'submit_for_review') {
        $contract = db_query("SELECT * FROM Contract WHERE ContractID=?", [$id])->fetch();
        if (!$contract || !in_array($contract['Status'], ['Draft', 'RevisionRequested'])) {
            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger',
                'Contract cannot be submitted for review at this stage.');
        }
        // Verify the user is the owner or admin
        $owner_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID=?", [$contract['OwnerStaffID']])->fetchColumn();
        if ($user['role'] !== 'admin' && (int)$owner_acc !== (int)$user['account_id']) {
            redirect_with_flash(BASE_URL . '/pages/contract_detail.php?id=' . $id, 'danger', 'Access denied.');
        }

        // Validate required fields for submission
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

            // Find legal reviewer (Legal dept staff, not the owner)
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

            // Cancel old pending reviews and create a fresh one
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

            // Create or update ContractRequest
            $existing_req = db_query(
                "SELECT RequestID FROM ContractRequest WHERE ContractID=? AND RequestType='InternalApproval' AND Status='InProgress'",
                [$id]
            )->fetchColumn();
            if (!$existing_req) {
                db_query(
                    "INSERT INTO ContractRequest (ContractID, RequestedBy, RequestType, AssignedSide, Status, Description, RequestedAt)
                     VALUES (?,?,'InternalApproval','Internal','InProgress',?,NOW())",
                    [$id, $user['account_id'], 'Contract resubmitted for review and approval: ' . $contract['ContractTitle']]
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

    // ---- Admin: set contract Active ----
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

// ---------------------------------------------------------------
// Load contract
// ---------------------------------------------------------------
$contract = db_query("
    SELECT c.*,
           ct.TypeName,
           cp.CompanyName AS CounterpartyName, cp.CounterpartyCode, cp.TaxCode,
           cp.LegalRepresentative, cp.Phone AS CounterpartyPhone, cp.Email AS CounterpartyEmail,
           s.FullName AS OwnerName, s.Position AS OwnerPosition,
           d.DepartmentName
    FROM Contract c
    JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
    JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
    JOIN Staff s         ON s.StaffID         = c.OwnerStaffID
    JOIN Department d    ON d.DepartmentID    = c.DepartmentID
    WHERE c.ContractID = ?
", [$id])->fetch();

if (!$contract) {
    redirect_with_flash(BASE_URL . '/pages/contracts.php', 'danger', 'Contract not found.');
}

// External users: strict backend access control
// Verify AccountID → Contact → CounterpartyID matches this contract's counterparty.
// Cannot be bypassed by URL manipulation since we resolve their counterparty
// from their session AccountID, not from any GET parameter.
if ($user['role'] === 'external') {
    $ext_cp_id = (int) db_query(
        "SELECT c.CounterpartyID
         FROM Contact c
         JOIN Account a ON a.AccountID = c.AccountID
         WHERE c.AccountID = ? AND a.Status = 'Active'
         LIMIT 1",
        [$user['account_id']]
    )->fetchColumn();

    if (!$ext_cp_id || (int)$ext_cp_id !== (int)$contract['CounterpartyID']) {
        // Log the attempt
        error_log(sprintf(
            'External access denied: account=%d tried contract=%d (counterparty=%d, allowed=%d)',
            $user['account_id'], $id, $contract['CounterpartyID'], $ext_cp_id
        ));
        redirect_with_flash(BASE_URL . '/pages/contracts.php', 'danger', 'Access denied.');
    }
}

// ---------------------------------------------------------------
// Load related data
// ---------------------------------------------------------------
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

// ---------------------------------------------------------------
// Determine action button visibility
// ---------------------------------------------------------------
$owner_account_id = db_query("SELECT AccountID FROM Staff WHERE StaffID=?",
    [$contract['OwnerStaffID']])->fetchColumn();

$is_owner = (int)$owner_account_id === (int)$user['account_id'];

// Is the current user an assigned pending reviewer for this contract?
$my_pending_review = db_query(
    "SELECT ReviewID FROM Review WHERE ContractID=? AND ReviewerAccountID=? AND ReviewStatus='Pending'",
    [$id, $user['account_id']]
)->fetch();

// Is there a pending approval for this contract?
$pending_approval = db_query(
    "SELECT ApprovalID FROM Approval WHERE ContractID=? AND ApprovalStatus='Pending'",
    [$id]
)->fetch();

$show_submit_for_review   = ($is_owner || $user['role'] === 'admin')
                            && in_array($contract['Status'], ['Draft', 'RevisionRequested']);
$show_complete_review     = ($my_pending_review || $user['role'] === 'admin')
                            && $contract['Status'] === 'PendingReview';
$show_approve_link        = $user['role'] === 'admin'
                            && in_array($contract['Status'], ['PendingReview', 'PendingApproval']);
$show_set_active          = $user['role'] === 'admin' && $contract['Status'] === 'Approved';

$page_title  = $contract['ContractCode'];
$active_nav  = 'contracts';
$breadcrumbs = [
    ['label' => 'Contracts', 'url' => BASE_URL . '/pages/contracts.php'],
    ['label' => $contract['ContractCode']],
];

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1><?= e($contract['ContractCode']) ?></h1>
    <p style="display:flex;align-items:center;gap:8px;margin-top:4px;">
      <?= status_badge($contract['Status']) ?>
      <span style="color:var(--color-text-muted);">·</span>
      <span><?= e($contract['TypeName']) ?></span>
      <span style="color:var(--color-text-muted);">·</span>
      <span>Updated <?= fmt_datetime($contract['UpdatedAt']) ?></span>
    </p>
  </div>
  <div class="page-header-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-secondary">
      <?= icon_svg('arrow-left') ?> Back
    </a>

    <?php if ($show_submit_for_review): ?>
    <form method="POST" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" value="submit_for_review">
      <button type="submit" class="btn btn-primary"
              data-confirm="Submit this contract for legal review? Make sure it has Start Date, End Date, and Summary filled in.">
        <?= icon_svg('check-circle') ?>
        <?= $contract['Status'] === 'RevisionRequested' ? 'Resubmit for Review' : 'Submit for Review' ?>
      </button>
    </form>
    <?php endif; ?>

    <?php if ($show_complete_review): ?>
    <button type="button" class="btn btn-primary"
            onclick="openReviewModal(<?= $my_pending_review ? $my_pending_review['ReviewID'] : 0 ?>, '<?= e(addslashes($contract['ContractCode'])) ?>', '<?= e(addslashes($contract['ContractTitle'])) ?>')">
      <?= icon_svg('edit') ?> Complete Legal Review
    </button>
    <?php endif; ?>

    <?php if ($show_approve_link): ?>
    <a href="<?= BASE_URL ?>/pages/approvals.php<?= $pending_approval ? '?act=' . $pending_approval['ApprovalID'] : '?contract_id=' . $id ?>"
       class="btn btn-primary">
      <?= icon_svg('check-circle') ?> Review &amp; Approve
    </a>
    <?php endif; ?>

    <?php if ($show_set_active): ?>
    <form method="POST" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" value="set_active">
      <button type="submit" class="btn btn-primary"
              data-confirm="Set this contract to Active status?">
        Set Active
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Workflow status hint for staff -->
<?php if ($user['role'] !== 'external'): ?>
<?php if ($contract['Status'] === 'Draft' && $is_owner): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>This contract is a <strong>Draft</strong>. Add Start Date, End Date, and Summary, then click <strong>Submit for Review</strong> to begin the approval workflow.</span>
</div>
<?php elseif ($contract['Status'] === 'RevisionRequested'): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span><strong>Revision Requested.</strong> Review the legal team's comments below, make corrections to the contract, then click <strong>Resubmit for Review</strong>.</span>
</div>
<?php elseif ($contract['Status'] === 'PendingReview'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>This contract is <strong>under legal review</strong>. The assigned reviewer will approve it or request revisions.</span>
</div>
<?php elseif ($contract['Status'] === 'PendingApproval'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>Legal review completed. This contract is awaiting <strong>Admin final approval</strong>.</span>
</div>
<?php elseif ($contract['Status'] === 'Approved'): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('check-circle') ?>
  <span><strong>Contract approved.</strong><?= $user['role'] === 'admin' ? ' You can set it to Active once it takes effect.' : ' Awaiting activation.' ?></span>
</div>
<?php endif; ?>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">

  <!-- Main column -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Contract details -->
    <div class="card">
      <div class="card-header"><span class="card-title">Contract Details</span></div>
      <div class="card-body">
        <h2 style="font-size:16px;font-weight:700;margin-bottom:20px;"><?= e($contract['ContractTitle']) ?></h2>
        <div class="detail-grid">
          <div class="detail-item">
            <div class="detail-label">Contract Code</div>
            <div class="detail-value mono"><?= e($contract['ContractCode']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Status</div>
            <div class="detail-value"><?= status_badge($contract['Status']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Contract Type</div>
            <div class="detail-value"><?= e($contract['TypeName']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Department</div>
            <div class="detail-value"><?= e($contract['DepartmentName']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Counterparty</div>
            <div class="detail-value"><?= e($contract['CounterpartyName']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Counterparty Code</div>
            <div class="detail-value mono"><?= e($contract['CounterpartyCode']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Legal Representative</div>
            <div class="detail-value"><?= e($contract['LegalRepresentative'] ?? '—') ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Contract Owner</div>
            <div class="detail-value">
              <?= e($contract['OwnerName']) ?>
              <br><span style="font-size:11px;color:var(--color-text-muted);"><?= e($contract['OwnerPosition'] ?? '') ?></span>
            </div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Contract Value</div>
            <div class="detail-value"><?= money($contract['ContractValue'], $contract['Currency'] ?? 'VND') ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Currency</div>
            <div class="detail-value"><?= e($contract['Currency'] ?? '—') ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Start Date</div>
            <div class="detail-value"><?= $contract['StartDate'] ? fmt_date($contract['StartDate']) : '<span style="color:var(--color-warning,#d97706);">Not set</span>' ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">End Date</div>
            <div class="detail-value"><?= $contract['EndDate'] ? fmt_date($contract['EndDate']) : '<span style="color:var(--color-warning,#d97706);">Not set</span>' ?></div>
          </div>
          <?php if ($contract['RenewalReviewDate']): ?>
          <div class="detail-item">
            <div class="detail-label">Renewal Review Date</div>
            <div class="detail-value"><?= fmt_date($contract['RenewalReviewDate']) ?></div>
          </div>
          <?php endif; ?>
          <div class="detail-item">
            <div class="detail-label">Auto Renewal Alert</div>
            <div class="detail-value"><?= $contract['AutoRenewalAlert'] ? 'Enabled' : 'Disabled' ?></div>
          </div>
        </div>

        <?php if ($contract['Summary']): ?>
        <div class="divider"></div>
        <div class="detail-item">
          <div class="detail-label">Summary</div>
          <div class="detail-value" style="font-weight:400;line-height:1.7;margin-top:6px;">
            <?= nl2br(e($contract['Summary'])) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Documents -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Documents</span>
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:12px;color:var(--color-text-muted);"><?= count($documents) ?> file<?= count($documents) !== 1 ? 's' : '' ?></span>
          <?php if ($user['role'] !== 'external'): ?>
          <a href="<?= BASE_URL ?>/pages/upload_document.php?contract_id=<?= $id ?>"
             class="btn btn-primary btn-sm">
            <?= icon_svg('upload') ?> Upload
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php if (empty($documents)): ?>
      <div class="card-body" style="text-align:center;padding:32px;color:var(--color-text-muted);font-size:13px;">
        <p>No documents attached to this contract.</p>
        <?php if ($user['role'] !== 'external'): ?>
        <a href="<?= BASE_URL ?>/pages/upload_document.php?contract_id=<?= $id ?>"
           class="btn btn-primary btn-sm" style="margin-top:12px;">
          <?= icon_svg('upload') ?> Upload First Document
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="card-body" style="padding:12px 20px;">
        <?php foreach ($documents as $doc): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--color-border);">
          <div style="width:36px;height:36px;background:var(--color-accent-soft);border-radius:var(--radius);
                      display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <?= icon_svg('file-text') ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:600;"><?= e($doc['DocumentName']) ?></div>
            <div class="td-meta">
              <?= e(str_replace(['MainContract','Appendix','CargoInsurance','FoodSafetyCert','BankGuarantee','Other'],
                ['Main Contract','Appendix','Cargo Insurance','Food Safety Cert','Bank Guarantee','Other'],
                $doc['DocumentType'])) ?>
              <?php if ($doc['VersionNumber']): ?> · v<?= $doc['VersionNumber'] ?><?php endif; ?>
              <?php if ($doc['FileSize']): ?> · <?= number_format($doc['FileSize']/1024, 1) ?> KB<?php endif; ?>
              <?php if ($doc['IsVisibleToPartner']): ?>
              · <span style="color:var(--color-success);font-weight:600;">Partner visible</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($doc['FilePath'] ?? null): ?>
          <a href="<?= BASE_URL ?>/pages/download_document.php?doc=<?= $doc['DocumentID'] ?>"
             class="btn btn-ghost btn-sm" title="Download">
            <?= icon_svg('download') ?>
          </a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Reviews -->
    <?php if (!empty($reviews)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Legal Reviews</span></div>
      <div class="card-body" style="padding:0;">
        <table class="data-table">
          <thead>
            <tr><th>Reviewer</th><th>Status</th><th>Comments</th><th>Reviewed At</th></tr>
          </thead>
          <tbody>
            <?php foreach ($reviews as $r): ?>
            <tr>
              <td><?= e($r['ReviewerName']) ?></td>
              <td><?= status_badge($r['ReviewStatus']) ?></td>
              <td style="font-size:12px;"><?= e($r['Comments'] ?? '—') ?></td>
              <td class="td-meta"><?= fmt_datetime($r['ReviewedAt'] ?: $r['SubmittedAt']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Approvals -->
    <?php if (!empty($approvals)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">Approval Records</span></div>
      <div class="card-body" style="padding:0;">
        <table class="data-table">
          <thead>
            <tr><th>Approver</th><th>Level</th><th>Status</th><th>Signature</th><th>Comments</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($approvals as $ap): ?>
            <tr>
              <td><?= e($ap['ApproverName']) ?></td>
              <td class="text-center"><?= $ap['ApprovalLevel'] ?></td>
              <td><?= status_badge($ap['ApprovalStatus']) ?></td>
              <td class="td-meta">
                <?php if ($ap['SignatureStatus']): ?>
                <span style="color:<?= $ap['SignatureStatus']==='Success' ? 'var(--color-success)' : 'var(--color-danger)' ?>;font-weight:600;">
                  <?= e($ap['SignatureStatus']) ?>
                </span>
                <?php else: echo '—'; endif; ?>
              </td>
              <td style="font-size:12px;"><?= e($ap['Comments'] ?? '—') ?></td>
              <td class="td-meta"><?= fmt_datetime($ap['ApprovedAt'] ?: $ap['SubmittedAt']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /main col -->

  <!-- Sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px;">

    <!-- Current status -->
    <div class="card">
      <div class="card-header"><span class="card-title">Current Status</span></div>
      <div class="card-body" style="padding:16px;text-align:center;">
        <?= status_badge($contract['Status']) ?>
        <div style="font-size:12px;color:var(--color-text-secondary);margin-top:8px;">
          Last updated <?= fmt_datetime($contract['UpdatedAt']) ?>
        </div>

        <?php if ($show_submit_for_review): ?>
        <div class="divider" style="margin:12px 0;"></div>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="post_action" value="submit_for_review">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;"
                  data-confirm="Submit this contract for legal review?">
            <?= icon_svg('check-circle') ?>
            <?= $contract['Status'] === 'RevisionRequested' ? 'Resubmit for Review' : 'Submit for Review' ?>
          </button>
        </form>
        <?php endif; ?>

        <?php if ($show_complete_review): ?>
        <div class="divider" style="margin:12px 0;"></div>
        <button type="button" class="btn btn-primary" style="width:100%;justify-content:center;"
                onclick="openReviewModal(<?= $my_pending_review ? $my_pending_review['ReviewID'] : 0 ?>, '<?= e(addslashes($contract['ContractCode'])) ?>', '<?= e(addslashes($contract['ContractTitle'])) ?>')">
          <?= icon_svg('edit') ?> Complete Legal Review
        </button>
        <?php endif; ?>

        <?php if ($show_approve_link): ?>
        <div class="divider" style="margin:12px 0;"></div>
        <a href="<?= BASE_URL ?>/pages/approvals.php<?= $pending_approval ? '?act=' . $pending_approval['ApprovalID'] : '?contract_id=' . $id ?>"
           class="btn btn-primary" style="width:100%;justify-content:center;">
          <?= icon_svg('check-circle') ?> Review This Contract
        </a>
        <?php endif; ?>

        <?php if ($show_set_active): ?>
        <div class="divider" style="margin:12px 0;"></div>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="post_action" value="set_active">
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;"
                  data-confirm="Set this contract to Active?">
            Set Active
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status timeline -->
    <div class="card">
      <div class="card-header"><span class="card-title">Status History</span></div>
      <div class="card-body" style="padding:16px;">
        <?php if (empty($history)): ?>
        <p style="font-size:13px;color:var(--color-text-muted);text-align:center;padding:12px 0;">
          No history available.
        </p>
        <?php else: ?>
        <ul class="timeline">
          <?php foreach ($history as $h): ?>
          <li class="timeline-item">
            <div class="timeline-dot <?= in_array($h['NewStatus'], ['Approved','Active']) ? 'approved' :
                ($h['NewStatus'] === 'RevisionRequested' ? 'rejected' : 'pending') ?>"></div>
            <div class="timeline-title" style="font-size:12px;">
              <strong><?= e($h['NewStatus']) ?></strong>
              <?php if ($h['OldStatus']): ?>
              <span style="color:var(--color-text-muted);"> from <?= e($h['OldStatus']) ?></span>
              <?php endif; ?>
            </div>
            <div class="timeline-meta"><?= e($h['ActorName']) ?> · <?= fmt_datetime($h['ChangedAt']) ?></div>
            <?php if ($h['ChangeReason']): ?>
            <div class="timeline-comment"><?= e($h['ChangeReason']) ?></div>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /sidebar -->

</div>

<!-- Review completion modal (for Legal reviewer / Admin) -->
<?php if ($show_complete_review): ?>
<div id="reviewModal" style="display:none;position:fixed;inset:0;z-index:1000;
     background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:28px;width:520px;max-width:95vw;
              box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <h3 style="margin:0 0 4px;font-size:16px;">Complete Legal Review</h3>
    <p id="reviewModalSub" style="font-size:13px;color:var(--color-text-secondary);margin:0 0 20px;"></p>
    <form method="POST" action="<?= BASE_URL ?>/pages/approvals.php" id="reviewModalForm">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" value="complete_review">
      <input type="hidden" name="review_id"   id="reviewModalId" value="">
      <div class="form-group" style="margin-bottom:16px;">
        <label style="font-weight:600;display:block;margin-bottom:8px;">Decision <span class="req">*</span></label>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="review_status" value="Approved" style="accent-color:var(--color-success);">
            <span style="color:var(--color-success);font-weight:500;"><?= icon_svg('check-circle') ?> Approved — forward to Admin for final approval</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="review_status" value="RevisionRequired" style="accent-color:#d97706;">
            <span style="color:#b45309;font-weight:500;"><?= icon_svg('alert-circle') ?> Revision Required</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="radio" name="review_status" value="Rejected" style="accent-color:var(--color-danger);">
            <span style="color:var(--color-danger);font-weight:500;"><?= icon_svg('x') ?> Rejected</span>
          </label>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:20px;">
        <label for="review_comments">Comments <span style="color:var(--color-text-muted);font-weight:400;">(required for Revision / Rejection)</span></label>
        <textarea id="review_comments" name="comments" class="form-control" rows="3"
                  placeholder="Enter review notes or required changes…"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-ghost" onclick="closeReviewModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Review</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReviewModal(reviewId, code, title) {
  document.getElementById('reviewModalId').value = reviewId;
  document.getElementById('reviewModalSub').textContent = code + ' — ' + title;
  document.getElementById('review_comments').value = '';
  document.querySelectorAll('input[name="review_status"]').forEach(r => r.checked = false);
  document.getElementById('reviewModal').style.display = 'flex';
}
function closeReviewModal() { document.getElementById('reviewModal').style.display = 'none'; }
document.getElementById('reviewModal').addEventListener('click', e => {
  if (e.target === document.getElementById('reviewModal')) closeReviewModal();
});
document.getElementById('reviewModalForm').addEventListener('submit', function(e) {
  const sel = this.querySelector('input[name="review_status"]:checked');
  if (!sel) { e.preventDefault(); alert('Please select a review decision.'); return; }
  const c = document.getElementById('review_comments').value.trim();
  if (sel.value !== 'Approved' && !c) {
    e.preventDefault();
    document.getElementById('review_comments').classList.add('is-invalid');
    document.getElementById('review_comments').focus();
  }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
