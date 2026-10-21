<?php
/**
 * Create New Contract
 *
 * Flow:
 *  - "Save as Draft"  → Contract.Status = 'Draft', no review record created
 *  - "Create Contract" → Contract.Status = 'PendingReview', creates a Review
 *    record assigned to the first active Legal-department staff member, and a
 *    ContractRequest of type 'InternalApproval' linked to the contract.
 *
 * Bug fixes applied:
 *  1. Both submit paths previously hard-coded 'Draft'; now 'submit' sets
 *     Status='PendingReview' and triggers the review workflow.
 *  2. StartDate / EndDate are allowed as NULL for Drafts (migration alters
 *     those columns to nullable); required only when submitting.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title  = 'Create New Contract';
$active_nav  = 'contracts';
$breadcrumbs = [
    ['label' => 'Contracts', 'url' => BASE_URL . '/pages/contracts.php'],
    ['label' => 'Create New Contract'],
];

require_login();
$user = current_user();
if ($user['role'] === 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'You do not have permission to create contracts.');
}
if ($user['role'] === 'admin') {
    redirect_with_flash(
        BASE_URL . '/pages/approvals.php',
        'danger',
        'Admins review and approve contracts — contract creation is a Staff function. Use Approvals & Reviews to act on submitted contracts.'
    );
}

// Pre-load lookup data for dropdowns
$contract_types  = get_contract_types();
$departments     = get_departments();
$counterparties  = get_counterparties();   // only Active counterparties
$internal_staff  = get_internal_staff();

// ---------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------
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

        // ---- Backend validation (always required) ----
        if ($old['ContractTitle'] === '')  $errors['ContractTitle']  = 'Contract title is required.';
        if (!$old['ContractTypeID'])       $errors['ContractTypeID'] = 'Please select a contract type.';
        if (!$old['DepartmentID'])         $errors['DepartmentID']   = 'Please select a department.';
        if (!$old['CounterpartyID'])       $errors['CounterpartyID'] = 'Please select a counterparty.';
        if ($old['OwnerStaffID'] === '')   $errors['OwnerStaffID']   = 'Please select a contract owner.';

        // ---- Extra validation for full submit ----
        if (!$is_draft) {
            if ($old['StartDate'] === '')  $errors['StartDate'] = 'Start date is required.';
            if ($old['EndDate']   === '')  $errors['EndDate']   = 'End date is required.';
            if ($old['Summary']   === '')  $errors['Summary']   = 'Summary / business purpose is required.';

            if (!isset($errors['StartDate'], $errors['EndDate'])
                && $old['StartDate'] && $old['EndDate']) {
                if (strtotime($old['EndDate']) < strtotime($old['StartDate'])) {
                    $errors['EndDate'] = 'End date must be on or after start date.';
                }
            }
        }

        // ---- DB-level validations ----
        if ($old['ContractTypeID'] && !isset($errors['ContractTypeID'])) {
            $ok = db_query(
                "SELECT 1 FROM ContractType WHERE ContractTypeID = ? AND IsActive = 1",
                [$old['ContractTypeID']]
            )->fetchColumn();
            if (!$ok) $errors['ContractTypeID'] = 'Invalid contract type selected.';
        }
        if ($old['DepartmentID'] && !isset($errors['DepartmentID'])) {
            $ok = db_query(
                "SELECT 1 FROM Department WHERE DepartmentID = ?",
                [$old['DepartmentID']]
            )->fetchColumn();
            if (!$ok) $errors['DepartmentID'] = 'Invalid department selected.';
        }
        if ($old['CounterpartyID'] && !isset($errors['CounterpartyID'])) {
            $ok = db_query(
                "SELECT 1 FROM Counterparty WHERE CounterpartyID = ? AND ApprovalStatus = 'Active'",
                [$old['CounterpartyID']]
            )->fetchColumn();
            if (!$ok) $errors['CounterpartyID'] = 'Selected counterparty is not active or does not exist.';
        }
        if ($old['OwnerStaffID'] !== '' && !isset($errors['OwnerStaffID'])) {
            $ok = db_query(
                "SELECT 1 FROM Staff s JOIN Account a ON a.AccountID = s.AccountID
                 WHERE s.StaffID = ? AND a.Status = 'Active'",
                [$old['OwnerStaffID']]
            )->fetchColumn();
            if (!$ok) $errors['OwnerStaffID'] = 'Selected owner is not a valid active staff member.';
        }

        // Contract value
        $contract_value = null;
        if ($old['ContractValue'] !== '') {
            if (!is_numeric($old['ContractValue']) || (float)$old['ContractValue'] < 0) {
                $errors['ContractValue'] = 'Contract value must be a non-negative number.';
            } else {
                $contract_value = (float)$old['ContractValue'];
            }
        }

        // Currency
        if (!in_array($old['Currency'], ['VND', 'USD'])) {
            $errors['Currency'] = 'Currency must be VND or USD.';
        }

        // ---- Persist ----
        if (empty($errors)) {
            $status = $is_draft ? 'Draft' : 'PendingReview';

            // If saving as draft and no dates provided, use placeholder dates
            // so the contract can be saved even if the migration hasn't made
            // StartDate/EndDate nullable yet
            $start_date = $old['StartDate'] ?: null;
            $end_date   = $old['EndDate']   ?: null;
            if ($is_draft && !$start_date) $start_date = date('Y-m-d');
            if ($is_draft && !$end_date)   $end_date   = date('Y-m-d', strtotime('+1 year'));

            // Retry loop handles the rare race-condition where two concurrent
            // requests both generate the same contract code.  At most 3 attempts.
            $max_attempts = 3;
            $attempt      = 0;
            $saved         = false;

            while (!$saved && $attempt < $max_attempts) {
                $attempt++;
                $contract_code = generate_contract_code();

                $db = get_db();
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare("
                        INSERT INTO Contract
                            (ContractCode, ContractTitle, ContractTypeID, DepartmentID, CounterpartyID,
                             OwnerStaffID, Summary, ContractValue, Currency, StartDate, EndDate,
                             RenewalReviewDate, AutoRenewalAlert, Status)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $stmt->execute([
                        $contract_code,
                        $old['ContractTitle'],
                        $old['ContractTypeID'],
                        $old['DepartmentID'],
                        $old['CounterpartyID'],
                        $old['OwnerStaffID'],
                        $old['Summary'] ?: null,
                        $contract_value,
                        $old['Currency'],
                        $start_date,
                        $end_date,
                        $old['RenewalReviewDate'] ?: null,
                        $old['AutoRenewalAlert'],
                        $status,
                    ]);
                    $contract_id = (int)$db->lastInsertId();

                    // StatusHistory — initial creation
                    record_status_change($contract_id, $user['account_id'], null, $status,
                        $is_draft ? 'Contract created as Draft' : 'Contract submitted for legal review');

                    // ---- Submit workflow: create Review + ContractRequest ----
                    if (!$is_draft) {
                        // Find an active legal reviewer (Legal dept staff, prefer Admin/Staff role)
                        $legal_reviewer = db_query(
                            "SELECT a.AccountID
                             FROM Staff s
                             JOIN Account a ON a.AccountID = s.AccountID
                             JOIN Role r ON r.RoleID = a.RoleID
                             JOIN Department d ON d.DepartmentID = s.DepartmentID
                             WHERE d.DepartmentName = 'Legal'
                               AND a.Status = 'Active'
                               AND r.RoleName IN ('Manager/Admin', 'Staff')
                               AND s.StaffID != ?
                             ORDER BY r.RoleID DESC, s.StaffID ASC
                             LIMIT 1",
                            [$old['OwnerStaffID']]
                        )->fetchColumn();

                        // Fallback: any active admin if no Legal dept found
                        if (!$legal_reviewer) {
                            $legal_reviewer = db_query(
                                "SELECT a.AccountID FROM Account a
                                 JOIN Role r ON r.RoleID = a.RoleID
                                 WHERE r.RoleName = 'Manager/Admin' AND a.Status = 'Active'
                                 LIMIT 1"
                            )->fetchColumn();
                        }

                        if ($legal_reviewer) {
                            db_query(
                                "INSERT INTO Review (ContractID, RequestID, ReviewerAccountID, ReviewStatus, SubmittedAt)
                                 VALUES (?, NULL, ?, 'Pending', NOW())",
                                [$contract_id, $legal_reviewer]
                            );
                        }

                        db_query(
                            "INSERT INTO ContractRequest
                                 (ContractID, RequestedBy, RequestType, AssignedSide, Status, Description, RequestedAt)
                             VALUES (?, ?, 'InternalApproval', 'Internal', 'InProgress', ?, NOW())",
                            [
                                $contract_id,
                                $user['account_id'],
                                'Contract submitted for review and approval: ' . $old['ContractTitle'],
                            ]
                        );

                        if ($legal_reviewer) {
                            db_query(
                                "INSERT INTO Notification
                                     (AccountID, ContractID, Title, Message, NotificationType, IsRead, CreatedAt)
                                 VALUES (?, ?, ?, ?, 'Info', 0, NOW())",
                                [
                                    $legal_reviewer,
                                    $contract_id,
                                    'New contract for legal review',
                                    "Contract $contract_code has been submitted for legal review.",
                                ]
                            );
                        }
                    }

                    $db->commit();
                    $saved = true;

                    audit('CREATE', 'Contract', $contract_id, [
                        'contractCode' => $contract_code,
                        'status'       => $status,
                        'attempt'      => $attempt,
                    ]);

                    $flash_msg = $is_draft
                        ? "Draft saved. Code: $contract_code"
                        : "Contract submitted for review. Code: $contract_code";

                    redirect_with_flash(
                        BASE_URL . '/pages/contract_detail.php?id=' . $contract_id,
                        'success',
                        $flash_msg
                    );

                } catch (Throwable $e) {
                    $db->rollBack();
                    // 1062 = duplicate entry — retry with a new code
                    if ($e->getCode() === '23000' && $attempt < $max_attempts) {
                        error_log("Contract code collision on '$contract_code' (attempt $attempt), retrying…");
                        continue;
                    }
                    error_log('create_contract error: ' . $e->getMessage());
                    $errors[] = 'An unexpected error occurred: ' . $e->getMessage();
                    break;
                }
            }

            if (!$saved && empty($errors)) {
                $errors[] = 'Could not generate a unique contract code after ' . $max_attempts . ' attempts. Please try again.';
            }
        }
    }
} else {
    // Pre-fill owner with current user's staff ID
    $old['OwnerStaffID']     = $user['staff_id'];
    $old['Currency']         = 'VND';
    $old['AutoRenewalAlert'] = 1;
    $old['DepartmentID']     = '';
    // Pre-select dept if we can match the user's department name
    if ($user['dept']) {
        $dept_row = db_query(
            "SELECT DepartmentID FROM Department WHERE DepartmentName = ? LIMIT 1",
            [$user['dept']]
        )->fetch();
        if ($dept_row) $old['DepartmentID'] = $dept_row['DepartmentID'];
    }
}

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Create New Contract</h1>
    <p>Fill in the details. Save as Draft to continue later, or submit directly to begin the legal review workflow.</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-secondary">
      <?= icon_svg('arrow-left') ?> Cancel
    </a>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <?= icon_svg('alert-circle') ?>
  <span>
    <?php if (isset($errors[0])): ?>
      <?= e($errors[0]) ?>
    <?php else: ?>
      Please correct the highlighted errors below.
    <?php endif; ?>
  </span>
</div>
<?php endif; ?>

<!-- Workflow notice -->
<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>
    <strong>Workflow:</strong>
    Save as Draft to finish later. Click <em>Submit for Review</em> to send directly to the legal team &mdash;
    they will review it, then it goes to the Admin for final approval.
  </span>
</div>

<div class="card">
<form id="contractRequestForm" method="POST"
      action="<?= BASE_URL ?>/pages/create_request.php" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="action_type" id="action_type" value="submit">

  <div class="card-body" style="padding:28px;">

    <!-- Section 1: Contract Information -->
    <div class="form-section">
      <div class="form-section-title">
        <?= icon_svg('file-text') ?>
        Contract Information
      </div>
      <div class="form-grid">
        <div class="form-group span-2">
          <label for="ContractTitle">Contract Title <span class="req">*</span></label>
          <input type="text" id="ContractTitle" name="ContractTitle"
                 class="form-control <?= isset($errors['ContractTitle']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['ContractTitle'] ?? '') ?>"
                 placeholder="e.g., Raw Material Supply Agreement — Q3 2026"
                 maxlength="200">
          <?php if (isset($errors['ContractTitle'])): ?>
            <span class="form-error visible"><?= e($errors['ContractTitle']) ?></span>
          <?php endif; ?>
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
          <?php if (isset($errors['ContractTypeID'])): ?>
            <span class="form-error visible"><?= e($errors['ContractTypeID']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="DepartmentID">Responsible Department <span class="req">*</span></label>
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
          <?php if (isset($errors['DepartmentID'])): ?>
            <span class="form-error visible"><?= e($errors['DepartmentID']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="OwnerStaffID">Contract Owner (Staff) <span class="req">*</span></label>
          <select id="OwnerStaffID" name="OwnerStaffID"
                  class="form-control <?= isset($errors['OwnerStaffID']) ? 'is-invalid' : '' ?>">
            <option value="">Select owner…</option>
            <?php foreach ($internal_staff as $s): ?>
            <option value="<?= e($s['StaffID']) ?>"
                    <?= ($old['OwnerStaffID'] ?? '') === $s['StaffID'] ? 'selected' : '' ?>>
              <?= e($s['FullName']) ?> — <?= e($s['Position'] ?? $s['DepartmentName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">The Vinamilk staff member accountable for this contract.</span>
          <?php if (isset($errors['OwnerStaffID'])): ?>
            <span class="form-error visible"><?= e($errors['OwnerStaffID']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Section 2: Counterparty -->
    <div class="form-section">
      <div class="form-section-title">
        <?= icon_svg('users') ?>
        Counterparty
      </div>
      <div class="form-grid cols-1">
        <div class="form-group">
          <label for="CounterpartyID">Counterparty / Partner Company <span class="req">*</span></label>
          <select id="CounterpartyID" name="CounterpartyID"
                  class="form-control <?= isset($errors['CounterpartyID']) ? 'is-invalid' : '' ?>">
            <option value="">Select counterparty…</option>
            <?php foreach ($counterparties as $cp): ?>
            <option value="<?= $cp['CounterpartyID'] ?>"
                    <?= (int)($old['CounterpartyID'] ?? 0) === (int)$cp['CounterpartyID'] ? 'selected' : '' ?>>
              [<?= e($cp['CounterpartyCode']) ?>] <?= e($cp['CompanyName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">Only approved (Active) counterparties are listed.
            <?php if ($user['role'] !== 'external'): ?>
            <a href="<?= BASE_URL ?>/pages/create_counterparty.php">Register a new partner company →</a>
            <?php endif; ?>
          </span>
          <?php if (isset($errors['CounterpartyID'])): ?>
            <span class="form-error visible"><?= e($errors['CounterpartyID']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Section 3: Contract Terms -->
    <div class="form-section">
      <div class="form-section-title">
        <?= icon_svg('calendar') ?>
        Contract Terms
      </div>
      <div class="form-grid">
        <div class="form-group">
          <label for="StartDate">Start Date <span class="req" id="start_req">*</span></label>
          <input type="date" id="StartDate" name="StartDate"
                 class="form-control <?= isset($errors['StartDate']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['StartDate'] ?? '') ?>">
          <span class="form-hint">Required when submitting; optional for Draft.</span>
          <?php if (isset($errors['StartDate'])): ?>
            <span class="form-error visible"><?= e($errors['StartDate']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="EndDate">End Date <span class="req" id="end_req">*</span></label>
          <input type="date" id="EndDate" name="EndDate"
                 class="form-control <?= isset($errors['EndDate']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['EndDate'] ?? '') ?>">
          <span class="form-hint">Must be on or after start date.</span>
          <?php if (isset($errors['EndDate'])): ?>
            <span class="form-error visible"><?= e($errors['EndDate']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="RenewalReviewDate">Renewal Review Date <span style="color:var(--color-text-muted); font-weight:400;">(optional)</span></label>
          <input type="date" id="RenewalReviewDate" name="RenewalReviewDate"
                 class="form-control"
                 value="<?= e($old['RenewalReviewDate'] ?? '') ?>">
          <span class="form-hint">Date to begin reviewing for renewal.</span>
        </div>

        <div class="form-group">
          <label for="ContractValue">Contract Value <span style="color:var(--color-text-muted); font-weight:400;">(optional)</span></label>
          <div class="input-group">
            <span class="input-prefix">
              <select name="Currency" id="Currency"
                      style="border:none;background:transparent;font-size:12px;font-weight:600;
                             color:var(--color-text-secondary);cursor:pointer;padding:0;
                             font-family:var(--font-sans);">
                <option value="VND" <?= ($old['Currency'] ?? 'VND') === 'VND' ? 'selected' : '' ?>>VND</option>
                <option value="USD" <?= ($old['Currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
              </select>
            </span>
            <input type="number" id="ContractValue" name="ContractValue"
                   class="form-control <?= isset($errors['ContractValue']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['ContractValue'] ?? '') ?>"
                   placeholder="0" min="0" step="1">
          </div>
          <?php if (isset($errors['ContractValue'])): ?>
            <span class="form-error visible"><?= e($errors['ContractValue']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="Summary">Contract Summary / Business Purpose <span class="req" id="summary_req">*</span></label>
          <textarea id="Summary" name="Summary"
                    class="form-control <?= isset($errors['Summary']) ? 'is-invalid' : '' ?>"
                    rows="4"
                    placeholder="Describe the scope, key obligations, and business rationale…"><?= e($old['Summary'] ?? '') ?></textarea>
          <span class="form-hint">Required when submitting; optional for Draft.</span>
          <?php if (isset($errors['Summary'])): ?>
            <span class="form-error visible"><?= e($errors['Summary']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500;">
            <input type="checkbox" name="AutoRenewalAlert" id="AutoRenewalAlert" value="1"
                   <?= ($old['AutoRenewalAlert'] ?? 1) ? 'checked' : '' ?>
                   style="width:16px;height:16px;accent-color:var(--color-brand-blue);">
            Enable automatic renewal alert notifications
          </label>
          <span class="form-hint">The system will notify the contract owner before the renewal review date.</span>
        </div>
      </div>
    </div>

  </div><!-- /card-body -->

  <!-- Footer -->
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <span style="font-size:12px;color:var(--color-text-muted);">
      <span style="color:var(--color-danger);">*</span> Required fields (some only required when submitting)
    </span>
    <div style="display:flex;gap:10px;align-items:center;">
      <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-ghost">Cancel</a>
      <button type="button" id="saveDraftBtn" class="btn btn-secondary">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
          <polyline points="17 21 17 13 7 13 7 21"/>
          <polyline points="7 3 7 8 15 8"/>
        </svg>
        Save as Draft
      </button>
      <button type="button" id="submitBtn" class="btn btn-primary">
        <?= icon_svg('check-circle') ?>
        Submit for Review
      </button>
    </div>
  </div>

</form>
</div>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
