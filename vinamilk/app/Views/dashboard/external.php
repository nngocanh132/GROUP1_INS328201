<?php
/**
 * External Partner Dashboard View
 *
 * Variables available:
 * - $user (current user array)
 * - $ext_contact (counterparty contact info)
 * - $ext_cp_id (counterparty ID)
 * - $ext_pending_proposals (array)
 * - $ext_contracts (array)
 * - $ext_notifications (array)
 * - $ext_total_contracts, $ext_active_contracts, $ext_pending_count, $ext_approved_count
 */

$page_title = 'Dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../../../includes/layout_head.php';
?>

<!-- ============================================================
     EXTERNAL / PARTNER DASHBOARD
     ============================================================ -->

<!-- Welcome banner -->
<div class="welcome-banner">
  <div class="welcome-text">
    <h2>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h2>
    <p>
      <?= date('l, F j, Y') ?>
      <span style="color:var(--color-text-muted);">·</span>
      <?= e($ext_contact['CompanyName'] ?? 'Partner Portal') ?>
    </p>
  </div>
  <?php if ($ext_pending_count > 0): ?>
  <div class="welcome-cta">
    <a href="<?= BASE_URL ?>/pages/partner_portal.php" class="btn btn-primary">
      <?= icon_svg('eye') ?>
      Review <?= $ext_pending_count ?> Pending Proposal<?= $ext_pending_count > 1 ? 's' : '' ?>
    </a>
  </div>
  <?php else: ?>
  <div class="welcome-cta">
    <a href="<?= BASE_URL ?>/pages/partner_portal.php" class="btn btn-secondary">
      <?= icon_svg('file-plus') ?>
      View Portal
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Partner KPI tiles -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-tile">
    <div class="stat-icon <?= $ext_pending_count > 0 ? 'orange' : 'blue' ?>">
      <?= icon_svg('file-plus') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $ext_pending_count ?></div>
      <div class="stat-label">Awaiting Your Review</div>
      <div class="stat-sub">Contract proposals</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon blue">
      <?= icon_svg('file-text') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $ext_total_contracts ?></div>
      <div class="stat-label">Total Contracts</div>
      <div class="stat-sub"><?= $ext_active_contracts ?> currently active</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon green">
      <?= icon_svg('check-circle') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $ext_active_contracts ?></div>
      <div class="stat-label">Active Contracts</div>
      <div class="stat-sub">with Vinamilk</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon green">
      <?= icon_svg('check-circle') ?>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $ext_approved_count ?></div>
      <div class="stat-label">Approved Proposals</div>
      <div class="stat-sub">All time</div>
    </div>
  </div>
</div>

