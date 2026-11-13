<?php
/**
 * NotificationController
 *
 * Handles:
 *   - GET  ?mark=<id>           — mark one as read, redirect back
 *   - GET  ?filter=all|unread   — display filtered list
 *   - POST action=mark_all      — mark all as read, redirect
 *
 * This controller replicates the exact logic from pages/notifications.php.
 */
class NotificationController
{
    private NotificationModel $model;

    public function __construct()
    {
        $this->model = new NotificationModel();
    }

    public function index(): void
    {
        // ── Auth ──────────────────────────────────────────────
        require_login();
        $user = current_user();

        // ── Mark single notification as read (GET ?mark=) ──────
        if (isset($_GET['mark'])) {
            $this->handleMarkOne($user);
            // handleMarkOne redirects and never returns
        }

        // ── Mark ALL as read (POST action=mark_all) ────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleMarkAll($user);
            // handleMarkAll redirects and never returns
        }

        // ── Load notifications for display ─────────────────────
        $filter  = trim($_GET['filter'] ?? 'all');
        $allowed = ['all', 'unread'];
        if (!in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        $notifications = $this->model->getByAccountId($user['account_id'], $filter);
        $unread_total  = $this->model->countUnread($user['account_id']);

        // ── View variables ──────────────────────────────────────
        $page_title = 'Notifications';
        $active_nav = 'notifications';

        // ── Render ──────────────────────────────────────────────
        require __DIR__ . '/../Views/notifications/index.php';
    }

    /**
     * Mark one notification as read, then redirect.
     * Never returns.
     */
    private function handleMarkOne(array $user): never
    {
        $nid = (int)$_GET['mark'];
        if ($nid > 0) {
            $this->model->markAsRead($nid, $user['account_id']);
        }

        // Redirect back to referrer or to notifications page
        $back = $_GET['back'] ?? '';
        if ($back && filter_var(BASE_URL . $back, FILTER_VALIDATE_URL)) {
            redirect_with_flash(BASE_URL . $back, 'success', '');
        }

        header('Location: ' . BASE_URL . '/pages/notifications.php');
        exit;
    }

    /**
     * Mark all unread notifications as read, then redirect.
     * Never returns.
     */
    private function handleMarkAll(array $user): never
    {
        if (!verify_csrf()) {
            redirect_with_flash(
                BASE_URL . '/pages/notifications.php',
                'danger',
                'Security token mismatch.'
            );
        }

        if (($_POST['action'] ?? '') === 'mark_all') {
            $this->model->markAllAsRead($user['account_id']);
            redirect_with_flash(
                BASE_URL . '/pages/notifications.php',
                'success',
                'All notifications marked as read.'
            );
        }

        // If action was something else, just redirect silently
        header('Location: ' . BASE_URL . '/pages/notifications.php');
        exit;
    }
}
