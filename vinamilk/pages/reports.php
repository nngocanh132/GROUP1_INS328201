<?php
/**
 * Reports — uses vinamilk_clm schema
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Reports';
$active_nav = 'reports';

require_login();
if (current_user()['role'] === 'external') {
    redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
}

// Contracts by status
$by_status = db_query("
    SELECT Status, COUNT(*) AS cnt FROM Contract GROUP BY Status ORDER BY cnt DESC
")->fetchAll();

// Contracts by type
$by_type = db_query("
    SELECT ct.TypeName, COUNT(c.ContractID) AS cnt,
           SUM(c.ContractValue) AS total_value
    FROM Contract c
    JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
    GROUP BY ct.TypeName ORDER BY cnt DESC
")->fetchAll();

// Contracts by department
$by_dept = db_query("
    SELECT d.DepartmentName, COUNT(c.ContractID) AS cnt
    FROM Contract c JOIN Department d ON d.DepartmentID = c.DepartmentID
    GROUP BY d.DepartmentName ORDER BY cnt DESC
")->fetchAll();

// Top counterparties by contract value
$top_parties = db_query("
    SELECT cp.CompanyName, COUNT(c.ContractID) AS cnt,
           SUM(c.ContractValue) AS total_value
    FROM Contract c JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
    WHERE c.ContractValue IS NOT NULL
    GROUP BY cp.CompanyName ORDER BY total_value DESC LIMIT 8
")->fetchAll();

// Monthly contract creation
$monthly = db_query("
    SELECT DATE_FORMAT(CreatedAt,'%Y-%m') AS month, COUNT(*) AS cnt
    FROM Contract WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

// Totals
$totals = db_query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN Status='Active' THEN ContractValue ELSE 0 END) AS active_value,
           SUM(ContractValue) AS total_value,
           SUM(CASE WHEN Status='Active' AND EndDate BETWEEN CURDATE()
               AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring_soon
    FROM Contract WHERE ContractValue IS NOT NULL
")->fetch();

// Average review SLA (days)
$sla = db_query("
    SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, SubmittedAt, ReviewedAt)) / 24, 1) AS sla_days
    FROM Review WHERE ReviewedAt IS NOT NULL
")->fetchColumn();

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Reports</h1>
    <p>Summary statistics derived from live contract and workflow data.</p>
  </div>
</div>

<!-- Top stats -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-tile">
    <div class="stat-icon blue">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= (int)$totals['total'] ?></div>
      <div class="stat-label">Contracts with Value</div>
    </div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon green">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value" style="font-size:18px;"><?= number_format((float)$totals['active_value']/1000000000, 2) ?>B</div>
      <div class="stat-label">Active Contract Value (VND)</div>
    </div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon purple">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="18" y1="20" x2="18" y2="10"/>
        <line x1="12" y1="20" x2="12" y2="4"/>
        <line x1="6" y1="20" x2="6" y2="14"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value" style="font-size:18px;"><?= number_format((float)$totals['total_value']/1000000000, 3) ?>B</div>
      <div class="stat-label">Total Portfolio Value (VND)</div>
    </div>
  </div>
  <div class="stat-tile">
    <div class="stat-icon orange">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 16 14"/>
      </svg>
    </div>
    <div class="stat-content">
      <div class="stat-value"><?= $sla ?: '—' ?></div>
      <div class="stat-label">Avg Review SLA (days)</div>
      <div class="stat-sub"><?= (int)$totals['expiring_soon'] ?> expiring in 30 days</div>
    </div>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
  <!-- By Status -->
  <div class="card">
    <div class="card-header"><span class="card-title">Contracts by Status</span></div>
    <div class="card-body" style="padding:16px;">
      <?php $total_c = max(1, array_sum(array_column($by_status,'cnt')));
      foreach ($by_status as $row):
        $pct = round($row['cnt']/$total_c*100); ?>
      <div style="margin-bottom:14px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
          <span style="font-size:13px;"><?= status_badge($row['Status']) ?></span>
          <span style="font-size:12px; font-weight:700;"><?= $row['cnt'] ?> <span style="color:var(--color-text-muted); font-weight:400;">(<?= $pct ?>%)</span></span>
        </div>
        <div style="height:5px; background:var(--color-bg); border-radius:99px; overflow:hidden;">
          <div style="height:100%; width:<?= $pct ?>%; background:var(--color-brand-blue); border-radius:99px;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- By Type -->
  <div class="card">
    <div class="card-header"><span class="card-title">Contracts by Type</span></div>
    <div class="card-body" style="padding:0;">
      <table class="data-table">
        <thead><tr><th>Type</th><th class="text-right">Count</th><th class="text-right">Total Value (VND)</th></tr></thead>
        <tbody>
          <?php foreach ($by_type as $r): ?>
          <tr>
            <td><?= e($r['TypeName']) ?></td>
            <td class="text-right font-semibold"><?= $r['cnt'] ?></td>
            <td class="text-right"><?= $r['total_value'] ? number_format($r['total_value']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
  <!-- By Department -->
  <div class="card">
    <div class="card-header"><span class="card-title">Contracts by Department</span></div>
    <div class="card-body" style="padding:0;">
      <table class="data-table">
        <thead><tr><th>Department</th><th class="text-right">Contracts</th></tr></thead>
        <tbody>
          <?php foreach ($by_dept as $r): ?>
          <tr><td><?= e($r['DepartmentName']) ?></td><td class="text-right font-semibold"><?= $r['cnt'] ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top counterparties -->
  <div class="card">
    <div class="card-header"><span class="card-title">Top Counterparties by Value</span></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($top_parties)): ?>
      <div style="padding:28px; text-align:center; color:var(--color-text-muted); font-size:13px;">No data.</div>
      <?php else: ?>
      <table class="data-table">
        <thead><tr><th>Counterparty</th><th class="text-right">Contracts</th><th class="text-right">Value (VND)</th></tr></thead>
        <tbody>
          <?php foreach ($top_parties as $r): ?>
          <tr>
            <td><?= e($r['CompanyName']) ?></td>
            <td class="text-right"><?= $r['cnt'] ?></td>
            <td class="text-right font-semibold"><?= number_format($r['total_value']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Monthly bar chart -->
<div class="card">
  <div class="card-header"><span class="card-title">Monthly Contract Creation (Last 12 Months)</span></div>
  <div class="card-body" style="padding:20px;">
    <?php if (empty($monthly)): ?>
    <p style="color:var(--color-text-muted); font-size:13px; text-align:center;">No data available.</p>
    <?php else:
      $max_cnt = max(max(array_column($monthly,'cnt')), 1); ?>
    <div style="display:flex; align-items:flex-end; gap:8px; height:120px; margin-bottom:28px; position:relative;">
      <?php foreach ($monthly as $m):
        $pct = round($m['cnt']/$max_cnt*100);
        $label = date('M y', strtotime($m['month'].'-01'));
      ?>
      <div style="flex:1; display:flex; flex-direction:column; align-items:center; position:relative;"
           title="<?= $label ?>: <?= $m['cnt'] ?>">
        <div style="width:100%; background:var(--color-brand-blue); border-radius:3px 3px 0 0;
                    height:<?= $pct ?>%; min-height:4px; margin-top:auto; opacity:.85;"></div>
        <div style="font-size:10px; color:var(--color-text-muted); text-align:center;
                    position:absolute; bottom:-20px; white-space:nowrap;"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex; margin-top:4px;">
      <?php foreach ($monthly as $m): ?>
      <div style="flex:1; text-align:center; font-size:11px; font-weight:600; color:var(--color-text-secondary);">
        <?= $m['cnt'] ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
