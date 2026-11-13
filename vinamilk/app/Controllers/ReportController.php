<?php
/**
 * ReportController
 *
 * Handles the Reports page request:
 *   1. Enforces authentication and role access (admin / staff only).
 *   2. Instantiates ReportModel and fetches all required data.
 *   3. Passes data to the View.
 *
 * This controller replicates the original logic from pages/reports.php
 * exactly — no business rules have been changed.
 *
 * Depends on:
 *   includes/config.php   — constants (BASE_URL, BASE_PATH …)
 *   includes/auth.php     — require_login(), current_user()
 *   includes/helpers.php  — redirect_with_flash()
 *   app/Models/ReportModel.php
 */
class ReportController
{
    public function index(): void
    {
        // ── Auth ──────────────────────────────────────────────
        require_login();
        if (current_user()['role'] === 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
        }

        // ── Data ──────────────────────────────────────────────
        $model = new ReportModel();

        $by_status   = $model->getByStatus();
        $by_type     = $model->getByType();
        $by_dept     = $model->getByDepartment();
        $top_parties = $model->getTopCounterparties();
        $monthly     = $model->getMonthlyCreation();
        $totals      = $model->getTotals();
        $sla         = $model->getAverageReviewSla();

        // ── View variables (set before including View) ─────────
        $page_title = 'Reports';
        $active_nav = 'reports';

        // ── Render ────────────────────────────────────────────
        require __DIR__ . '/../Views/reports/index.php';
    }
}
