<?php
/**
 * Contracts Listing View
 */

$page_title = 'Contracts';
$active_nav = 'contracts';
include __DIR__ . '/../../../includes/layout_head.php';
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
        <?php
        $allowed_statuses = ['Draft','PendingReview','PendingApproval','RevisionRequested','Approved','Active','Closed'];
        foreach ($allowed_statuses as $s): ?>
        <option value="<?= $s ?>" <?= $filter_status===$s?'selected':'' ?>><?= $s ?></option>
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
          $expiry   = $c['EndDate'] ? new DateTime($c['EndDate']) : null;
          $days_left= $expiry ? (int)$today->diff($expiry)->format('%r%a') : null;
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
            <?php if ($c['Status'] === 'Active' && $days_left !== null && $days_left >= 0 && $days_left <= 60): ?>
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

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
