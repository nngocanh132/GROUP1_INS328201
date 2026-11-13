<?php
/**
 * Create Counterparty View (MVC)
 * Variables from CounterpartyController::create():
 *   $user, $errors, $old, $page_title, $active_nav, $breadcrumbs
 */
include __DIR__ . '/../../../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Register Partner Company</h1>
    <p>Add a new counterparty and their contact person. The registration will be sent to the Admin for review before becoming active.</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/counterparties.php" class="btn btn-secondary">
      <?= icon_svg('arrow-left') ?> Cancel
    </a>
  </div>
</div>

<div class="alert alert-info" style="margin-bottom:16px;">
  <?= icon_svg('info') ?>
  <span>
    <strong>Approval required:</strong>
    After you submit this form, the Admin must approve the company before it can be used in contracts.
    The contact person's login account will also remain inactive until the Admin activates it.
  </span>
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

<form id="createCounterpartyForm" method="POST"
      action="<?= BASE_URL ?>/pages/create_counterparty.php" novalidate>
  <?= csrf_field() ?>

  <!-- Company Information -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header"><span class="card-title"><?= icon_svg('users') ?> Company Information</span></div>
    <div class="card-body" style="padding:28px;">
      <div class="form-grid">

        <div class="form-group span-2">
          <label for="CompanyName">Company Name <span class="req">*</span></label>
          <input type="text" id="CompanyName" name="CompanyName"
                 class="form-control <?= isset($errors['CompanyName']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['CompanyName'] ?? '') ?>"
                 placeholder="e.g., ABC Logistics Co., Ltd." maxlength="200">
          <?php if (isset($errors['CompanyName'])): ?>
          <span class="form-error visible"><?= e($errors['CompanyName']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="CounterpartyType">Company Type <span class="req">*</span></label>
          <select id="CounterpartyType" name="CounterpartyType"
                  class="form-control <?= isset($errors['CounterpartyType']) ? 'is-invalid' : '' ?>">
            <option value="">Select type…</option>
            <option value="Supplier"        <?= ($old['CounterpartyType'] ?? '') === 'Supplier'        ? 'selected' : '' ?>>Supplier</option>
            <option value="Customer"        <?= ($old['CounterpartyType'] ?? '') === 'Customer'        ? 'selected' : '' ?>>Customer</option>
            <option value="ServiceProvider" <?= ($old['CounterpartyType'] ?? '') === 'ServiceProvider' ? 'selected' : '' ?>>Service Provider</option>
          </select>
          <?php if (isset($errors['CounterpartyType'])): ?>
          <span class="form-error visible"><?= e($errors['CounterpartyType']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="TaxCode">Tax Code <span class="req">*</span></label>
          <input type="text" id="TaxCode" name="TaxCode"
                 class="form-control <?= isset($errors['TaxCode']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['TaxCode'] ?? '') ?>"
                 placeholder="e.g., 0314892019" maxlength="20">
          <?php if (isset($errors['TaxCode'])): ?>
          <span class="form-error visible"><?= e($errors['TaxCode']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group span-2">
          <label for="Address">Registered Address</label>
          <input type="text" id="Address" name="Address"
                 class="form-control"
                 value="<?= e($old['Address'] ?? '') ?>"
                 placeholder="e.g., KCN Sóng Thần 2, Dĩ An, Tỉnh Bình Dương" maxlength="255">
        </div>

        <div class="form-group">
          <label for="CompanyEmail">Company Email</label>
          <input type="email" id="CompanyEmail" name="CompanyEmail"
                 class="form-control"
                 value="<?= e($old['CompanyEmail'] ?? '') ?>"
                 placeholder="info@company.vn" maxlength="150">
        </div>

        <div class="form-group">
          <label for="Phone">Phone Number</label>
          <input type="text" id="Phone" name="Phone"
                 class="form-control"
                 value="<?= e($old['Phone'] ?? '') ?>"
                 placeholder="(028) 3892 1199" maxlength="20">
        </div>

        <div class="form-group">
          <label for="LegalRepresentative">Legal Representative</label>
          <input type="text" id="LegalRepresentative" name="LegalRepresentative"
                 class="form-control"
                 value="<?= e($old['LegalRepresentative'] ?? '') ?>"
                 placeholder="Full name of director / authorized signatory" maxlength="100">
        </div>

        <div class="form-group">
          <label for="BusinessLicense">Business License Number / Reference</label>
          <input type="text" id="BusinessLicense" name="BusinessLicense"
                 class="form-control"
                 value="<?= e($old['BusinessLicense'] ?? '') ?>"
                 placeholder="e.g., 0314892019 do Sở KH&ĐT cấp" maxlength="255">
        </div>

      </div>
    </div>
  </div>

  <!-- Contact Person -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">
      <span class="card-title"><?= icon_svg('user') ?> Primary Contact Person</span>
      <span style="font-size:12px;color:var(--color-text-muted);">This person will receive a login account (pending Admin activation)</span>
    </div>
    <div class="card-body" style="padding:28px;">
      <div class="form-grid">

        <div class="form-group">
          <label for="ContactFullName">Full Name <span class="req">*</span></label>
          <input type="text" id="ContactFullName" name="ContactFullName"
                 class="form-control <?= isset($errors['ContactFullName']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['ContactFullName'] ?? '') ?>"
                 placeholder="e.g., Nguyễn Văn An" maxlength="100">
          <?php if (isset($errors['ContactFullName'])): ?>
          <span class="form-error visible"><?= e($errors['ContactFullName']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="ContactEmail">Login Email <span class="req">*</span></label>
          <input type="email" id="ContactEmail" name="ContactEmail"
                 class="form-control <?= isset($errors['ContactEmail']) ? 'is-invalid' : '' ?>"
                 value="<?= e($old['ContactEmail'] ?? '') ?>"
                 placeholder="contact@partnercompany.vn" maxlength="150">
          <span class="form-hint">This email will be their username once activated.</span>
          <?php if (isset($errors['ContactEmail'])): ?>
          <span class="form-error visible"><?= e($errors['ContactEmail']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label for="ContactJobTitle">Job Title</label>
          <input type="text" id="ContactJobTitle" name="ContactJobTitle"
                 class="form-control"
                 value="<?= e($old['ContactJobTitle'] ?? '') ?>"
                 placeholder="e.g., Director of Operations" maxlength="100">
        </div>

        <div class="form-group">
          <label for="ContactPhone">Phone Number</label>
          <input type="text" id="ContactPhone" name="ContactPhone"
                 class="form-control"
                 value="<?= e($old['ContactPhone'] ?? '') ?>"
                 placeholder="e.g., 0901234001" maxlength="20">
        </div>

      </div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:32px;">
    <a href="<?= BASE_URL ?>/pages/counterparties.php" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      <?= icon_svg('plus') ?> Submit for Admin Approval
    </button>
  </div>

</form>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
