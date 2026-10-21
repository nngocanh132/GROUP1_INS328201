<?php
/**
 * Contracts List — queries Contract table (vinamilk_clm)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Contracts';
$active_nav = 'contracts';

require_login();
$user = current_user();

$q             = trim($_GET['q']      ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_type   = (int)($_GET['type']  ?? 0);
$page          = max(1, (int)($_GET['page'] ?? 1));

$allowed_statuses = ['Draft','PendingReview','PendingApproval','RevisionRequested','Approved','Active','Closed'];
if (!in_array($filter_status, $allowed_statuses)) $filter_status = '';

$where  = ['1=1'];
$params = [];

// ---------------------------------------------------------------
// External users: strict company-level isolation
// We join Contact using the exact AccountID so a user cannot see
// contracts belonging to a different company even by changing
// URL parameters. Only contracts for *their* CounterpartyID
// (verified via their AccountID in the Contact table) are shown.
// ---------------------------------------------------------------
if ($user['role'] === 'external') {
    // First resolve the counterparty for this specific account
    $ext_counterparty_id = (int) db_query(
        "SELECT CounterpartyID FROM Contact WHERE AccountID = ? LIMIT 1",
        [$user['account_id']]
    )->fetchColumn();

    if (!$ext_counterparty_id) {
        // Account has no linked counterparty — show nothing
        $where[] = '0=1';
    } else {
        // Lock to exactly this counterparty — cannot be overridden by GET params
        $where[]  = 'c.CounterpartyID = ?';
        $params[] = $ext_counterparty_id;
    }
}

if ($q !== '') {
    $where[]  = '(c.ContractTitle LIKE ? OR c.ContractCode LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%";
}
if ($filter_status !== '') { $where[] = 'c.Status = ?'; $params[] = $filter_status; }
if ($filter_type > 0)      { $where[] = 'c.ContractTypeID = ?'; $params[] = $filter_type; }

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT c.ContractID, c.ContractCode, c.ContractTitle, c.Status,
           c.ContractValue, c.Currency, c.StartDate, c.EndDate,
           c.CreatedAt, c.UpdatedAt,
           ct.TypeName, cp.CompanyName AS CounterpartyName,
           s.FullName AS OwnerName, d2.DepartmentName
    FROM Contract c
    JOIN ContractType ct ON ct.ContractTypeID = c.ContractTypeID
    JOIN Counterparty cp ON cp.CounterpartyID = c.CounterpartyID
    JOIN Staff s          ON s.StaffID         = c.OwnerStaffID
    JOIN Department d2    ON d2.DepartmentID   = c.DepartmentID
    WHERE $where_sql
    ORDER BY c.UpdatedAt DESC
";

$result   = paginate($sql, $params, $page, DEFAULT_PAGE_SIZE);
$contracts = $result['rows'];
$contract_types = get_contract_types();

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Contracts</h1>
    <p><?= $user['role'] === 'external' ? 'Contracts associated with your company.' : 'All contracts in the system.' ?></p>
  </div>
  <?php if ($user['role'] === 'staff'): ?>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/create_request.php" class="btn btn-primary">
      <?= icon_svg('plus') ?> New Contract
    </a>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <form id="filterForm" method="GET">
    <div class="table-toolbar">
      <div class="search-field">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="search" name="q" value="<?= e($q) ?>"
               placeholder="<?= $user['role'] === 'external' ? 'Search by title or code…' : 'Search by title, code, counterparty…' ?>" autocomplete="off">
      </div>
      <select name="status" class="filter-select" data-autosubmit>
        <option value="">All Statuses</option>
        <?php foreach ($allowed_statuses as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= status_badge($s) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type" class="filter-select" data-autosubmit>
        <option value="">All Types</option>
        <?php foreach ($contract_types as $ct): ?>
        <option value="<?= $ct['ContractTypeID'] ?>" <?= $filter_type===(int)$ct['ContractTypeID']?'selected':'' ?>>
          <?= e($ct['TypeName']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($q || $filter_status || $filter_type): ?>
      <a href="<?= BASE_URL ?>/pages/contracts.php" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
      <div class="toolbar-spacer"></div>
      <span style="font-size:12px; color:var(--color-text-muted);"><?= $result['total'] ?> record<?= $result['total']!==1?'s':'' ?></span>
    </div>
  </form>

  <?php if (empty($contracts)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><?= icon_svg('file-text') ?></div>
    <h3><?= $q||$filter_status||$filter_type ? 'No matching contracts' : 'No contracts yet' ?></h3>
    <p><?= $q||$filter_status||$filter_type ? 'Try adjusting your search or filters.' : 'Create your first contract to get started.' ?></p>
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
          <th>Owner</th>
          <th>Value</th>
          <th>End Date</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($contracts as $c):
          $today    = new DateTime();
          $expiry   = new DateTime($c['EndDate']);
          $days_left= (int)$today->diff($expiry)->format('%r%a');
        ?>
        <tr>
          <td class="td-ref"><?= e($c['ContractCode']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $c['ContractID'] ?>"
               style="font-weight:600; color:var(--color-text-primary);">
              <?= e(mb_strimwidth($c['ContractTitle'], 0, 55, '…')) ?>
            </a>
            <div class="td-meta"><?= e($c['DepartmentName']) ?></div>
          </td>
          <td class="td-meta"><?= e($c['TypeName']) ?></td>
          <td><?= e($c['CounterpartyName']) ?></td>
          <td class="td-meta"><?= e($c['OwnerName']) ?></td>
          <td><?= money($c['ContractValue'], $c['Currency'] ?? 'VND') ?></td>
          <td class="td-meta">
            <?= fmt_date($c['EndDate']) ?>
            <?php if ($c['Status'] === 'Active' && $days_left >= 0 && $days_left <= 60): ?>
            <div style="font-size:11px; color:<?= $days_left<=30?'var(--color-danger)':'var(--color-warning)' ?>; font-weight:600;">
              <?= $days_left ?> day<?= $days_left!==1?'s':'' ?> left
            </div>
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

  <?php if ($result['pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">
      Showing <?= ($page-1)*DEFAULT_PAGE_SIZE+1 ?>–<?= min($page*DEFAULT_PAGE_SIZE,$result['total']) ?> of <?= $result['total'] ?>
    </span>
    <div class="page-btns">
      <?php $base = BASE_URL . '/pages/contracts.php?' . http_build_query(array_filter(['q'=>$q,'status'=>$filter_status,'type'=>$filter_type?:''])); ?>
      <a href="<?= $base ?>&page=<?= $page-1 ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">‹</a>
      <?php for ($p=1;$p<=$result['pages'];$p++): ?>
      <a href="<?= $base ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="<?= $base ?>&page=<?= $page+1 ?>" class="page-btn <?= $page>=$result['pages']?'disabled':'' ?>">›</a>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
