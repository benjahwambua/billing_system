<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {
    $databaseFile = dirname(__DIR__) . '/config/database.php';

    if (file_exists($databaseFile)) {
        require_once $databaseFile;
    }
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

$conn->set_charset('utf8mb4');




if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


if (!function_exists('redirect')) {
    function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
}


if (!function_exists('back')) {
    function back($fallback = '../index.php')
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;

        header('Location: ' . $referer);
        exit;
    }
}


if (!function_exists('csrfToken')) {
    function csrfToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}


if (!function_exists('csrfField')) {
    function csrfField()
    {
        return '<input type="hidden" name="csrf_token" value="' .
            e(csrfToken()) .
            '">';
    }
}


if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token)
    {
        if (
            empty($_SESSION['csrf_token']) ||
            empty($token)
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
    }
}


if (!function_exists('requireCsrf')) {
    function requireCsrf()
    {
        $token = $_POST['csrf_token'] ?? '';

        if (!verifyCsrfToken($token)) {
            http_response_code(419);
            die('Invalid or expired security token.');
        }
    }
}



if (!function_exists('formatMoney')) {
    function formatMoney($amount, $currency = null)
    {
        if ($currency === null) {
            $currency = getSetting('currency', 'KES');
        }

        return $currency . ' ' . number_format(
            (float)$amount,
            2
        );
    }
}


if (!function_exists('formatNumber')) {
    function formatNumber($number, $decimals = 0)
    {
        return number_format(
            (float)$number,
            $decimals
        );
    }
}


if (!function_exists('cleanMoney')) {
    function cleanMoney($amount)
    {
        return round(
            (float)preg_replace(
                '/[^0-9.\-]/',
                '',
                (string)$amount
            ),
            2
        );
    }
}


if (!function_exists('getBalance')) {
    function getBalance($debit, $credit)
    {
        return round(
            (float)$debit - (float)$credit,
            2
        );
    }
}




if (!function_exists('getSetting')) {
    function getSetting($key, $default = null)
    {
        global $conn;

        if (!($conn instanceof mysqli)) {
            return $default;
        }

        $tenantId = function_exists('getCurrentTenantId')
            ? getCurrentTenantId()
            : null;

        if ($tenantId) {
            $stmt = $conn->prepare("
                SELECT setting_value
                FROM tenant_settings
                WHERE tenant_id = ?
                  AND setting_key = ?
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param('is', $tenantId, $key);

                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();

                    if ($row !== null) {
                        return $row['setting_value'];
                    }
                } else {
                    $stmt->close();
                }
            }
        }

        $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");

        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT setting_value
                FROM settings
                WHERE setting_key = ?
                LIMIT 1
            ");

            if ($stmt) {
                $stmt->bind_param('s', $key);

                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();

                    if ($row !== null) {
                        return $row['setting_value'];
                    }
                } else {
                    $stmt->close();
                }
            }
        }

        return $default;
    }
}


if (!function_exists('setSetting')) {
    function setSetting($key, $value)
    {
        global $conn;

        $stmt = $conn->prepare(
            "INSERT INTO settings
                (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value)"
        );

        if (!$stmt) {
            return false;
        }

        $value = (string)$value;

        $stmt->bind_param(
            'ss',
            $key,
            $value
        );

        $success = $stmt->execute();

        $stmt->close();

        return $success;
    }
}




if (!function_exists('getPlatformSetting')) {
    function getPlatformSetting($key, $default = null)
    {
        global $conn;

        $stmt = $conn->prepare(
            "SELECT setting_value
             FROM platform_settings
             WHERE setting_key = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return $default;
        }

        $stmt->bind_param('s', $key);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();

        return $row
            ? $row['setting_value']
            : $default;
    }
}




if (!function_exists('generateReference')) {
    function generateReference($prefix)
    {
        return $prefix .
            '-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                substr(
                    bin2hex(random_bytes(4)),
                    0,
                    6
                )
            );
    }
}


