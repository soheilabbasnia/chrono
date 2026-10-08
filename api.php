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

try {
    $db = new PDO('sqlite:' . __DIR__ . '/chrono.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON;');
} catch (Exception $e) {
    jsonResponse(['error' => 'خطا در اتصال به دیتابیس'], 500);
}

$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT CHECK(role IN ('manager', 'partner')) NOT NULL DEFAULT 'partner',
        created_at INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS report_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        viewer_id INTEGER NOT NULL,
        target_id INTEGER NOT NULL,
        FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (viewer_id, target_id)
    );

    CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        startTime INTEGER NOT NULL,
        endTime INTEGER,
        task TEXT,
        is_active INTEGER DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
");

$userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, full_name, role, created_at)
        VALUES (:username, :hash, :full_name, 'manager', :time)
    ");
    $stmt->execute([
        ':username' => 'admin',
        ':hash' => password_hash('admin1234', PASSWORD_BCRYPT),
        ':full_name' => 'مدیر سیستم',
        ':time' => time()
    ]);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'login' && $method === 'POST') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        jsonResponse(['error' => 'نام کاربری و رمز عبور الزامی است'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'نام کاربری یا رمز عبور اشتباه است'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    jsonResponse([
        'message' => 'ورود موفقیت‌آمیز بود',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'created_at' => $user['created_at']
        ]
    ]);
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    jsonResponse(['message' => 'خروج انجام شد']);
}

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'لطفاً وارد سیستم شوید'], 401);
}

$currentUserId = (int)$_SESSION['user_id'];
$currentUserRole = $_SESSION['role'];

