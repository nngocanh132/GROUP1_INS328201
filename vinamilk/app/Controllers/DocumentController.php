<?php
/**
 * DocumentController
 *
 * Handles document downloads and uploads
 */

class DocumentController
{
    /**
     * Download document with access control
     */
    public static function download()
    {
        require_login();
        $user = current_user();

        $doc_id = (int)($_GET['doc'] ?? 0);
        if (!$doc_id) {
            http_response_code(400);
            exit('Invalid document ID.');
        }

        // Load document
        $doc = DocumentModel::getDocumentWithContract($doc_id);
        if (!$doc) {
            http_response_code(404);
            exit('Document not found.');
        }

        // Access control
        if ($user['role'] === 'external') {
            // Must belong to their counterparty AND be partner-visible
            if (!$doc['IsVisibleToPartner']) {
                http_response_code(403);
                exit('Access denied.');
            }
            if (!DocumentModel::externalUserHasAccess($user['account_id'], $doc['CounterpartyID'])) {
                http_response_code(403);
                exit('Access denied.');
            }
        }

        // Resolve physical path
        if (!$doc['FilePath']) {
            http_response_code(404);
            exit('No file attached to this document version.');
        }

        $relative = ltrim($doc['FilePath'], '/');
        $abs_path = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                   . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        // Security: path traversal check
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

        // Stream file
        self::streamFile($real_abs, $doc);
    }

    /**
     * Stream file to browser
     */
    private static function streamFile($abs_path, $doc)
    {
        $ext        = strtolower(pathinfo($abs_path, PATHINFO_EXTENSION));
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
        $file_size  = filesize($abs_path);

        // Audit the download
        audit('DOWNLOAD', 'Document', $doc['DocumentID'], [
            'contractCode' => $doc['ContractCode'],
            'fileName'     => basename($abs_path),
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

        readfile($abs_path);
        exit;
    }

    /**
     * Upload document form and handler
     */
    public static function upload()
    {
        require_login();
        $user = current_user();

        if ($user['role'] === 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'You do not have permission to upload documents.');
        }

        $errors = [];
        $old    = [];

        // Pre-fill contract_id from query string
        $prefill_contract_id = (int)($_GET['contract_id'] ?? 0);

        // Load available contracts
        $available_contracts = DocumentModel::getAvailableContracts();

        $doc_types = [
            'MainContract'   => 'Main Contract',
            'Appendix'       => 'Appendix',
            'CargoInsurance' => 'Cargo Insurance',
            'FoodSafetyCert' => 'Food Safety Cert',
            'BankGuarantee'  => 'Bank Guarantee',
            'Other'          => 'Other',
        ];

        // Handle POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$errors, $old] = self::handleUploadPost($user, $doc_types);
        }

        // Pre-fill defaults
        if (empty($old)) {
            $old['ContractID']         = $prefill_contract_id;
            $old['DocumentType']       = 'MainContract';
            $old['IsVisibleToPartner'] = 0;
        }

        // Display view
        require __DIR__ . '/../Views/documents/upload.php';
    }

    /**
     * Handle upload POST request
     */
    private static function handleUploadPost($user, $doc_types)
    {
        $errors = [];
        $old = [];

        if (!verify_csrf()) {
            $errors[] = 'Security token mismatch. Please refresh and try again.';
            return [$errors, $old];
        }

        $old = [
            'ContractID'        => (int)($_POST['ContractID']        ?? 0),
            'DocumentType'      => trim($_POST['DocumentType']        ?? ''),
            'DocumentName'      => trim($_POST['DocumentName']        ?? ''),
            'IsVisibleToPartner'=> isset($_POST['IsVisibleToPartner']) ? 1 : 0,
            'ChangeSummary'     => trim($_POST['ChangeSummary']       ?? ''),
        ];

        // Validation
        $contract = null;
        if (!$old['ContractID']) {
            $errors['ContractID'] = 'Please select a contract.';
        } else {
            $contract = DocumentModel::getContractById($old['ContractID']);
            if (!$contract) {
                $errors['ContractID'] = 'Selected contract does not exist.';
            }
        }

        if (!array_key_exists($old['DocumentType'], $doc_types)) {
            $errors['DocumentType'] = 'Please select a document type.';
        }

        if ($old['DocumentName'] === '') {
            $errors['DocumentName'] = 'Document name is required.';
        } elseif (mb_strlen($old['DocumentName']) > 200) {
            $errors['DocumentName'] = 'Document name must be 200 characters or fewer.';
        }

        // File validation
        $uploaded_file = $_FILES['document_file'] ?? null;
        [$file_errors, $upload_dir, $unique_name, $db_path] = self::validateAndPrepareFile($uploaded_file, $contract, $old);
        $errors = array_merge($errors, $file_errors);

        // Persist if no errors
        if (empty($errors)) {
            self::saveUploadedDocument($uploaded_file, $upload_dir, $unique_name, $db_path, $old, $contract, $user);
        }

        return [$errors, $old];
    }

