<?php
/**
 * Application Configuration
 * Vinamilk Contract & Document Lifecycle Management System
 */

// ---------------------------------------------------------------
// Environment
// ---------------------------------------------------------------
define('APP_NAME',    'Vinamilk CDM');
define('APP_TITLE',   'Contract & Document Management');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/GROUP1_INS328201/vinamilk');
define('BASE_PATH',   dirname(__DIR__));

// ---------------------------------------------------------------
// Database
// ---------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'vinamilk_clm');
define('DB_USER', 'root');
define('DB_PASS', '');          // Change for non-default XAMPP setups
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------
// File Upload
// ---------------------------------------------------------------
// Store outside public web root when possible; falls back to storage/ dir
define('UPLOAD_DIR',      BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents');
define('UPLOAD_MAX_MB',   10);
define('UPLOAD_ALLOWED',  ['application/pdf', 'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'image/png', 'image/jpeg']);
define('UPLOAD_EXT_ALLOWED', ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg']);

// ---------------------------------------------------------------
// Session
// ---------------------------------------------------------------
define('SESSION_NAME',    'vmcdm_session');
define('SESSION_LIFETIME', 7200);  // 2 hours

// ---------------------------------------------------------------
// Security
// ---------------------------------------------------------------
define('CSRF_TOKEN_NAME', 'csrf_token');

// ---------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------
define('DEFAULT_PAGE_SIZE', 15);

// ---------------------------------------------------------------
// Error display (set false in production)
// ---------------------------------------------------------------
define('DEV_MODE', true);

if (DEV_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
