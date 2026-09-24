<?php

/**
 * Flexihub Billing System
 * Authentication and session utilities.
 *
 * Shared user/tenant/permission helpers live in functions.php.
 * This file must not redeclare them.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return !empty($_SESSION['user_id']);
    }
}


if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (!isLoggedIn()) {
            header('Location: ../auth/login.php');
            exit;
        }

        if (function_exists('updateUserActivity')) {
            updateUserActivity();
        }
    }
}


if (!function_exists('getCurrentUser')) {
    function getCurrentUser()
    {
        global $conn;

        $userId = function_exists('getCurrentUserId')
            ? getCurrentUserId()
            : ($_SESSION['user_id'] ?? null);

        if (!$userId || !($conn instanceof mysqli)) {
            return null;
        }

        $stmt = $conn->prepare("
            SELECT
                id,
                tenant_id,
                user_scope,
                staff_id,
                username,
                role,
                status,
                last_login,
                last_activity_at
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        $userId = (int)$userId;
        $stmt->bind_param('i', $userId);

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user ?: null;
    }
}


if (!function_exists('isCurrentUserActive')) {
    function isCurrentUserActive()
    {
        $user = getCurrentUser();

        return $user &&
            strtolower(trim($user['status'] ?? '')) === 'active';
    }
}


if (!function_exists('requireActiveUser')) {
    function requireActiveUser()
    {
        requireLogin();

        if (!isCurrentUserActive()) {
            http_response_code(403);
            exit('Your user account is inactive. Please contact the administrator.');
        }
    }
}


if (!function_exists('regenerateLoginSession')) {
    function regenerateLoginSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}


if (!function_exists('createLoginSession')) {
    function createLoginSession($userId)
    {
        global $conn;

        $userId = (int)$userId;

        if ($userId <= 0 || !($conn instanceof mysqli)) {
            return null;
        }

        $tenantId = function_exists('getCurrentTenantId')
            ? getCurrentTenantId()
            : null;

        $sessionToken = bin2hex(random_bytes(32));
        $sessionHash = hash('sha256', $sessionToken);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $check = $conn->query("SHOW TABLES LIKE 'user_sessions'");

        if (!$check || $check->num_rows === 0) {
            return null;
        }

        $stmt = $conn->prepare("
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
            VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param(
            'iisss',
            $userId,
            $tenantId,
            $sessionHash,
            $ipAddress,
            $userAgent
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $stmt->close();

        $_SESSION['session_token'] = $sessionHash;

        return $sessionHash;
    }
}


if (!function_exists('logoutUser')) {
    function logoutUser($databaseConnection = null)
    {
        global $conn;

        if ($databaseConnection instanceof mysqli) {
            $conn = $databaseConnection;
        }

        $userId = function_exists('getCurrentUserId')
            ? getCurrentUserId()
            : ($_SESSION['user_id'] ?? null);

        $userId = $userId ? (int)$userId : null;

        if (
            $userId &&
            !empty($_SESSION['session_token']) &&
            $conn instanceof mysqli
        ) {
            $check = $conn->query("SHOW TABLES LIKE 'user_sessions'");

            if ($check && $check->num_rows > 0) {
                $stmt = $conn->prepare("
                    UPDATE user_sessions
                    SET revoked_at = NOW()
                    WHERE session_token = ?
                      AND user_id = ?
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        'si',
                        $_SESSION['session_token'],
                        $userId
                    );
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        if ($userId && function_exists('logAudit') && $conn instanceof mysqli) {
            try {
                logAudit(
                    'LOGOUT',
                    'AUTH',
                    'User logged out of Flexihub.',
                    'user',
                    $userId
                );
            } catch (Throwable $e) {
                // Audit failure must never block logout.
            }
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        header('Location: ../auth/login.php');
        exit;
    }
}


if (!function_exists('getCurrentUserDisplayName')) {
    function getCurrentUserDisplayName()
    {
        global $conn;

        $userId = function_exists('getCurrentUserId')
            ? getCurrentUserId()
            : ($_SESSION['user_id'] ?? null);

        if (!$userId || !($conn instanceof mysqli)) {
            return 'Guest';
        }

        $stmt = $conn->prepare("
            SELECT
                COALESCE(
                    NULLIF(
                        TRIM(CONCAT_WS(' ', s.first_name, s.last_name)),
                        ''
                    ),
                    u.username
                ) AS display_name
            FROM users u
            LEFT JOIN staffs s ON s.id = u.staff_id
            WHERE u.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return 'User';
        }

        $userId = (int)$userId;
        $stmt->bind_param('i', $userId);

        if (!$stmt->execute()) {
            $stmt->close();
            return 'User';
        }

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return !empty($row['display_name'])
            ? $row['display_name']
            : 'User';
    }
}
