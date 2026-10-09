<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sessions.php';
require_once __DIR__ . '/includes/users.php';

$db = getDbConnection();
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// درخواست‌های بدون نیاز به احراز هویت
if ($action === 'login' && $method === 'POST') {
    handleLogin($db, $input);
}
if ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        logUserActivity((int)$_SESSION['user_id'], 'LOGOUT', 'کاربر خارج شد');
    }
    handleLogout();
}

// بررسی احراز هویت
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'لطفاً ابتدا وارد سیستم شوید'], 401);
}

$currentUserId = (int)$_SESSION['user_id'];
$currentUserRole = (string)$_SESSION['role'];

// مسیریابی اکشن‌ها
switch ($action) {
    case 'me':
        handleGetCurrentUser($db, $currentUserId, $currentUserRole);
        break;

    case 'get_sessions':
        handleGetSessions($db, $currentUserId);
        break;

    case 'start_session':
        logUserActivity($currentUserId, 'START_SESSION', 'شروع نوبت: ' . ($input['task'] ?? 'بدون عنوان'));
        handleStartSession($db, $currentUserId, $input);
        break;

    case 'stop_session':
        logUserActivity($currentUserId, 'STOP_SESSION', 'اتمام نوبت کاری');
        handleStopSession($db, $currentUserId, $input);
        break;

    case 'update_session':
        logUserActivity($currentUserId, 'UPDATE_SESSION', 'ویرایش نوبت شناسه: ' . ($input['id'] ?? ''));
        handleUpdateSession($db, $currentUserId, $currentUserRole, $input);
        break;

    case 'delete_session':
        logUserActivity($currentUserId, 'DELETE_SESSION', 'حذف نوبت شناسه: ' . ($input['id'] ?? ''));
        handleDeleteSession($db, $currentUserId, $currentUserRole, $input);
        break;

    case 'get_visible_users':
        handleGetVisibleUsers($db, $currentUserId, $currentUserRole);
        break;

    case 'get_user_report':
        handleGetUserReport($db, $currentUserId, $currentUserRole);
        break;

    case 'change_password':
        logUserActivity($currentUserId, 'CHANGE_PASSWORD', 'درخواست تغییر رمز برای کاربر: ' . ($input['user_id'] ?? $currentUserId));
        handleChangePassword($db, $currentUserId, $currentUserRole, $input);
        break;

    // اکشن‌های مدیر
    case 'admin_create_user':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'CREATE_USER', 'ایجاد همکار جدید: ' . ($input['username'] ?? ''));
        handleCreateUser($db, $input);
        break;

    case 'admin_delete_user':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'DELETE_USER', 'حذف کاربر شناسه: ' . ($input['user_id'] ?? ''));
        handleDeleteUser($db, $currentUserId, $input);
        break;

    case 'admin_save_permissions':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'SAVE_PERMISSIONS', 'به‌روزرسانی دسترسی‌های کاربر شناسه: ' . ($input['viewer_id'] ?? ''));
        handleSavePermissions($db, $input);
        break;

    case 'export_user_json':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'EXPORT_JSON', 'دریافت خروجی بکاپ برای کاربر شناسه: ' . ($_GET['user_id'] ?? ''));
        handleExportUserJson($db, $input);
        break;

    case 'import_user_json':
        if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
        logUserActivity($currentUserId, 'IMPORT_JSON', 'بازیابی بکاپ برای کاربر شناسه: ' . ($input['user_id'] ?? ''));
        handleImportUserJson($db, $input);
        break;

    default:
        logDebug("اکشن نامعتبر دریافت شد: {$action}", ['user_id' => $currentUserId]);
        jsonResponse(['error' => 'اکشن نامعتبر است'], 404);
}