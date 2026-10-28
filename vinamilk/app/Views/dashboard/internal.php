<?php
/**
 * Internal Dashboard View (staff/admin)
 *
 * Variables available:
 * - $user (current user array)
 * - $metrics (contract metrics)
 * - $expiring_soon (count)
 * - $pending_approvals (count)
 * - $by_type_rows, $monthly_rows (chart data)
 * - $recent_contracts, $activity (arrays)
 * - $status_labels, $status_values, $status_colors (chart arrays)
 * - $type_labels, $type_values, $month_labels, $month_values (chart arrays)
 */

$page_title = 'Dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/../../../includes/layout_head.php';
?>

<!-- ============================================================
     INTERNAL DASHBOARD
     ============================================================ -->

<!-- Welcome banner -->
<div class="welcome-banner">
  <div class="welcome-text">
    <h2>Welcome back, <?= e(explode(' ', $user['name'])[0]) ?></h2>
    <p><?= date('l, F j, Y') ?> · <?= e(role_label($user['role'])) ?></p>
  </div>
  <?php if ($user['role'] === 'staff'): ?>
  <div class="welcome-cta">
    <a href="<?= BASE_URL ?>/pages/create_request.php" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      New Contract
    </a>
  </div>
  <?php elseif ($user['role'] === 'admin'): ?>
  <div class="welcome-cta">
    <a href="<?= BASE_URL ?>/pages/approvals.php" class="btn btn-primary">
      <?= icon_svg('check-circle') ?>
      Review &amp; Approve
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- KPI tiles -->
<div class="stats-grid">
  <div class="stat-tile">
    <div class="stat-icon blue">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$metrics['total'] ?></div>
      <div class="stat-label">Total Contracts</div>
      <div class="stat-sub"><?= (int)$metrics['active_count'] ?> active</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon orange">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$metrics['pending_review'] + (int)$metrics['pending_approval'] ?></div>
      <div class="stat-label">Awaiting Action</div>
      <div class="stat-sub"><?= $pending_approvals ?> pending signature<?= $pending_approvals !== 1 ? 's' : '' ?></div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon green">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$metrics['approved'] + (int)$metrics['active_count'] ?></div>
      <div class="stat-label">Approved / Active</div>
      <div class="stat-sub"><?= (int)$metrics['closed'] ?> closed</div>
    </div>
  </div>

  <div class="stat-tile">
    <div class="stat-icon red">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $expiring_soon ?></div>
      <div class="stat-label">Expiring in 30 Days</div>
      <div class="stat-sub"><?= (int)$metrics['revision'] ?> revision requested</div>
    </div>
  </div>
</div>

<!-- Charts row -->
<?php $has_data = (int)$metrics['total'] > 0; ?>
<div class="charts-grid" style="margin-bottom:20px;">

  <!-- Doughnut: Contract Status Distribution -->
  <div class="chart-card">
    <div class="chart-card-header">
      <div class="chart-card-title">Contract Status Distribution</div>
      <div class="chart-card-subtitle">All contracts by current status</div>
    </div>
    <div class="chart-body">
      <?php if (!$has_data): ?>
      <div class="chart-empty">No contract data yet.</div>
      <?php else: ?>
      <div class="doughnut-wrap">
        <div class="doughnut-canvas-wrap">
          <canvas id="chartStatus" width="130" height="130"></canvas>
          <div class="doughnut-center-label">
            <div class="doughnut-center-value"><?= (int)$metrics['total'] ?></div>
            <div class="doughnut-center-sub">total</div>
          </div>
        </div>
        <div class="chart-legend">
          <?php foreach (array_map(null, $status_labels, $status_values, $status_colors) as [$lbl,$val,$col]): ?>
          <?php if ($val > 0): ?>
          <div class="legend-item">
            <span class="legend-dot" style="background:<?= $col ?>;"></span>
            <span class="legend-label"><?= e($lbl) ?></span>
            <span class="legend-value"><?= $val ?></span>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bar: Contract Type Distribution -->
  <div class="chart-card">
    <div class="chart-card-header">
      <div class="chart-card-title">Contracts by Type</div>
      <div class="chart-card-subtitle">Distribution across contract categories</div>
    </div>
    <div class="chart-body">
      <?php if (empty($by_type_rows)): ?>
      <div class="chart-empty">No contract data yet.</div>
      <?php else: ?>
      <canvas id="chartTypes"></canvas>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Monthly trend + activity row -->
