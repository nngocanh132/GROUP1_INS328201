<?php
/**
 * Partner Portal — for External (partner company) users only
 *
 * Shows:
 *   - Pending contract proposals addressed to the user's company, with
 *     Approve / Request Revisions actions
 *   - All proposals history for their company (all statuses)
 *   - Quick summary of their company's contracts
 *   - Unread notifications
 *
 * Security:
 *   - Only External-role accounts can access this page.
 *   - Every proposal action is verified: the proposal's CounterpartyID
 *     must match the user's own CounterpartyID resolved from Contact.AccountID.
 *     This cannot be bypassed by tampering with POST data.
 *
 * Workflow transitions:
 *   Approve          → ContractProposal.Status = 'PartnerApproved'
 *                      Notifies all admin accounts
 *   Request Revisions→ ContractProposal.Status = 'Pending'  (reset to start)
 *                      Saves PartnerReviewNote
 *                      Notifies the proposing staff member
 *
 * After partner approves, the proposal still requires Admin approval before
 * staff can create the actual contract — partner approval alone is not enough.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Partner Portal';
$active_nav = 'partner_portal';

require_login();
$user = current_user();

// External-only page
if ($user['role'] !== 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'This page is for partner company users only.');
}

// ---------------------------------------------------------------
// Resolve this user's counterparty (from Contact.AccountID)
// This is the ground-truth company binding — not user-provided.
// ---------------------------------------------------------------
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
    // Account exists but has no counterparty link — show a clean error
    include __DIR__ . '/../includes/layout_head.php';
    echo '<div class="alert alert-danger" style="margin:32px auto;max-width:540px;">'
       . icon_svg('alert-circle')
       . '<span>Your account is not linked to any partner company. Please contact the Vinamilk team.</span></div>';
    include __DIR__ . '/../includes/layout_foot.php';
    exit;
}

$my_cp_id = (int)$my_contact['CounterpartyID'];

// Check if ContractProposal table exists
$proposals_enabled = db_table_exists('ContractProposal');

// ---------------------------------------------------------------
// Handle POST actions
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Security token mismatch.');
    }
    if (!$proposals_enabled) {
        redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
            'The Contract Proposals feature is not yet available. Please ask your admin to run the database migration.');
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

    // Security: CounterpartyID must match this user's company
    if (!$prop || (int)$prop['CounterpartyID'] !== $my_cp_id) {
        error_log(sprintf(
            'Partner portal access denied: account=%d tried proposal=%d (cp=%d, allowed=%d)',
            $user['account_id'], $prop_id,
            (int)($prop['CounterpartyID'] ?? 0), $my_cp_id
        ));
        redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Access denied.');
    }

    // The proposal must be in Pending status for partner to act on it
    if ($prop['Status'] !== 'Pending') {
        redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
            'This proposal is no longer awaiting your review (current status: ' . $prop['Status'] . ').');
    }

    if ($post_action === 'partner_approve') {
        // ---- Approve ----
        $db = get_db();
        $db->beginTransaction();
        try {
            db_query(
                "UPDATE ContractProposal
                 SET Status              = 'PartnerApproved',
                     PartnerReviewNote   = ?,
                     PartnerReviewedBy   = ?,
                     PartnerReviewedAt   = NOW()
                 WHERE ProposalID = ?",
                [$note ?: null, $user['account_id'], $prop_id]
            );

            // Notify all active admins
            $admin_accounts = db_query(
                "SELECT a.AccountID FROM Account a
                 JOIN Role r ON r.RoleID = a.RoleID
                 WHERE r.RoleName = 'Manager/Admin' AND a.Status = 'Active'"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($admin_accounts as $admin_id) {
                db_query(
                    "INSERT INTO Notification
                         (AccountID, Title, Message, NotificationType, IsRead, CreatedAt)
                     VALUES (?, ?, ?, 'Info', 0, NOW())",
                    [
                        $admin_id,
                        'Contract proposal approved by partner',
                        "The proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) has been approved by {$my_contact['CompanyName']} and is now awaiting your review.",
                    ]
                );
            }

            // Notify the proposing staff member
            db_query(
                "INSERT INTO Notification
                     (AccountID, Title, Message, NotificationType, IsRead, CreatedAt)
                 VALUES (?, ?, ?, 'Success', 0, NOW())",
                [
                    $prop['ProposerAccountID'],
                    'Partner company approved your proposal',
                    "{$my_contact['CompanyName']} has approved the proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}). It is now awaiting Admin approval.",
                ]
            );

            $db->commit();
            audit('PARTNER_APPROVE', 'ContractProposal', $prop_id, [
                'company' => $my_contact['CompanyName'],
                'note'    => $note,
            ]);

            redirect_with_flash(
                BASE_URL . '/pages/partner_portal.php',
                'success',
                "Proposal \"{$prop['ProposalTitle']}\" approved. It is now awaiting Admin review before a contract can be created."
            );

        } catch (Throwable $e) {
            $db->rollBack();
            error_log('partner_approve error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
                'An error occurred. Please try again.');
        }

    } elseif ($post_action === 'partner_revise') {
        // ---- Request Revisions ----
        if ($note === '') {
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
                'Please provide details explaining what revisions are needed.');
        }

        $db = get_db();
        $db->beginTransaction();
        try {
            // Reset to Pending so staff can amend and resubmit
            db_query(
                "UPDATE ContractProposal
                 SET Status            = 'PartnerRejected',
                     PartnerReviewNote = ?,
                     PartnerReviewedBy = ?,
                     PartnerReviewedAt = NOW()
                 WHERE ProposalID = ?",
                [$note, $user['account_id'], $prop_id]
            );

            // Notify the proposing staff member with the revision request
            db_query(
                "INSERT INTO Notification
                     (AccountID, Title, Message, NotificationType, IsRead, CreatedAt)
                 VALUES (?, ?, ?, 'Warning', 0, NOW())",
                [
                    $prop['ProposerAccountID'],
                    'Partner company requested revisions',
                    "{$my_contact['CompanyName']} has reviewed the proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) and requested revisions: {$note}",
                ]
            );

            $db->commit();
            audit('PARTNER_REVISE', 'ContractProposal', $prop_id, [
                'company' => $my_contact['CompanyName'],
                'note'    => $note,
            ]);

            redirect_with_flash(
                BASE_URL . '/pages/partner_portal.php',
                'success',
                "Revision request sent for \"{$prop['ProposalTitle']}\". The Vinamilk team has been notified."
            );

        } catch (Throwable $e) {
            $db->rollBack();
            error_log('partner_revise error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger',
                'An error occurred. Please try again.');
        }

    } else {
        redirect_with_flash(BASE_URL . '/pages/partner_portal.php', 'danger', 'Unknown action.');
    }
}

// ---------------------------------------------------------------
// Mark notifications as read when user visits
// ---------------------------------------------------------------
db_query(
    "UPDATE Notification SET IsRead = 1, ReadAt = NOW()
     WHERE AccountID = ? AND IsRead = 0",
    [$user['account_id']]
);

// ---------------------------------------------------------------
// Load proposals for this company
// ---------------------------------------------------------------
$filter_pstatus = trim($_GET['pstatus'] ?? '');
$allowed_pstatuses = ['Pending','PartnerApproved','PartnerRejected','AdminApproved','AdminRejected','ContractCreated','Withdrawn'];
if (!in_array($filter_pstatus, $allowed_pstatuses)) $filter_pstatus = '';

$all_proposals  = [];
$pending_count  = 0;

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
         ORDER BY
             CASE p.Status WHEN 'Pending' THEN 0 ELSE 1 END,
             p.CreatedAt DESC",
        $prop_params
    )->fetchAll();

    $pending_count = count(array_filter($all_proposals, fn($p) => $p['Status'] === 'Pending'));
}

// ---------------------------------------------------------------
// Load company contracts summary
// ---------------------------------------------------------------
$contracts_summary = db_query(
    "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
            c.ContractValue, c.Currency, c.StartDate, c.EndDate, c.UpdatedAt,
            ct.TypeName
     FROM Contract c
     JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
     WHERE c.CounterpartyID = ?
     ORDER BY c.UpdatedAt DESC
     LIMIT 10",
    [$my_cp_id]
)->fetchAll();

$contract_counts = db_query(
    "SELECT Status, COUNT(*) AS cnt FROM Contract WHERE CounterpartyID = ? GROUP BY Status",
    [$my_cp_id]
)->fetchAll(PDO::FETCH_KEY_PAIR);

$total_contracts  = array_sum($contract_counts);
$active_contracts = (int)($contract_counts['Active'] ?? 0);
$pending_contracts= (int)(($contract_counts['PendingReview'] ?? 0)
                         + ($contract_counts['PendingApproval'] ?? 0));

// ---------------------------------------------------------------
// Proposal to act on (from ?review=ID)
// ---------------------------------------------------------------
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

// Use proposal_badge() from helpers.php — maps statuses to partner-friendly labels
function proposal_status_badge(string $status): string {
    $map = [
        'Pending'         => ['Awaiting Your Review', 'badge-pending'],
        'PartnerApproved' => ['You Approved',         'badge-approved'],
        'PartnerRejected' => ['Revisions Requested',  'badge-cancelled'],
        'AdminApproved'   => ['Admin Approved',       'badge-active'],
        'AdminRejected'   => ['Admin Rejected',       'badge-rejected'],
        'ContractCreated' => ['Contract Created',     'badge-active'],
        'Withdrawn'       => ['Withdrawn',            'badge-draft'],
    ];
    [$label, $class] = $map[$status] ?? [$status, 'badge-draft'];
    return '<span class="badge ' . $class . '">' . htmlspecialchars($label, ENT_QUOTES) . '</span>';
}

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Partner Portal</h1>
    <p>
      <strong><?= e($my_contact['CompanyName']) ?></strong>
      <span style="color:var(--color-text-muted);">·</span>
      <?= e($my_contact['CounterpartyCode']) ?>
      <span style="color:var(--color-text-muted);">·</span>
      <?= status_badge($my_contact['CompanyStatus']) ?>
    </p>
  </div>
</div>

<!-- Company status warning -->
<?php if ($my_contact['CompanyStatus'] !== 'Active'): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span>Your company account status is <strong><?= e($my_contact['CompanyStatus']) ?></strong>. Some features may be restricted. Contact the Vinamilk team for assistance.</span>
</div>
<?php endif; ?>

<?php if (!$proposals_enabled): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span><strong>Contract Proposals</strong> feature is being set up. Please check back shortly.</span>
</div>
<?php endif; ?>

<!-- KPI tiles -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-tile">
    <div class="stat-icon orange">
      <?= icon_svg('file-plus') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $pending_count ?></div>
      <div class="stat-label">Awaiting Your Review</div>
      <div class="stat-sub">Contract proposals</div>
    </div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon blue">
      <?= icon_svg('file-text') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $total_contracts ?></div>
      <div class="stat-label">Total Contracts</div>
      <div class="stat-sub"><?= $active_contracts ?> active</div>
    </div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon green">
      <?= icon_svg('check-circle') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $active_contracts ?></div>
      <div class="stat-label">Active Contracts</div>
      <div class="stat-sub">with Vinamilk</div>
    </div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon <?= $pending_contracts > 0 ? 'orange' : 'blue' ?>">
      <?= icon_svg('clock') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $pending_contracts ?></div>
      <div class="stat-label">In Review / Approval</div>
      <div class="stat-sub">Contracts being processed</div>
    </div>
  </div>
</div>

<!-- ============================================================
     PROPOSAL REVIEW PANEL (when ?review=ID)
     ============================================================ -->
<?php if ($review_proposal): ?>
<div class="card" style="margin-bottom:20px;border:2px solid var(--color-brand-blue,#1761a8);">
  <div class="card-header" style="background:var(--color-accent-soft,#eef2ff);">
    <div style="display:flex;align-items:center;gap:10px;">
      <?= icon_svg('file-plus') ?>
      <span class="card-title">Review Proposal — <?= e($review_proposal['ProposalCode']) ?></span>
      <?= proposal_status_badge($review_proposal['Status']) ?>
    </div>
    <a href="<?= BASE_URL ?>/pages/partner_portal.php" class="btn btn-ghost btn-sm">
      <?= icon_svg('x') ?> Close
    </a>
  </div>
  <div class="card-body" style="padding:24px;">

    <!-- Proposal details -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
      <div>
        <h4 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin:0 0 12px;">
          Proposal Details
        </h4>
        <div class="detail-grid" style="gap:10px;">
          <div class="detail-item" style="grid-column:1/-1;">
            <div class="detail-label">Title</div>
            <div class="detail-value" style="font-weight:700;font-size:14px;"><?= e($review_proposal['ProposalTitle']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Proposal Code</div>
            <div class="detail-value mono"><?= e($review_proposal['ProposalCode']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Contract Type</div>
            <div class="detail-value"><?= e($review_proposal['TypeName']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Vinamilk Department</div>
            <div class="detail-value"><?= e($review_proposal['DepartmentName']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Proposed By</div>
            <div class="detail-value">
              <?= e($review_proposal['ProposerName']) ?>
              <div class="td-meta"><?= e($review_proposal['ProposerDept']) ?></div>
            </div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Submitted On</div>
            <div class="detail-value"><?= fmt_datetime($review_proposal['CreatedAt']) ?></div>
          </div>
          <?php if ($review_proposal['ProposedStartDate']): ?>
          <div class="detail-item">
            <div class="detail-label">Proposed Start</div>
            <div class="detail-value"><?= fmt_date($review_proposal['ProposedStartDate']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($review_proposal['ProposedEndDate']): ?>
          <div class="detail-item">
            <div class="detail-label">Proposed End</div>
            <div class="detail-value"><?= fmt_date($review_proposal['ProposedEndDate']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($review_proposal['EstimatedValue']): ?>
          <div class="detail-item" style="grid-column:1/-1;">
            <div class="detail-label">Estimated Value</div>
            <div class="detail-value" style="font-weight:700;font-size:15px;">
              <?= money($review_proposal['EstimatedValue'], $review_proposal['Currency']) ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <h4 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--color-text-muted);margin:0 0 12px;">
          Business Purpose
        </h4>
        <div style="background:var(--color-bg-secondary,#f8fafc);border:1px solid var(--color-border);border-radius:8px;padding:16px;font-size:13px;line-height:1.7;color:var(--color-text-primary);">
          <?= nl2br(e($review_proposal['Summary'])) ?>
        </div>

        <?php if ($review_proposal['PartnerReviewNote']): ?>
        <div class="alert" style="margin-top:12px;background:#fff7ed;border-color:#fed7aa;padding:12px 14px;font-size:12px;">
          <?= icon_svg('info') ?>
          <span><strong>Previous note from your company:</strong> <?= e($review_proposal['PartnerReviewNote']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($review_proposal['Status'] === 'Pending'): ?>
    <!-- Action forms -->
    <div style="border-top:1px solid var(--color-border);padding-top:20px;
                display:grid;grid-template-columns:1fr 1fr;gap:16px;">

      <!-- Approve -->
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px;">
        <div style="font-weight:700;font-size:13px;color:#15803d;margin-bottom:6px;">
          <?= icon_svg('check-circle') ?> Approve Proposal
        </div>
        <p style="font-size:12px;color:#166534;margin:0 0 12px;">
          Approving confirms your company agrees to proceed with this contract proposal.
          The request will then be forwarded to <strong>Vinamilk Admin</strong> for final approval
          before a contract is created.
        </p>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="post_action"  value="partner_approve">
          <input type="hidden" name="proposal_id"  value="<?= $review_proposal['ProposalID'] ?>">
          <div class="form-group" style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;color:#166534;">
              Optional Note
            </label>
            <textarea name="review_note" class="form-control" rows="2" style="font-size:12px;"
                      placeholder="Any comments or conditions (optional)…"></textarea>
          </div>
          <button type="submit" class="btn btn-primary"
                  style="background:#16a34a;border-color:#16a34a;width:100%;justify-content:center;"
                  data-confirm="Approve this contract proposal from Vinamilk?">
            <?= icon_svg('check-circle') ?> Approve Proposal
          </button>
        </form>
      </div>

      <!-- Request Revisions -->
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:16px;">
        <div style="font-weight:700;font-size:13px;color:#c2410c;margin-bottom:6px;">
          <?= icon_svg('edit') ?> Request Revisions
        </div>
        <p style="font-size:12px;color:#9a3412;margin:0 0 12px;">
          If the proposal contains incorrect or unreasonable terms, you can request revisions.
          The Vinamilk team will receive your comments and revise the proposal before resubmitting.
        </p>
        <form method="POST" id="reviseForm">
          <?= csrf_field() ?>
          <input type="hidden" name="post_action"  value="partner_revise">
          <input type="hidden" name="proposal_id"  value="<?= $review_proposal['ProposalID'] ?>">
          <div class="form-group" style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;color:#9a3412;">
              Required Revisions <span class="req">*</span>
            </label>
            <textarea name="review_note" class="form-control" rows="3" style="font-size:12px;"
                      id="revisionNote"
                      placeholder="Describe specifically what needs to be changed or corrected…"
                      required></textarea>
          </div>
          <button type="submit" class="btn btn-primary"
                  style="background:#ea580c;border-color:#ea580c;width:100%;justify-content:center;">
            <?= icon_svg('edit') ?> Request Revisions
          </button>
        </form>
      </div>

    </div>

    <?php else: ?>
    <div class="alert alert-info" style="margin-top:0;">
      <?= icon_svg('info') ?>
      <span>This proposal has already been processed (status: <strong><?= e($review_proposal['Status']) ?></strong>). No further action is needed from your side.</span>
    </div>
    <?php endif; ?>

  </div><!-- /card-body -->
</div>
<?php endif; /* review panel */ ?>

