<?php
/**
 * ErrorController
 *
 * Handles error pages (403, etc.)
 */

class ErrorController
{
    /**
     * 403 Access Denied page
     */
    public static function error403()
    {
        http_response_code(403);
        require __DIR__ . '/../Views/errors/403.php';
        exit;
    }
}