<div class="charts-grid" style="grid-template-columns:1fr 1fr;margin-bottom:20px;">

  <div class="chart-card">
    <div class="chart-card-header">
      <div class="chart-card-title">Contract Activity</div>
      <div class="chart-card-subtitle">Contracts created per month (last 12 months)</div>
    </div>
    <div class="chart-body">
      <?php if (empty($monthly_rows)): ?>
      <div class="chart-empty">No monthly data available.</div>
      <?php else: ?>
      <canvas id="chartMonthly"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <div class="chart-card">
    <div class="chart-card-header">
      <div class="chart-card-title">Recent Activity</div>
    </div>
    <div class="chart-body" style="padding:8px 16px 14px;">
      <?php if (empty($activity)): ?>
      <div class="chart-empty">No activity recorded yet.</div>
      <?php else: ?>
      <?php foreach ($activity as $act):
        $actor_name = $act['ActorName'] ?? 'System';
        $parts      = explode(' ', $actor_name);
        $initials   = mb_strtoupper(mb_substr($parts[0],0,1) . mb_substr(end($parts),0,1));
      ?>
      <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--color-border);">
        <div class="avatar sm" style="flex-shrink:0;margin-top:1px;"><?= e($initials) ?></div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:12px;font-weight:600;color:var(--color-text-primary);"><?= e($actor_name) ?></div>
          <div style="font-size:11px;color:var(--color-text-secondary);">
            <?= e($act['Action'] ?? '') ?>
            <span style="color:var(--color-text-muted);">·</span>
            <?= e($act['EntityName'] ?? '') ?><?php if ($act['EntityID']): ?> #<?= (int)$act['EntityID'] ?><?php endif; ?>
          </div>
          <div style="font-size:10.5px;color:var(--color-text-muted);margin-top:1px;"><?= fmt_datetime($act['CreatedAt']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Recent contracts table -->
<div class="card">
  <div class="card-header">
    <span class="card-title">Recent Contracts</span>
    <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-ghost btn-sm">
      View all
      <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <?php if (empty($recent_contracts)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
    </div>
    <h3>No contracts yet</h3>
    <p>Create a new contract to get started.</p>
    <?php if ($user['role'] === 'staff'): ?>
    <a href="<?= BASE_URL ?>/pages/create_request.php" class="btn btn-primary btn-sm">New Contract</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Code</th>
          <th>Title</th>
          <th>Type</th>
          <th>Counterparty</th>
          <th>Status</th>
          <th>End Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_contracts as $c): ?>
        <tr>
          <td class="td-ref"><?= e($c['ContractCode']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $c['ContractID'] ?>"
               style="font-weight:600;color:var(--color-text-primary);">
              <?= e(mb_strimwidth($c['ContractTitle'], 0, 52, '…')) ?>
            </a>
            <div class="td-meta"><?= e($c['DepartmentName']) ?></div>
          </td>
          <td class="td-meta"><?= e($c['TypeName']) ?></td>
          <td><?= e($c['CounterpartyName']) ?></td>
          <td><?= status_badge($c['Status']) ?></td>
          <td class="td-meta"><?= fmt_date($c['EndDate']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $c['ContractID'] ?>"
               class="btn btn-ghost btn-sm">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              View
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
window.VNM_CHARTS = {
  status:  { labels: <?= json_encode($status_labels, JSON_UNESCAPED_UNICODE) ?>, data: <?= json_encode($status_values) ?>, colors: <?= json_encode($status_colors) ?> },
  types:   { labels: <?= json_encode($type_labels,   JSON_UNESCAPED_UNICODE) ?>, data: <?= json_encode($type_values) ?> },
  monthly: { labels: <?= json_encode($month_labels,  JSON_UNESCAPED_UNICODE) ?>, data: <?= json_encode($month_values) ?> }
};
</script>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
