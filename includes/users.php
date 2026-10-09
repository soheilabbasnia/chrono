<?php
declare(strict_types=1);

function handleGetVisibleUsers(PDO $db, int $currentUserId, string $currentUserRole): void {
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

function handleChangePassword(PDO $db, int $currentUserId, string $currentUserRole, array $input): void {
    $targetId = (int)($input['user_id'] ?? $currentUserId);
    $oldPass = $input['old_password'] ?? '';
    $newPass = $input['new_password'] ?? '';

    if (empty($newPass)) jsonResponse(['error' => 'رمز عبور جدید الزامی است'], 400);
    if ($targetId !== $currentUserId && $currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);

    if ($targetId === $currentUserId && $currentUserRole !== 'manager') {
        $stmtCheck = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmtCheck->execute([':id' => $currentUserId]);
        $currHash = $stmtCheck->fetchColumn();
        if (!$currHash || !password_verify($oldPass, $currHash)) {
            jsonResponse(['error' => 'رمز عبور فعلی نادرست است'], 400);
        }
    }

    $stmtUpdate = $db->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
    $stmtUpdate->execute([':p' => password_hash($newPass, PASSWORD_BCRYPT), ':id' => $targetId]);

    jsonResponse(['status' => 'success']);
}

function handleCreateUser(PDO $db, array $input): void {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $fullName = trim($input['full_name'] ?? '');
    $role = ($input['role'] ?? 'partner') === 'manager' ? 'manager' : 'partner';

    if (empty($username) || empty($password) || empty($fullName)) jsonResponse(['error' => 'تمام فیلدها الزامی هستند'], 400);

    $stmtExists = $db->prepare("SELECT 1 FROM users WHERE username = :u");
    $stmtExists->execute([':u' => $username]);
    if ($stmtExists->fetch()) jsonResponse(['error' => 'این نام کاربری قبلاً ثبت شده است'], 400);

    $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, created_at) VALUES (:u, :p, :f, :r, :c)");
    $stmt->execute([':u' => $username, ':p' => password_hash($password, PASSWORD_BCRYPT), ':f' => $fullName, ':r' => $role, ':c' => time()]);

    jsonResponse(['status' => 'success']);
}

function handleDeleteUser(PDO $db, int $currentUserId, array $input): void {
    $targetId = (int)($input['user_id'] ?? 0);
    if ($targetId === $currentUserId) jsonResponse(['error' => 'نمی‌توانید حساب خودتان را حذف کنید'], 400);

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetId]);

    jsonResponse(['status' => 'success']);
}

function handleSavePermissions(PDO $db, array $input): void {
    // خواندن شناسه کاربر هم از ورودی JSON و هم از کوئری‌پارامتر
    $viewerId = (int)($input['viewer_id'] ?? ($_GET['viewer_id'] ?? 0));
    $targets = $input['targets'] ?? [];

    if ($viewerId <= 0) {
        logDebug("خطا: شناسه کاربر برای ذخیره دسترسی نامعتبر است", ['input' => $input]);
        jsonResponse(['error' => 'شناسه کاربر نامعتبر است'], 400);
    }

    try {
        // ۱. حذف تمام دسترسی‌های قبلی این کاربر
        $stmtDel = $db->prepare("DELETE FROM report_permissions WHERE viewer_id = :vid");
        $stmtDel->execute([':vid' => $viewerId]);

        // ۲. درج دسترسی‌های جدید انتخاب‌شده
        if (!empty($targets) && is_array($targets)) {
            $stmtIns = $db->prepare("INSERT INTO report_permissions (viewer_id, target_id) VALUES (:vid, :tid)");
            foreach ($targets as $tid) {
                $targetId = (int)$tid;
                // جلوگیری از ثبت دسترسی کاربر برای خودش یا شناسه‌های نامعتبر
                if ($targetId > 0 && $targetId !== $viewerId) {
                    $stmtIns->execute([
                        ':vid' => $viewerId,
                        ':tid' => $targetId
                    ]);
                }
            }
        }

        logUserActivity($viewerId, 'SAVE_PERMISSIONS', 'دسترسی‌های گزارش با موفقیت به‌روزرسانی شد');
        jsonResponse(['status' => 'success']);
    } catch (Exception $e) {
        logDebug("خطای دیتابیس در ذخیره دسترسی‌ها: " . $e->getMessage(), ['viewer_id' => $viewerId]);
        jsonResponse(['error' => 'خطا در ثبت پایگاه داده: ' . $e->getMessage()], 500);
    }
}

function handleExportUserJson(PDO $db, array $input): void {
    $targetId = (int)($_GET['user_id'] ?? 0);
    $stmtU = $db->prepare("SELECT id, username, full_name, role, created_at FROM users WHERE id = :id");
    $stmtU->execute([':id' => $targetId]);
    $uData = $stmtU->fetch();
    if (!$uData) jsonResponse(['error' => 'کاربر یافت نشد'], 404);

    $stmtS = $db->prepare("SELECT id, startTime, endTime, task, is_active FROM sessions WHERE user_id = :id ORDER BY startTime ASC");
    $stmtS->execute([':id' => $targetId]);
    $sessions = $stmtS->fetchAll();

    jsonResponse(['export_time' => time(), 'user' => $uData, 'sessions' => $sessions]);
}

function handleImportUserJson(PDO $db, array $input): void {
    $targetId = (int)($input['user_id'] ?? 0);
    $sessions = $input['sessions'] ?? [];

    if (!$targetId || !is_array($sessions)) jsonResponse(['error' => 'داده‌های فایل نامعتبر است'], 400);

    $db->prepare("DELETE FROM sessions WHERE user_id = :uid")->execute([':uid' => $targetId]);
    $stmtIns = $db->prepare("INSERT INTO sessions (id, user_id, startTime, endTime, task, is_active) VALUES (:id, :uid, :startTime, :endTime, :task, :is_active)");
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