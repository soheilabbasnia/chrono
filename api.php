<?php
declare(strict_types=1);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
if ($isHttps) {
    ini_set('session.cookie_secure', '1');
}

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $json !== false ? $json : '{"error":"خطای داخلی سرور"}';
    exit;
}

function inputString(array $input, string $key, bool $trim = true): string {
    $value = $input[$key] ?? '';
    if (!is_string($value)) return '';
    return $trim ? trim($value) : $value;
}

function cleanTask(string $task): string {
    $task = trim($task);
    return function_exists('mb_substr') ? mb_substr($task, 0, 20000) : substr($task, 0, 20000);
}

set_exception_handler(function (Throwable $e): void {
    if (function_exists('logDebug')) {
        logDebug('خطای پیش‌بینی‌نشده: ' . $e->getMessage(), ['file' => basename($e->getFile()), 'line' => $e->getLine()]);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '{"error":"خطای داخلی سرور"}';
});

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sessions.php';
require_once __DIR__ . '/includes/users.php';

$db = getDbConnection();
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput, true);
$input = is_array($decodedInput) ? $decodedInput : [];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'login' && $method === 'POST') {
    handleLogin($db, $input);
}
if ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        logUserActivity((int)$_SESSION['user_id'], 'LOGOUT', 'کاربر خارج شد');
    }
    handleLogout();
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'لطفاً ابتدا وارد سیستم شوید'], 401);
}

$currentUserId = (int)$_SESSION['user_id'];

$stmtAuth = $db->prepare("SELECT role FROM users WHERE id = :id");
$stmtAuth->execute([':id' => $currentUserId]);
$dbRole = $stmtAuth->fetchColumn();
if ($dbRole === false) {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['error' => 'لطفاً ابتدا وارد سیستم شوید'], 401);
}
$currentUserRole = (string)$dbRole;

session_write_close();

switch ($action) {
    case 'me':
        handleGetCurrentUser($db, $currentUserId, $currentUserRole);
        break;

    case 'get_sessions':
        handleGetSessions($db, $currentUserId);
        break;

    case 'start_session':
        logUserActivity($currentUserId, 'START_SESSION', 'شروع نوبت: ' . inputString($input, 'task'));
        handleStartSession($db, $currentUserId, $input);
        break;

    case 'stop_session':
        logUserActivity($currentUserId, 'STOP_SESSION', 'اتمام نوبت کاری');
        handleStopSession($db, $currentUserId, $input);
        break;

    case 'update_session':
        logUserActivity($currentUserId, 'UPDATE_SESSION', 'ویرایش نوبت شناسه: ' . (is_scalar($input['id'] ?? null) ? $input['id'] : ''));
        handleUpdateSession($db, $currentUserId, $currentUserRole, $input);
        break;

    case 'delete_session':
        logUserActivity($currentUserId, 'DELETE_SESSION', 'حذف نوبت شناسه: ' . (is_scalar($input['id'] ?? null) ? $input['id'] : ''));
        handleDeleteSession($db, $currentUserId, $currentUserRole, $input);
        break;

    case 'get_visible_users':
        handleGetVisibleUsers($db, $currentUserId, $currentUserRole);
        break;

    case 'get_user_report':
        handleGetUserReport($db, $currentUserId, $currentUserRole);
        break;

    case 'change_password':
        logUserActivity($currentUserId, 'CHANGE_PASSWORD', 'درخواست تغییر رمز برای کاربر: ' . (is_scalar($input['user_id'] ?? null) ? $input['user_id'] : $currentUserId));
        handleChangePassword($db, $currentUserId, $currentUserRole, $input);
        break;

    case 'admin_create_user':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'CREATE_USER', 'ایجاد همکار جدید: ' . inputString($input, 'username'));
        handleCreateUser($db, $input);
        break;

    case 'admin_delete_user':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'DELETE_USER', 'حذف کاربر شناسه: ' . (is_scalar($input['user_id'] ?? null) ? $input['user_id'] : ''));
        handleDeleteUser($db, $currentUserId, $input);
        break;

    case 'admin_save_permissions':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'SAVE_PERMISSIONS', 'به‌روزرسانی دسترسی‌های کاربر شناسه: ' . (is_scalar($input['viewer_id'] ?? null) ? $input['viewer_id'] : ''));
        handleSavePermissions($db, $input);
        break;

    case 'export_user_json':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'EXPORT_JSON', 'دریافت خروجی بکاپ برای کاربر شناسه: ' . ($_GET['user_id'] ?? ''));
        handleExportUserJson($db, $input);
        break;

    case 'import_user_json':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'IMPORT_JSON', 'بازیابی بکاپ برای کاربر شناسه: ' . (is_scalar($input['user_id'] ?? null) ? $input['user_id'] : ''));
        handleImportUserJson($db, $input);
        break;

    default:
        logDebug("اکشن نامعتبر دریافت شد: " . (is_string($action) ? $action : ''), ['user_id' => $currentUserId]);
        jsonResponse(['error' => 'اکشن نامعتبر است'], 404);
}
