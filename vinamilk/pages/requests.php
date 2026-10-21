<?php
/**
 * Contract Requests page
 *
 * Tab 1 — Contract Proposals (ContractProposal table)
 *   Staff creates → Partner approves → Admin approves → Staff creates Contract
 *
 * Tab 2 — Workflow Requests (ContractRequest table)
 *   Existing workflow requests (revision, review, approval) on Contracts
 *
 * Roles:
 *   Staff  : create proposals, see own proposals, see workflow requests
 *   Admin  : approve/reject proposals, see all proposals, see workflow requests
 *   External : access denied
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Contract Requests';
$active_nav = 'requests';

require_login();
if (current_user()['role'] === 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
}
$user = current_user();

// Check if the ContractProposal table exists (migration may not have run)
$proposals_enabled = db_table_exists('ContractProposal');

// ---------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------
$errors    = [];
$old       = [];
$show_form = isset($_GET['new']) && $user['role'] === 'staff';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
        $show_form = true;
    } elseif (!$proposals_enabled) {
        redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger',
            'The Contract Proposals feature requires a database migration. Please ask your admin to run vinamilk_clm_migration_001.sql in phpMyAdmin.');
    } else {
        $post_action = trim($_POST['post_action'] ?? '');

        // ---- Admin: approve / reject proposal ----
        if ($post_action === 'admin_approve' || $post_action === 'admin_reject') {
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
                "UPDATE ContractProposal
                 SET Status = ?, AdminReviewNote = ?, AdminReviewedBy = ?, AdminReviewedAt = NOW()
                 WHERE ProposalID = ?",
                [$new_status, $note ?: null, $user['account_id'], $prop_id]
            );

            // Notify proposing staff
            $staff_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID = ?",
                [$prop['ProposedByStaffID']])->fetchColumn();
            if ($staff_acc) {
                $msg = ($post_action === 'admin_approve')
                    ? "Your proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) was approved by Admin. You can now create the contract."
                    : "Your proposal \"{$prop['ProposalTitle']}\" ({$prop['ProposalCode']}) was rejected by Admin." . ($note ? " Reason: $note" : '');
                db_query(
                    "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt)
                     VALUES (?, ?, ?, 'Info', 0, NOW())",
                    [$staff_acc,
                     'Proposal ' . ($post_action === 'admin_approve' ? 'approved' : 'rejected') . ' by Admin',
                     $msg]
                );
            }

            audit('PROPOSAL_' . ($post_action === 'admin_approve' ? 'APPROVED' : 'REJECTED'),
                  'ContractProposal', $prop_id, ['note' => $note]);

            redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'success',
                $post_action === 'admin_approve'
                    ? "Proposal \"{$prop['ProposalTitle']}\" approved."
                    : "Proposal \"{$prop['ProposalTitle']}\" rejected.");

        // ---- Staff: withdraw proposal ----
        } elseif ($post_action === 'withdraw') {
            $prop_id = (int)($_POST['proposal_id'] ?? 0);
            $prop    = db_query("SELECT * FROM ContractProposal WHERE ProposalID = ?", [$prop_id])->fetch();
            if (!$prop || !in_array($prop['Status'], ['Pending', 'PartnerApproved'])) {
                redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'danger',
                    'Proposal cannot be withdrawn at this stage.');
            }
            $proposer_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID = ?",
                [$prop['ProposedByStaffID']])->fetchColumn();
            if ((int)$proposer_acc !== (int)$user['account_id'] && $user['role'] !== 'admin') {
                redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger', 'Access denied.');
            }
            db_query("UPDATE ContractProposal SET Status = 'Withdrawn' WHERE ProposalID = ?", [$prop_id]);
            audit('PROPOSAL_WITHDRAWN', 'ContractProposal', $prop_id, []);
            redirect_with_flash(BASE_URL . '/pages/requests.php?tab=proposals', 'success',
                "Proposal \"{$prop['ProposalTitle']}\" withdrawn.");

        // ---- Staff: create new proposal ----
        } elseif ($post_action === 'create_proposal') {
            if ($user['role'] !== 'staff') {
                redirect_with_flash(BASE_URL . '/pages/requests.php', 'danger', 'Only staff can create proposals.');
            }
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

                    // Notify active partner contacts
                    $partner_accs = db_query(
                        "SELECT a.AccountID FROM Contact c JOIN Account a ON a.AccountID=c.AccountID
                         WHERE c.CounterpartyID = ? AND a.Status = 'Active'",
                        [$old['CounterpartyID']]
                    )->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($partner_accs as $pid) {
                        db_query(
                            "INSERT INTO Notification (AccountID, Title, Message, NotificationType, IsRead, CreatedAt)
                             VALUES (?, ?, ?, 'Info', 0, NOW())",
                            [$pid,
                             'New contract proposal for your review',
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
        }
    }
}

// ---------------------------------------------------------------
// Load display data
// ---------------------------------------------------------------
$tab = trim($_GET['tab'] ?? 'proposals');

// Proposals
$proposals         = [];
$pending_admin     = 0;
$prop_status_filter= trim($_GET['pstatus'] ?? '');
$prop_allowed      = ['Pending','PartnerApproved','PartnerRejected','AdminApproved','AdminRejected','ContractCreated','Withdrawn'];
if (!in_array($prop_status_filter, $prop_allowed)) $prop_status_filter = '';

if ($proposals_enabled) {
    $pw = ['1=1'];
    $pp = [];
    if ($user['role'] === 'staff') { $pw[] = 'p.ProposedByStaffID = ?'; $pp[] = $user['staff_id']; }
    if ($prop_status_filter)       { $pw[] = 'p.Status = ?';            $pp[] = $prop_status_filter; }

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

// Workflow requests (ContractRequest)
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

$contract_types = get_contract_types();
$departments    = get_departments();
$counterparties = get_counterparties();

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Contract Requests</h1>
    <p>Manage contract proposals and workflow requests.</p>
  </div>
  <?php if ($user['role'] === 'staff' && !$show_form && $proposals_enabled): ?>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/requests.php?new=1&tab=proposals" class="btn btn-primary">
      <?= icon_svg('plus') ?> New Contract Proposal
    </a>
  </div>
  <?php endif; ?>
</div>

<?php if (!$proposals_enabled): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span>
    <strong>Database migration required.</strong>
    The Contract Proposals feature needs the <code>ContractProposal</code> table.
    Please run <code>database/vinamilk_clm_migration_001.sql</code> in phpMyAdmin, then reload this page.
  </span>
</div>
<?php endif; ?>

<?php if ($pending_admin > 0 && $user['role'] === 'admin'): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span>
    <strong><?= $pending_admin ?> proposal<?= $pending_admin > 1 ? 's' : '' ?></strong>
    approved by partner and awaiting your review.
    <a href="?tab=proposals&pstatus=PartnerApproved" style="font-weight:600;">Review now →</a>
  </span>
</div>
<?php endif; ?>

<!-- ============================================================
     NEW PROPOSAL FORM
     ============================================================ -->
<?php if ($show_form && $user['role'] === 'staff' && $proposals_enabled): ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <?= icon_svg('alert-circle') ?>
  <span><?= isset($errors[0]) ? e($errors[0]) : 'Please correct the errors below.' ?></span>
</div>
<?php endif; ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span><strong>Workflow:</strong> Submit proposal → partner reviews → Admin approves → you create the contract.</span>
</div>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('file-plus') ?> New Contract Proposal</span>
  </div>
  <form method="POST" action="<?= BASE_URL ?>/pages/requests.php?tab=proposals" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="post_action" value="create_proposal">
    <div class="card-body" style="padding:28px;">
      <div class="form-grid">

        <div class="form-group span-2">
          <label for="ProposalTitle">Proposal Title <span class="req">*</span></label>
          <input type="text" id="ProposalTitle" name="ProposalTitle" maxlength="200"
                 class="form-control <?= isset($errors['ProposalTitle']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['ProposalTitle'] ?? '') ?>"
                 placeholder="e.g., Supply Agreement for Raw Materials Q1 2027">
          <?php if (isset($errors['ProposalTitle'])): ?><span class="form-error visible"><?= e($errors['ProposalTitle']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="ContractTypeID">Contract Type <span class="req">*</span></label>
          <select id="ContractTypeID" name="ContractTypeID"
                  class="form-control <?= isset($errors['ContractTypeID']) ? 'is-invalid' : '' ?>">
            <option value="">Select type…</option>
            <?php foreach ($contract_types as $ct): ?>
            <option value="<?= $ct['ContractTypeID'] ?>"
                    <?= (int)($old['ContractTypeID'] ?? 0) === (int)$ct['ContractTypeID'] ? 'selected' : '' ?>>
              <?= e($ct['TypeName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['ContractTypeID'])): ?><span class="form-error visible"><?= e($errors['ContractTypeID']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="DepartmentID">Department <span class="req">*</span></label>
          <select id="DepartmentID" name="DepartmentID"
                  class="form-control <?= isset($errors['DepartmentID']) ? 'is-invalid' : '' ?>">
            <option value="">Select department…</option>
            <?php foreach ($departments as $dep): ?>
            <option value="<?= $dep['DepartmentID'] ?>"
                    <?= (int)($old['DepartmentID'] ?? 0) === (int)$dep['DepartmentID'] ? 'selected' : '' ?>>
              <?= e($dep['DepartmentName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['DepartmentID'])): ?><span class="form-error visible"><?= e($errors['DepartmentID']) ?></span><?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="CounterpartyID">Partner Company <span class="req">*</span></label>
          <select id="CounterpartyID" name="CounterpartyID"
                  class="form-control <?= isset($errors['CounterpartyID']) ? 'is-invalid' : '' ?>">
            <option value="">Select partner company…</option>
            <?php foreach ($counterparties as $cp): ?>
            <option value="<?= $cp['CounterpartyID'] ?>"
                    <?= (int)($old['CounterpartyID'] ?? 0) === (int)$cp['CounterpartyID'] ? 'selected' : '' ?>>
              [<?= e($cp['CounterpartyCode']) ?>] <?= e($cp['CompanyName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">Only Active companies listed. <a href="<?= BASE_URL ?>/pages/create_counterparty.php">Register new →</a></span>
          <?php if (isset($errors['CounterpartyID'])): ?><span class="form-error visible"><?= e($errors['CounterpartyID']) ?></span><?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="Summary">Business Purpose <span class="req">*</span></label>
          <textarea id="Summary" name="Summary" rows="3"
                    class="form-control <?= isset($errors['Summary']) ? 'is-invalid' : '' ?>"
                    placeholder="Describe the purpose and key deliverables…"><?= e($old['Summary'] ?? '') ?></textarea>
          <?php if (isset($errors['Summary'])): ?><span class="form-error visible"><?= e($errors['Summary']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="ProposedStartDate">Proposed Start Date <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></label>
          <input type="date" id="ProposedStartDate" name="ProposedStartDate" class="form-control"
                 value="<?= e($old['ProposedStartDate'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label for="ProposedEndDate">Proposed End Date <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></label>
          <input type="date" id="ProposedEndDate" name="ProposedEndDate"
                 class="form-control <?= isset($errors['ProposedEndDate']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['ProposedEndDate'] ?? '') ?>">
          <?php if (isset($errors['ProposedEndDate'])): ?><span class="form-error visible"><?= e($errors['ProposedEndDate']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="EstimatedValue">Estimated Value <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></label>
          <div class="input-group">
            <span class="input-prefix">
              <select name="Currency" style="border:none;background:transparent;font-size:12px;font-weight:600;color:var(--color-text-secondary);cursor:pointer;padding:0;font-family:var(--font-sans);">
                <option value="VND" <?= ($old['Currency'] ?? 'VND') === 'VND' ? 'selected' : '' ?>>VND</option>
                <option value="USD" <?= ($old['Currency'] ?? '') === 'USD'    ? 'selected' : '' ?>>USD</option>
              </select>
            </span>
            <input type="number" id="EstimatedValue" name="EstimatedValue" min="0" step="1" placeholder="0"
                   class="form-control <?= isset($errors['EstimatedValue']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['EstimatedValue'] ?? '') ?>">
          </div>
          <?php if (isset($errors['EstimatedValue'])): ?><span class="form-error visible"><?= e($errors['EstimatedValue']) ?></span><?php endif; ?>
        </div>

      </div>
    </div>
    <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px;">
      <a href="<?= BASE_URL ?>/pages/requests.php?tab=proposals" class="btn btn-ghost">Cancel</a>
      <button type="submit" class="btn btn-primary">
        <?= icon_svg('check-circle') ?> Submit Proposal
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--color-border);padding-bottom:0;">
  <a href="<?= BASE_URL ?>/pages/requests.php?tab=proposals"
     style="padding:8px 16px;font-size:13px;font-weight:600;border-radius:6px 6px 0 0;
            text-decoration:none;border:2px solid transparent;border-bottom:none;margin-bottom:-2px;
            <?= $tab !== 'workflow' ? 'background:#fff;border-color:var(--color-border);color:var(--color-brand-blue);' : 'color:var(--color-text-secondary);' ?>">
    Contract Proposals
    <?php $awaiting = array_filter($proposals, fn($p) => in_array($p['Status'], ['Pending','PartnerApproved'])); ?>
    <?php if ($user['role'] === 'admin' && $pending_admin > 0): ?>
    <span class="badge badge-pending" style="margin-left:4px;"><?= $pending_admin ?></span>
    <?php elseif ($user['role'] === 'staff' && count($awaiting) > 0): ?>
    <span class="badge badge-pending" style="margin-left:4px;"><?= count($awaiting) ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= BASE_URL ?>/pages/requests.php?tab=workflow"
     style="padding:8px 16px;font-size:13px;font-weight:600;border-radius:6px 6px 0 0;
            text-decoration:none;border:2px solid transparent;border-bottom:none;margin-bottom:-2px;
            <?= $tab === 'workflow' ? 'background:#fff;border-color:var(--color-border);color:var(--color-brand-blue);' : 'color:var(--color-text-secondary);' ?>">
    Workflow Requests
  </a>
</div>

<!-- ============================================================
     TAB: PROPOSALS
     ============================================================ -->
<?php if ($tab !== 'workflow'): ?>
<div class="card">
  <div class="table-toolbar" style="padding:12px 16px;">
    <form method="GET" style="display:contents;">
      <input type="hidden" name="tab" value="proposals">
      <select name="pstatus" class="filter-select" data-autosubmit>
        <option value="">All Statuses</option>
        <?php foreach ($prop_allowed as $s): ?>
        <option value="<?= $s ?>" <?= $prop_status_filter === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($prop_status_filter): ?>
      <a href="<?= BASE_URL ?>/pages/requests.php?tab=proposals" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
    </form>
    <div class="toolbar-spacer"></div>
    <span style="font-size:12px;color:var(--color-text-muted);"><?= count($proposals) ?> proposal<?= count($proposals) !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (empty($proposals)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><?= icon_svg('file-plus') ?></div>
    <h3><?= $prop_status_filter ? 'No matching proposals' : 'No contract proposals yet' ?></h3>
    <p><?= $proposals_enabled ? 'Submit a proposal to start the approval workflow.' : 'Run the database migration to enable proposals.' ?></p>
    <?php if ($proposals_enabled && $user['role'] === 'staff'): ?>
    <a href="<?= BASE_URL ?>/pages/requests.php?new=1&tab=proposals" class="btn btn-primary btn-sm">
      <?= icon_svg('plus') ?> New Proposal
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th><th>Title</th><th>Partner Company</th><th>Type</th>
          <th>Est. Value</th><th>By</th><th>Date</th><th>Status</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($proposals as $p): ?>
        <tr>
          <td class="td-ref"><?= e($p['ProposalCode']) ?></td>
          <td>
            <span style="font-weight:600;"><?= e(mb_strimwidth($p['ProposalTitle'], 0, 50, '…')) ?></span>
            <?php if ($p['Summary']): ?><div class="td-meta"><?= e(mb_strimwidth($p['Summary'], 0, 55, '…')) ?></div><?php endif; ?>
          </td>
          <td><?= e($p['CounterpartyName']) ?></td>
          <td class="td-meta"><?= e($p['TypeName']) ?></td>
          <td class="td-meta"><?= $p['EstimatedValue'] ? money($p['EstimatedValue'], $p['Currency']) : '—' ?></td>
          <td class="td-meta"><?= e($p['ProposerName']) ?></td>
          <td class="td-meta"><?= fmt_date($p['CreatedAt']) ?></td>
          <td><?= proposal_badge($p['Status']) ?></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:nowrap;">
              <?php if ($p['Status'] === 'AdminApproved' && !$p['ContractID']): ?>
              <a href="<?= BASE_URL ?>/pages/create_request.php" class="btn btn-primary btn-sm">
                <?= icon_svg('file-plus') ?> Create Contract
              </a>
              <?php endif; ?>
              <?php if ($user['role'] === 'admin' && $p['Status'] === 'PartnerApproved'): ?>
              <button type="button" class="btn btn-ghost btn-sm"
                      style="color:var(--color-success);border-color:var(--color-success);"
                      onclick="openAdminModal(<?= $p['ProposalID'] ?>, '<?= e(addslashes($p['ProposalTitle'])) ?>', 'approve')">
                &#10003; Approve
              </button>
              <button type="button" class="btn btn-ghost btn-sm"
                      style="color:var(--color-danger);border-color:var(--color-danger);"
                      onclick="openAdminModal(<?= $p['ProposalID'] ?>, '<?= e(addslashes($p['ProposalTitle'])) ?>', 'reject')">
                &#10005; Reject
              </button>
              <?php endif; ?>
              <?php if (in_array($p['Status'], ['Pending','PartnerApproved'])): ?>
              <?php
              $prop_acc = db_query("SELECT AccountID FROM Staff WHERE StaffID = ?", [$p['ProposedByStaffID']])->fetchColumn();
              if ((int)$prop_acc === (int)$user['account_id'] || $user['role'] === 'admin'):
              ?>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="post_action" value="withdraw">
                <input type="hidden" name="proposal_id" value="<?= $p['ProposalID'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"
                        data-confirm="Withdraw proposal &quot;<?= e($p['ProposalTitle']) ?>&quot;?">
                  Withdraw
                </button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
              <?php if ($p['ContractID']): ?>
              <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $p['ContractID'] ?>"
                 class="btn btn-ghost btn-sm">View Contract</a>
              <?php endif; ?>
            </div>
            <?php if (in_array($p['Status'], ['AdminRejected','PartnerRejected']) && ($p['AdminReviewNote'] || $p['PartnerReviewNote'])): ?>
            <div class="td-meta" style="color:var(--color-danger);margin-top:4px;font-size:11px;">
              <?= e($p['AdminReviewNote'] ?? $p['PartnerReviewNote'] ?? '') ?>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     TAB: WORKFLOW REQUESTS
     ============================================================ -->
<?php if ($tab === 'workflow'): ?>
<div class="card">
  <form id="filterForm" method="GET">
    <input type="hidden" name="tab" value="workflow">
    <div class="table-toolbar">
      <div class="search-field">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="search" name="q" value="<?= e($req_q) ?>" placeholder="Search by contract…" autocomplete="off">
      </div>
      <select name="status" class="filter-select" data-autosubmit>
        <option value="">All Statuses</option>
        <?php foreach ($req_allowed_st as $s): ?>
        <option value="<?= $s ?>" <?= $req_st === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type" class="filter-select" data-autosubmit>
        <option value="">All Types</option>
        <?php foreach ($req_allowed_type as $t): ?>
        <option value="<?= $t ?>" <?= $req_type === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($req_q || $req_st || $req_type): ?>
      <a href="<?= BASE_URL ?>/pages/requests.php?tab=workflow" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
      <div class="toolbar-spacer"></div>
      <span style="font-size:12px;color:var(--color-text-muted);"><?= $req_result['total'] ?> record<?= $req_result['total'] !== 1 ? 's' : '' ?></span>
    </div>
  </form>

  <?php if (empty($req_result['rows'])): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><?= icon_svg('file-plus') ?></div>
    <h3>No workflow requests yet</h3>
    <p>Workflow requests are created automatically when contracts enter the review/approval process.</p>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Contract</th><th>Type</th><th>Assigned Side</th><th>Requested By</th><th>Date</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($req_result['rows'] as $r): ?>
        <tr>
          <td class="td-ref">#<?= $r['RequestID'] ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $r['ContractID'] ?>" style="font-weight:600;"><?= e($r['ContractCode']) ?></a>
            <div class="td-meta"><?= e(mb_strimwidth($r['ContractTitle'], 0, 45, '…')) ?></div>
          </td>
          <td><?= e($r['RequestType']) ?></td>
          <td><span class="badge <?= $r['AssignedSide'] === 'Internal' ? 'badge-review' : 'badge-pending' ?>"><?= e($r['AssignedSide']) ?></span></td>
          <td><?= e($r['RequesterName']) ?></td>
          <td class="td-meta"><?= fmt_datetime($r['RequestedAt']) ?></td>
          <td><?= status_badge($r['Status']) ?></td>
          <td><a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $r['ContractID'] ?>" class="btn btn-ghost btn-sm">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($req_result['pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">Showing <?= ($req_page-1)*DEFAULT_PAGE_SIZE+1 ?>–<?= min($req_page*DEFAULT_PAGE_SIZE,$req_result['total']) ?> of <?= $req_result['total'] ?></span>
    <div class="page-btns">
      <?php $base = BASE_URL . '/pages/requests.php?' . http_build_query(array_filter(['tab'=>'workflow','q'=>$req_q,'status'=>$req_st,'type'=>$req_type])); ?>
      <a href="<?= $base ?>&page=<?= $req_page-1 ?>" class="page-btn <?= $req_page<=1?'disabled':'' ?>">‹</a>
      <?php for ($p=1;$p<=$req_result['pages'];$p++): ?><a href="<?= $base ?>&page=<?= $p ?>" class="page-btn <?= $p===$req_page?'active':'' ?>"><?= $p ?></a><?php endfor; ?>
      <a href="<?= $base ?>&page=<?= $req_page+1 ?>" class="page-btn <?= $req_page>=$req_result['pages']?'disabled':'' ?>">›</a>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Admin approve/reject modal -->
<?php if ($user['role'] === 'admin' && $proposals_enabled): ?>
<div id="adminModal" style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;padding:28px;width:520px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2);">
    <h3 id="adminModalTitle" style="margin:0 0 8px;font-size:16px;"></h3>
    <p id="adminModalSub" style="font-size:13px;color:var(--color-text-secondary);margin:0 0 20px;"></p>
    <form method="POST" id="adminModalForm" action="<?= BASE_URL ?>/pages/requests.php?tab=proposals">
      <?= csrf_field() ?>
      <input type="hidden" name="post_action" id="adminModalAction" value="">
      <input type="hidden" name="proposal_id" id="adminModalId"     value="">
      <div class="form-group" style="margin-bottom:20px;">
        <label for="admin_note">Review Note</label>
        <textarea id="admin_note" name="admin_note" class="form-control" rows="3"
                  placeholder="Optional note to the staff member…"></textarea>
        <span id="adminModalHint" style="display:none;font-size:12px;color:var(--color-danger);margin-top:4px;">A note is required when rejecting.</span>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button type="button" class="btn btn-ghost" onclick="closeAdminModal()">Cancel</button>
        <button type="submit" id="adminModalBtn" class="btn btn-primary">Submit</button>
      </div>
    </form>
  </div>
</div>
<script>
function openAdminModal(id, title, mode) {
  document.getElementById('adminModalId').value     = id;
  document.getElementById('adminModalAction').value = mode === 'approve' ? 'admin_approve' : 'admin_reject';
  document.getElementById('adminModalTitle').textContent = mode === 'approve' ? 'Approve Proposal' : 'Reject Proposal';
  document.getElementById('adminModalSub').textContent   = title;
  document.getElementById('adminModalHint').style.display = mode === 'reject' ? '' : 'none';
  document.getElementById('admin_note').required = mode === 'reject';
  document.getElementById('adminModalBtn').textContent  = mode === 'approve' ? 'Approve' : 'Reject';
  document.getElementById('adminModalBtn').style.background   = mode === 'approve' ? '' : 'var(--color-danger)';
  document.getElementById('adminModalBtn').style.borderColor  = mode === 'approve' ? '' : 'var(--color-danger)';
  document.getElementById('admin_note').value = '';
  document.getElementById('adminModal').style.display = 'flex';
}
function closeAdminModal() { document.getElementById('adminModal').style.display = 'none'; }
document.getElementById('adminModal').addEventListener('click', e => { if (e.target === document.getElementById('adminModal')) closeAdminModal(); });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
