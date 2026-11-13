<?php
/**
 * CounterpartyController
 *
 * Counterparty listing and admin approval workflow
 */

class CounterpartyController
{
    public static function index()
    {
        require_login();
        $user = current_user();

        if ($user['role'] === 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
        }

        // Admin POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'admin') {
            self::handleAdminActions($user);
        }

        // Load review detail if admin + ?review=ID
        $review_cp = null;
        $review_contacts = [];
        $linked_contracts = 0;

        if ($user['role'] === 'admin' && isset($_GET['review'])) {
            $rev_id = (int)$_GET['review'];
            if ($rev_id) {
                $review_cp = CounterpartyModel::getById($rev_id);
                if ($review_cp) {
                    $review_contacts = CounterpartyModel::getContacts($rev_id);
                    $linked_contracts = CounterpartyModel::countLinkedContracts($rev_id);
                }
            }
        }

        // Pending count
        $pending_count = CounterpartyModel::countPending();

        // Filters
        $q             = trim($_GET['q']      ?? '');
        $filter_status = trim($_GET['status'] ?? '');
        $filter_type   = trim($_GET['type']   ?? '');
        $page          = max(1, (int)($_GET['page'] ?? 1));

        $allowed_statuses = ['Pending', 'Active', 'Rejected', 'Suspended'];
        $allowed_types    = ['Supplier', 'Customer', 'ServiceProvider'];
        if (!in_array($filter_status, $allowed_statuses)) $filter_status = '';
        if (!in_array($filter_type,   $allowed_types))    $filter_type   = '';

        $filters = [
            'q' => $q,
            'status' => $filter_status,
            'type' => $filter_type,
        ];

        // Query counterparties
        $result = CounterpartyModel::getFiltered($filters, $page, DEFAULT_PAGE_SIZE);
        $counterparties = $result['rows'];