<!-- ============================================================
     PROPOSALS FOR YOUR COMPANY
     ============================================================ -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('file-plus') ?> Contract Proposals</span>
    <div style="display:flex;align-items:center;gap:12px;">
      <?php if ($pending_count > 0): ?>
      <span class="badge badge-pending"><?= $pending_count ?> awaiting review</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="table-toolbar" style="padding:10px 16px;">
    <form method="GET" style="display:contents;">
      <select name="pstatus" class="filter-select" data-autosubmit>
        <option value="">All Statuses</option>
        <?php foreach ($allowed_pstatuses as $s): ?>
        <option value="<?= $s ?>" <?= $filter_pstatus === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($filter_pstatus): ?>
      <a href="<?= BASE_URL ?>/pages/partner_portal.php" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
    </form>
    <div class="toolbar-spacer"></div>
    <span style="font-size:12px;color:var(--color-text-muted);"><?= count($all_proposals) ?> total</span>
  </div>

  <?php if (empty($all_proposals)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><?= icon_svg('file-plus') ?></div>
    <h3>No contract proposals yet</h3>
    <p>Vinamilk will submit contract proposals to your company here for review.</p>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Proposal Title</th>
          <th>Type</th>
          <th>Est. Value</th>
          <th>Submitted By</th>
          <th>Submitted At</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($all_proposals as $p): ?>
        <?php $is_reviewing = (isset($_GET['review']) && (int)$_GET['review'] === (int)$p['ProposalID']); ?>
        <tr <?= $is_reviewing ? 'style="background:var(--color-accent-soft,#eef2ff);"' : '' ?>>
          <td class="td-ref"><?= e($p['ProposalCode']) ?></td>
          <td>
            <span style="font-weight:600;"><?= e(mb_strimwidth($p['ProposalTitle'], 0, 50, '…')) ?></span>
            <?php if ($p['Summary']): ?>
            <div class="td-meta"><?= e(mb_strimwidth($p['Summary'], 0, 60, '…')) ?></div>
            <?php endif; ?>
            <?php if ($p['PartnerReviewNote'] && in_array($p['Status'], ['PartnerRejected'])): ?>
            <div class="td-meta" style="color:var(--color-warning,#d97706);margin-top:2px;">
              Your note: <?= e(mb_strimwidth($p['PartnerReviewNote'], 0, 55, '…')) ?>
            </div>
            <?php endif; ?>
            <?php if ($p['AdminReviewNote'] && in_array($p['Status'], ['AdminRejected'])): ?>
            <div class="td-meta" style="color:var(--color-danger);margin-top:2px;">
              Admin note: <?= e(mb_strimwidth($p['AdminReviewNote'], 0, 55, '…')) ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="td-meta"><?= e($p['TypeName']) ?></td>
          <td class="td-meta"><?= $p['EstimatedValue'] ? money($p['EstimatedValue'], $p['Currency']) : '—' ?></td>
          <td class="td-meta"><?= e($p['ProposerName']) ?></td>
          <td class="td-meta"><?= fmt_date($p['CreatedAt']) ?></td>
          <td><?= proposal_status_badge($p['Status']) ?></td>
          <td>
            <?php if ($p['Status'] === 'Pending'): ?>
            <a href="<?= BASE_URL ?>/pages/partner_portal.php?review=<?= $p['ProposalID'] ?>"
               class="btn btn-primary btn-sm">
              <?= icon_svg('eye') ?> Review
            </a>
            <?php elseif ($p['ContractID']): ?>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $p['ContractID'] ?>"
               class="btn btn-ghost btn-sm">
              <?= icon_svg('file-text') ?> View Contract
            </a>
            <?php else: ?>
            <span style="font-size:11px;color:var(--color-text-muted);">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     COMPANY CONTRACTS SUMMARY
     ============================================================ -->
<?php if (!empty($contracts_summary)): ?>
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('file-text') ?> Our Contracts with Vinamilk</span>
    <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-ghost btn-sm">
      View all →
    </a>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Contract Title</th>
          <th>Type</th>
          <th>Value</th>
          <th>End Date</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contracts_summary as $c): ?>
        <tr>
          <td class="td-ref"><?= e($c['ContractCode']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $c['ContractID'] ?>"
               style="font-weight:600;"><?= e(mb_strimwidth($c['ContractTitle'], 0, 50, '…')) ?></a>
          </td>
          <td class="td-meta"><?= e($c['TypeName']) ?></td>
          <td><?= money($c['ContractValue'], $c['Currency'] ?? 'VND') ?></td>
          <td class="td-meta">
            <?= $c['EndDate'] ? fmt_date($c['EndDate']) : '—' ?>
            <?php if ($c['EndDate'] && $c['Status'] === 'Active'): ?>
            <?php $dl = (int)(new DateTime())->diff(new DateTime($c['EndDate']))->format('%r%a'); ?>
            <?php if ($dl >= 0 && $dl <= 60): ?>
            <div style="font-size:11px;color:<?= $dl<=30?'var(--color-danger)':'var(--color-warning)' ?>;font-weight:600;">
              <?= $dl ?> day<?= $dl!==1?'s':'' ?> left
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?= status_badge($c['Status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $c['ContractID'] ?>"
               class="btn btn-ghost btn-sm"><?= icon_svg('eye') ?> View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
// Scroll to review panel when a proposal is being reviewed
<?php if ($review_proposal): ?>
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('[style*="border:2px solid"]');
  if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>

// Validate revision form — note is required
document.getElementById('reviseForm')?.addEventListener('submit', function(e) {
  const note = document.getElementById('revisionNote').value.trim();
  if (!note) {
    e.preventDefault();
    document.getElementById('revisionNote').classList.add('is-invalid');
    document.getElementById('revisionNote').focus();
    return;
  }
  document.getElementById('revisionNote').classList.remove('is-invalid');
});
</script>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