if (!function_exists('generateCustomerNumber')) {
    function generateCustomerNumber()
    {
        global $conn;

        $prefix = getSetting(
            'customer_prefix',
            'CUS'
        );

        do {
            $number = $prefix .
                '-' .
                str_pad(
                    random_int(1, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $stmt = $conn->prepare(
                "SELECT id
                 FROM customers
                 WHERE customer_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generateStaffNumber')) {
    function generateStaffNumber()
    {
        global $conn;

        $prefix = getSetting('staff_prefix', 'STF');

        do {
            $number = $prefix . '-' .
                str_pad(
                    random_int(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            $stmt = $conn->prepare("
                SELECT id
                FROM staffs
                WHERE staff_code = ?
                LIMIT 1
            ");

            if (!$stmt) {
                return $number;
            }

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generateStaffCode')) {
    function generateStaffCode($databaseConnection = null)
    {
        return generateStaffNumber();
    }
}


if (!function_exists('generateInternetAccountNumber')) {
    function generateInternetAccountNumber()
    {
        global $conn;

        $prefix = getSetting(
            'internet_prefix',
            'INT'
        );

        do {
            $number = $prefix .
                '-' .
                str_pad(
                    random_int(1, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            $stmt = $conn->prepare(
                "SELECT id
                 FROM internet_accounts
                 WHERE account_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber()
    {
        global $conn;

        $prefix = getSetting(
            'invoice_prefix',
            'INV'
        );

        do {
            $number = $prefix .
                '-' .
                date('Ym') .
                '-' .
                str_pad(
                    random_int(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                );

            $stmt = $conn->prepare(
                "SELECT id
                 FROM invoices
                 WHERE invoice_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generateOrderNumber')) {
    function generateOrderNumber()
    {
        global $conn;

        $prefix = getSetting(
            'order_prefix',
            'ORD'
        );

        do {
            $number = $prefix .
                '-' .
                date('Ymd') .
                '-' .
                random_int(1000, 9999);

            $stmt = $conn->prepare(
                "SELECT id
                 FROM orders
                 WHERE order_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generatePaymentNumber')) {
    function generatePaymentNumber()
    {
        global $conn;

        $prefix = getSetting(
            'payment_prefix',
            'PAY'
        );

        do {
            $number = $prefix .
                '-' .
                date('YmdHis') .
                '-' .
                random_int(100, 999);

            $stmt = $conn->prepare(
                "SELECT id
                 FROM payments
                 WHERE payment_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generateReceiptNumber')) {
    function generateReceiptNumber()
    {
        global $conn;

        $prefix = getSetting(
            'receipt_prefix',
            'RCT'
        );

        do {
            $number = $prefix .
                '-' .
                date('YmdHis') .
                '-' .
                random_int(100, 999);

            $stmt = $conn->prepare(
                "SELECT id
                 FROM receipts
                 WHERE receipt_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


if (!function_exists('generateExpenseNumber')) {
    function generateExpenseNumber()
    {
        global $conn;

        $prefix = getSetting(
            'expense_prefix',
            'EXP'
        );

        do {
            $number = $prefix .
                '-' .
                date('Ymd') .
                '-' .
                random_int(1000, 9999);

            $stmt = $conn->prepare(
                "SELECT id
                 FROM expenses
                 WHERE expense_number = ?
                 LIMIT 1"
            );

            $stmt->bind_param('s', $number);
            $stmt->execute();

            $exists = $stmt->get_result()->num_rows > 0;

            $stmt->close();

        } while ($exists);

        return $number;
    }
}


/*
|--------------------------------------------------------------------------
| DATE / TIME
|--------------------------------------------------------------------------
*/

if (!function_exists('systemTimezone')) {
    function systemTimezone()
    {
        $timezone = getPlatformSetting(
            'timezone',
            'Africa/Nairobi'
        );

        return $timezone ?: 'Africa/Nairobi';
    }
}


if (!function_exists('systemNow')) {
    function systemNow()
    {
        $date = new DateTime(
            'now',
            new DateTimeZone(systemTimezone())
        );

        return $date;
    }
}


if (!function_exists('systemDate')) {
    function systemDate()
    {
        return systemNow()->format('Y-m-d');
    }
}


if (!function_exists('systemDateTime')) {
    function systemDateTime()
    {
        return systemNow()->format(
            'Y-m-d H:i:s'
        );
    }
}


if (!function_exists('addDays')) {
    function addDays($date, $days)
    {
        $dateObject = new DateTime($date);

        $dateObject->modify(
            ($days >= 0 ? '+' : '') .
            (int)$days .
            ' days'
        );

        return $dateObject->format('Y-m-d');
    }
}


if (!function_exists('addMonths')) {
    function addMonths($date, $months)
    {
        $dateObject = new DateTime($date);

        $dateObject->modify(
            ($months >= 0 ? '+' : '') .
            (int)$months .
            ' months'
        );

        return $dateObject->format('Y-m-d');
    }
}


if (!function_exists('calculateBillingEndDate')) {
    function calculateBillingEndDate(
        $startDate,
        $billingCycle = 'monthly',
        $days = null
    ) {
        if ($days !== null && $days > 0) {
            return addDays(
                $startDate,
                $days
            );
        }

        switch (strtolower($billingCycle)) {

            case 'daily':
                return addDays(
                    $startDate,
                    1
                );

            case 'weekly':
                return addDays(
                    $startDate,
                    7
                );

            case 'quarterly':
                return addMonths(
                    $startDate,
                    3
                );

            case 'yearly':
            case 'annual':
                return addMonths(
                    $startDate,
                    12
                );

            case 'monthly':
            default:
                return addMonths(
                    $startDate,
                    1
                );
        }
    }
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION CONTEXT
|--------------------------------------------------------------------------
*/

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId()
    {
        return isset($_SESSION['user_id'])
            ? (int)$_SESSION['user_id']
            : null;
    }
}


if (!function_exists('getCurrentTenantId')) {
    function getCurrentTenantId()
    {
        if (
            isset($_SESSION['tenant_id']) &&
            $_SESSION['tenant_id'] !== null &&
            $_SESSION['tenant_id'] !== ''
        ) {
            return (int)$_SESSION['tenant_id'];
        }

        return null;
    }
}


if (!function_exists('getCurrentUserScope')) {
    function getCurrentUserScope()
    {
        return $_SESSION['user_scope'] ?? null;
    }
}


if (!function_exists('isHostUser')) {
    function isHostUser()
    {
        return getCurrentUserScope() === 'host';
    }
}


if (!function_exists('isTenantUser')) {
    function isTenantUser()
    {
        return getCurrentUserScope() === 'tenant';
    }
}


if (!function_exists('requireTenantContext')) {
    function requireTenantContext()
    {
        $tenantId = getCurrentTenantId();

        if (!$tenantId || !isTenantUser()) {
            http_response_code(403);
            die('Tenant context is required.');
        }

        return $tenantId;
    }
}


if (!function_exists('requireTenant')) {
    function requireTenant()
    {
        return requireTenantContext();
    }
}


if (!function_exists('requireHostContext')) {
    function requireHostContext()
    {
        if (!isHostUser()) {
            http_response_code(403);
            die('Host administrator access required.');
        }

        return true;
    }
}


if (!function_exists('requireHost')) {
    function requireHost()
    {
        return requireHostContext();
    }
}


/*
|--------------------------------------------------------------------------
| TENANT HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getCurrentTenant')) {
    function getCurrentTenant()
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        if (!$tenantId) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT *
             FROM tenants
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param(
            'i',
            $tenantId
        );

        $stmt->execute();

        $result = $stmt->get_result();
        $tenant = $result->fetch_assoc();

        $stmt->close();

        return $tenant ?: null;
    }
}


if (!function_exists('isCurrentTenantActive')) {
    function isCurrentTenantActive()
    {
        $tenant = getCurrentTenant();

        if (!$tenant) {
            return false;
        }

        return in_array(
            strtolower($tenant['status'] ?? ''),
            ['active', 'trial'],
            true
        );
    }
}


if (!function_exists('isTenantActive')) {
    function isTenantActive()
    {
        return isCurrentTenantActive();
    }
}


if (!function_exists('recordBelongsToTenant')) {
    function recordBelongsToTenant(
        $table,
        $recordId,
        $idColumn = 'id'
    ) {
        global $conn;

        $allowedTables = [
            'customers',
            'internet_plans',
            'mikrotik_routers',
            'pppoe_servers',
            'ip_pools',
            'internet_accounts',
            'pppoe_accounts',
            'active_sessions',
            'products',
            'orders',
            'invoices',
            'payments',
            'receipts',
            'expenses',
            'communications',
            'audit_logs'
        ];

        if (!in_array($table, $allowedTables, true)) {
            return false;
        }

        $tenantId = getCurrentTenantId();

        if (!$tenantId) {
            return false;
        }

        $sql = "
            SELECT id
            FROM `{$table}`
            WHERE `{$idColumn}` = ?
            AND tenant_id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $recordId = (int)$recordId;

        $stmt->bind_param(
            'ii',
            $recordId,
            $tenantId
        );

        $stmt->execute();

        $exists = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        return $exists;
    }
}


if (!function_exists('requireTenantRecord')) {
    function requireTenantRecord(
        $table,
        $recordId,
        $idColumn = 'id'
    ) {
        if (!recordBelongsToTenant(
            $table,
            $recordId,
            $idColumn
        )) {
            http_response_code(404);
            die('Record not found.');
        }

        return true;
    }
}


if (!function_exists('tenantWhere')) {
    function tenantWhere($column = 'tenant_id')
    {
        $tenantId = getCurrentTenantId();

        if (!$tenantId) {
            return '1 = 0';
        }

        return $column .
            ' = ' .
            (int)$tenantId;
    }
}


if (!function_exists('tenantIdForInsert')) {
    function tenantIdForInsert()
    {
        $tenantId = getCurrentTenantId();

        if (!$tenantId) {
            return null;
        }

        return (int)$tenantId;
    }
}


if (!function_exists('getTenantInsertId')) {
    function getTenantInsertId()
    {
        return tenantIdForInsert();
    }
}


/*
|--------------------------------------------------------------------------
| ROLES / PERMISSIONS
|--------------------------------------------------------------------------
*/

if (!function_exists('getCurrentUserRoles')) {
    function getCurrentUserRoles()
    {
        global $conn;

        $userId = getCurrentUserId();

        if (!$userId) {
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT r.role_code
             FROM user_roles ur
             INNER JOIN roles r
                 ON r.id = ur.role_id
             WHERE ur.user_id = ?
             AND r.status = 'active'"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param(
            'i',
            $userId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $roles = [];

        while ($row = $result->fetch_assoc()) {
            $roles[] = $row['role_code'];
        }

        $stmt->close();

        return $roles;
    }
}


if (!function_exists('hasRole')) {
    function hasRole($roleCode)
    {
        return in_array(
            $roleCode,
            getCurrentUserRoles(),
            true
        );
    }
}


if (!function_exists('userHasPermission')) {
    function userHasPermission($permissionCode)
    {
        global $conn;

        $userId = getCurrentUserId();

        if (!$userId) {
            return false;
        }

        if (isHostUser() &&
            hasRole('HOST_SUPER_ADMIN')
        ) {
            return true;
        }

        $stmt = $conn->prepare(
            "SELECT 1
             FROM user_roles ur
             INNER JOIN role_permissions rp
                 ON rp.role_id = ur.role_id
             INNER JOIN permissions p
                 ON p.id = rp.permission_id
             WHERE ur.user_id = ?
             AND p.permission_code = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'is',
            $userId,
            $permissionCode
        );

        $stmt->execute();

        $allowed = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        return $allowed;
    }
}


if (!function_exists('requirePermission')) {
    function requirePermission($permissionCode)
    {
        if (!userHasPermission($permissionCode)) {
            http_response_code(403);
            die(
                'You do not have permission to perform this action.'
            );
        }

        return true;
    }
}


if (!function_exists('assertTenantQuery')) {
    function assertTenantQuery($sql)
    {
        if (!isTenantUser()) {
            return true;
        }

        /*
         * Basic protection against accidentally querying tenant data
         * without tenant isolation.
         */
        $protectedTables = [
            'customers',
            'internet_accounts',
            'pppoe_accounts',
            'payments',
            'invoices',
            'orders',
            'expenses',
            'mikrotik_routers',
            'pppoe_servers',
            'ip_pools'
        ];

        $lowerSql = strtolower($sql);

        foreach ($protectedTables as $table) {
            if (
                strpos(
                    $lowerSql,
                    $table
                ) !== false &&
                strpos(
                    $lowerSql,
                    'tenant_id'
                ) === false
            ) {
                /*
                 * We do not kill the query here because some
                 * legitimate JOINs may be handled elsewhere.
                 */
                return false;
            }
        }

        return true;
    }
}


/*
|--------------------------------------------------------------------------
| CUSTOMER HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getCustomer')) {
    function getCustomer($id)
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT *
            FROM customers
            WHERE id = ?
        ";

        if ($tenantId) {
            $sql .= " AND tenant_id = ?";
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        if ($tenantId) {
            $stmt->bind_param(
                'ii',
                $id,
                $tenantId
            );
        } else {
            $stmt->bind_param(
                'i',
                $id
            );
        }

        $stmt->execute();

        $customer = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return $customer ?: null;
    }
}


if (!function_exists('customerExists')) {
    function customerExists($id)
    {
        return getCustomer($id) !== null;
    }
}


if (!function_exists('getCustomerName')) {
    function getCustomerName($id)
    {
        $customer = getCustomer($id);

        if (!$customer) {
            return 'Unknown Customer';
        }

        return trim(
            ($customer['first_name'] ?? '') .
            ' ' .
            ($customer['last_name'] ?? '')
        );
    }
}


if (!function_exists('getCustomerAccounts')) {
    function getCustomerAccounts($customerId)
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT *
            FROM internet_accounts
            WHERE customer_id = ?
        ";

        if ($tenantId) {
            $sql .= " AND tenant_id = ?";
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        if ($tenantId) {
            $stmt->bind_param(
                'ii',
                $customerId,
                $tenantId
            );
        } else {
            $stmt->bind_param(
                'i',
                $customerId
            );
        }

        $stmt->execute();

        $result = $stmt->get_result();

        $accounts = [];

        while ($row = $result->fetch_assoc()) {
            $accounts[] = $row;
        }

        $stmt->close();

        return $accounts;
    }
}


/*
|--------------------------------------------------------------------------
| INTERNET ACCOUNT HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getInternetAccount')) {
    function getInternetAccount($id)
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT ia.*,
                   c.customer_number,
                   c.first_name,
                   c.last_name,
                   ip.name AS plan_name,
                   ip.price AS plan_price
            FROM internet_accounts ia
            LEFT JOIN customers c
                ON c.id = ia.customer_id
            LEFT JOIN internet_plans ip
                ON ip.id = ia.plan_id
            WHERE ia.id = ?
        ";

        if ($tenantId) {
            $sql .= " AND ia.tenant_id = ?";
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        if ($tenantId) {
            $stmt->bind_param(
                'ii',
                $id,
                $tenantId
            );
        } else {
            $stmt->bind_param(
                'i',
                $id
            );
        }

        $stmt->execute();

        $account = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return $account ?: null;
    }
}


if (!function_exists('getInternetAccountStatus')) {
    function getInternetAccountStatus($account)
    {
        if (!$account) {
            return 'unknown';
        }

        $status = strtolower(
            $account['status'] ?? ''
        );

        $expiry = $account['expiry_date']
            ?? $account['billing_date']
            ?? null;

        if (
            $status === 'active' &&
            $expiry &&
            strtotime($expiry) < time()
        ) {
            return 'expired';
        }

        return $status ?: 'pending';
    }
}


if (!function_exists('isInternetAccountActive')) {
    function isInternetAccountActive($account)
    {
        return getInternetAccountStatus(
            $account
        ) === 'active';
    }
}


if (!function_exists('calculateAccountExpiry')) {
    function calculateAccountExpiry(
        $activationDate,
        $plan
    ) {
        if (!$plan) {
            return addMonths(
                $activationDate,
                1
            );
        }

        $days = $plan['billing_days']
            ?? null;

        $cycle = $plan['billing_cycle']
            ?? 'monthly';

        return calculateBillingEndDate(
            $activationDate,
            $cycle,
            $days
        );
    }
}


/*
|--------------------------------------------------------------------------
| PPPoE HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getPppoeAccount')) {
    function getPppoeAccount($id)
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT pa.*,
                   ia.account_number,
                   ia.customer_id,
                   ia.plan_id
            FROM pppoe_accounts pa
            INNER JOIN internet_accounts ia
                ON ia.id = pa.internet_account_id
            WHERE pa.id = ?
        ";

        if ($tenantId) {
            $sql .= " AND pa.tenant_id = ?";
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        if ($tenantId) {
            $stmt->bind_param(
                'ii',
                $id,
                $tenantId
            );
        } else {
            $stmt->bind_param(
                'i',
                $id
            );
        }

        $stmt->execute();

        $account = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return $account ?: null;
    }
}


/*
|--------------------------------------------------------------------------
| MIKROTIK HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getMikrotikRouter')) {
    function getMikrotikRouter($id)
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT *
            FROM mikrotik_routers
            WHERE id = ?
        ";

        if ($tenantId) {
            $sql .= " AND tenant_id = ?";
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        if ($tenantId) {
            $stmt->bind_param(
                'ii',
                $id,
                $tenantId
            );
        } else {
            $stmt->bind_param(
                'i',
                $id
            );
        }

        $stmt->execute();

        $router = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return $router ?: null;
    }
}


if (!function_exists('getRouterStatus')) {
    function getRouterStatus($router)
    {
        if (!$router) {
            return 'offline';
        }

        return $router['status'] ?? 'unknown';
    }
}


/*
|--------------------------------------------------------------------------
| ACTIVE SESSION HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getActiveSessions')) {
    function getActiveSessions(
        $limit = 100
    ) {
        global $conn;

        $tenantId = getCurrentTenantId();

        $limit = max(
            1,
            min(
                (int)$limit,
                500
            )
        );

        $sql = "
            SELECT *
            FROM active_sessions
            WHERE status = 'online'
        ";

        if ($tenantId) {
            $sql .= " AND tenant_id = " .
                (int)$tenantId;
        }

        $sql .= "
            ORDER BY connected_at DESC
            LIMIT {$limit}
        ";

        $result = $conn->query($sql);

        if (!$result) {
            return [];
        }

        $sessions = [];

        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }

        return $sessions;
    }
}


if (!function_exists('countActiveSessions')) {
    function countActiveSessions()
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT COUNT(*) AS total
            FROM active_sessions
            WHERE status = 'online'
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();

        return (int)$row['total'];
    }
}


/*
|--------------------------------------------------------------------------
| AUDIT LOGGING
|--------------------------------------------------------------------------
*/

if (!function_exists('logAudit')) {
    function logAudit(
        $action,
        $module = null,
        $description = null,
        $recordType = null,
        $recordId = null,
        $oldData = null,
        $newData = null
    ) {
        global $conn;

        $userId = getCurrentUserId();
        $tenantId = getCurrentTenantId();

        $ipAddress =
            $_SERVER['REMOTE_ADDR']
            ?? null;

        $userAgent =
            $_SERVER['HTTP_USER_AGENT']
            ?? null;

        $oldJson = $oldData !== null
            ? json_encode(
                $oldData,
                JSON_UNESCAPED_UNICODE
            )
            : null;

        $newJson = $newData !== null
            ? json_encode(
                $newData,
                JSON_UNESCAPED_UNICODE
            )
            : null;

        /*
         * Prefer platform audit log for host activity
         * where the table exists.
         */
        if (
            $tenantId === null &&
            isHostUser()
        ) {
            $stmt = $conn->prepare(
                "INSERT INTO platform_audit_logs
                (
                    tenant_id,
                    user_id,
                    action,
                    module,
                    description,
                    record_type,
                    record_id,
                    old_data,
                    new_data,
                    ip_address,
                    user_agent,
                    created_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            if ($stmt) {

                $stmt->bind_param(
                    'iissssissss',
                    $tenantId,
                    $userId,
                    $action,
                    $module,
                    $description,
                    $recordType,
                    $recordId,
                    $oldJson,
                    $newJson,
                    $ipAddress,
                    $userAgent
                );

                $stmt->execute();
                $stmt->close();

                return true;
            }
        }

        /*
         * Legacy tenant audit table.
         */
        $stmt = $conn->prepare(
            "INSERT INTO audit_logs
            (
                tenant_id,
                user_id,
                action,
                module,
                description,
                record_type,
                record_id,
                old_data,
                new_data,
                ip_address,
                user_agent,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iissssissss',
            $tenantId,
            $userId,
            $action,
            $module,
            $description,
            $recordType,
            $recordId,
            $oldJson,
            $newJson,
            $ipAddress,
            $userAgent
        );

        $success = $stmt->execute();

        $stmt->close();

        return $success;
    }
}


/*
|--------------------------------------------------------------------------
| SERVICE STATUS LOGGING
|--------------------------------------------------------------------------
*/

if (!function_exists('logServiceStatus')) {
    function logServiceStatus(
        $internetAccountId,
        $status,
        $reason = null
    ) {
        global $conn;

        $tenantId = getCurrentTenantId();
        $userId = getCurrentUserId();

        $stmt = $conn->prepare(
            "INSERT INTO service_status_logs
            (
                tenant_id,
                internet_account_id,
                status,
                reason,
                changed_by,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iissi',
            $tenantId,
            $internetAccountId,
            $status,
            $reason,
            $userId
        );

        $success = $stmt->execute();

        $stmt->close();

        return $success;
    }
}


/*
|--------------------------------------------------------------------------
| ACCOUNT TRANSACTIONS
|--------------------------------------------------------------------------
*/

if (!function_exists('recordAccountTransaction')) {
    function recordAccountTransaction(
        $customerId,
        $type,
        $amount,
        $description = null,
        $reference = null,
        $debit = 0,
        $credit = 0
    ) {
        global $conn;

        $tenantId = getCurrentTenantId();

        $amount = cleanMoney($amount);
        $debit = cleanMoney($debit);
        $credit = cleanMoney($credit);

        if (!$reference) {
            $reference = generateReference(
                'TXN'
            );
        }

        $stmt = $conn->prepare(
            "INSERT INTO account_transactions
            (
                tenant_id,
                customer_id,
                transaction_type,
                amount,
                debit,
                credit,
                description,
                reference,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'iisdddss',
            $tenantId,
            $customerId,
            $type,
            $amount,
            $debit,
            $credit,
            $description,
            $reference
        );

        $success = $stmt->execute();

        $stmt->close();

        return $success;
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getPayment')) {
    function getPayment($id)
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $sql = "
            SELECT *
            FROM payments
            WHERE id = ?
        ";

        if ($tenantId) {
            $sql .= " AND tenant_id = ?";
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        if ($tenantId) {
            $stmt->bind_param(
                'ii',
                $id,
                $tenantId
            );
        } else {
            $stmt->bind_param(
                'i',
                $id
            );
        }

        $stmt->execute();

        $payment = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return $payment ?: null;
    }
}


/*
|--------------------------------------------------------------------------
| INVOICE HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('calculateInvoiceStatus')) {
    function calculateInvoiceStatus(
        $total,
        $paid,
        $dueDate = null
    ) {
        $total = cleanMoney($total);
        $paid = cleanMoney($paid);

        if ($paid >= $total && $total > 0) {
            return 'paid';
        }

        if ($paid > 0 && $paid < $total) {
            if (
                $dueDate &&
                strtotime($dueDate) < time()
            ) {
                return 'overdue';
            }

            return 'partially_paid';
        }

        if (
            $dueDate &&
            strtotime($dueDate) < time()
        ) {
            return 'overdue';
        }

        return 'unpaid';
    }
}


/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

if (!function_exists('getDashboardStats')) {
    function getDashboardStats()
    {
        global $conn;

        $tenantId = getCurrentTenantId();

        $stats = [
            'customers' => 0,
            'active_accounts' => 0,
            'expired_accounts' => 0,
            'online_users' => 0,
            'revenue_today' => 0,
            'revenue_month' => 0,
            'payments_today' => 0,
            'outstanding' => 0
        ];

        /*
         * Customers
         */
        $sql = "
            SELECT COUNT(*) AS total
            FROM customers
        ";

        if ($tenantId) {
            $sql .= "
                WHERE tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['customers'] =
                (int)$result
                    ->fetch_assoc()['total'];
        }

        /*
         * Active accounts
         */
        $sql = "
            SELECT COUNT(*) AS total
            FROM internet_accounts
            WHERE status = 'active'
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['active_accounts'] =
                (int)$result
                    ->fetch_assoc()['total'];
        }

        /*
         * Expired accounts
         */
        $sql = "
            SELECT COUNT(*) AS total
            FROM internet_accounts
            WHERE
                status = 'expired'
                OR (
                    expiry_date IS NOT NULL
                    AND expiry_date < CURDATE()
                )
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['expired_accounts'] =
                (int)$result
                    ->fetch_assoc()['total'];
        }

        /*
         * Online users
         */
        $stats['online_users'] =
            countActiveSessions();

        /*
         * Today's revenue
         */
        $sql = "
            SELECT COALESCE(
                SUM(amount),
                0
            ) AS total
            FROM payments
            WHERE status = 'completed'
            AND DATE(payment_date) = CURDATE()
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['revenue_today'] =
                (float)$result
                    ->fetch_assoc()['total'];
        }

        /*
         * Monthly revenue
         */
        $sql = "
            SELECT COALESCE(
                SUM(amount),
                0
            ) AS total
            FROM payments
            WHERE status = 'completed'
            AND YEAR(payment_date) = YEAR(CURDATE())
            AND MONTH(payment_date) = MONTH(CURDATE())
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['revenue_month'] =
                (float)$result
                    ->fetch_assoc()['total'];
        }

        /*
         * Payments today
         */
        $sql = "
            SELECT COUNT(*) AS total
            FROM payments
            WHERE status = 'completed'
            AND DATE(payment_date) = CURDATE()
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['payments_today'] =
                (int)$result
                    ->fetch_assoc()['total'];
        }

        /*
         * Outstanding invoices
         */
        $sql = "
            SELECT COALESCE(
                SUM(
                    GREATEST(
                        total_amount - paid_amount,
                        0
                    )
                ),
                0
            ) AS total
            FROM invoices
            WHERE status NOT IN
                ('paid', 'cancelled')
        ";

        if ($tenantId) {
            $sql .= "
                AND tenant_id = " .
                (int)$tenantId;
        }

        $result = $conn->query($sql);

        if ($result) {
            $stats['outstanding'] =
                (float)$result
                    ->fetch_assoc()['total'];
        }

        return $stats;
    }
}


/*
|--------------------------------------------------------------------------
| AI HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('getAiSetting')) {
    function getAiSetting(
        $key,
        $default = null
    ) {
        return getPlatformSetting(
            'ai_' . $key,
            $default
        );
    }
}


if (!function_exists('isAiEnabled')) {
    function isAiEnabled()
    {
        return filter_var(
            getAiSetting(
                'enabled',
                false
            ),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}


if (!function_exists('buildAiSystemContext')) {
    function buildAiSystemContext()
    {
        $tenant = getCurrentTenant();
        $stats = getDashboardStats();

        $context = [
            'platform' => 'Flexihub Billing System',
            'currency' => getSetting(
                'currency',
                'KES'
            ),
            'timezone' => systemTimezone(),
            'user_id' => getCurrentUserId(),
            'user_scope' => getCurrentUserScope(),
            'tenant_id' => getCurrentTenantId(),
            'tenant' => $tenant,
            'dashboard' => $stats
        ];

        return json_encode(
            $context,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE
        );
    }
}


if (!function_exists('getAiAvailableTools')) {
    function getAiAvailableTools()
    {
        return [
            [
                'name' => 'get_dashboard_stats',
                'description' =>
                    'Get current tenant dashboard statistics.'
            ],
            [
                'name' => 'get_online_users',
                'description' =>
                    'Get currently online internet users.'
            ],
            [
                'name' => 'get_expiring_accounts',
                'description' =>
                    'Get internet accounts approaching expiry.'
            ],
            [
                'name' => 'get_revenue_summary',
                'description' =>
                    'Get tenant revenue statistics.'
            ],
            [
                'name' => 'get_router_status',
                'description' =>
                    'Get MikroTik router status.'
            ],
            [
                'name' => 'get_customer',
                'description' =>
                    'Get customer information.'
            ],
            [
                'name' => 'suspend_account',
                'description' =>
                    'Suspend an internet account. Requires confirmation.'
            ],
            [
                'name' => 'reactivate_account',
                'description' =>
                    'Reactivate an internet account. Requires confirmation.'
            ],
            [
                'name' => 'change_plan',
                'description' =>
                    'Change an internet account plan. Requires confirmation.'
            ]
        ];
    }
}


if (!function_exists('aiActionRequiresConfirmation')) {
    function aiActionRequiresConfirmation(
        $action
    ) {
        $actions = [
            'suspend_account',
            'reactivate_account',
            'change_plan',
            'record_payment',
            'create_invoice',
            'disconnect_user',
            'change_pppoe_password',
            'change_pppoe_profile',
            'restart_router',
            'modify_router',
            'delete_customer',
            'delete_account'
        ];

        return in_array(
            $action,
            $actions,
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| JSON RESPONSES
|--------------------------------------------------------------------------
*/

if (!function_exists('jsonResponse')) {
    function jsonResponse(
        $success,
        $message = '',
        $data = [],
        $statusCode = 200
    ) {
        http_response_code($statusCode);

        header(
            'Content-Type: application/json; charset=utf-8'
        );

        echo json_encode(
            [
                'success' => (bool)$success,
                'message' => $message,
                'data' => $data
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

if (!function_exists('setFlash')) {
    function setFlash(
        $type,
        $message
    ) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}


if (!function_exists('getFlash')) {
    function getFlash()
    {
        if (
            empty($_SESSION['flash'])
        ) {
            return null;
        }

        $flash = $_SESSION['flash'];

        unset(
            $_SESSION['flash']
        );

        return $flash;
    }
}


/*
|--------------------------------------------------------------------------
| VALIDATION HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('isValidEmail')) {
    function isValidEmail($email)
    {
        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) !== false;
    }
}


if (!function_exists('isValidPhone')) {
    function isValidPhone($phone)
    {
        $phone = preg_replace(
            '/[\s\-\(\)]/',
            '',
            $phone
        );

        return preg_match(
            '/^\+?[0-9]{9,15}$/',
            $phone
        ) === 1;
    }
}


/*
|--------------------------------------------------------------------------
| SAFE INPUT
|--------------------------------------------------------------------------
*/

if (!function_exists('post')) {
    function post(
        $key,
        $default = null
    ) {
        return isset($_POST[$key])
            ? trim((string)$_POST[$key])
            : $default;
    }
}


if (!function_exists('getInput')) {
    function getInput(
        $key,
        $default = null
    ) {
        return isset($_GET[$key])
            ? trim((string)$_GET[$key])
            : $default;
    }
}


/*
|--------------------------------------------------------------------------
| URL HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('baseUrl')) {
    function baseUrl()
    {
        $protocol =
            (!empty($_SERVER['HTTPS']) &&
             $_SERVER['HTTPS'] !== 'off')
                ? 'https'
                : 'http';

        $host =
            $_SERVER['HTTP_HOST']
            ?? 'localhost';

        return $protocol .
            '://' .
            $host .
            '/billing_system';
    }
}


if (!function_exists('assetUrl')) {
    function assetUrl($path)
    {
        return rtrim(
            baseUrl(),
            '/'
        ) .
            '/' .
            ltrim(
                $path,
                '/'
            );
    }
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

if (!function_exists('pagination')) {
    function pagination(
        $currentPage,
        $perPage,
        $totalRecords
    ) {
        $currentPage = max(
            1,
            (int)$currentPage
        );

        $perPage = max(
            1,
            (int)$perPage
        );

        $totalPages = max(
            1,
            (int)ceil(
                $totalRecords / $perPage
            )
        );

        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'offset' =>
                ($currentPage - 1) * $perPage
        ];
    }
}


/*
|--------------------------------------------------------------------------
| GENERAL DATABASE HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('dbExecute')) {
    function dbExecute(
        $sql,
        $types = '',
        ...$params
    ) {
        global $conn;

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        if ($types !== '') {
            $stmt->bind_param(
                $types,
                ...$params
            );
        }

        $success = $stmt->execute();

        $insertId = $conn->insert_id;

        $stmt->close();

        return $success
            ? $insertId
            : false;
    }
}


if (!function_exists('dbFetchOne')) {
    function dbFetchOne(
        $sql,
        $types = '',
        ...$params
    ) {
        global $conn;

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return null;
        }

        if ($types !== '') {
            $stmt->bind_param(
                $types,
                ...$params
            );
        }

        $stmt->execute();

        $row = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        return $row ?: null;
    }
}


if (!function_exists('dbFetchAll')) {
    function dbFetchAll(
        $sql,
        $types = '',
        ...$params
    ) {
        global $conn;

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        if ($types !== '') {
            $stmt->bind_param(
                $types,
                ...$params
            );
        }

        $stmt->execute();

        $result = $stmt->get_result();

        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();

        return $rows;
    }
}


/*
|--------------------------------------------------------------------------
| TENANT-SAFE INSERT HELPER
|--------------------------------------------------------------------------
*/

if (!function_exists('tenantData')) {
    function tenantData(array $data)
    {
        if (
            isTenantUser() &&
            !array_key_exists(
                'tenant_id',
                $data
            )
        ) {
            $data['tenant_id'] =
                getCurrentTenantId();
        }

        return $data;
    }
}


/*
|--------------------------------------------------------------------------
| TENANT STATUS
|--------------------------------------------------------------------------
*/

if (!function_exists('canOperateTenant')) {
    function canOperateTenant()
    {
        if (isHostUser()) {
            return true;
        }

        if (!isTenantUser()) {
            return false;
        }

        return isCurrentTenantActive();
    }
}


/*
|--------------------------------------------------------------------------
| APPLICATION HEALTH
|--------------------------------------------------------------------------
*/

if (!function_exists('getSystemHealth')) {
    function getSystemHealth()
    {
        global $conn;

        $health = [
            'database' => false,
            'session' => session_status() === PHP_SESSION_ACTIVE,
            'php_version' => PHP_VERSION,
            'time' => systemDateTime()
        ];

        if ($conn instanceof mysqli) {
            $health['database'] =
                $conn->ping();
        }

        return $health;
    }
}


if (!function_exists('getCompanyName')) {

    function getCompanyName()
    {
        global $conn;

        /*
        | First try platform settings.
        */

        if ($conn instanceof mysqli) {

            $stmt = $conn->prepare("
                SELECT setting_value
                FROM platform_settings
                WHERE setting_key = 'company_name'
                LIMIT 1
            ");

            if ($stmt) {

                $stmt->execute();

                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {

                    $stmt->close();

                    if (!empty($row['setting_value'])) {
                        return $row['setting_value'];
                    }
                }

                $stmt->close();
            }
        }

        /*
        | Fallback.
        */

        return 'Flexihub';
    }
}


if (!function_exists('getCurrency')) {

    function getCurrency()
    {
        return getSetting(
            'currency',
            'KES'
        );
    }
}


if (!function_exists('getSystemTimezone')) {

    function getSystemTimezone()
    {
        return getSetting(
            'timezone',
            'Africa/Nairobi'
        );
    }
}


if (!function_exists('getUserDisplayName')) {

    function getUserDisplayName()
    {
        global $conn;

        $userId = function_exists('getCurrentUserId')
            ? getCurrentUserId()
            : null;

        if (!$userId) {
            return 'User';
        }

        $stmt = $conn->prepare("
            SELECT
                u.username,
                s.first_name,
                s.last_name
            FROM users u
            LEFT JOIN staffs s
                ON s.id = u.staff_id
            WHERE u.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return 'User';
        }

        $stmt->bind_param(
            'i',
            $userId
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $user = $result->fetch_assoc();

        $stmt->close();

        if (!$user) {
            return 'User';
        }

        $name = trim(
            ($user['first_name'] ?? '') .
            ' ' .
            ($user['last_name'] ?? '')
        );

        return $name !== ''
            ? $name
            : ($user['username'] ?? 'User');
    }
}


if (!function_exists('getLoggedInUserName')) {

    function getLoggedInUserName()
    {
        return getUserDisplayName();
    }
}

if (!function_exists('updateUserActivity')) {
    function updateUserActivity()
    {
        global $conn;
        $userId = getCurrentUserId();

        if (!$userId || !($conn instanceof mysqli)) {
            return false;
        }

        $stmt = $conn->prepare("
            UPDATE users
            SET last_activity_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