<div class="charts-grid" style="grid-template-columns:1fr 1fr;margin-bottom:20px;">

  <!-- Pending proposals panel -->
  <div class="chart-card">
    <div class="chart-card-header">
      <div class="chart-card-title">
        <?= icon_svg('file-plus') ?> Proposals Awaiting Your Review
      </div>
      <?php if ($ext_pending_count > 0): ?>
      <span class="badge badge-pending"><?= $ext_pending_count ?> pending</span>
      <?php endif; ?>
    </div>
    <div class="chart-body" style="padding:0;">
      <?php if (empty($ext_pending_proposals)): ?>
      <div style="padding:28px;text-align:center;color:var(--color-text-muted);">
        <?= icon_svg('check-circle') ?>
        <p style="margin:8px 0 0;font-size:13px;">No proposals awaiting your review.</p>
        <a href="<?= BASE_URL ?>/pages/partner_portal.php" style="font-size:12px;">View all proposals →</a>
      </div>
      <?php else: ?>
      <?php foreach ($ext_pending_proposals as $p): ?>
      <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;
                  border-bottom:1px solid var(--color-border);">
        <div style="width:36px;height:36px;background:#fef3c7;border-radius:var(--radius);
                    display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
          <?= icon_svg('file-plus') ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:13px;font-weight:600;"><?= e(mb_strimwidth($p['ProposalTitle'], 0, 48, '…')) ?></div>
          <div class="td-meta">
            <?= e($p['ProposalCode']) ?> · <?= e($p['TypeName']) ?> · <?= fmt_date($p['CreatedAt']) ?>
          </div>
          <div class="td-meta">Submitted by <?= e($p['ProposerName']) ?></div>
          <?php if ($p['Summary']): ?>
          <div style="font-size:11px;color:var(--color-text-secondary);margin-top:3px;line-height:1.5;">
            <?= e(mb_strimwidth($p['Summary'], 0, 100, '…')) ?>
          </div>
          <?php endif; ?>
        </div>
        <a href="<?= BASE_URL ?>/pages/partner_portal.php?review=<?= $p['ProposalID'] ?>"
           class="btn btn-primary btn-sm" style="white-space:nowrap;margin-top:2px;">
          <?= icon_svg('eye') ?> Review
        </a>
      </div>
      <?php endforeach; ?>
      <div style="padding:10px 16px;text-align:right;">
        <a href="<?= BASE_URL ?>/pages/partner_portal.php" style="font-size:12px;color:var(--color-brand-blue);">
          View all proposals →
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Notifications panel -->
  <div class="chart-card">
    <div class="chart-card-header">
      <div class="chart-card-title"><?= icon_svg('bell') ?> Recent Notifications</div>
    </div>
    <div class="chart-body" style="padding:0;">
      <?php if (empty($ext_notifications)): ?>
      <div style="padding:28px;text-align:center;color:var(--color-text-muted);">
        <p style="font-size:13px;">No notifications yet.</p>
      </div>
      <?php else: ?>
      <?php foreach ($ext_notifications as $n):
        $icon_name = match($n['NotificationType']) {
            'Success' => 'check-circle',
            'Warning' => 'alert-circle',
            default   => 'info',
        };
        $icon_color = match($n['NotificationType']) {
            'Success' => 'var(--color-success)',
            'Warning' => 'var(--color-warning,#d97706)',
            default   => 'var(--color-brand-blue)',
        };
      ?>
      <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 16px;
                  border-bottom:1px solid var(--color-border);
                  <?= !$n['IsRead'] ? 'background:var(--color-accent-soft,#eef2ff);' : '' ?>">
        <div style="color:<?= $icon_color ?>;flex-shrink:0;margin-top:1px;">
          <?= icon_svg($icon_name) ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:12px;font-weight:600;"><?= e($n['Title']) ?></div>
          <div style="font-size:11px;color:var(--color-text-secondary);line-height:1.4;margin-top:2px;">
            <?= e(mb_strimwidth($n['Message'], 0, 90, '…')) ?>
          </div>
          <div style="font-size:10.5px;color:var(--color-text-muted);margin-top:2px;">
            <?= fmt_datetime($n['CreatedAt']) ?>
            <?php if (!$n['IsRead']): ?>
            <span style="color:var(--color-brand-blue);font-weight:600;margin-left:6px;">● New</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Company contracts table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><?= icon_svg('file-text') ?> Our Contracts with Vinamilk</span>
    <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-ghost btn-sm">
      View all
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php if (empty($ext_contracts)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><?= icon_svg('file-text') ?></div>
    <h3>No contracts yet</h3>
    <p>Contracts between your company and Vinamilk will appear here once created.</p>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Title</th>
          <th>Type</th>
          <th>Value</th>
          <th>End Date</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ext_contracts as $c): ?>
        <tr>
          <td class="td-ref"><?= e($c['ContractCode']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $c['ContractID'] ?>"
               style="font-weight:600;color:var(--color-text-primary);">
              <?= e(mb_strimwidth($c['ContractTitle'], 0, 52, '…')) ?>
            </a>
          </td>
          <td class="td-meta"><?= e($c['TypeName']) ?></td>
          <td><?= money($c['ContractValue'], $c['Currency'] ?? 'VND') ?></td>
          <td class="td-meta">
            <?= $c['EndDate'] ? fmt_date($c['EndDate']) : '—' ?>
            <?php if ($c['EndDate'] && $c['Status'] === 'Active'): ?>
            <?php $dl = (int)(new DateTime())->diff(new DateTime($c['EndDate']))->format('%r%a'); ?>
            <?php if ($dl >= 0 && $dl <= 60): ?>
            <div style="font-size:11px;color:<?= $dl<=30?'var(--color-danger)':'var(--color-warning,#d97706)' ?>;font-weight:600;">
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
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
