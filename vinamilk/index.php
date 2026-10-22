<?php
/**
 * Dashboard
 *
 * Renders two completely different dashboards based on role:
 *   external → Partner Dashboard  (company contracts, pending proposals, notifications)
 *   staff / admin → Internal Dashboard  (full KPIs, charts, activity feed)
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = 'Dashboard';
$active_nav = 'dashboard';
require_login();
$user = current_user();

// ================================================================
//  EXTERNAL USER — fetch partner-specific data only
// ================================================================
if ($user['role'] === 'external') {

    // Resolve this user's counterparty (immutable, session-bound)
    $ext_contact = db_query(
        "SELECT c.CounterpartyID, c.FullName, c.JobTitle,
                cp.CompanyName, cp.CounterpartyCode, cp.CounterpartyType,
                cp.ApprovalStatus AS CompanyStatus
         FROM Contact c
         JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
         WHERE c.AccountID = ?
         LIMIT 1",
        [$user['account_id']]
    )->fetch();

    $ext_cp_id = $ext_contact ? (int)$ext_contact['CounterpartyID'] : 0;

    // Pending proposals for this company
    $ext_pending_proposals = [];
    $ext_all_proposals     = [];
    $ext_contracts         = [];
    $ext_contract_stats    = [];
    $ext_notifications     = [];

    if ($ext_cp_id) {
        if (db_table_exists('ContractProposal')) {
            $ext_pending_proposals = db_query(
                "SELECT p.*, ct.TypeName, s.FullName AS ProposerName
                 FROM ContractProposal p
                 JOIN ContractType ct ON ct.ContractTypeID = p.ContractTypeID
                 JOIN Staff s         ON s.StaffID         = p.ProposedByStaffID
                 WHERE p.CounterpartyID = ? AND p.Status = 'Pending'
                 ORDER BY p.CreatedAt DESC",
                [$ext_cp_id]
            )->fetchAll();

            $ext_all_proposals = db_query(
                "SELECT p.Status, COUNT(*) AS cnt
                 FROM ContractProposal p
                 WHERE p.CounterpartyID = ?
                 GROUP BY p.Status",
                [$ext_cp_id]
            )->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        $ext_contracts = db_query(
            "SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
                    c.ContractValue, c.Currency, c.StartDate, c.EndDate, c.UpdatedAt,
                    ct.TypeName
             FROM Contract c
             JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
             WHERE c.CounterpartyID = ?
             ORDER BY c.UpdatedAt DESC
             LIMIT 8",
            [$ext_cp_id]
        )->fetchAll();

        $ext_contract_stats = db_query(
            "SELECT Status, COUNT(*) AS cnt FROM Contract
             WHERE CounterpartyID = ? GROUP BY Status",
            [$ext_cp_id]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $ext_notifications = db_query(
            "SELECT Title, Message, NotificationType, CreatedAt, IsRead
             FROM Notification
             WHERE AccountID = ?
             ORDER BY CreatedAt DESC
             LIMIT 8",
            [$user['account_id']]
        )->fetchAll();

        // Mark all as read on dashboard visit
        db_query(
            "UPDATE Notification SET IsRead = 1, ReadAt = NOW()
             WHERE AccountID = ? AND IsRead = 0",
            [$user['account_id']]
        );
    }

    $ext_total_contracts  = array_sum($ext_contract_stats);
    $ext_active_contracts = (int)($ext_contract_stats['Active'] ?? 0);
    $ext_pending_count    = count($ext_pending_proposals);
    $ext_approved_count   = (int)($ext_all_proposals['AdminApproved'] ?? 0)
                          + (int)($ext_all_proposals['ContractCreated'] ?? 0);

    include __DIR__ . '/includes/layout_head.php';
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

    <?php
    include __DIR__ . '/includes/layout_foot.php';
    exit;
}

// ================================================================
//  INTERNAL USERS (staff / admin) — original dashboard
// ================================================================

// ── Metrics ──────────────────────────────────────────────────
$metrics = db_query("
    SELECT
        COUNT(*)                              AS total,
        SUM(Status = 'Draft')                 AS draft,
        SUM(Status = 'PendingReview')         AS pending_review,
        SUM(Status = 'PendingApproval')       AS pending_approval,
        SUM(Status = 'RevisionRequested')     AS revision,
        SUM(Status = 'Approved')              AS approved,
        SUM(Status = 'Active')                AS active_count,
        SUM(Status = 'Closed')                AS closed
    FROM Contract
")->fetch();

$expiring_soon = (int) db_query("
    SELECT COUNT(*) FROM Contract
    WHERE Status = 'Active'
      AND EndDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();

$pending_approvals = (int) db_query(
    "SELECT COUNT(*) FROM Approval WHERE ApprovalStatus = 'Pending'"
)->fetchColumn();

// ── Chart data ────────────────────────────────────────────────
$by_type_rows = db_query("
    SELECT ct.TypeName, COUNT(c.ContractID) AS cnt
    FROM Contract c
    JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
    GROUP BY ct.TypeName ORDER BY cnt DESC
")->fetchAll();

$monthly_rows = db_query("
    SELECT DATE_FORMAT(CreatedAt,'%b %Y') AS month_label,
           DATE_FORMAT(CreatedAt,'%Y-%m') AS month_key,
           COUNT(*) AS cnt
    FROM Contract
    WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 11 MONTH)
    GROUP BY month_key, month_label ORDER BY month_key ASC
")->fetchAll();

// ── Recent contracts ──────────────────────────────────────────
$recent_contracts = db_query("
    SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
           c.EndDate, c.UpdatedAt, ct.TypeName,
           cp.CompanyName AS CounterpartyName,
           s.FullName AS OwnerName, d.DepartmentName
    FROM Contract c
    JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
    JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
    JOIN Staff s          ON s.StaffID         = c.OwnerStaffID
    JOIN Department d     ON d.DepartmentID    = c.DepartmentID
    ORDER BY c.UpdatedAt DESC LIMIT 8
")->fetchAll();

// ── Recent activity ───────────────────────────────────────────
$activity = db_query("
    SELECT al.Action, al.EntityName, al.EntityID, al.CreatedAt,
           COALESCE(s.FullName, a.Email) AS ActorName
    FROM AuditLog al
    LEFT JOIN Account a ON a.AccountID = al.AccountID
    LEFT JOIN Staff s   ON s.AccountID = al.AccountID
    ORDER BY al.CreatedAt DESC LIMIT 5
")->fetchAll();

// ── JSON for charts ───────────────────────────────────────────
$status_labels = ['Active','Pending Review','Pending Approval','Revision','Approved','Draft','Closed'];
$status_values = [
    (int)$metrics['active_count'],
    (int)$metrics['pending_review'],
    (int)$metrics['pending_approval'],
    (int)$metrics['revision'],
    (int)$metrics['approved'],
    (int)$metrics['draft'],
    (int)$metrics['closed'],
];
$status_colors = ['#1761a8','#d97706','#ea6f1a','#7c3aed','#12a150','#c8d3e0','#8b9db5'];

$type_labels  = array_column($by_type_rows, 'TypeName');
$type_values  = array_map('intval', array_column($by_type_rows, 'cnt'));
$month_labels = array_column($monthly_rows, 'month_label');
$month_values = array_map('intval', array_column($monthly_rows, 'cnt'));

include __DIR__ . '/includes/layout_head.php';
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

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