    /**
     * Validate uploaded file
     */
    private static function validateAndPrepareFile($uploaded_file, $contract, $old)
    {
        $errors = [];
        $upload_dir = null;
        $unique_name = null;
        $db_path = null;

        if (empty($uploaded_file) || $uploaded_file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['document_file'] = 'Please choose a file to upload.';
            return [$errors, $upload_dir, $unique_name, $db_path];
        }

        if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'Server failed to write the file.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
            ];
            $errors['document_file'] = $upload_errors[$uploaded_file['error']]
                ?? 'Upload failed (error code ' . $uploaded_file['error'] . ').';
            return [$errors, $upload_dir, $unique_name, $db_path];
        }

        // Size check
        $max_bytes = UPLOAD_MAX_MB * 1024 * 1024;
        if ($uploaded_file['size'] > $max_bytes) {
            $errors['document_file'] = 'File is too large. Maximum allowed size is ' . UPLOAD_MAX_MB . ' MB.';
            return [$errors, $upload_dir, $unique_name, $db_path];
        }

        // MIME type check
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($uploaded_file['tmp_name']);
        $ext   = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));

        if (!in_array($mime, UPLOAD_ALLOWED, true) || !in_array($ext, UPLOAD_EXT_ALLOWED, true)) {
            $errors['document_file'] = 'File type not allowed. Accepted types: PDF, Word (doc/docx), Excel (xls/xlsx), PNG, JPG.';
            return [$errors, $upload_dir, $unique_name, $db_path];
        }

        // Prepare upload directory
        $upload_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents';

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors['document_file'] = 'Upload directory could not be created. Contact the system administrator.';
                return [$errors, $upload_dir, $unique_name, $db_path];
            }
        }

        // Write probe test
        $probe = $upload_dir . DIRECTORY_SEPARATOR . '.write_probe_' . uniqid() . '.tmp';
        $probe_ok = @file_put_contents($probe, '1');
        if ($probe_ok === false) {
            $errors['document_file'] = 'Upload directory is not writable. Contact the system administrator.';
            return [$errors, $upload_dir, $unique_name, $db_path];
        }
        @unlink($probe);

        // Build unique filename
        $safe_contract = preg_replace('/[^A-Za-z0-9\-]/', '', $contract['ContractCode']);
        $safe_type     = preg_replace('/[^A-Za-z0-9]/', '', $old['DocumentType']);
        $safe_ext      = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        $unique_name   = $safe_contract . '_' . $safe_type . '_'
                       . date('Ymd_His') . '_' . bin2hex(random_bytes(4))
                       . '.' . $safe_ext;

        $db_path = '/storage/documents/' . $unique_name;

        return [$errors, $upload_dir, $unique_name, $db_path];
    }

    /**
     * Save uploaded document to database and disk
     */
    private static function saveUploadedDocument($uploaded_file, $upload_dir, $unique_name, $db_path, $old, $contract, $user)
    {
        $dest_path = $upload_dir . DIRECTORY_SEPARATOR . $unique_name;

        $db = get_db();
        $db->beginTransaction();
        try {
            // Move uploaded file
            if (!move_uploaded_file($uploaded_file['tmp_name'], $dest_path)) {
                throw new RuntimeException('move_uploaded_file() failed. Check directory permissions.');
            }

            // Insert Document
            $document_id = DocumentModel::insertDocument(
                $old['ContractID'],
                $old['DocumentType'],
                $old['DocumentName'],
                $old['IsVisibleToPartner']
            );

            // Insert ContractVersion
            $file_hash = hash_file('sha256', $dest_path) ?: null;
            DocumentModel::insertVersion(
                $document_id,
                1, // First version
                $db_path,
                (int)$uploaded_file['size'],
                $file_hash,
                $user['account_id'],
                $old['ChangeSummary'] ?: null
            );

            $db->commit();

            audit('UPLOAD', 'Document', $document_id, [
                'contractCode' => $contract['ContractCode'],
                'documentName' => $old['DocumentName'],
                'fileName'     => $unique_name,
                'size'         => $uploaded_file['size'],
            ]);

            redirect_with_flash(
                BASE_URL . '/pages/contract_detail.php?id=' . $old['ContractID'],
                'success',
                "Document \"{$old['DocumentName']}\" uploaded successfully."
            );

        } catch (Throwable $e) {
            $db->rollBack();
            if (file_exists($dest_path)) {
                @unlink($dest_path);
            }
            error_log('upload_document error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Document listing page
     */
    public static function index()
    {
        require_login();
        $user = current_user();

        // Filters
        $q            = trim($_GET['q']    ?? '');
        $filter_type  = trim($_GET['type'] ?? '');
        $filter_cid   = (int)($_GET['contract'] ?? 0);
        $page         = max(1, (int)($_GET['page'] ?? 1));

        $doc_type_map = [
            'MainContract'   => 'Main Contract',
            'Appendix'       => 'Appendix',
            'CargoInsurance' => 'Cargo Insurance',
            'FoodSafetyCert' => 'Food Safety Cert',
            'BankGuarantee'  => 'Bank Guarantee',
            'Other'          => 'Other',
        ];

        $allowed_types = array_keys($doc_type_map);
        if (!in_array($filter_type, $allowed_types, true)) $filter_type = '';

        // Build filters array
        $filters = [
            'q' => $q,
            'type' => $filter_type,
            'contract' => $filter_cid,
        ];

        // Query documents
        $result = DocumentModel::getDocumentsFiltered($user, $filters, $page, DEFAULT_PAGE_SIZE);
        $documents = $result['rows'];

        // Type counts
        $type_counts = DocumentModel::getTypeCounts($user);

        // Display view
        require __DIR__ . '/../Views/documents/index.php';
    }
}
