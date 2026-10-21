<?php
/**
 * Download Document
 *
 * Serves a contract document file securely.
 * The files are stored in storage/documents/ which is protected by .htaccess
 * (Deny from all), so they cannot be accessed directly. This script enforces
 * access control before streaming the file.
 *
 * Access rules:
 *   Internal (staff/admin) : can download any document
 *   External               : can only download documents where IsVisibleToPartner = 1
 *                            AND the document belongs to their counterparty's contract
 *
 * GET params:
 *   ?doc=<DocumentID>
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_login();
$user = current_user();

$doc_id = (int)($_GET['doc'] ?? 0);
if (!$doc_id) {
    http_response_code(400);
    exit('Invalid document ID.');
}

// Load document + version + contract info in one query
$doc = db_query(
    "SELECT d.DocumentID, d.DocumentName, d.DocumentType, d.ContractID, d.IsVisibleToPartner,
            c.CounterpartyID, c.ContractCode,
            cv.FilePath, cv.FileSize, cv.VersionNumber
     FROM Document d
     JOIN Contract c         ON c.ContractID   = d.ContractID
     LEFT JOIN ContractVersion cv ON cv.DocumentID = d.DocumentID AND cv.IsCurrent = 1
     WHERE d.DocumentID = ?",
    [$doc_id]
)->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

// ---- Access control ----
if ($user['role'] === 'external') {
    // Must belong to their counterparty AND be partner-visible
    if (!$doc['IsVisibleToPartner']) {
        http_response_code(403);
        exit('Access denied.');
    }
    $allowed = db_query(
        "SELECT 1 FROM Contact WHERE AccountID = ? AND CounterpartyID = ?",
        [$user['account_id'], $doc['CounterpartyID']]
    )->fetchColumn();
    if (!$allowed) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// ---- Resolve physical path ----
// FilePath is stored as '/storage/documents/filename.ext'
// BASE_PATH is dirname(__DIR__) = the vinamilk project root
if (!$doc['FilePath']) {
    http_response_code(404);
    exit('No file attached to this document version.');
}

$relative = ltrim($doc['FilePath'], '/');
$abs_path = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
           . str_replace('/', DIRECTORY_SEPARATOR, $relative);

// Security: make sure the resolved path is actually inside BASE_PATH/storage
$storage_base = realpath(BASE_PATH . DIRECTORY_SEPARATOR . 'storage');
$real_abs     = realpath($abs_path);

if (!$real_abs || !$storage_base || strpos($real_abs, $storage_base) !== 0) {
    http_response_code(403);
    exit('Access denied: path traversal blocked.');
}

if (!is_file($real_abs) || !is_readable($real_abs)) {
    http_response_code(404);
    exit('File not found on disk. It may have been moved or deleted.');
}

// ---- Stream the file ----
$ext        = strtolower(pathinfo($real_abs, PATHINFO_EXTENSION));
$mime_map   = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
];
$mime       = $mime_map[$ext] ?? 'application/octet-stream';
$safe_name  = preg_replace('/[^\w\-\.]/', '_', $doc['DocumentName']) . '.' . $ext;
$file_size  = filesize($real_abs);

// Audit the download
audit('DOWNLOAD', 'Document', $doc_id, [
    'contractCode' => $doc['ContractCode'],
    'fileName'     => basename($real_abs),
]);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $safe_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Clear any output buffer
while (ob_get_level()) { ob_end_clean(); }

readfile($real_abs);
exit;
