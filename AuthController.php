<?php
/**
 * AuthController
 *
 * Handles login page display and authentication POST.
 * Replicates the exact logic from login.php.
 */
class AuthController
{
    private AuthModel $model;

    public function __construct()
    {
        $this->model = new AuthModel();
    }

    public function login(): void
    {
        session_start_safe();

        // ── Already logged in? Redirect to dashboard ───────────
        if (is_logged_in()) {
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        }
