<?php
/**
 * Counterparties page
 *
 * Staff  : read-only list + "Register Partner Company" button
 * Admin  : full approval workflow — Approve / Reject with detail review panel,
 *          Suspend / Reactivate active companies
 *
 * Approval detail panel (admin only):
 *   Shown when ?review=<CounterpartyID> is in the URL.
 *   Displays all company fields + contact person details + proposer info
 *   so admin can make an informed decision before clicking Approve/Reject.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Counterparties';
$active_nav = 'counterparties';

require_login();
if (current_user()['role'] === 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
}
$user = current_user();

// ---------------------------------------------------------------
// Admin POST actions
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin') {
    if (!verify_csrf()) {
        redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Security token mismatch.');
    }

    $action = trim($_POST['action']           ?? '');
    $cp_id  = (int)($_POST['counterparty_id'] ?? 0);
    $reason = trim($_POST['rejection_reason'] ?? '');

    if (!$cp_id) {
        redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Invalid request.');
    }

    $cp = db_query("SELECT * FROM Counterparty WHERE CounterpartyID = ?", [$cp_id])->fetch();
    if (!$cp) {
        redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Counterparty not found.');
    }

    $db = get_db();
    $db->beginTransaction();
    try {
        if ($action === 'approve' && $cp['ApprovalStatus'] === 'Pending') {
            db_query(
                "UPDATE Counterparty
                 SET ApprovalStatus = 'Active', DecidedBy = ?, DecidedAt = NOW()
                 WHERE CounterpartyID = ?",
                [$user['staff_id'], $cp_id]
            );
            // Activate all pending contact accounts for this company
            db_query(
                "UPDATE Account a
                 JOIN Contact c ON c.AccountID = a.AccountID
                 SET a.Status = 'Active'
                 WHERE c.CounterpartyID = ? AND a.Status = 'Pending'",
                [$cp_id]
            );

            // Notify the proposing staff member
            $proposer_acc = db_query(
                "SELECT AccountID FROM Staff WHERE StaffID = ?",
                [$cp['ProposedBy']]
            )->fetchColumn();
            if ($proposer_acc) {
                db_query(
                    "INSERT INTO Notification (AccountID, CounterpartyID, Title, Message, NotificationType, IsRead, CreatedAt)
                     VALUES (?, ?, ?, ?, 'Success', 0, NOW())",
                    [
                        $proposer_acc, $cp_id,
                        'Partner company approved',
                        "Your registration request for \"{$cp['CompanyName']}\" ({$cp['CounterpartyCode']}) has been approved. Their contact account is now active.",
                    ]
                );
            }

            $db->commit();
            audit('APPROVE', 'Counterparty', $cp_id, ['company' => $cp['CompanyName']]);
            redirect_with_flash(
                BASE_URL . '/pages/counterparties.php',
                'success',
                "\"{$cp['CompanyName']}\" approved. Their contact account is now active."
            );

        } elseif ($action === 'reject' && $cp['ApprovalStatus'] === 'Pending') {
            if ($reason === '') {
                redirect_with_flash(
                    BASE_URL . '/pages/counterparties.php?review=' . $cp_id,
                    'danger',
                    'A rejection reason is required.'
                );
            }
            db_query(
                "UPDATE Counterparty
                 SET ApprovalStatus = 'Rejected', RejectionReason = ?,
                     DecidedBy = ?, DecidedAt = NOW()
                 WHERE CounterpartyID = ?",
                [$reason, $user['staff_id'], $cp_id]
            );
            // Deactivate linked pending contact accounts
            db_query(
                "UPDATE Account a
                 JOIN Contact c ON c.AccountID = a.AccountID
                 SET a.Status = 'Inactive'
                 WHERE c.CounterpartyID = ? AND a.Status = 'Pending'",
                [$cp_id]
            );

            // Notify proposer
            $proposer_acc = db_query(
                "SELECT AccountID FROM Staff WHERE StaffID = ?",
                [$cp['ProposedBy']]
            )->fetchColumn();
            if ($proposer_acc) {
                db_query(
                    "INSERT INTO Notification (AccountID, CounterpartyID, Title, Message, NotificationType, IsRead, CreatedAt)
                     VALUES (?, ?, ?, ?, 'Warning', 0, NOW())",
                    [
                        $proposer_acc, $cp_id,
                        'Partner company registration rejected',
                        "Your registration request for \"{$cp['CompanyName']}\" has been rejected. Reason: $reason",
                    ]
                );
            }

            $db->commit();
            audit('REJECT', 'Counterparty', $cp_id, ['reason' => $reason]);
            redirect_with_flash(
                BASE_URL . '/pages/counterparties.php',
                'success',
                "\"{$cp['CompanyName']}\" has been rejected."
            );

        } elseif ($action === 'suspend' && $cp['ApprovalStatus'] === 'Active') {
            db_query(
                "UPDATE Counterparty SET ApprovalStatus = 'Suspended' WHERE CounterpartyID = ?",
                [$cp_id]
            );
            // Deactivate linked active contact accounts
            db_query(
                "UPDATE Account a
                 JOIN Contact c ON c.AccountID = a.AccountID
                 SET a.Status = 'Inactive'
                 WHERE c.CounterpartyID = ? AND a.Status = 'Active'",
                [$cp_id]
            );
            $db->commit();
            audit('SUSPEND', 'Counterparty', $cp_id, []);
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'success',
                "\"{$cp['CompanyName']}\" has been suspended and their accounts deactivated.");

        } elseif ($action === 'reactivate' && $cp['ApprovalStatus'] === 'Suspended') {
            db_query(
                "UPDATE Counterparty SET ApprovalStatus = 'Active' WHERE CounterpartyID = ?",
                [$cp_id]
            );
            // Re-activate contact accounts that were deactivated on suspension
            db_query(
                "UPDATE Account a
                 JOIN Contact c ON c.AccountID = a.AccountID
                 SET a.Status = 'Active'
                 WHERE c.CounterpartyID = ? AND a.Status = 'Inactive'",
                [$cp_id]
            );
            $db->commit();
            audit('REACTIVATE', 'Counterparty', $cp_id, []);
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'success',
                "\"{$cp['CompanyName']}\" has been reactivated.");

        } else {
            $db->rollBack();
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Invalid action.');
        }
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('counterparty action error: ' . $e->getMessage());
        redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger',
            'An error occurred. Please try again.');
    }
}

// ---------------------------------------------------------------
// Load detail for approval review panel (admin, ?review=ID)
// ---------------------------------------------------------------
$review_cp      = null;
$review_contact = null;
$review_proposer= null;

if ($user['role'] === 'admin' && isset($_GET['review'])) {
    $rev_id = (int)$_GET['review'];
    if ($rev_id) {
        $review_cp = db_query(
            "SELECT cp.*, s_prop.FullName AS ProposerFullName, s_prop.Position AS ProposerPosition,
                    dp.DepartmentName AS ProposerDept,
                    s_dec.FullName AS DeciderFullName
             FROM Counterparty cp
             LEFT JOIN Staff s_prop ON s_prop.StaffID = cp.ProposedBy
             LEFT JOIN Department dp ON dp.DepartmentID = s_prop.DepartmentID
             LEFT JOIN Staff s_dec  ON s_dec.StaffID  = cp.DecidedBy
             WHERE cp.CounterpartyID = ?",
            [$rev_id]
        )->fetch();

        if ($review_cp) {
            // Load all contacts for this company with their account status
            $review_contacts = db_query(
                "SELECT c.*, a.Email, a.Status AS AccountStatus, a.CreatedAt AS AccountCreated
                 FROM Contact c
                 JOIN Account a ON a.AccountID = c.AccountID
                 WHERE c.CounterpartyID = ?
                 ORDER BY c.IsPrimary DESC, c.ContactID ASC",
                [$rev_id]
            )->fetchAll();

            // Count contracts already linked to this counterparty
            $linked_contracts = (int) db_query(
                "SELECT COUNT(*) FROM Contract WHERE CounterpartyID = ?",
                [$rev_id]
            )->fetchColumn();
        }
    }
}

// ---------------------------------------------------------------
// Pending count for badge / alert
// ---------------------------------------------------------------
$pending_count = (int) db_query(
    "SELECT COUNT(*) FROM Counterparty WHERE ApprovalStatus = 'Pending'"
)->fetchColumn();

// ---------------------------------------------------------------
// Filter & paginated query
// ---------------------------------------------------------------
$q             = trim($_GET['q']      ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_type   = trim($_GET['type']   ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));

$allowed_statuses = ['Pending', 'Active', 'Rejected', 'Suspended'];
$allowed_types    = ['Supplier', 'Customer', 'ServiceProvider'];
if (!in_array($filter_status, $allowed_statuses)) $filter_status = '';
if (!in_array($filter_type,   $allowed_types))    $filter_type   = '';

$where  = ['1=1'];
$params = [];

if ($q !== '') {
    $where[]  = '(cp.CompanyName LIKE ? OR cp.CounterpartyCode LIKE ? OR cp.TaxCode LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($filter_status !== '') { $where[] = 'cp.ApprovalStatus = ?'; $params[] = $filter_status; }
if ($filter_type   !== '') { $where[] = 'cp.CounterpartyType = ?'; $params[] = $filter_type; }

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT cp.*,
           s.FullName  AS ProposerName,
           ds.FullName AS DeciderName,
           (SELECT COUNT(*) FROM Contact ct WHERE ct.CounterpartyID = cp.CounterpartyID) AS ContactCount,
           (SELECT COUNT(*) FROM Contract cc WHERE cc.CounterpartyID = cp.CounterpartyID) AS ContractCount
    FROM Counterparty cp
    LEFT JOIN Staff s  ON s.StaffID  = cp.ProposedBy
    LEFT JOIN Staff ds ON ds.StaffID = cp.DecidedBy
    WHERE $where_sql
    ORDER BY
        CASE cp.ApprovalStatus WHEN 'Pending' THEN 0 ELSE 1 END,
        cp.ProposedAt DESC
";

$result         = paginate($sql, $params, $page, DEFAULT_PAGE_SIZE);
$counterparties = $result['rows'];

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Counterparties</h1>
    <p>Partner companies, suppliers, and customers.</p>
  </div>
  <?php if ($user['role'] === 'staff'): ?>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/create_counterparty.php" class="btn btn-primary">
      <?= icon_svg('plus') ?> Register Partner Company
    </a>
  </div>
  <?php endif; ?>
</div>

<?php if ($pending_count > 0 && $user['role'] === 'admin'): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span>
    <strong><?= $pending_count ?> partner compan<?= $pending_count === 1 ? 'y' : 'ies' ?></strong>
    awaiting your approval.
    <a href="?status=Pending" style="font-weight:600;">Review pending →</a>
  </span>
</div>
<?php endif; ?>

<!-- ============================================================
     APPROVAL REVIEW PANEL (admin + ?review=ID)
     ============================================================ -->
<?php if ($user['role'] === 'admin' && $review_cp): ?>
<div class="card" style="margin-bottom:20px;border:2px solid var(--color-brand-blue,#1761a8);">
  <div class="card-header" style="background:var(--color-accent-soft,#eef2ff);">
    <div style="display:flex;align-items:center;gap:10px;">
      <?= icon_svg('users') ?>
      <span class="card-title">
        Review Registration Request — <?= e($review_cp['CompanyName']) ?>
      </span>
      <?= status_badge($review_cp['ApprovalStatus']) ?>
    </div>
    <a href="<?= BASE_URL ?>/pages/counterparties.php<?= $filter_status ? '?status='.$filter_status : '' ?>"
       class="btn btn-ghost btn-sm"><?= icon_svg('x') ?> Close Review</a>
  </div>

  <div class="card-body" style="padding:24px;">

    <!-- Two-column: company details + contact details -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

      <!-- Company Details -->
      <div>
        <h4 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
                   color:var(--color-text-muted);margin:0 0 12px;">Company Information</h4>
        <div class="detail-grid" style="gap:10px;">
          <div class="detail-item">
            <div class="detail-label">Code</div>
            <div class="detail-value mono"><?= e($review_cp['CounterpartyCode']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Company Name</div>
            <div class="detail-value" style="font-weight:700;"><?= e($review_cp['CompanyName']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Type</div>
            <div class="detail-value"><?= e($review_cp['CounterpartyType']) ?></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Tax Code</div>
            <div class="detail-value mono"><?= e($review_cp['TaxCode']) ?></div>
          </div>
          <?php if ($review_cp['LegalRepresentative']): ?>
          <div class="detail-item">
            <div class="detail-label">Legal Representative</div>
            <div class="detail-value"><?= e($review_cp['LegalRepresentative']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($review_cp['BusinessLicense']): ?>
          <div class="detail-item">
            <div class="detail-label">Business License</div>
            <div class="detail-value" style="font-size:12px;"><?= e($review_cp['BusinessLicense']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($review_cp['Address']): ?>
          <div class="detail-item" style="grid-column:1/-1;">
            <div class="detail-label">Address</div>
            <div class="detail-value"><?= e($review_cp['Address']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($review_cp['Email']): ?>
          <div class="detail-item">
            <div class="detail-label">Company Email</div>
            <div class="detail-value"><?= e($review_cp['Email']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($review_cp['Phone']): ?>
          <div class="detail-item">
            <div class="detail-label">Phone</div>
            <div class="detail-value"><?= e($review_cp['Phone']) ?></div>
          </div>
          <?php endif; ?>
          <div class="detail-item">
            <div class="detail-label">Registered by</div>
            <div class="detail-value">
              <?= e($review_cp['ProposerFullName'] ?? '—') ?>
              <?php if ($review_cp['ProposerPosition']): ?>
              <div class="td-meta"><?= e($review_cp['ProposerPosition']) ?><?= $review_cp['ProposerDept'] ? ' · ' . e($review_cp['ProposerDept']) : '' ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Submitted At</div>
            <div class="detail-value"><?= fmt_datetime($review_cp['ProposedAt']) ?></div>
          </div>
        </div>
      </div>

      <!-- Contact Person(s) -->
      <div>
        <h4 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
                   color:var(--color-text-muted);margin:0 0 12px;">Contact Person(s)</h4>
        <?php if (empty($review_contacts)): ?>
        <p style="font-size:13px;color:var(--color-text-muted);">No contact persons registered.</p>
        <?php else: ?>
        <?php foreach ($review_contacts as $con): ?>
        <div style="background:var(--color-bg-secondary,#f8fafc);border:1px solid var(--color-border);
                    border-radius:8px;padding:14px;margin-bottom:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div style="font-weight:700;font-size:13px;"><?= e($con['FullName']) ?></div>
            <div style="display:flex;gap:6px;align-items:center;">
              <?php if ($con['IsPrimary']): ?>
              <span class="badge badge-review" style="font-size:10px;">Primary</span>
              <?php endif; ?>
              <?php if ($con['AccountStatus'] === 'Pending'): ?>
              <span class="badge badge-pending" style="font-size:10px;">Account Pending</span>
              <?php elseif ($con['AccountStatus'] === 'Active'): ?>
              <span class="badge badge-active" style="font-size:10px;">Account Active</span>
              <?php else: ?>
              <span class="badge badge-cancelled" style="font-size:10px;">Account Inactive</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="detail-grid" style="gap:6px;">
            <?php if ($con['JobTitle']): ?>
            <div class="detail-item">
              <div class="detail-label">Job Title</div>
              <div class="detail-value"><?= e($con['JobTitle']) ?></div>
            </div>
            <?php endif; ?>
            <div class="detail-item">
              <div class="detail-label">Login Email</div>
              <div class="detail-value mono" style="font-size:12px;"><?= e($con['Email']) ?></div>
            </div>
            <?php if ($con['Phone']): ?>
            <div class="detail-item">
              <div class="detail-label">Phone</div>
              <div class="detail-value"><?= e($con['Phone']) ?></div>
            </div>
            <?php endif; ?>
            <div class="detail-item">
              <div class="detail-label">Account Created</div>
              <div class="detail-value"><?= fmt_datetime($con['AccountCreated']) ?></div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($linked_contracts > 0): ?>
        <div class="alert alert-info" style="margin-top:8px;padding:10px 14px;font-size:12px;">
          <?= icon_svg('info') ?>
          <span>This company is already linked to <strong><?= $linked_contracts ?></strong> contract<?= $linked_contracts > 1 ? 's' : '' ?> in the system.</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Previous rejection reason if any -->
    <?php if ($review_cp['ApprovalStatus'] === 'Rejected' && $review_cp['RejectionReason']): ?>
    <div class="alert" style="background:#fff1f0;border-color:var(--color-danger);margin-bottom:16px;padding:12px 16px;">
      <?= icon_svg('alert-circle') ?>
      <span><strong>Previously rejected:</strong> <?= e($review_cp['RejectionReason']) ?></span>
    </div>
    <?php endif; ?>

    <!-- Decision area (only for Pending) -->
    <?php if ($review_cp['ApprovalStatus'] === 'Pending'): ?>
    <div style="border-top:1px solid var(--color-border);padding-top:20px;
                display:grid;grid-template-columns:1fr 1fr;gap:16px;">

      <!-- Approve form -->
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px;">
        <div style="font-weight:700;font-size:13px;color:#15803d;margin-bottom:8px;">
          <?= icon_svg('check-circle') ?> Approve Registration
        </div>
        <p style="font-size:12px;color:#166534;margin:0 0 12px;">
          Approving will set the company status to <strong>Active</strong> and activate all pending contact accounts, granting them portal access.
        </p>
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="counterparty_id" value="<?= $review_cp['CounterpartyID'] ?>">
          <button type="submit" class="btn btn-primary"
                  style="background:#16a34a;border-color:#16a34a;width:100%;justify-content:center;"
                  data-confirm="Approve &quot;<?= e($review_cp['CompanyName']) ?>&quot; and activate their contact account(s)?">
            <?= icon_svg('check-circle') ?> Approve Partner Company
          </button>
        </form>
      </div>

      <!-- Reject form -->
      <div style="background:#fff1f0;border:1px solid #fca5a5;border-radius:8px;padding:16px;">
        <div style="font-weight:700;font-size:13px;color:#dc2626;margin-bottom:8px;">
          <?= icon_svg('x') ?> Reject Registration
        </div>
        <p style="font-size:12px;color:#991b1b;margin:0 0 10px;">
          Rejecting will mark the company as <strong>Rejected</strong> and deactivate their contact accounts. The proposing staff member will be notified.
        </p>
        <form method="POST" id="rejectPanelForm">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="counterparty_id" value="<?= $review_cp['CounterpartyID'] ?>">
          <div class="form-group" style="margin-bottom:10px;">
            <label for="panel_rejection_reason" style="font-size:12px;font-weight:600;color:#7f1d1d;">
              Rejection Reason <span class="req">*</span>
            </label>
            <textarea id="panel_rejection_reason" name="rejection_reason"
                      class="form-control" rows="2"
                      style="font-size:12px;"
                      placeholder="Explain why this registration is being rejected…"
                      required></textarea>
          </div>
          <button type="submit" class="btn btn-primary"
                  style="background:var(--color-danger);border-color:var(--color-danger);width:100%;justify-content:center;">
            <?= icon_svg('x') ?> Reject Registration
          </button>
        </form>
      </div>
    </div>

    <?php elseif ($review_cp['ApprovalStatus'] === 'Active'): ?>
    <div style="border-top:1px solid var(--color-border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end;">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="suspend">
        <input type="hidden" name="counterparty_id" value="<?= $review_cp['CounterpartyID'] ?>">
        <button type="submit" class="btn btn-secondary"
                data-confirm="Suspend this company? Their contact accounts will also be deactivated.">
          Suspend Company
        </button>
      </form>
    </div>

    <?php elseif ($review_cp['ApprovalStatus'] === 'Suspended'): ?>
    <div style="border-top:1px solid var(--color-border);padding-top:16px;display:flex;gap:10px;justify-content:flex-end;">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reactivate">
        <input type="hidden" name="counterparty_id" value="<?= $review_cp['CounterpartyID'] ?>">
        <button type="submit" class="btn btn-primary"
                data-confirm="Reactivate this company and re-enable their contact accounts?">
          <?= icon_svg('check-circle') ?> Reactivate Company
        </button>
      </form>
    </div>
    <?php endif; ?>

  </div><!-- /card-body -->
</div>
<?php endif; /* review panel */ ?>

