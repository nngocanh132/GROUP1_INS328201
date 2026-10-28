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
// ── Quick Login availability (dev only) ─────────────────
        $is_dev = in_array(
            $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost',
            ['localhost', '127.0.0.1', '::1'],
            true
        );

        // ── POST: process login ─────────────────────────────────
        $error    = '';
        $email    = '';
        $remember = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handleLoginPost();
            $error    = $result['error'];
            $email    = $result['email'];
            $remember = $result['remember'];
            // If login succeeds, handleLoginPost() redirects and never returns
        }

        // ── Render login view ────────────────────────────────────
        require __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Handle POST login submission.
     * On success: redirects to index.php and never returns.
     * On failure: returns error details.
     *
     * @return array ['error' => string, 'email' => string, 'remember' => bool]
     */
    private function handleLoginPost(): array
    {
        $email    = trim($_POST['email']    ?? '');
        $password =       $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (!$email || !$password) {
            return [
                'error'    => 'Please enter your email address and password.',
                'email'    => $email,
                'remember' => $remember,
            ];
        }

        $row = $this->model->findByEmail($email);

        if (!$row) {
            return [
                'error'    => 'Invalid email or password. Please try again.',
                'email'    => $email,
                'remember' => $remember,
            ];
        }
