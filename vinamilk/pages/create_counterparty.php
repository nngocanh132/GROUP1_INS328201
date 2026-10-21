<?php
/**
 * Create Partner Company (Counterparty) Account
 *
 * Staff fills in:
 *   - Company details  → Counterparty row (ApprovalStatus = 'Pending')
 *   - Contact person   → Account row (Status = 'Pending', RoleID = 3 External)
 *                        + Contact row
 *
 * Admin must then activate both (Counterparty.ApprovalStatus → Active,
 * Account.Status → Active) via Settings or Counterparties pages.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title  = 'Register Partner Company';
$active_nav  = 'counterparties';
$breadcrumbs = [
    ['label' => 'Counterparties', 'url' => BASE_URL . '/pages/counterparties.php'],
    ['label' => 'Register Partner Company'],
];

require_login();
$user = current_user();
if ($user['role'] === 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
}
if ($user['staff_id'] === '') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Only staff members can register partner companies.');
}

$errors = [];
$old    = [];

// ---------------------------------------------------------------
// Handle POST
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $old = [
            // Company
            'CompanyName'         => trim($_POST['CompanyName']         ?? ''),
            'CounterpartyType'    => trim($_POST['CounterpartyType']    ?? ''),
            'TaxCode'             => trim($_POST['TaxCode']             ?? ''),
            'Address'             => trim($_POST['Address']             ?? ''),
            'CompanyEmail'        => trim($_POST['CompanyEmail']        ?? ''),
            'Phone'               => trim($_POST['Phone']               ?? ''),
            'LegalRepresentative' => trim($_POST['LegalRepresentative'] ?? ''),
            'BusinessLicense'     => trim($_POST['BusinessLicense']     ?? ''),
            // Contact person
            'ContactFullName'     => trim($_POST['ContactFullName']     ?? ''),
            'ContactEmail'        => trim($_POST['ContactEmail']        ?? ''),
            'ContactJobTitle'     => trim($_POST['ContactJobTitle']     ?? ''),
            'ContactPhone'        => trim($_POST['ContactPhone']        ?? ''),
        ];

        // ---- Validation ----
        if ($old['CompanyName'] === '')
            $errors['CompanyName'] = 'Company name is required.';

        $allowed_types = ['Supplier', 'Customer', 'ServiceProvider'];
        if (!in_array($old['CounterpartyType'], $allowed_types))
            $errors['CounterpartyType'] = 'Please select a company type.';

        if ($old['TaxCode'] === '')
            $errors['TaxCode'] = 'Tax code is required.';

        if ($old['ContactFullName'] === '')
            $errors['ContactFullName'] = 'Contact person full name is required.';

        if ($old['ContactEmail'] === '') {
            $errors['ContactEmail'] = 'Contact person email is required.';
        } elseif (!filter_var($old['ContactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['ContactEmail'] = 'Contact email is not a valid email address.';
        }

        // Check for duplicate tax code
        if ($old['TaxCode'] !== '' && !isset($errors['TaxCode'])) {
            $dup = db_query(
                "SELECT 1 FROM Counterparty WHERE TaxCode = ?",
                [$old['TaxCode']]
            )->fetchColumn();
            if ($dup) $errors['TaxCode'] = 'A company with this tax code already exists.';
        }

        // Check for duplicate contact email
        if (!isset($errors['ContactEmail'])) {
            $dup = db_query(
                "SELECT 1 FROM Account WHERE Email = ?",
                [$old['ContactEmail']]
            )->fetchColumn();
            if ($dup) $errors['ContactEmail'] = 'An account with this email already exists.';
        }

        // ---- Persist ----
        if (empty($errors)) {
            // Generate counterparty code: VNM-EXT-NNN
            $next_num = (int) db_query(
                "SELECT COUNT(*) FROM Counterparty"
            )->fetchColumn();
            $cp_code = 'VNM-EXT-' . str_pad($next_num + 1, 3, '0', STR_PAD_LEFT);

            // Ensure uniqueness
            while (db_query("SELECT 1 FROM Counterparty WHERE CounterpartyCode = ?", [$cp_code])->fetchColumn()) {
                $next_num++;
                $cp_code = 'VNM-EXT-' . str_pad($next_num + 1, 3, '0', STR_PAD_LEFT);
            }

            $db = get_db();
            $db->beginTransaction();
            try {
                // 1. Insert Counterparty (Pending approval)
                db_query(
                    "INSERT INTO Counterparty
                         (CounterpartyCode, CompanyName, CounterpartyType, TaxCode,
                          Address, Email, Phone, LegalRepresentative, BusinessLicense,
                          ApprovalStatus, ProposedBy, ProposedAt)
                     VALUES (?,?,?,?,?,?,?,?,?,'Pending',?,NOW())",
                    [
                        $cp_code,
                        $old['CompanyName'],
                        $old['CounterpartyType'],
                        $old['TaxCode'],
                        $old['Address']             ?: null,
                        $old['CompanyEmail']        ?: null,
                        $old['Phone']               ?: null,
                        $old['LegalRepresentative'] ?: null,
                        $old['BusinessLicense']     ?: null,
                        $user['staff_id'],
                    ]
                );
                $cp_id = (int) $db->lastInsertId();

                // 2. Get External role ID
                $ext_role_id = (int) db_query(
                    "SELECT RoleID FROM Role WHERE RoleName = 'External' LIMIT 1"
                )->fetchColumn();
                if (!$ext_role_id) throw new RuntimeException('External role not found in database.');

                // 3. Insert Account for contact person (Pending — needs admin activation)
                $temp_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                db_query(
                    "INSERT INTO Account (RoleID, Email, PasswordHash, Status, CreatedAt)
                     VALUES (?, ?, ?, 'Pending', NOW())",
                    [$ext_role_id, $old['ContactEmail'], $temp_hash]
                );
                $account_id = (int) $db->lastInsertId();

                // 4. Insert Contact linking the account to the counterparty
                db_query(
                    "INSERT INTO Contact
                         (AccountID, CounterpartyID, FullName, JobTitle, Phone, IsPrimary)
                     VALUES (?, ?, ?, ?, ?, 1)",
                    [
                        $account_id,
                        $cp_id,
                        $old['ContactFullName'],
                        $old['ContactJobTitle'] ?: null,
                        $old['ContactPhone']    ?: null,
                    ]
                );

                // 5. Notify admins about the pending counterparty
                $admin_accounts = db_query(
                    "SELECT a.AccountID FROM Account a
                     JOIN Role r ON r.RoleID = a.RoleID
                     WHERE r.RoleName = 'Manager/Admin' AND a.Status = 'Active'"
                )->fetchAll(PDO::FETCH_COLUMN);

                foreach ($admin_accounts as $admin_id) {
                    db_query(
                        "INSERT INTO Notification
                             (AccountID, CounterpartyID, Title, Message, NotificationType, IsRead, CreatedAt)
                         VALUES (?, ?, ?, ?, 'Info', 0, NOW())",
                        [
                            $admin_id,
                            $cp_id,
                            'New partner company awaiting approval',
                            "Staff member {$user['name']} has registered {$old['CompanyName']} ({$cp_code}). Please review and approve or reject.",
                        ]
                    );
                }

                $db->commit();
                audit('CREATE', 'Counterparty', $cp_id, [
                    'code'    => $cp_code,
                    'company' => $old['CompanyName'],
                    'status'  => 'Pending',
                ]);

                redirect_with_flash(
                    BASE_URL . '/pages/counterparties.php?status=Pending',
                    'success',
                    "Partner company \"{$old['CompanyName']}\" ({$cp_code}) registered and is awaiting Admin approval. The contact account for {$old['ContactEmail']} has also been created pending activation."
                );
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('create_counterparty error: ' . $e->getMessage());
                $errors[] = 'An unexpected error occurred: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../includes/layout_head.php';
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

<!-- Approval notice -->
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

  <!-- Footer -->
  <div style="display:flex;justify-content:flex-end;gap:10px;margin-bottom:32px;">
    <a href="<?= BASE_URL ?>/pages/counterparties.php" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary">
      <?= icon_svg('plus') ?>
      Submit for Admin Approval
    </button>
  </div>

</form>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