if ($action === 'me' && $method === 'GET') {
    $stmt = $db->prepare("SELECT id, username, full_name, role, created_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $currentUserId]);
    $me = $stmt->fetch();
    if (!$me) jsonResponse(['error' => 'کاربر یافت نشد'], 404);
    
    $permCount = 0;
    if ($currentUserRole !== 'manager') {
        $stmtP = $db->prepare("SELECT COUNT(*) FROM report_permissions WHERE viewer_id = :uid");
        $stmtP->execute([':uid' => $currentUserId]);
        $permCount = (int)$stmtP->fetchColumn();
    }
    
    jsonResponse(['user' => $me, 'can_view_users' => ($currentUserRole === 'manager' || $permCount > 0)]);
}

// تغییر رمز عبور
if ($action === 'change_password' && $method === 'POST') {
    $targetId = (int)($input['user_id'] ?? $currentUserId);
    $oldPass = $input['old_password'] ?? '';
    $newPass = $input['new_password'] ?? '';

    if (empty($newPass)) {
        jsonResponse(['error' => 'رمز عبور جدید نمی‌تواند خالی باشد'], 400);
    }

    if ($targetId !== $currentUserId && $currentUserRole !== 'manager') {
        jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
    }

    if ($targetId === $currentUserId && $currentUserRole !== 'manager') {
        $stmtCheck = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmtCheck->execute([':id' => $currentUserId]);
        $currHash = $stmtCheck->fetchColumn();
        if (!$currHash || !password_verify($oldPass, $currHash)) {
            jsonResponse(['error' => 'رمز عبور فعلی نادرست است'], 400);
        }
    }

    $stmtUpdate = $db->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
    $stmtUpdate->execute([
        ':p' => password_hash($newPass, PASSWORD_BCRYPT),
        ':id' => $targetId
    ]);

    jsonResponse(['status' => 'success']);
}

// پشتیبان‌گیری JSON اختصاصی کاربر (فقط مدیر)
if ($action === 'export_user_json' && $method === 'GET') {
    if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
    $targetId = (int)($_GET['user_id'] ?? 0);
    
    $stmtU = $db->prepare("SELECT id, username, full_name, role, created_at FROM users WHERE id = :id");
    $stmtU->execute([':id' => $targetId]);
    $uData = $stmtU->fetch();
    if (!$uData) jsonResponse(['error' => 'کاربر یافت نشد'], 404);

    $stmtS = $db->prepare("SELECT id, startTime, endTime, task, is_active FROM sessions WHERE user_id = :id ORDER BY startTime ASC");
    $stmtS->execute([':id' => $targetId]);
    $sessions = $stmtS->fetchAll();

    jsonResponse([
        'export_time' => time(),
        'user' => $uData,
        'sessions' => $sessions
    ]);
}

// بازیابی JSON اختصاصی کاربر (فقط مدیر)
if ($action === 'import_user_json' && $method === 'POST') {
    if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
    $targetId = (int)($input['user_id'] ?? 0);
    $sessions = $input['sessions'] ?? [];

    if (!$targetId || !is_array($sessions)) {
        jsonResponse(['error' => 'داده‌های فایل نامعتبر است'], 400);
    }

    $db->prepare("DELETE FROM sessions WHERE user_id = :uid")->execute([':uid' => $targetId]);

    $stmtIns = $db->prepare("
        INSERT INTO sessions (id, user_id, startTime, endTime, task, is_active)
        VALUES (:id, :uid, :startTime, :endTime, :task, :is_active)
    ");

    foreach ($sessions as $s) {
        $stmtIns->execute([
            ':id' => (int)$s['id'],
            ':uid' => $targetId,
            ':startTime' => (int)$s['startTime'],
            ':endTime' => !empty($s['endTime']) ? (int)$s['endTime'] : null,
            ':task' => $s['task'] ?? '',
            ':is_active' => (int)($s['is_active'] ?? 0)
        ]);
    }

    jsonResponse(['status' => 'success']);
}

if ($action === 'get_sessions' && $method === 'GET') {
    $stmt = $db->prepare("SELECT id, startTime, endTime, task FROM sessions WHERE user_id = :uid AND is_active = 0 ORDER BY startTime ASC");
    $stmt->execute([':uid' => $currentUserId]);
    $sessions = $stmt->fetchAll();

    $stmtActive = $db->prepare("SELECT id, startTime, task FROM sessions WHERE user_id = :uid AND is_active = 1 LIMIT 1");
    $stmtActive->execute([':uid' => $currentUserId]);
    $activeSession = $stmtActive->fetch() ?: null;

    jsonResponse([
        'sessions' => $sessions,
        'activeSession' => $activeSession
    ]);
}

if ($action === 'start_session' && $method === 'POST') {
    $task = trim($input['task'] ?? '');
    $id = (int)($input['id'] ?? (microtime(true) * 1000));
    $startTime = (int)($input['startTime'] ?? (microtime(true) * 1000));

    $db->prepare("DELETE FROM sessions WHERE user_id = :uid AND is_active = 1")->execute([':uid' => $currentUserId]);

    $stmt = $db->prepare("
        INSERT INTO sessions (id, user_id, startTime, endTime, task, is_active)
        VALUES (:id, :uid, :startTime, NULL, :task, 1)
    ");
    $stmt->execute([
        ':id' => $id,
        ':uid' => $currentUserId,
        ':startTime' => $startTime,
        ':task' => $task
    ]);

    jsonResponse(['status' => 'success', 'session' => ['id' => $id, 'startTime' => $startTime, 'task' => $task]]);
}

if ($action === 'stop_session' && $method === 'POST') {
    $endTime = (int)($input['endTime'] ?? (microtime(true) * 1000));

    $stmt = $db->prepare("
        UPDATE sessions 
        SET endTime = :endTime, is_active = 0 
        WHERE user_id = :uid AND is_active = 1
    ");
    $stmt->execute([
        ':endTime' => $endTime,
        ':uid' => $currentUserId
    ]);

    jsonResponse(['status' => 'success']);
}

// ویرایش نشست (برای صاحب نشست یا مدیر سیستم)
if ($action === 'update_session' && $method === 'POST') {
    $id = (int)($input['id'] ?? 0);
    $startTime = (int)($input['startTime'] ?? 0);
    $endTime = (int)($input['endTime'] ?? 0);
    $task = trim($input['task'] ?? '');

    if ($currentUserRole === 'manager') {
        $stmt = $db->prepare("UPDATE sessions SET startTime = :startTime, endTime = :endTime, task = :task WHERE id = :id");
        $stmt->execute([':id' => $id, ':startTime' => $startTime, ':endTime' => $endTime, ':task' => $task]);
    } else {
        $stmt = $db->prepare("UPDATE sessions SET startTime = :startTime, endTime = :endTime, task = :task WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':startTime' => $startTime, ':endTime' => $endTime, ':task' => $task, ':uid' => $currentUserId]);
    }

    jsonResponse(['status' => 'success']);
}

// حذف نشست (برای صاحب نشست یا مدیر سیستم)
if ($action === 'delete_session' && $method === 'POST') {
    $id = (int)($input['id'] ?? 0);

    if ($currentUserRole === 'manager') {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $currentUserId]);
    }

    jsonResponse(['status' => 'success']);
}

if ($action === 'get_visible_users' && $method === 'GET') {
    if ($currentUserRole === 'manager') {
        $stmt = $db->query("
            SELECT u.id, u.username, u.full_name, u.role, u.created_at,
                   s.task AS active_task, s.startTime AS active_startTime
            FROM users u
            LEFT JOIN sessions s ON u.id = s.user_id AND s.is_active = 1
            ORDER BY u.id ASC
        ");
        $users = $stmt->fetchAll();
        $perms = $db->query("SELECT viewer_id, target_id FROM report_permissions")->fetchAll();
    } else {
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.full_name, u.role, u.created_at,
                   s.task AS active_task, s.startTime AS active_startTime
            FROM users u
            JOIN report_permissions rp ON u.id = rp.target_id
            LEFT JOIN sessions s ON u.id = s.user_id AND s.is_active = 1
            WHERE rp.viewer_id = :uid
            ORDER BY u.full_name ASC
        ");
        $stmt->execute([':uid' => $currentUserId]);
        $users = $stmt->fetchAll();
        $perms = [];
    }
    jsonResponse(['users' => $users, 'permissions' => $perms, 'is_manager' => ($currentUserRole === 'manager')]);
}

if ($action === 'get_user_report' && $method === 'GET') {
    $targetId = (int)($_GET['target_id'] ?? $currentUserId);
    $hasAccess = false;
    if ($targetId === $currentUserId || $currentUserRole === 'manager') {
        $hasAccess = true;
    } else {
        $stmtCheck = $db->prepare("SELECT 1 FROM report_permissions WHERE viewer_id = :v AND target_id = :t");
        $stmtCheck->execute([':v' => $currentUserId, ':t' => $targetId]);
        if ($stmtCheck->fetch()) $hasAccess = true;
    }

    if (!$hasAccess) {
        jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);
    }

    $stmtUser = $db->prepare("SELECT id, full_name, username, role, created_at FROM users WHERE id = :id");
    $stmtUser->execute([':id' => $targetId]);
    $targetUser = $stmtUser->fetch();

    $stmt = $db->prepare("SELECT id, startTime, endTime, task FROM sessions WHERE user_id = :uid AND is_active = 0 ORDER BY startTime ASC");
    $stmt->execute([':uid' => $targetId]);
    $sessions = $stmt->fetchAll();

    jsonResponse(['user' => $targetUser, 'sessions' => $sessions]);
}

if ($action === 'admin_create_user' && $method === 'POST') {
    if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);

    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $fullName = trim($input['full_name'] ?? '');
    $role = ($input['role'] ?? 'partner') === 'manager' ? 'manager' : 'partner';

    if (empty($username) || empty($password) || empty($fullName)) {
        jsonResponse(['error' => 'تمام فیلدها الزامی هستند'], 400);
    }

    $stmtExists = $db->prepare("SELECT 1 FROM users WHERE username = :u");
    $stmtExists->execute([':u' => $username]);
    if ($stmtExists->fetch()) {
        jsonResponse(['error' => 'این نام کاربری قبلاً ثبت شده است'], 400);
    }

    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, full_name, role, created_at)
        VALUES (:u, :p, :f, :r, :c)
    ");
    $stmt->execute([
        ':u' => $username,
        ':p' => password_hash($password, PASSWORD_BCRYPT),
        ':f' => $fullName,
        ':r' => $role,
        ':c' => time()
    ]);

    jsonResponse(['status' => 'success']);
}

if ($action === 'admin_delete_user' && $method === 'POST') {
    if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);

    $targetId = (int)($input['user_id'] ?? 0);
    if ($targetId === $currentUserId) {
        jsonResponse(['error' => 'نمی‌توانید حساب کاربری خودتان را حذف کنید'], 400);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetId]);

    jsonResponse(['status' => 'success']);
}

if ($action === 'admin_save_permissions' && $method === 'POST') {
    if ($currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);

    $viewerId = (int)($input['viewer_id'] ?? 0);
    $targets = $input['targets'] ?? [];

    $db->prepare("DELETE FROM report_permissions WHERE viewer_id = :vid")->execute([':vid' => $viewerId]);

    $stmt = $db->prepare("INSERT INTO report_permissions (viewer_id, target_id) VALUES (:vid, :tid)");
    foreach ($targets as $tid) {
        $tid = (int)$tid;
        if ($tid !== $viewerId) {
            $stmt->execute([':vid' => $viewerId, ':tid' => $tid]);
        }
    }

    jsonResponse(['status' => 'success']);
}

jsonResponse(['error' => 'اکشن نامعتبر است'], 404);