<!-- ============================================================
     MAIN COUNTERPARTIES TABLE
     ============================================================ -->
<div class="card">
  <form id="filterForm" method="GET">
    <div class="table-toolbar">
      <div class="search-field">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search by name, code, tax ID…" autocomplete="off">
      </div>
      <select name="status" class="filter-select" data-autosubmit>
        <option value="">All Statuses</option>
        <?php foreach ($allowed_statuses as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>>
          <?= $s ?><?= ($s === 'Pending' && $pending_count > 0) ? " ($pending_count)" : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <select name="type" class="filter-select" data-autosubmit>
        <option value="">All Types</option>
        <?php foreach ($allowed_types as $t): ?>
        <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($q || $filter_status || $filter_type): ?>
      <a href="<?= BASE_URL ?>/pages/counterparties.php" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
      <div class="toolbar-spacer"></div>
      <span style="font-size:12px;color:var(--color-text-muted);"><?= $result['total'] ?> record<?= $result['total'] !== 1 ? 's' : '' ?></span>
    </div>
  </form>

  <?php if (empty($counterparties)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><?= icon_svg('users') ?></div>
    <h3><?= $q || $filter_status || $filter_type ? 'No matching counterparties' : 'No counterparties yet' ?></h3>
    <p>Counterparties are registered by staff and approved by Admin before contracts can reference them.</p>
    <?php if ($user['role'] === 'staff'): ?>
    <a href="<?= BASE_URL ?>/pages/create_counterparty.php" class="btn btn-primary btn-sm">
      <?= icon_svg('plus') ?> Register Partner Company
    </a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Company Name</th>
          <th>Type</th>
          <th>Tax Code</th>
          <th>Legal Rep.</th>
          <th>Contacts</th>
          <th>Proposed By</th>
          <th>Date</th>
          <th>Status</th>
          <?php if ($user['role'] === 'admin'): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($counterparties as $cp): ?>
        <?php $is_reviewing = (isset($_GET['review']) && (int)$_GET['review'] === (int)$cp['CounterpartyID']); ?>
        <tr <?= $is_reviewing ? 'style="background:var(--color-accent-soft,#eef2ff);"' : '' ?>>
          <td class="td-ref"><?= e($cp['CounterpartyCode']) ?></td>
          <td>
            <span style="font-weight:600;"><?= e($cp['CompanyName']) ?></span>
            <?php if ($cp['Address']): ?>
            <div class="td-meta"><?= e(mb_strimwidth($cp['Address'], 0, 50, '…')) ?></div>
            <?php endif; ?>
            <?php if ($cp['ApprovalStatus'] === 'Rejected' && $cp['RejectionReason']): ?>
            <div class="td-meta" style="color:var(--color-danger);margin-top:2px;">
              <?= icon_svg('x') ?> <?= e(mb_strimwidth($cp['RejectionReason'], 0, 55, '…')) ?>
            </div>
            <?php endif; ?>
          </td>
          <td><?= e($cp['CounterpartyType']) ?></td>
          <td class="td-meta text-mono"><?= e($cp['TaxCode']) ?></td>
          <td class="td-meta"><?= e($cp['LegalRepresentative'] ?? '—') ?></td>
          <td class="text-center">
            <span style="font-size:12px;font-weight:600;"><?= (int)$cp['ContactCount'] ?></span>
            <?php if ($cp['ContractCount'] > 0): ?>
            <div class="td-meta"><?= (int)$cp['ContractCount'] ?> contract<?= $cp['ContractCount']>1?'s':'' ?></div>
            <?php endif; ?>
          </td>
          <td class="td-meta"><?= e($cp['ProposerName'] ?? '—') ?></td>
          <td class="td-meta"><?= fmt_date($cp['ProposedAt']) ?></td>
          <td><?= status_badge($cp['ApprovalStatus']) ?></td>

          <?php if ($user['role'] === 'admin'): ?>
          <td>
            <?php if ($cp['ApprovalStatus'] === 'Pending'): ?>
            <!-- Review button opens detail panel -->
            <a href="<?= BASE_URL ?>/pages/counterparties.php?review=<?= $cp['CounterpartyID'] ?><?= $filter_status ? '&status='.$filter_status : '' ?>#review-panel"
               class="btn btn-primary btn-sm">
              <?= icon_svg('eye') ?> Review
            </a>

            <?php elseif ($cp['ApprovalStatus'] === 'Active'): ?>
            <a href="<?= BASE_URL ?>/pages/counterparties.php?review=<?= $cp['CounterpartyID'] ?>"
               class="btn btn-ghost btn-sm"><?= icon_svg('eye') ?> View</a>
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="suspend">
              <input type="hidden" name="counterparty_id" value="<?= $cp['CounterpartyID'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      data-confirm="Suspend &quot;<?= e($cp['CompanyName']) ?>&quot;?">
                Suspend
              </button>
            </form>

            <?php elseif ($cp['ApprovalStatus'] === 'Suspended'): ?>
            <form method="POST" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reactivate">
              <input type="hidden" name="counterparty_id" value="<?= $cp['CounterpartyID'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"
                      data-confirm="Reactivate &quot;<?= e($cp['CompanyName']) ?>&quot;?">
                Reactivate
              </button>
            </form>

            <?php elseif ($cp['ApprovalStatus'] === 'Rejected'): ?>
            <span style="font-size:11px;color:var(--color-text-muted);">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">
      Showing <?= ($page - 1) * DEFAULT_PAGE_SIZE + 1 ?>–<?= min($page * DEFAULT_PAGE_SIZE, $result['total']) ?>
      of <?= $result['total'] ?>
    </span>
    <div class="page-btns">
      <?php $base = BASE_URL . '/pages/counterparties.php?' . http_build_query(array_filter(['q' => $q, 'status' => $filter_status, 'type' => $filter_type])); ?>
      <a href="<?= $base ?>&page=<?= $page - 1 ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">‹</a>
      <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
      <a href="<?= $base ?>&page=<?= $p ?>" class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="<?= $base ?>&page=<?= $page + 1 ?>" class="page-btn <?= $page >= $result['pages'] ? 'disabled' : '' ?>">›</a>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
// Scroll to review panel when opened
<?php if ($review_cp): ?>
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('[style*="border:2px solid"]');
  if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
