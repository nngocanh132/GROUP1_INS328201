<?php
/**
 * Upload Document
 *
 * Attaches a file to an existing Contract:
 *   1. Creates a Document row  (Document table)
 *   2. Saves the uploaded file to UPLOAD_DIR
 *   3. Creates a ContractVersion row  (ContractVersion table, VersionNumber=1, IsCurrent=1)
 *
 * Access:
 *   Staff / Admin  — can upload to any contract
 *   External       — not allowed
 *
 * GET params:
 *   ?contract_id=<id>   pre-select the contract (optional)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title  = 'Upload Document';
$active_nav  = 'documents';

require_login();
$user = current_user();

if ($user['role'] === 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'You do not have permission to upload documents.');
}

$errors = [];
$old    = [];

// Pre-fill contract_id from query string
$prefill_contract_id = (int)($_GET['contract_id'] ?? 0);

// Load contracts for the dropdown (all non-closed contracts the user can see)
if ($user['role'] === 'staff') {
    // Staff can upload to any contract (they own or any active contract)
    $available_contracts = db_query(
        "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status
         FROM Contract c
         WHERE c.Status NOT IN ('Closed')
         ORDER BY c.UpdatedAt DESC
         LIMIT 200"
    )->fetchAll();
} else {
    // Admin: all contracts
    $available_contracts = db_query(
        "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status
         FROM Contract c
         WHERE c.Status NOT IN ('Closed')
         ORDER BY c.UpdatedAt DESC
         LIMIT 200"
    )->fetchAll();
}

$doc_types = [
    'MainContract'   => 'Main Contract',
    'Appendix'       => 'Appendix',
    'CargoInsurance' => 'Cargo Insurance',
    'FoodSafetyCert' => 'Food Safety Cert',
    'BankGuarantee'  => 'Bank Guarantee',
    'Other'          => 'Other',
];

// ---------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $old = [
            'ContractID'        => (int)($_POST['ContractID']        ?? 0),
            'DocumentType'      => trim($_POST['DocumentType']        ?? ''),
            'DocumentName'      => trim($_POST['DocumentName']        ?? ''),
            'IsVisibleToPartner'=> isset($_POST['IsVisibleToPartner']) ? 1 : 0,
            'ChangeSummary'     => trim($_POST['ChangeSummary']       ?? ''),
        ];

        // ---- Validation ----
        if (!$old['ContractID']) {
            $errors['ContractID'] = 'Please select a contract.';
        } else {
            $contract = db_query(
                "SELECT ContractID, ContractCode, ContractTitle, Status FROM Contract WHERE ContractID = ?",
                [$old['ContractID']]
            )->fetch();
            if (!$contract) {
                $errors['ContractID'] = 'Selected contract does not exist.';
            }
        }

        if (!array_key_exists($old['DocumentType'], $doc_types)) {
            $errors['DocumentType'] = 'Please select a document type.';
        }

        if ($old['DocumentName'] === '') {
            $errors['DocumentName'] = 'Document name is required.';
        } elseif (mb_strlen($old['DocumentName']) > 200) {
            $errors['DocumentName'] = 'Document name must be 200 characters or fewer.';
        }

        // ---- File validation ----
        $uploaded_file = $_FILES['document_file'] ?? null;

        if (empty($uploaded_file) || $uploaded_file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['document_file'] = 'Please choose a file to upload.';
        } elseif ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            $errors['document_file'] = $upload_errors[$uploaded_file['error']]
                ?? 'Upload failed (error code ' . $uploaded_file['error'] . ').';
        } else {
            // Size check
            $max_bytes = UPLOAD_MAX_MB * 1024 * 1024;
            if ($uploaded_file['size'] > $max_bytes) {
                $errors['document_file'] = 'File is too large. Maximum allowed size is ' . UPLOAD_MAX_MB . ' MB.';
            }

            // MIME type check using finfo (more reliable than browser-reported type)
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mime     = $finfo->file($uploaded_file['tmp_name']);
            $ext      = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));

            if (!in_array($mime, UPLOAD_ALLOWED, true) || !in_array($ext, UPLOAD_EXT_ALLOWED, true)) {
                $errors['document_file'] = 'File type not allowed. Accepted types: PDF, Word (doc/docx), Excel (xls/xlsx), PNG, JPG.';
            }
        }

        // ---- Ensure upload directory is ready ----
        if (empty($errors)) {
            $upload_dir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR);
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors['document_file'] = 'Upload directory does not exist and could not be created. Contact the system administrator.';
                }
            } elseif (!is_writable($upload_dir)) {
                $errors['document_file'] = 'Upload directory is not writable. Contact the system administrator.';
            }
        }

        // ---- Persist ----
        if (empty($errors)) {
            // Build a safe, unique filename:  CONTRACTCODE_TYPE_TIMESTAMP_RANDOM.ext
            $safe_contract = preg_replace('/[^A-Za-z0-9\-]/', '', $contract['ContractCode']);
            $safe_type     = preg_replace('/[^A-Za-z0-9]/', '', $old['DocumentType']);
            $safe_ext      = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
            $unique_name   = $safe_contract . '_' . $safe_type . '_'
                           . date('Ymd_His') . '_' . bin2hex(random_bytes(4))
                           . '.' . $safe_ext;

            $dest_path = $upload_dir . DIRECTORY_SEPARATOR . $unique_name;

            // The web-accessible path stored in DB is relative to the storage root
            $db_path = '/storage/documents/' . $unique_name;

            $db = get_db();
            $db->beginTransaction();
            try {
                // 1. Move the uploaded file
                if (!move_uploaded_file($uploaded_file['tmp_name'], $dest_path)) {
                    throw new RuntimeException('move_uploaded_file() failed. Check directory permissions.');
                }

                // 2. Insert Document row
                db_query(
                    "INSERT INTO Document (ContractID, DocumentType, DocumentName, IsVisibleToPartner, CreatedAt)
                     VALUES (?, ?, ?, ?, NOW())",
                    [
                        $old['ContractID'],
                        $old['DocumentType'],
                        $old['DocumentName'],
                        $old['IsVisibleToPartner'],
                    ]
                );
                $document_id = (int)$db->lastInsertId();

                // 3. Determine next version number for this document
                //    (always 1 for a brand-new Document row, but defend against edge cases)
                $next_version = 1;

                // 4. Insert ContractVersion row
                $file_hash = hash_file('sha256', $dest_path) ?: null;
                db_query(
                    "INSERT INTO ContractVersion
                         (DocumentID, VersionNumber, FilePath, FileSize, FileHash,
                          UploadedBy, ChangeSummary, IsCurrent, UploadedAt)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                    [
                        $document_id,
                        $next_version,
                        $db_path,
                        (int)$uploaded_file['size'],
                        $file_hash,
                        $user['account_id'],
                        $old['ChangeSummary'] ?: null,
                    ]
                );

                $db->commit();
                audit('UPLOAD', 'Document', $document_id, [
                    'contractCode' => $contract['ContractCode'],
                    'documentName' => $old['DocumentName'],
                    'fileName'     => $unique_name,
                    'size'         => $uploaded_file['size'],
                ]);

                redirect_with_flash(
                    BASE_URL . '/pages/contract_detail.php?id=' . $old['ContractID'],
                    'success',
                    "Document \"{$old['DocumentName']}\" uploaded successfully."
                );

            } catch (Throwable $e) {
                $db->rollBack();
                // Clean up the file if we moved it before the DB failed
                if (file_exists($dest_path)) {
                    @unlink($dest_path);
                }
                error_log('upload_document error: ' . $e->getMessage());
                $errors[] = 'An error occurred while saving the document: ' . $e->getMessage();
            }
        }
    }
}

// Pre-fill
if (empty($old)) {
    $old['ContractID']         = $prefill_contract_id;
    $old['DocumentType']       = 'MainContract';
    $old['IsVisibleToPartner'] = 0;
}

$breadcrumbs = [
    ['label' => 'Documents', 'url' => BASE_URL . '/pages/documents.php'],
    ['label' => 'Upload Document'],
];

include __DIR__ . '/../includes/layout_head.php';
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

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
