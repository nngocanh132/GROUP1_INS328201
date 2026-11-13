<?php
/**
 * Upload Document View
 *
 * Variables available:
 * - $user
 * - $errors (array)
 * - $old (form data)
 * - $available_contracts (array)
 * - $doc_types (array)
 */

$page_title = 'Upload Document';
$active_nav = 'documents';
$breadcrumbs = [
    ['label' => 'Documents', 'url' => BASE_URL . '/pages/documents.php'],
    ['label' => 'Upload Document'],
];
include __DIR__ . '/../../../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Upload Document</h1>
    <p>Attach a file to a contract. Accepted formats: PDF, Word, Excel, PNG, JPG (max <?= UPLOAD_MAX_MB ?> MB).</p>
  </div>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/documents.php" class="btn btn-secondary">
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
      Please correct the errors highlighted below.
    <?php endif; ?>
  </span>
</div>
<?php endif; ?>

<div class="card">
  <form id="uploadForm"
        method="POST"
        action="<?= BASE_URL ?>/pages/upload_document.php"
        enctype="multipart/form-data"
        novalidate>
    <?= csrf_field() ?>
    <!-- Hidden max-size hint for PHP (no real enforcement, just a hint) -->
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_MB * 1024 * 1024 ?>">

    <div class="card-body" style="padding:28px;">

      <!-- Section: Contract & Type -->
      <div class="form-section">
        <div class="form-section-title">
          <?= icon_svg('file-text') ?>
          Document Details
        </div>
        <div class="form-grid">

          <div class="form-group span-2">
            <label for="ContractID">Contract <span class="req">*</span></label>
            <select id="ContractID" name="ContractID"
                    class="form-control <?= isset($errors['ContractID']) ? 'is-invalid' : '' ?>">
              <option value="">Select contract…</option>
              <?php foreach ($available_contracts as $c): ?>
              <option value="<?= $c['ContractID'] ?>"
                      <?= (int)($old['ContractID'] ?? 0) === (int)$c['ContractID'] ? 'selected' : '' ?>>
                [<?= e($c['ContractCode']) ?>] <?= e(mb_strimwidth($c['ContractTitle'], 0, 60, '…')) ?>
                — <?= e($c['Status']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['ContractID'])): ?>
              <span class="form-error visible"><?= e($errors['ContractID']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="DocumentType">Document Type <span class="req">*</span></label>
            <select id="DocumentType" name="DocumentType"
                    class="form-control <?= isset($errors['DocumentType']) ? 'is-invalid' : '' ?>">
              <?php foreach ($doc_types as $val => $label): ?>
              <option value="<?= $val ?>"
                      <?= ($old['DocumentType'] ?? '') === $val ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['DocumentType'])): ?>
              <span class="form-error visible"><?= e($errors['DocumentType']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="DocumentName">Document Name <span class="req">*</span></label>
            <input type="text" id="DocumentName" name="DocumentName"
                   class="form-control <?= isset($errors['DocumentName']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['DocumentName'] ?? '') ?>"
                   placeholder="e.g., Raw Material Supply Agreement v1.0"
                   maxlength="200">
            <span class="form-hint">A descriptive name for this document (not the filename).</span>
            <?php if (isset($errors['DocumentName'])): ?>
              <span class="form-error visible"><?= e($errors['DocumentName']) ?></span>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- Section: File Upload -->
      <div class="form-section">
        <div class="form-section-title">
          <?= icon_svg('upload') ?>
          File
        </div>
        <div class="form-grid cols-1">

          <div class="form-group">
            <label for="document_file">Upload File <span class="req">*</span></label>
            <input type="file" id="document_file" name="document_file"
                   class="form-control <?= isset($errors['document_file']) ? 'is-invalid' : '' ?>"
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg"
                   style="padding:6px 11px; cursor:pointer;">
            <span class="form-hint">
              Max <?= UPLOAD_MAX_MB ?> MB. Accepted: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG.
            </span>
            <?php if (isset($errors['document_file'])): ?>
              <span class="form-error visible"><?= e($errors['document_file']) ?></span>
            <?php endif; ?>
          </div>

          <div class="form-group">
            <label for="ChangeSummary">Version Notes <span style="color:var(--color-text-muted);font-weight:400;">(optional)</span></label>
            <textarea id="ChangeSummary" name="ChangeSummary"
                      class="form-control"
                      rows="2"
                      placeholder="e.g., Initial version, Revised clause 4.2…"><?= e($old['ChangeSummary'] ?? '') ?></textarea>
          </div>

          <div class="form-group">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:500;">
              <input type="checkbox" name="IsVisibleToPartner" value="1"
                     <?= ($old['IsVisibleToPartner'] ?? 0) ? 'checked' : '' ?>
                     style="width:16px;height:16px;accent-color:var(--color-brand-blue);">
              Make this document visible to the partner company
            </label>
            <span class="form-hint">If checked, the partner's portal users can see and download this file.</span>
          </div>

        </div>
      </div>

    </div><!-- /card-body -->

    <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;">
      <span style="font-size:12px;color:var(--color-text-muted);">
        <span style="color:var(--color-danger);">*</span> Required fields
      </span>
      <div style="display:flex;gap:10px;">
        <a href="<?= BASE_URL ?>/pages/documents.php" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary" id="uploadBtn">
          <?= icon_svg('upload') ?> Upload Document
        </button>
      </div>
    </div>

  </form>
</div>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
