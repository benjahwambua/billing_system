<?php

/**
 * Flexihub Billing System
 * Authentication / Session Utilities
 *
 * IMPORTANT:
 * Shared user-context functions are maintained in functions.php.
 *
 * This file does NOT redeclare:
 * - getCurrentUserId()
 * - getCurrentTenantId()
 * - getCurrentUserScope()
 * - isHostUser()
 * - isTenantUser()
 * - requireHost()
 * - requireTenant()
 * - requirePermission()
 * - getLoggedInUserName()
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| LOAD DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION CHECK
|--------------------------------------------------------------------------
*/

/**
 * Get the active database connection.
 *
 * The project uses $conn as the standard MySQLi connection.
 *
 * @return mysqli
 */
function authDatabase()
{
    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {
        http_response_code(500);

        exit(
            "Database connection is not available. " .
            "Please check config/database.php."
        );
    }

    /*
     * Check whether MySQLi connection is still alive.
     */
    if ($conn->connect_errno) {
        http_response_code(500);

        exit(
            "Database connection failed: " .
            $conn->connect_error
        );
    }

    return $conn;
}


/*
|--------------------------------------------------------------------------
| LOGIN STATUS
|--------------------------------------------------------------------------
*/

function isLoggedIn()
{
    return !empty($_SESSION['user_id']);
}


/*
|--------------------------------------------------------------------------
| REQUIRE LOGIN
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: ../auth/login.php");
        exit;
    }

    /*
     * updateUserActivity() belongs to functions.php
     * when available.
     */
    if (function_exists('updateUserActivity')) {
        updateUserActivity();
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

function getCurrentUser()
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $conn = authDatabase();

    $userId = (int) $_SESSION['user_id'];

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

    $stmt->bind_param("i", $userId);

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $result = $stmt->get_result();

    $user = $result->fetch_assoc();

    $stmt->close();

    return $user ?: null;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER ACTIVE
|--------------------------------------------------------------------------
*/

function isCurrentUserActive()
{
    $user = getCurrentUser();

    if (!$user) {
        return false;
    }

    return strtolower(
        trim($user['status'] ?? '')
    ) === 'active';
}


/*
|--------------------------------------------------------------------------
| REQUIRE ACTIVE USER
|--------------------------------------------------------------------------
*/

function requireActiveUser()
{
    requireLogin();

    if (!isCurrentUserActive()) {

        http_response_code(403);

        exit(
            "Your user account is inactive. Please contact the administrator."
        );
    }
}


/*
|--------------------------------------------------------------------------
| SESSION SECURITY
|--------------------------------------------------------------------------
*/

function regenerateLoginSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}


/*
|--------------------------------------------------------------------------
| CREATE LOGIN SESSION
|--------------------------------------------------------------------------
*/

function createLoginSession($userId)
{
    $conn = authDatabase();

    $userId = (int) $userId;

    if ($userId <= 0) {
        return null;
    }

    /*
     * Check whether user_sessions exists.
     */
    $check = $conn->query(
        "SHOW TABLES LIKE 'user_sessions'"
    );

    if (!$check || $check->num_rows === 0) {
        return null;
    }

    $sessionToken = bin2hex(
        random_bytes(32)
    );

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO user_sessions
        (
            user_id,
            session_token,
            ip_address,
            user_agent,
            last_activity_at,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            NOW(),
            NOW()
        )
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param(
        "isss",
        $userId,
        $sessionToken,
        $ipAddress,
        $userAgent
    );

    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }

    $stmt->close();

    $_SESSION['session_token'] = $sessionToken;

    return $sessionToken;
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

function logoutUser($databaseConnection = null)
{
    global $conn;

    /*
     * If a connection was explicitly supplied,
     * use it. Otherwise validate the global connection.
     */
    if ($databaseConnection instanceof mysqli) {
        $conn = $databaseConnection;
    } else {
        $conn = authDatabase();
    }

    /*
     * Use the existing helper from functions.php.
     */
    if (function_exists('getCurrentUserId')) {
        $userId = getCurrentUserId();
    } else {
        $userId = $_SESSION['user_id'] ?? null;
    }

    $userId = $userId
        ? (int) $userId
        : null;


    /*
     * Revoke persistent login session.
     */
    if (
        $userId &&
        !empty($_SESSION['session_token'])
    ) {

        $check = $conn->query(
            "SHOW TABLES LIKE 'user_sessions'"
        );

        if (
            $check &&
            $check->num_rows > 0
        ) {

            $stmt = $conn->prepare("
                UPDATE user_sessions
                SET revoked_at = NOW()
                WHERE session_token = ?
                  AND user_id = ?
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "si",
                    $_SESSION['session_token'],
                    $userId
                );

                $stmt->execute();

                $stmt->close();
            }
        }
    }


    /*
     * Audit logout if available.
     */
    if (
        $userId &&
        function_exists('logAudit')
    ) {

        try {

            logAudit(
                $conn,
                'logout',
                'users',
                $userId,
                null,
                'User logged out'
            );

        } catch (Throwable $e) {
            /*
             * Do not prevent logout because
             * audit logging failed.
             */
        }
    }


    /*
     * Clear session.
     */
    $_SESSION = [];


    /*
     * Remove session cookie.
     */
    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }


    /*
     * Destroy session.
     */
    session_destroy();


    /*
     * Return to login.
     */
    header(
        "Location: ../auth/login.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER DISPLAY NAME
|--------------------------------------------------------------------------
*/

function getCurrentUserDisplayName()
{
    if (function_exists('getCurrentUserId')) {
        $userId = getCurrentUserId();
    } else {
        $userId = $_SESSION['user_id'] ?? null;
    }

    if (!$userId) {
        return 'Guest';
    }

    $conn = authDatabase();

    $userId = (int) $userId;

    $stmt = $conn->prepare("
        SELECT
            COALESCE(
                NULLIF(
                    TRIM(
                        CONCAT_WS(
                            ' ',
                            s.first_name,
                            s.last_name
                        )
                    ),
                    ''
                ),
                u.username
            ) AS display_name

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
        "i",
        $userId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        return 'User';
    }

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    return !empty($row['display_name'])
        ? $row['display_name']
        : 'User';
}