<?php
/**
 * HTML <head> + sidebar + topnav opening
 * Set $page_title, $active_nav (and optionally $breadcrumbs) before including.
 */
require_once __DIR__ . '/auth.php';
require_login();
$user = current_user();
$flash = consume_flash();
$page_title  = $page_title  ?? 'Dashboard';
$active_nav  = $active_nav  ?? 'dashboard';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> — Vinamilk CDM</title>
  <meta name="robots" content="noindex,nofollow">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <!-- Chart.js (bundled, no build step) -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell">

  <!-- ====================================================
       Sidebar
       ==================================================== -->
  <aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
          <?= icon_svg('briefcase') ?>
        </div>
        <div class="sidebar-logo-text">
          <strong>Vinamilk</strong>
          <span>Contract Management</span>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">

      <?php if ($user['role'] === 'external'): ?>
      <!-- ================================================
           EXTERNAL (PARTNER) SIDEBAR
           ================================================ -->
      <?php
      // Compute pending proposal count for this partner
      $ext_pending_proposals = 0;
      try {
          $ext_cp_id = (int) db_query(
              "SELECT CounterpartyID FROM Contact WHERE AccountID = ? LIMIT 1",
              [$user['account_id']]
          )->fetchColumn();
          if ($ext_cp_id && db_table_exists('ContractProposal')) {
              $ext_pending_proposals = (int) db_query(
                  "SELECT COUNT(*) FROM ContractProposal
                   WHERE CounterpartyID = ? AND Status = 'Pending'",
                  [$ext_cp_id]
              )->fetchColumn();
          }
      } catch (Throwable) {}
      ?>

      <div class="nav-section">
        <a href="<?= BASE_URL ?>/index.php"
           class="nav-item <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
          <?= icon_svg('layout-dashboard') ?>
          Dashboard
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-section-label">My Company</div>
        <a href="<?= BASE_URL ?>/pages/partner_portal.php"
           class="nav-item <?= $active_nav === 'partner_portal' ? 'active' : '' ?>">
          <?= icon_svg('file-plus') ?>
          Contract Proposals
          <?php if ($ext_pending_proposals > 0): ?>
          <span style="margin-left:auto;background:var(--color-warning,#d97706);color:#fff;
                       font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;
                       min-width:18px;text-align:center;"><?= $ext_pending_proposals ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/pages/contracts.php"
           class="nav-item <?= $active_nav === 'contracts' ? 'active' : '' ?>">
          <?= icon_svg('file-text') ?>
          Our Contracts
        </a>
        <a href="<?= BASE_URL ?>/pages/documents.php"
           class="nav-item <?= $active_nav === 'documents' ? 'active' : '' ?>">
          <?= icon_svg('folder') ?>
          Documents
        </a>
      </div>

      <?php else: ?>
      <!-- ================================================
           INTERNAL (STAFF / ADMIN) SIDEBAR
           ================================================ -->
      <div class="nav-section">
        <a href="<?= BASE_URL ?>/index.php"
           class="nav-item <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
          <?= icon_svg('layout-dashboard') ?>
          Dashboard
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-section-label">Contracts</div>
        <a href="<?= BASE_URL ?>/pages/contracts.php"
           class="nav-item <?= $active_nav === 'contracts' ? 'active' : '' ?>">
          <?= icon_svg('file-text') ?>
          Contracts
        </a>
        <a href="<?= BASE_URL ?>/pages/documents.php"
           class="nav-item <?= $active_nav === 'documents' ? 'active' : '' ?>">
          <?= icon_svg('folder') ?>
          Documents
        </a>
        <?php
        $sidebar_pending_cp = 0;
        try {
            if ($user['role'] === 'admin') {
                $sidebar_pending_cp = (int) db_query(
                    "SELECT COUNT(*) FROM Counterparty WHERE ApprovalStatus = 'Pending'"
                )->fetchColumn();
            }
        } catch (Throwable) {}
        ?>
        <a href="<?= BASE_URL ?>/pages/counterparties.php"
           class="nav-item <?= $active_nav === 'counterparties' ? 'active' : '' ?>">
          <?= icon_svg('users') ?>
          Counterparties
          <?php if ($sidebar_pending_cp > 0): ?>
          <span style="margin-left:auto;background:var(--color-warning,#d97706);color:#fff;
                       font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;
                       min-width:18px;text-align:center;"><?= $sidebar_pending_cp ?></span>
          <?php endif; ?>
        </a>
      </div>

      <div class="nav-section">
        <div class="nav-section-label">Workflow</div>
        <?php
        $sidebar_pending_proposals = 0;
        $sidebar_pending_reviews   = 0;
        try {
            if ($user['role'] === 'admin') {
                $sidebar_pending_reviews = (int) db_query(
                    "SELECT COUNT(*) FROM Approval WHERE ApprovalStatus = 'Pending'"
                )->fetchColumn();
                $sidebar_pending_reviews += (int) db_query(
                    "SELECT COUNT(*) FROM Review WHERE ReviewStatus = 'Pending'"
                )->fetchColumn();
                $sidebar_pending_reviews += (int) db_query(
                    "SELECT COUNT(*) FROM Counterparty WHERE ApprovalStatus = 'Pending'"
                )->fetchColumn();
                $sidebar_pending_reviews += (int) db_query(
                    "SELECT COUNT(*) FROM ContractProposal WHERE Status = 'PartnerApproved'"
                )->fetchColumn();
            } elseif ($user['role'] === 'staff' && $user['staff_id']) {
                $sidebar_pending_proposals = (int) db_query(
                    "SELECT COUNT(*) FROM ContractProposal
                     WHERE ProposedByStaffID = ? AND Status IN ('Pending','PartnerApproved')",
                    [$user['staff_id']]
                )->fetchColumn();
                $sidebar_pending_reviews = (int) db_query(
                    "SELECT COUNT(*) FROM Review
                     WHERE ReviewerAccountID = ? AND ReviewStatus = 'Pending'",
                    [$user['account_id']]
                )->fetchColumn();
            }
        } catch (Throwable) {}
        ?>
        <?php if ($user['role'] === 'staff'): ?>
        <a href="<?= BASE_URL ?>/pages/requests.php"
           class="nav-item <?= $active_nav === 'requests' ? 'active' : '' ?>">
          <?= icon_svg('file-plus') ?>
          Contract Requests
          <?php if ($sidebar_pending_proposals > 0): ?>
          <span style="margin-left:auto;background:var(--color-warning,#d97706);color:#fff;
                       font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;
                       min-width:18px;text-align:center;"><?= $sidebar_pending_proposals ?></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/pages/approvals.php"
           class="nav-item <?= $active_nav === 'approvals' ? 'active' : '' ?>">
          <?= icon_svg('check-circle') ?>
          Approvals &amp; Reviews
          <?php if ($sidebar_pending_reviews > 0): ?>
          <span style="margin-left:auto;background:var(--color-danger,#dc2626);color:#fff;
                       font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;
                       min-width:18px;text-align:center;"><?= $sidebar_pending_reviews ?></span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/pages/reports.php"
           class="nav-item <?= $active_nav === 'reports' ? 'active' : '' ?>">
          <?= icon_svg('bar-chart') ?>
          Reports
        </a>
      </div>

      <?php if ($user['role'] === 'admin'): ?>
      <div class="nav-section">
        <div class="nav-section-label">Administration</div>
        <a href="<?= BASE_URL ?>/pages/settings.php"
           class="nav-item <?= $active_nav === 'settings' ? 'active' : '' ?>">
          <?= icon_svg('settings') ?>
          Settings
        </a>
      </div>
      <?php endif; ?>

      <?php endif; /* end role switch */ ?>

    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="avatar" title="<?= e($user['name']) ?>"><?= e($user['initials']) ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= e($user['name']) ?></div>
          <div class="sidebar-user-role">
            <?= e(role_label($user['role'])) ?>
            <?php if ($user['dept']): ?> · <?= e($user['dept']) ?><?php endif; ?>
          </div>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="sidebar-signout" title="Sign out of your account">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Sign out
      </a>
    </div>
  </aside>

  <!-- ====================================================
       Main wrapper
       ==================================================== -->
  <div class="main-wrapper">

    <!-- Top navigation -->
    <header class="topnav">
      <button class="icon-btn" id="sidebarToggle" style="display:none" aria-label="Toggle menu">
        <?= icon_svg('menu') ?>
      </button>

      <div class="topnav-title">
        <?php if ($breadcrumbs): ?>
          <div class="breadcrumb">
            <span class="breadcrumb-item"><a href="<?= BASE_URL ?>">Home</a></span>
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
              <span class="breadcrumb-sep">›</span>
              <?php if ($i < count($breadcrumbs) - 1): ?>
                <span class="breadcrumb-item"><a href="<?= e($crumb['url'] ?? '#') ?>"><?= e($crumb['label']) ?></a></span>
              <?php else: ?>
                <span class="breadcrumb-item current"><?= e($crumb['label']) ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <?= e($page_title) ?>
        <?php endif; ?>
      </div>

      <!-- Global search -->
      <div class="topnav-search">
        <svg class="topnav-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="search" placeholder="Search contracts, counterparties…"
               id="globalSearch" autocomplete="off">
      </div>

      <!-- Notification bell — unread count from Notification table -->
      <?php
      $unread_count = 0;
      try {
          $unread_count = (int) db_query(
              "SELECT COUNT(*) FROM Notification WHERE AccountID = ? AND IsRead = 0",
              [$user['account_id']]
          )->fetchColumn();
      } catch (Throwable) {}
      ?>
      <a href="<?= $user['role'] === 'external' ? BASE_URL . '/pages/partner_portal.php' : BASE_URL . '/pages/approvals.php' ?>"
         class="icon-btn" aria-label="Notifications">
        <?= icon_svg('bell') ?>
        <?php if ($unread_count > 0): ?>
        <span class="notif-dot"></span>
        <?php endif; ?>
      </a>

      <!-- User menu dropdown -->
      <div class="user-menu" id="userMenuWrap">
        <button class="user-menu-trigger" id="userMenuTrigger"
                aria-haspopup="true" aria-expanded="false" type="button">
          <div class="avatar sm"><?= e($user['initials']) ?></div>
          <div class="user-menu-info">
            <div class="user-menu-name"><?= e($user['name']) ?></div>
            <div class="user-menu-role"><?= e(role_label($user['role'])) ?></div>
          </div>
          <span class="user-menu-caret">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </span>
        </button>
        <div class="user-dropdown" id="userDropdown" role="menu">
          <div class="user-dropdown-header">
            <div class="user-dropdown-name"><?= e($user['name']) ?></div>
            <div class="user-dropdown-email"><?= e($user['email']) ?></div>
            <?php if ($user['dept']): ?>
            <div class="user-dropdown-dept"><?= e($user['dept']) ?><?php if ($user['position']): ?> · <?= e($user['position']) ?><?php endif; ?></div>
            <?php endif; ?>
          </div>
          <a href="<?= BASE_URL ?>/logout.php" class="user-dropdown-item danger" role="menuitem">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Sign out
          </a>
        </div>
      </div>
    </header>
    <?php if ($flash): ?>
    <div style="padding:0 24px; margin-top:10px;">
      <div class="alert alert-<?= e($flash['type']) ?>" id="flashAlert">
        <?= $flash['type'] === 'success' ? icon_svg('check-circle') : icon_svg('alert-circle') ?>
        <span><?= e($flash['message']) ?></span>
        <button class="alert-dismiss" onclick="this.parentElement.remove()" aria-label="Dismiss">✕</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Page content -->
    <main class="page-content">
<?php

/**
 * Inline SVG icon helper
 */
function icon_svg(string $name): string {
    $icons = [
        'briefcase'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
        'layout-dashboard'=> '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
        'file-plus'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
        'file-text'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'folder'          => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
        'check-circle'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        'bar-chart'       => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'settings'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
        'log-out'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'bell'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'menu'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
        'plus'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'eye'             => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'edit'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'alert-circle'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        'info'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'x'               => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'arrow-left'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
        'user'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'users'           => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'calendar'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'dollar-sign'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'clock'           => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'download'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'upload'          => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
    ];
    return $icons[$name] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
}