        // Display view
        require __DIR__ . '/../Views/counterparties/index.php';
    }

    /**
     * Create counterparty form + POST handler (Staff only)
     */
    public static function create()
    {
        require_login();
        $user = current_user();

        if ($user['role'] === 'external') {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Access denied.');
        }
        if (!$user['staff_id']) {
            redirect_with_flash(BASE_URL . '/index.php', 'danger', 'Only staff members can register partner companies.');
        }

        $page_title  = 'Register Partner Company';
        $active_nav  = 'counterparties';
        $breadcrumbs = [
            ['label' => 'Counterparties', 'url' => BASE_URL . '/pages/counterparties.php'],
            ['label' => 'Register Partner Company'],
        ];

        $errors = [];
        $old    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verify_csrf()) {
                $errors[] = 'Security token mismatch. Please refresh and try again.';
            } else {
                $old = [
                    'CompanyName'         => trim($_POST['CompanyName']         ?? ''),
                    'CounterpartyType'    => trim($_POST['CounterpartyType']    ?? ''),
                    'TaxCode'             => trim($_POST['TaxCode']             ?? ''),
                    'Address'             => trim($_POST['Address']             ?? ''),
                    'CompanyEmail'        => trim($_POST['CompanyEmail']        ?? ''),
                    'Phone'               => trim($_POST['Phone']               ?? ''),
                    'LegalRepresentative' => trim($_POST['LegalRepresentative'] ?? ''),
                    'BusinessLicense'     => trim($_POST['BusinessLicense']     ?? ''),
                    'ContactFullName'     => trim($_POST['ContactFullName']     ?? ''),
                    'ContactEmail'        => trim($_POST['ContactEmail']        ?? ''),
                    'ContactJobTitle'     => trim($_POST['ContactJobTitle']     ?? ''),
                    'ContactPhone'        => trim($_POST['ContactPhone']        ?? ''),
                ];

                $allowed_types = ['Supplier', 'Customer', 'ServiceProvider'];
                if ($old['CompanyName'] === '') $errors['CompanyName'] = 'Company name is required.';
                if (!in_array($old['CounterpartyType'], $allowed_types)) $errors['CounterpartyType'] = 'Please select a company type.';
                if ($old['TaxCode'] === '') $errors['TaxCode'] = 'Tax code is required.';
                if ($old['ContactFullName'] === '') $errors['ContactFullName'] = 'Contact person full name is required.';
                if ($old['ContactEmail'] === '') {
                    $errors['ContactEmail'] = 'Contact person email is required.';
                } elseif (!filter_var($old['ContactEmail'], FILTER_VALIDATE_EMAIL)) {
                    $errors['ContactEmail'] = 'Contact email is not a valid email address.';
                }
                if ($old['TaxCode'] !== '' && !isset($errors['TaxCode'])) {
                    if (db_query("SELECT 1 FROM Counterparty WHERE TaxCode = ?", [$old['TaxCode']])->fetchColumn()) {
                        $errors['TaxCode'] = 'A company with this tax code already exists.';
                    }
                }
                if (!isset($errors['ContactEmail'])) {
                    if (db_query("SELECT 1 FROM Account WHERE Email = ?", [$old['ContactEmail']])->fetchColumn()) {
                        $errors['ContactEmail'] = 'An account with this email already exists.';
                    }
                }

                if (empty($errors)) {
                    $next_num = (int) db_query("SELECT COUNT(*) FROM Counterparty")->fetchColumn();
                    $cp_code  = 'VNM-EXT-' . str_pad($next_num + 1, 3, '0', STR_PAD_LEFT);
                    while (db_query("SELECT 1 FROM Counterparty WHERE CounterpartyCode = ?", [$cp_code])->fetchColumn()) {
                        $next_num++;
                        $cp_code = 'VNM-EXT-' . str_pad($next_num + 1, 3, '0', STR_PAD_LEFT);
                    }

                    $db = get_db();
                    $db->beginTransaction();
                    try {
                        db_query(
                            "INSERT INTO Counterparty
                                 (CounterpartyCode, CompanyName, CounterpartyType, TaxCode,
                                  Address, Email, Phone, LegalRepresentative, BusinessLicense,
                                  ApprovalStatus, ProposedBy, ProposedAt)
                             VALUES (?,?,?,?,?,?,?,?,?,'Pending',?,NOW())",
                            [$cp_code, $old['CompanyName'], $old['CounterpartyType'], $old['TaxCode'],
                             $old['Address'] ?: null, $old['CompanyEmail'] ?: null, $old['Phone'] ?: null,
                             $old['LegalRepresentative'] ?: null, $old['BusinessLicense'] ?: null,
                             $user['staff_id']]
                        );
                        $cp_id = (int)$db->lastInsertId();

                        $ext_role_id = (int) db_query("SELECT RoleID FROM Role WHERE RoleName = 'External' LIMIT 1")->fetchColumn();
                        if (!$ext_role_id) throw new RuntimeException('External role not found in database.');

                        $temp_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                        db_query("INSERT INTO Account (RoleID, Email, PasswordHash, Status, CreatedAt) VALUES (?,?,?,'Pending',NOW())",
                            [$ext_role_id, $old['ContactEmail'], $temp_hash]);
                        $account_id = (int)$db->lastInsertId();

                        db_query("INSERT INTO Contact (AccountID, CounterpartyID, FullName, JobTitle, Phone, IsPrimary) VALUES (?,?,?,?,?,1)",
                            [$account_id, $cp_id, $old['ContactFullName'],
                             $old['ContactJobTitle'] ?: null, $old['ContactPhone'] ?: null]);

                        $admin_accounts = db_query(
                            "SELECT a.AccountID FROM Account a JOIN Role r ON r.RoleID=a.RoleID
                             WHERE r.RoleName='Manager/Admin' AND a.Status='Active'"
                        )->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($admin_accounts as $admin_id) {
                            db_query(
                                "INSERT INTO Notification (AccountID, CounterpartyID, Title, Message, NotificationType, IsRead, CreatedAt)
                                 VALUES (?,?,?,?,'Info',0,NOW())",
                                [$admin_id, $cp_id,
                                 'New partner company awaiting approval',
                                 "Staff member {$user['name']} has registered {$old['CompanyName']} ({$cp_code}). Please review and approve or reject."]
                            );
                        }

                        $db->commit();
                        audit('CREATE', 'Counterparty', $cp_id, ['code' => $cp_code, 'company' => $old['CompanyName'], 'status' => 'Pending']);
                        redirect_with_flash(
                            BASE_URL . '/pages/counterparties.php?status=Pending',
                            'success',
                            "Partner company \"{$old['CompanyName']}\" ({$cp_code}) registered and is awaiting Admin approval. The contact account for {$old['ContactEmail']} has also been created pending activation."
                        );
                    } catch (Throwable $e) {
                        $db->rollBack();
                        error_log('create_counterparty error: ' . $e->getMessage());
                        $errors[] = 'An unexpected error occurred: ' . $e->getMessage();
                    }
                }
            }
        }

        require __DIR__ . '/../Views/counterparties/create.php';
    }

    private static function handleAdminActions($user)
    {
        if (!verify_csrf()) {
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Security token mismatch.');
        }

        $action = trim($_POST['action']           ?? '');
        $cp_id  = (int)($_POST['counterparty_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');

        if (!$cp_id) {
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Invalid request.');
        }

        $cp = CounterpartyModel::getById($cp_id);
        if (!$cp) {
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Counterparty not found.');
        }

        $db = get_db();
        $db->beginTransaction();
        try {
            if ($action === 'approve' && $cp['ApprovalStatus'] === 'Pending') {
                self::doApprove($cp, $user);
            } elseif ($action === 'reject' && $cp['ApprovalStatus'] === 'Pending') {
                if ($reason === '') {
                    redirect_with_flash(
                        BASE_URL . '/pages/counterparties.php?review=' . $cp_id,
                        'danger',
                        'A rejection reason is required.'
                    );
                }
                self::doReject($cp, $user, $reason);
            } elseif ($action === 'suspend' && $cp['ApprovalStatus'] === 'Active') {
                self::doSuspend($cp, $user);
            } elseif ($action === 'reactivate' && $cp['ApprovalStatus'] === 'Suspended') {
                self::doReactivate($cp, $user);
            } else {
                $db->rollBack();
                redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger', 'Invalid action.');
            }
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('counterparty action error: ' . $e->getMessage());
            redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'danger',
                'An error occurred. Please try again.');
        }
    }

    private static function doApprove($cp, $user)
    {
        $cp_id = $cp['CounterpartyID'];
        CounterpartyModel::approve($cp_id, $user['staff_id']);

        // Notify proposer
        CounterpartyModel::notifyProposer(
            $cp['ProposedBy'],
            $cp_id,
            'Partner company approved',
            "Your registration request for \"{$cp['CompanyName']}\" ({$cp['CounterpartyCode']}) has been approved. Their contact account is now active.",
            'Success'
        );

        get_db()->commit();
        audit('APPROVE', 'Counterparty', $cp_id, ['company' => $cp['CompanyName']]);
        redirect_with_flash(
            BASE_URL . '/pages/counterparties.php',
            'success',
            "\"{$cp['CompanyName']}\" approved. Their contact account is now active."
        );
    }

    private static function doReject($cp, $user, $reason)
    {
        $cp_id = $cp['CounterpartyID'];
        CounterpartyModel::reject($cp_id, $user['staff_id'], $reason);

        // Notify proposer
        CounterpartyModel::notifyProposer(
            $cp['ProposedBy'],
            $cp_id,
            'Partner company registration rejected',
            "Your registration request for \"{$cp['CompanyName']}\" has been rejected. Reason: $reason",
            'Warning'
        );

        get_db()->commit();
        audit('REJECT', 'Counterparty', $cp_id, ['reason' => $reason]);
        redirect_with_flash(
            BASE_URL . '/pages/counterparties.php',
            'success',
            "\"{$cp['CompanyName']}\" has been rejected."
        );
    }

    private static function doSuspend($cp, $user)
    {
        $cp_id = $cp['CounterpartyID'];
        CounterpartyModel::suspend($cp_id);
        get_db()->commit();
        audit('SUSPEND', 'Counterparty', $cp_id, []);
        redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'success',
            "\"{$cp['CompanyName']}\" has been suspended and their accounts deactivated.");
    }

    private static function doReactivate($cp, $user)
    {
        $cp_id = $cp['CounterpartyID'];
        CounterpartyModel::reactivate($cp_id);
        get_db()->commit();
        audit('REACTIVATE', 'Counterparty', $cp_id, []);
        redirect_with_flash(BASE_URL . '/pages/counterparties.php', 'success',
            "\"{$cp['CompanyName']}\" has been reactivated.");
    }
}
