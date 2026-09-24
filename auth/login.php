<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';
$username = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

  

    if ($username === '' || $password === '') {

        $error = 'Please enter your username and password.';

    } else {

   

        $stmt = $conn->prepare("
            SELECT
                id,
                tenant_id,
                user_scope,
                staff_id,
                username,
                password,
                role,
                status
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {

            $error = 'Unable to process login. Database error.';

        } else {

            $stmt->bind_param('s', $username);

            if (!$stmt->execute()) {

                $error = 'Unable to process login. Database error.';

                $stmt->close();

            } else {

                $result = $stmt->get_result();

                $user = $result->fetch_assoc();

                $stmt->close();

               

                if (!$user) {

                    $error = 'Invalid username or password.';

                }

             

                elseif (($user['status'] ?? '') !== 'Active') {

                    $error =
                        'Your account is inactive. Please contact the administrator.';

                }

                else {

                   

                    $passwordValid = false;

                    if (!empty($user['password'])) {

                      

                        $passwordValid = password_verify(
                            $password,
                            $user['password']
                        );

                        

                        if (
                            !$passwordValid &&
                            hash_equals(
                                (string) $user['password'],
                                (string) $password
                            )
                        ) {

                            $newHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $passwordUpdate = $conn->prepare("
                                UPDATE users
                                SET password = ?
                                WHERE id = ?
                            ");

                            if ($passwordUpdate) {

                                $userId = (int) $user['id'];

                                $passwordUpdate->bind_param(
                                    'si',
                                    $newHash,
                                    $userId
                                );

                                if ($passwordUpdate->execute()) {
                                    $passwordValid = true;
                                }

                                $passwordUpdate->close();
                            }
                        }
                    }

                   

                    if (!$passwordValid) {

                        $error = 'Invalid username or password.';

                    }

                    else {

                       

                        $userScope = $user['user_scope'] ?? 'tenant';

                        $tenantId = null;

                        if (
                            isset($user['tenant_id']) &&
                            $user['tenant_id'] !== null &&
                            $user['tenant_id'] !== ''
                        ) {
                            $tenantId = (int) $user['tenant_id'];
                        }

                      

                        if ($userScope === 'host') {

                            $tenantId = null;

                        }

                      

                        if ($userScope === 'tenant' && !$tenantId) {

                            $error =
                                'This user is not assigned to an organisation.';

                        }

                  

                        if (empty($error) && $userScope === 'tenant') {

                            $tenantStmt = $conn->prepare("
                                SELECT
                                    id,
                                    tenant_code,
                                    name,
                                    status
                                FROM tenants
                                WHERE id = ?
                                LIMIT 1
                            ");

                            if (!$tenantStmt) {

                                $error =
                                    'Unable to verify your organisation account.';

                            } else {

                                $tenantStmt->bind_param(
                                    'i',
                                    $tenantId
                                );

                                if (!$tenantStmt->execute()) {

                                    $error =
                                        'Unable to verify your organisation account.';

                                } else {

                                    $tenantResult =
                                        $tenantStmt->get_result();

                                    $tenant =
                                        $tenantResult->fetch_assoc();

                                    if (!$tenant) {

                                        $error =
                                            'The organisation assigned to this user does not exist.';

                                    } elseif (
                                        !in_array(
                                            strtolower(
                                                trim(
                                                    $tenant['status'] ?? ''
                                                )
                                            ),
                                            ['active', 'trial'],
                                            true
                                        )
                                    ) {

                                        $error =
                                            'Your organisation account is not active.';
                                    }
                                }

                                $tenantStmt->close();
                            }
                        }

                        

                        if (empty($error)) {

                            

                            session_regenerate_id(true);

                        

                            $_SESSION['user_id'] =
                                (int) $user['id'];

                            $_SESSION['tenant_id'] =
                                $tenantId;

                            $_SESSION['user_scope'] =
                                $userScope;

                            $_SESSION['staff_id'] =
                                !empty($user['staff_id'])
                                    ? (int) $user['staff_id']
                                    : null;

                            $_SESSION['username'] =
                                $user['username'];

                            $_SESSION['legacy_role'] =
                                $user['role'] ?? null;

                           

                            $_SESSION['roles'] = [];

                         

                            $roleStmt = $conn->prepare("
                                SELECT r.role_code
                                FROM user_roles ur
                                INNER JOIN roles r
                                    ON r.id = ur.role_id
                                WHERE ur.user_id = ?
                                AND r.status = 'active'
                            ");

                            if ($roleStmt) {

                                $userId = (int) $user['id'];

                                if ($roleStmt->bind_param('i', $userId)) {

                                    if ($roleStmt->execute()) {

                                        $roleResult =
                                            $roleStmt->get_result();

                                        while (
                                            $role =
                                                $roleResult->fetch_assoc()
                                        ) {

                                            if (
                                                !empty(
                                                    $role['role_code']
                                                )
                                            ) {

                                                $_SESSION['roles'][] =
                                                    $role['role_code'];
                                            }
                                        }
                                    }
                                }

                                $roleStmt->close();
                            }

                        

                            if (
                                $userScope === 'host' &&
                                empty($_SESSION['roles']) &&
                                ($user['role'] ?? '') === 'Administrator'
                            ) {

                                $_SESSION['roles'][] =
                                    'HOST_SUPER_ADMIN';
                            }

                           

                            $userId = (int) $user['id'];

                            $update = $conn->prepare("
                                UPDATE users
                                SET
                                    last_login = NOW(),
                                    last_activity_at = NOW()
                                WHERE id = ?
                            ");

                            if ($update) {

                                $update->bind_param(
                                    'i',
                                    $userId
                                );

                                $update->execute();

                                $update->close();
                            }

                           

                            try {

                                $sessionToken =
                                    bin2hex(
                                        random_bytes(32)
                                    );

                                $sessionHash =
                                    hash(
                                        'sha256',
                                        $sessionToken
                                    );

                                $ipAddress =
                                    $_SERVER['REMOTE_ADDR']
                                    ?? null;

                                $userAgent =
                                    $_SERVER['HTTP_USER_AGENT']
                                    ?? null;

                                $sessionStmt = $conn->prepare("
                                    INSERT INTO user_sessions
                                    (
                                        user_id,
                                        tenant_id,
                                        session_token,
                                        ip_address,
                                        user_agent,
                                        last_activity_at,
                                        expires_at,
                                        created_at
                                    )
                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        ?,
                                        NOW(),
                                        DATE_ADD(NOW(), INTERVAL 1 DAY),
                                        NOW()
                                    )
                                ");

                                if ($sessionStmt) {

                                    $sessionStmt->bind_param(
                                        'iisss',
                                        $userId,
                                        $tenantId,
                                        $sessionHash,
                                        $ipAddress,
                                        $userAgent
                                    );

                                    $sessionStmt->execute();

                                    $sessionStmt->close();
                                }

                            } catch (Throwable $sessionError) {

                               
                            }

                       

                            try {

                                if (function_exists('logAudit')) {

                                    logAudit(
                                        'LOGIN',
                                        'AUTH',
                                        'User logged into Flexihub.',
                                        'user',
                                        $userId
                                    );
                                }

                            } catch (Throwable $auditError) {

                                /*
                                | Do nothing.
                                | Login should still succeed.
                                */

                            }

                          

                            header(
                                'Location: ../dashboard/index.php'
                            );

                            exit;
                        }
                    }
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login - Flexihub Billing System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f4f6f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 35px;
            box-shadow:
                0 10px 30px
                rgba(0, 0, 0, 0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo h1 {
            margin: 0;
            font-size: 30px;
            color: #1d4ed8;
        }

        .logo p {
            margin-top: 7px;
            color: #6b7280;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #9ca3af;
        }

    </style>

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <div class="logo">

            <h1>Flexihub</h1>

            <p>
                Billing & ISP Management System
            </p>

        </div>

        <?php if ($error): ?>

            <div class="error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($username) ?>"
                    autocomplete="username"
                    required
                    autofocus
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button type="submit">
                Sign In
            </button>

        </form>

        <div class="footer">
            Flexihub Billing System
        </div>

    </div>

</div>

</body>

</html>
