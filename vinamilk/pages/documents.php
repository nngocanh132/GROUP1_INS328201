<?php
/**
 * Documents page — Document + ContractVersion tables (vinamilk_clm)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$page_title = 'Documents';
$active_nav = 'documents';
require_login();
$user = current_user();

// ── Filters ───────────────────────────────────────────────────
$q            = trim($_GET['q']    ?? '');
$filter_type  = trim($_GET['type'] ?? '');
$filter_cid   = (int)($_GET['contract'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));

$doc_type_map = [
    'MainContract'   => 'Main Contract',
    'Appendix'       => 'Appendix',
    'CargoInsurance' => 'Cargo Insurance',
    'FoodSafetyCert' => 'Food Safety Cert',
    'BankGuarantee'  => 'Bank Guarantee',
    'Other'          => 'Other',
];

$allowed_types = array_keys($doc_type_map);
if (!in_array($filter_type, $allowed_types, true)) $filter_type = '';

// ── Query ─────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($user['role'] === 'external') {
    $where[]  = 'doc.IsVisibleToPartner = 1';
    $where[]  = 'co.AccountID = ?';
    $params[] = $user['account_id'];
}

if ($q !== '') {
    $where[]  = '(doc.DocumentName LIKE ? OR c.ContractCode LIKE ? OR c.ContractTitle LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}

if ($filter_type !== '') {
    $where[]  = 'doc.DocumentType = ?';
    $params[] = $filter_type;
}

if ($filter_cid > 0) {
    $where[]  = 'doc.ContractID = ?';
    $params[] = $filter_cid;
}

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT doc.DocumentID, doc.DocumentName, doc.DocumentType,
           doc.IsVisibleToPartner, doc.CreatedAt,
           c.ContractID, c.ContractCode, c.ContractTitle,
           cv.VersionNumber, cv.FileSize, cv.FilePath, cv.UploadedAt,
           COALESCE(s.FullName, a.Email) AS UploaderName
    FROM Document doc
    JOIN Contract c ON c.ContractID = doc.ContractID
    " . ($user['role'] === 'external' ? "JOIN Contact co ON co.CounterpartyID = c.CounterpartyID" : "") . "
    LEFT JOIN ContractVersion cv ON cv.DocumentID = doc.DocumentID AND cv.IsCurrent = 1
    LEFT JOIN Account a ON a.AccountID = cv.UploadedBy
    LEFT JOIN Staff s ON s.AccountID = cv.UploadedBy
    WHERE $where_sql
    ORDER BY doc.CreatedAt DESC
";

$result    = paginate($sql, $params, $page, DEFAULT_PAGE_SIZE);
$documents = $result['rows'];

// Summary counts by type (for the filter pills)
$type_counts = [];
try {
    $tc_rows = db_query("
        SELECT doc.DocumentType, COUNT(*) AS cnt
        FROM Document doc
        JOIN Contract c ON c.ContractID = doc.ContractID
        " . ($user['role'] === 'external' ? "JOIN Contact co ON co.CounterpartyID = c.CounterpartyID AND co.AccountID = " . (int)$user['account_id'] : "") . "
        " . ($user['role'] === 'external' ? "WHERE doc.IsVisibleToPartner = 1" : "") . "
        GROUP BY doc.DocumentType
    ")->fetchAll();
    foreach ($tc_rows as $r) $type_counts[$r['DocumentType']] = (int)$r['cnt'];
} catch (Throwable) {}

// Document type → CSS modifier
function doc_type_class(string $type): string {
    return match($type) {
        'MainContract'   => 'doc-icon-main',
        'Appendix'       => 'doc-icon-appendix',
        'CargoInsurance' => 'doc-icon-insurance',
        'FoodSafetyCert' => 'doc-icon-food',
        'BankGuarantee'  => 'doc-icon-bank',
        default          => 'doc-icon-other',
    };
}

include __DIR__ . '/../includes/layout_head.php';
?>

<div class="page-header">
  <div class="page-header-title">
    <h1>Documents</h1>
    <p>Contract documents and version history.</p>
  </div>
  <?php if ($user['role'] !== 'external'): ?>
  <div class="page-header-actions">
    <a href="<?= BASE_URL ?>/pages/upload_document.php" class="btn btn-primary">
      <?= icon_svg('upload') ?> Upload Document
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Type filter pills -->
<?php if (!empty($type_counts)): ?>
<div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px;">
  <a href="<?= BASE_URL ?>/pages/documents.php<?= $q ? '?q=' . urlencode($q) : '' ?>"
     class="btn btn-sm <?= $filter_type === '' ? 'btn-primary' : 'btn-secondary' ?>">
    All <span style="opacity:.7;">(<?= $result['total'] ?>)</span>
  </a>
  <?php foreach ($doc_type_map as $key => $label):
    if (empty($type_counts[$key])) continue;
    $active = $filter_type === $key;
    $href   = BASE_URL . '/pages/documents.php?' . http_build_query(array_filter(['q'=>$q,'type'=>$key]));
  ?>
  <a href="<?= $href ?>"
     class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-secondary' ?>">
    <?= e($label) ?> <span style="opacity:.7;">(<?= $type_counts[$key] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <form id="filterForm" method="GET">
    <input type="hidden" name="type" value="<?= e($filter_type) ?>">
    <div class="table-toolbar">
      <div class="search-field">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search by name, contract code or title…"
               autocomplete="off">
      </div>

      <?php if ($q || $filter_type || $filter_cid): ?>
      <a href="<?= BASE_URL ?>/pages/documents.php" class="btn btn-ghost btn-sm">Clear filters</a>
      <?php endif; ?>

      <div class="toolbar-spacer"></div>
      <span style="font-size:11.5px; color:var(--color-text-muted);">
        <?= $result['total'] ?> document<?= $result['total'] !== 1 ? 's' : '' ?>
      </span>
    </div>
  </form>

  <?php if (empty($documents)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
      </svg>
    </div>
    <h3><?= $q || $filter_type || $filter_cid ? 'No matching documents' : 'No documents yet' ?></h3>
    <p>
      <?php if ($q || $filter_type || $filter_cid): ?>
        Try adjusting your search or filters.
      <?php else: ?>
        Documents are attached to contracts. They will appear here once uploaded.
      <?php endif; ?>
    </p>
    <?php if ($q || $filter_type || $filter_cid): ?>
    <a href="<?= BASE_URL ?>/pages/documents.php" class="btn btn-secondary btn-sm">Clear filters</a>
    <?php endif; ?>
  </div>
  <?php else: ?>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Document</th>
          <th>Type</th>
          <th>Related Contract</th>
          <th>Version</th>
          <th>Size</th>
          <th>Uploaded By</th>
          <th>Date</th>
          <?php if ($user['role'] !== 'external'): ?><th>Visibility</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($documents as $doc):
          $type_cls   = doc_type_class($doc['DocumentType']);
          $type_label = $doc_type_map[$doc['DocumentType']] ?? $doc['DocumentType'];
        ?>
        <tr>
          <td>
            <div style="display:flex; align-items:center; gap:10px;">
              <div style="width:32px; height:32px; border-radius:var(--radius);
                          display:flex; align-items:center; justify-content:center;
                          flex-shrink:0;"
                   class="<?= $type_cls ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                  <line x1="16" y1="13" x2="8" y2="13"/>
                  <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
              </div>
              <div style="min-width:0;">
                <div style="font-size:12.5px; font-weight:600; color:var(--color-text-primary);
                            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px;">
                  <?= e($doc['DocumentName']) ?>
                </div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge <?= match($doc['DocumentType']) {
              'MainContract'   => 'badge-active',
              'Appendix'       => 'badge-review',
              'BankGuarantee'  => 'badge-approved',
              'FoodSafetyCert' => 'badge-approved',
              'CargoInsurance' => 'badge-pending',
              default          => 'badge-draft',
            } ?>">
              <?= e($type_label) ?>
            </span>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= $doc['ContractID'] ?>"
               class="td-ref" style="display:block; margin-bottom:1px;">
              <?= e($doc['ContractCode']) ?>
            </a>
            <span class="td-meta"><?= e(mb_strimwidth($doc['ContractTitle'], 0, 38, '…')) ?></span>
          </td>
          <td class="td-meta" style="font-weight:600;">
            <?= $doc['VersionNumber'] ? 'v' . $doc['VersionNumber'] : '—' ?>
          </td>
          <td class="td-meta">
            <?= $doc['FileSize'] ? number_format($doc['FileSize'] / 1024, 0) . ' KB' : '—' ?>
          </td>
          <td class="td-meta"><?= e($doc['UploaderName'] ?? '—') ?></td>
          <td class="td-meta"><?= fmt_date($doc['UploadedAt'] ?: $doc['CreatedAt']) ?></td>
          <?php if ($user['role'] !== 'external'): ?>
          <td>
            <?php if ($doc['IsVisibleToPartner']): ?>
            <span class="badge badge-approved" style="font-size:10px;">Partner</span>
            <?php else: ?>
            <span class="badge badge-draft" style="font-size:10px;">Internal</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
  <div class="pagination">
    <span class="pagination-info">
      Showing <?= ($page-1)*DEFAULT_PAGE_SIZE+1 ?>–<?= min($page*DEFAULT_PAGE_SIZE,$result['total']) ?>
      of <?= $result['total'] ?>
    </span>
    <div class="page-btns">
      <?php
      $base = BASE_URL . '/pages/documents.php?' . http_build_query(array_filter([
          'q' => $q, 'type' => $filter_type, 'contract' => $filter_cid ?: ''
      ]));
      ?>
      <a href="<?= $base ?>&page=<?= $page-1 ?>" class="page-btn <?= $page<=1?'disabled':'' ?>">‹</a>
      <?php for ($p=1; $p<=$result['pages']; $p++): ?>
      <a href="<?= $base ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="<?= $base ?>&page=<?= $page+1 ?>" class="page-btn <?= $page>=$result['pages']?'disabled':'' ?>">›</a>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<!-- Document type legend (internal users) -->
<?php if ($user['role'] !== 'external' && $result['total'] > 0): ?>
<div style="display:flex; gap:16px; flex-wrap:wrap; padding:14px 0 4px; font-size:11px; color:var(--color-text-muted);">
  <?php foreach ($doc_type_map as $key => $label): if (empty($type_counts[$key])) continue; ?>
  <?php
    $dot_colors = [
      'MainContract'   => '#1761a8',
      'Appendix'       => '#7c3aed',
      'CargoInsurance' => '#c26a00',
      'FoodSafetyCert' => '#12a150',
      'BankGuarantee'  => '#0d7a3a',
      'Other'          => '#94a3b5',
    ];
    $dot_color = $dot_colors[$key] ?? '#94a3b5';
  ?>
  <span style="display:flex; align-items:center; gap:5px;">
    <span style="width:8px; height:8px; border-radius:2px; flex-shrink:0; background:<?= $dot_color ?>; display:inline-block;"></span>
    <?= e($label) ?> (<?= $type_counts[$key] ?>)
  </span>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/layout_foot.php'; ?>
