<?php
/**
 * View: Notifications — index
 *
 * Expected variables from NotificationController::index():
 *   $page_title       string
 *   $active_nav       string
 *   $notifications    array
 *   $unread_total     int
 *   $filter           string  ('all' or 'unread')
 *
 * Helper function for icon/class mapping (presentation logic only):
 */
function notif_meta(string $type): array {
    return match($type) {
        'Success' => ['check-circle', 'notif-success'],
        'Warning' => ['alert-circle',  'notif-warning'],
        'Info'    => ['info',           'notif-info'],
        default   => ['bell',           'notif-info'],
    };
}

include __DIR__ . '/../../../includes/layout_head.php';
?>

<style>
/* ── Notification page styles ── */
.notif-list { display: flex; flex-direction: column; gap: 0; }

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--color-border);
  transition: background var(--transition);
  position: relative;
}
.notif-item:last-child { border-bottom: none; }
.notif-item.unread { background: #f5f8ff; }
.notif-item:hover  { background: var(--color-accent-soft); }

.notif-icon-wrap {
  width: 36px; height: 36px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: 1px;
}
.notif-success { background: var(--color-success-bg);  color: var(--color-success); }
.notif-warning { background: var(--color-warning-bg);  color: var(--color-warning); }
.notif-info    { background: var(--color-info-bg);     color: var(--color-info); }
.notif-icon-wrap svg { width: 16px; height: 16px; }

.notif-body { flex: 1; min-width: 0; }
.notif-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: 3px;
}
.notif-msg {
  font-size: 12px;
  color: var(--color-text-secondary);
  line-height: 1.55;
  margin-bottom: 5px;
}
.notif-meta {
  font-size: 11px;
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.notif-contract-link {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-brand-blue);
  text-decoration: none;
}
.notif-contract-link:hover { text-decoration: underline; }

.notif-unread-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--color-brand-blue);
  flex-shrink: 0;
  margin-top: 6px;
}

.notif-mark-btn {
  flex-shrink: 0;
  font-size: 11px;
  color: var(--color-text-muted);
  text-decoration: none;
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  transition: background var(--transition), color var(--transition);
}
.notif-mark-btn:hover {
  background: var(--color-accent-soft);
  color: var(--color-brand-blue);
}

/* filter pills */
.notif-filter-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  border-bottom: 1px solid var(--color-border);
}
.notif-pill {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  transition: background var(--transition), color var(--transition);
  border: 1.5px solid transparent;
}
.notif-pill.active {
  background: var(--color-brand-blue);
  color: #fff;
}
.notif-pill:not(.active) {
  color: var(--color-text-secondary);
  border-color: var(--color-border);
  background: var(--color-surface);
}
.notif-pill:not(.active):hover {
  border-color: var(--color-brand-blue);
  color: var(--color-brand-blue);
}
</style>

<div class="page-header">
  <div class="page-header-title">
    <h1>Notifications</h1>
    <p>
      Your activity feed —
      <?php if ($unread_total > 0): ?>
        <strong style="color:var(--color-brand-blue);"><?= $unread_total ?> unread</strong>
      <?php else: ?>
        all caught up
      <?php endif; ?>
    </p>
  </div>
  <?php if ($unread_total > 0): ?>
  <div class="page-header-actions">
    <form method="POST" action="<?= BASE_URL ?>/pages/notifications.php" style="display:inline;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="mark_all">
      <button type="submit" class="btn btn-secondary btn-sm">
        <?= icon_svg('check-circle') ?> Mark all as read
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<div class="card">

  <!-- Filter bar -->
  <div class="notif-filter-bar">
    <a href="<?= BASE_URL ?>/pages/notifications.php"
       class="notif-pill <?= $filter === 'all' ? 'active' : '' ?>">
      All
    </a>
    <a href="<?= BASE_URL ?>/pages/notifications.php?filter=unread"
       class="notif-pill <?= $filter === 'unread' ? 'active' : '' ?>">
      Unread
      <?php if ($unread_total > 0): ?>
      <span style="margin-left:4px;background:var(--color-danger);color:#fff;
                   font-size:9.5px;font-weight:700;border-radius:8px;padding:1px 5px;">
        <?= $unread_total ?>
      </span>
      <?php endif; ?>
    </a>
  </div>

  <?php if (empty($notifications)): ?>
  <!-- Empty state -->
  <div class="empty-state">
    <div class="empty-state-icon">
      <?= icon_svg('bell') ?>
    </div>
    <h3>
      <?= $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' ?>
    </h3>
    <p>
      <?php if ($filter === 'unread'): ?>
        You're all caught up. Switch to "All" to see your history.
      <?php else: ?>
        Activity related to your contracts and approvals will appear here.
      <?php endif; ?>
    </p>
    <?php if ($filter === 'unread'): ?>
    <a href="<?= BASE_URL ?>/pages/notifications.php" class="btn btn-secondary btn-sm">
      View all notifications
    </a>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <div class="notif-list">
    <?php foreach ($notifications as $n):
      [$icon_name, $icon_cls] = notif_meta($n['NotificationType'] ?? 'Info');
      $is_unread = !(bool)$n['IsRead'];
    ?>
    <div class="notif-item <?= $is_unread ? 'unread' : '' ?>">

      <!-- Type icon -->
      <div class="notif-icon-wrap <?= $icon_cls ?>">
        <?= icon_svg($icon_name) ?>
      </div>

      <!-- Body -->
      <div class="notif-body">
        <div class="notif-title"><?= e($n['Title'] ?? '') ?></div>
        <?php if ($n['Message'] ?? ''): ?>
        <div class="notif-msg"><?= e($n['Message']) ?></div>
        <?php endif; ?>
        <div class="notif-meta">
          <span><?= fmt_datetime($n['CreatedAt']) ?></span>
          <?php if ($n['ContractID'] && $n['ContractCode']): ?>
          <a href="<?= BASE_URL ?>/pages/contract_detail.php?id=<?= (int)$n['ContractID'] ?>"
             class="notif-contract-link">
            <?= e($n['ContractCode']) ?>
            <?php if ($n['ContractTitle']): ?>
            — <?= e(mb_strimwidth($n['ContractTitle'], 0, 40, '…')) ?>
            <?php endif; ?>
          </a>
          <?php endif; ?>
          <?php if ($n['ReadAt'] && !$is_unread): ?>
          <span style="color:var(--color-text-muted);">
            Read <?= fmt_datetime($n['ReadAt']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Unread dot + mark-as-read -->
      <?php if ($is_unread): ?>
      <div class="notif-unread-dot"></div>
      <a href="<?= BASE_URL ?>/pages/notifications.php?mark=<?= (int)$n['NotificationID'] ?>"
         class="notif-mark-btn" title="Mark as read">
        <?= icon_svg('check-circle') ?>
      </a>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>
  </div>

  <?php if (count($notifications) >= 100): ?>
  <div style="padding:12px 18px;font-size:12px;color:var(--color-text-muted);text-align:center;
              border-top:1px solid var(--color-border);">
    Showing the 100 most recent notifications.
  </div>
  <?php endif; ?>

  <?php endif; ?>

</div>

<?php include __DIR__ . '/../../../includes/layout_foot.php'; ?>
