<?php
/**
 * Settings View (Admin)
 *
 * Variables available:
 * - $user (current user)
 * - $accounts (array of all accounts)
 * - $pending_count (count of pending accounts)
 */

$page_title = 'Settings';
$active_nav = 'settings';
include __DIR__ . '/../../../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Settings &amp; Administration</h1>
    <p>Manage user accounts and view system information.</p>
  </div>
</div>

<?php if ($pending_count > 0): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
  <?= icon_svg('alert-circle') ?>
  <span>
    <strong><?= $pending_count ?> account<?= $pending_count > 1 ? 's' : '' ?> pending approval.</strong>
    These are partner company contacts registered by staff. Activate them to grant login access.
  </span>
</div>
<?php endif; ?>

<!-- Account management -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <span class="card-title">User Accounts</span>
    <div style="display:flex;align-items:center;gap:12px;">
      <?php if ($pending_count > 0): ?>
      <span class="badge badge-pending"><?= $pending_count ?> Pending</span>
      <?php endif; ?>
      <span style="font-size:12px;color:var(--color-text-muted);"><?= count($accounts) ?> total</span>
    </div>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Staff ID / Partner</th>
          <th>Name / Email</th>
          <th>Role</th>
          <th>Department / Company</th>
          <th>Account Status</th>
          <th>Last Login</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $acc): ?>
        <?php
          $is_self    = (int)$acc['AccountID'] === (int)$user['account_id'];
          $is_pending = $acc['Status'] === 'Pending';
          $is_active  = $acc['Status'] === 'Active';
          $parts      = explode(' ', $acc['FullName'] ?? '');
          $ini        = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        ?>
        <tr <?= $is_pending ? 'style="background:rgba(217,119,6,.04);"' : '' ?>>
          <td class="td-ref">
            <?= e($acc['StaffID'] ?? ($acc['PartnerCompany'] ? '—' : '—')) ?>
            <?php if ($acc['PartnerCompany']): ?>
            <div class="td-meta"><?= e(mb_strimwidth($acc['PartnerCompany'], 0, 30, '…')) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="avatar sm" style="<?= $is_pending ? 'background:var(--color-warning-soft,#fef3c7);color:#92400e;' : '' ?>"><?= e($ini ?: 'U') ?></div>
              <div>
                <div style="font-weight:600;font-size:13px;"><?= e($acc['FullName'] ?: $acc['Email']) ?></div>
                <div class="td-meta"><?= e($acc['Email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge <?= match($acc['RoleName']) {
                'Manager/Admin' => 'badge-approved',
                'Staff'         => 'badge-review',
                default         => 'badge-draft',
            } ?>">
              <?= e($acc['RoleName']) ?>
            </span>
          </td>
          <td><?= e($acc['DepartmentName'] ?? ($acc['PartnerCompany'] ?? '—')) ?></td>
          <td>
            <?php if ($is_pending): ?>
              <span class="badge badge-pending">Pending Approval</span>
            <?php elseif ($is_active): ?>
              <span class="badge badge-active">Active</span>
            <?php else: ?>
              <span class="badge badge-cancelled">Inactive</span>
            <?php endif; ?>
          </td>
          <td class="td-meta"><?= fmt_datetime($acc['LastLoginAt']) ?></td>
          <td class="td-meta"><?= fmt_date($acc['CreatedAt']) ?></td>
          <td>
            <?php if ($is_self): ?>
              <span style="font-size:12px;color:var(--color-text-muted);">(you)</span>
            <?php elseif ($is_pending): ?>
              <!-- Approve pending account -->
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="account_id" value="<?= $acc['AccountID'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"
                        style="color:var(--color-success);border-color:var(--color-success);"
                        data-confirm="Activate account for <?= e($acc['Email']) ?>?">
                  <?= icon_svg('check-circle') ?> Activate
                </button>
              </form>
              <!-- Reject pending account -->
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject_pending">
                <input type="hidden" name="account_id" value="<?= $acc['AccountID'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"
                        style="color:var(--color-danger);border-color:var(--color-danger);"
                        data-confirm="Reject and deactivate account for <?= e($acc['Email']) ?>?">
                  <?= icon_svg('x') ?> Reject
                </button>
              </form>
            <?php elseif ($is_active): ?>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="account_id" value="<?= $acc['AccountID'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"
                        data-confirm="Deactivate account for <?= e($acc['Email']) ?>?">
                  Deactivate
                </button>
              </form>
            <?php else: ?>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="activate">
                <input type="hidden" name="account_id" value="<?= $acc['AccountID'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm"
                        data-confirm="Activate account for <?= e($acc['Email']) ?>?">
                  Activate
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- System info -->
<div class="card">
  <div class="card-header"><span class="card-title">System Information</span></div>
  <div class="card-body">
    <div class="detail-grid">
      <div class="detail-item">
        <div class="detail-label">Application</div>
        <div class="detail-value"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">PHP Version</div>
        <div class="detail-value mono"><?= phpversion() ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Database</div>
        <div class="detail-value mono"><?= DB_NAME ?> @ <?= DB_HOST ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Schema Tables</div>
        <div class="detail-value">17 tables (vinamilk_clm + ContractProposal)</div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Environment</div>
        <div class="detail-value"><?= DEV_MODE ? 'Development' : 'Production' ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Server Time</div>
        <div class="detail-value mono"><?= date('Y-m-d H:i:s') ?></div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
