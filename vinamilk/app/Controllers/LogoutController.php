<?php
/**
 * LogoutController
 *
 * Handles user logout: destroys session and redirects to login page.
 * Replicates the exact logic from logout.php.
 */
class LogoutController
{
    public function logout(): void
    {
        session_start_safe();
        
        if (is_logged_in()) {
            $user = current_user();
            audit('LOGOUT', 'Account', $user['account_id']);
        }
        
        logout_user();
        
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
