<?php
/**
 * Create Contract View (MVC)
 * Variables from RequestController::create():
 *   $user, $errors, $old, $contract_types, $departments, $counterparties, $internal_staff
 *   $page_title, $active_nav, $breadcrumbs
 */
include __DIR__ . '/../../../includes/layout_head.php';
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

<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>
    <strong>Workflow:</strong>
    Save as Draft to finish later. Click <em>Submit for Review</em> to send directly to the legal team —
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
      <div class="form-section-title"><?= icon_svg('file-text') ?> Contract Information</div>
      <div class="form-grid">

        <div class="form-group span-2">
          <label for="ContractTitle">Contract Title <span class="req">*</span></label>
          <input type="text" id="ContractTitle" name="ContractTitle"
                 class="form-control <?= isset($errors['ContractTitle']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['ContractTitle'] ?? '') ?>"
                 placeholder="e.g., Raw Material Supply Agreement — Q3 2026" maxlength="200">
          <?php if (isset($errors['ContractTitle'])): ?><span class="form-error visible"><?= e($errors['ContractTitle']) ?></span><?php endif; ?>
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
          <?php if (isset($errors['DepartmentID'])): ?><span class="form-error visible"><?= e($errors['DepartmentID']) ?></span><?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="OwnerStaffID">Contract Owner (Staff) <span class="req">*</span></label>
          <select id="OwnerStaffID" name="OwnerStaffID"
                  class="form-control <?= isset($errors['OwnerStaffID']) ? 'is-invalid' : '' ?>">
            <option value="">Select owner…</option>
            <?php foreach ($internal_staff as $s): ?>
            <option value="<?= e($s['StaffID']) ?>"
                    <?= ($old['OwnerStaffID'] ?? '') == $s['StaffID'] ? 'selected' : '' ?>>
              <?= e($s['FullName']) ?> — <?= e($s['Position'] ?? $s['DepartmentName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="form-hint">The Vinamilk staff member accountable for this contract.</span>
          <?php if (isset($errors['OwnerStaffID'])): ?><span class="form-error visible"><?= e($errors['OwnerStaffID']) ?></span><?php endif; ?>
        </div>

      </div>
    </div>

    <!-- Section 2: Counterparty -->
    <div class="form-section">
      <div class="form-section-title"><?= icon_svg('users') ?> Counterparty</div>
      <div class="form-grid">
        <div class="form-group span-2">
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
            <a href="<?= BASE_URL ?>/pages/create_counterparty.php">Register a new partner company →</a>
          </span>
          <?php if (isset($errors['CounterpartyID'])): ?><span class="form-error visible"><?= e($errors['CounterpartyID']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Section 3: Contract Terms -->
    <div class="form-section">
      <div class="form-section-title"><?= icon_svg('calendar') ?> Contract Terms</div>
      <div class="form-grid">

        <div class="form-group">
          <label for="StartDate">Start Date <span class="req">*</span></label>
          <input type="date" id="StartDate" name="StartDate"
                 class="form-control <?= isset($errors['StartDate']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['StartDate'] ?? '') ?>">
          <span class="form-hint">Required when submitting; optional for Draft.</span>
          <?php if (isset($errors['StartDate'])): ?><span class="form-error visible"><?= e($errors['StartDate']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="EndDate">End Date <span class="req">*</span></label>
          <input type="date" id="EndDate" name="EndDate"
                 class="form-control <?= isset($errors['EndDate']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['EndDate'] ?? '') ?>">
          <span class="form-hint">Must be on or after start date.</span>
          <?php if (isset($errors['EndDate'])): ?><span class="form-error visible"><?= e($errors['EndDate']) ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="RenewalReviewDate">Renewal Review Date <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></label>
          <input type="date" id="RenewalReviewDate" name="RenewalReviewDate"
                 class="form-control" value="<?= e($old['RenewalReviewDate'] ?? '') ?>">
          <span class="form-hint">Date to begin reviewing for renewal.</span>
        </div>

        <div class="form-group">
          <label for="ContractValue">Contract Value <span style="font-weight:400;color:var(--color-text-muted);">(optional)</span></label>
          <div class="input-group">
            <span class="input-prefix">
              <select name="Currency" style="border:none;background:transparent;font-size:12px;font-weight:600;color:var(--color-text-secondary);cursor:pointer;padding:0;font-family:var(--font-sans);">
                <option value="VND" <?= ($old['Currency'] ?? 'VND') === 'VND' ? 'selected' : '' ?>>VND</option>
                <option value="USD" <?= ($old['Currency'] ?? '') === 'USD'    ? 'selected' : '' ?>>USD</option>
              </select>
            </span>
            <input type="number" id="ContractValue" name="ContractValue"
                   class="form-control <?= isset($errors['ContractValue']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['ContractValue'] ?? '') ?>" placeholder="0" min="0" step="1">
          </div>
          <?php if (isset($errors['ContractValue'])): ?><span class="form-error visible"><?= e($errors['ContractValue']) ?></span><?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="Summary">Contract Summary / Business Purpose <span class="req">*</span></label>
          <textarea id="Summary" name="Summary"
                    class="form-control <?= isset($errors['Summary']) ? 'is-invalid' : '' ?>"
                    rows="4" placeholder="Describe the scope, key obligations, and business rationale…"><?= e($old['Summary'] ?? '') ?></textarea>
          <span class="form-hint">Required when submitting; optional for Draft.</span>
          <?php if (isset($errors['Summary'])): ?><span class="form-error visible"><?= e($errors['Summary']) ?></span><?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500;">
            <input type="checkbox" name="AutoRenewalAlert" value="1"
                   <?= ($old['AutoRenewalAlert'] ?? 1) ? 'checked' : '' ?>
                   style="width:16px;height:16px;accent-color:var(--color-brand-blue);">
            Enable automatic renewal alert notifications
          </label>
          <span class="form-hint">The system will notify the contract owner before the renewal review date.</span>
        </div>

      </div>
    </div>

  </div>

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
        <?= icon_svg('check-circle') ?> Submit for Review
      </button>
    </div>
  </div>

</form>
</div>

<script>
// Wire draft / submit buttons to hidden field + form submit
document.getElementById('saveDraftBtn').addEventListener('click', function() {
  document.getElementById('action_type').value = 'draft';
  document.getElementById('contractRequestForm').submit();
});
document.getElementById('submitBtn').addEventListener('click', function() {
  document.getElementById('action_type').value = 'submit';
  document.getElementById('contractRequestForm').submit();
});
</script>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
