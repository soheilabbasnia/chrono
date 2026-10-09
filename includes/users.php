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
    $oldPass = inputString($input, 'old_password', false);
    $newPass = inputString($input, 'new_password', false);

    if ($newPass === '') jsonResponse(['error' => 'رمز عبور جدید الزامی است'], 400);
    if ($targetId !== $currentUserId && $currentUserRole !== 'manager') jsonResponse(['error' => 'دسترسی غیرمجاز'], 403);

    $stmtCheck = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmtCheck->execute([':id' => $targetId]);
    $currHash = $stmtCheck->fetchColumn();
    if ($currHash === false) jsonResponse(['error' => 'کاربر یافت نشد'], 404);

    // تغییر رمز خود شخص (حتی مدیر) نیازمند رمز فعلی است؛ مدیر برای دیگران بدون رمز فعلی می‌تواند تغییر دهد
    if ($targetId === $currentUserId) {
        if ($oldPass === '' || !password_verify($oldPass, (string)$currHash)) {
            jsonResponse(['error' => 'رمز عبور فعلی نادرست است'], 400);
        }
    }

    $stmtUpdate = $db->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
    $stmtUpdate->execute([':p' => password_hash($newPass, PASSWORD_BCRYPT), ':id' => $targetId]);

    jsonResponse(['status' => 'success']);
}

function handleCreateUser(PDO $db, array $input): void {
    $username = inputString($input, 'username');
    $password = inputString($input, 'password', false);
    $fullName = inputString($input, 'full_name');
    $role = ($input['role'] ?? 'partner') === 'manager' ? 'manager' : 'partner';

    if ($username === '' || $password === '' || $fullName === '') jsonResponse(['error' => 'تمام فیلدها الزامی هستند'], 400);

    $tooLong = function_exists('mb_strlen')
        ? (mb_strlen($username) > 191 || mb_strlen($fullName) > 191)
        : (strlen($username) > 191 || strlen($fullName) > 191);
    if ($tooLong) jsonResponse(['error' => 'نام یا نام کاربری بیش از حد طولانی است'], 400);

    $stmtExists = $db->prepare("SELECT 1 FROM users WHERE username = :u");
    $stmtExists->execute([':u' => $username]);
    if ($stmtExists->fetch()) jsonResponse(['error' => 'این نام کاربری قبلاً ثبت شده است'], 400);

    try {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, created_at) VALUES (:u, :p, :f, :r, :c)");
        $stmt->execute([':u' => $username, ':p' => password_hash($password, PASSWORD_BCRYPT), ':f' => $fullName, ':r' => $role, ':c' => time()]);
    } catch (PDOException $e) {
        // برخورد هم‌زمان دو درخواست با یک نام کاربری (کلید یکتا)
        if ($e->getCode() === '23000') {
            jsonResponse(['error' => 'این نام کاربری قبلاً ثبت شده است'], 400);
        }
        throw $e;
    }

    jsonResponse(['status' => 'success']);
}

function handleDeleteUser(PDO $db, int $currentUserId, array $input): void {
    $targetId = (int)($input['user_id'] ?? 0);
    if ($targetId === $currentUserId) jsonResponse(['error' => 'نمی‌توانید حساب خودتان را حذف کنید'], 400);

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute([':id' => $targetId]);
    if ($stmt->rowCount() === 0) jsonResponse(['error' => 'کاربر یافت نشد'], 404);

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
    if (!is_array($targets)) $targets = [];

    // فقط شناسه‌های صحیح و موجود، بدون تکرار و بدون خودِ کاربر
    $validIds = array_map('intval', $db->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN));
    if (!in_array($viewerId, $validIds, true)) {
        jsonResponse(['error' => 'کاربر یافت نشد'], 404);
    }
    $cleanTargets = [];
    foreach ($targets as $tid) {
        $targetId = is_scalar($tid) ? (int)$tid : 0;
        if ($targetId > 0 && $targetId !== $viewerId && in_array($targetId, $validIds, true)) {
            $cleanTargets[$targetId] = $targetId;
        }
    }

    try {
        // حذف دسترسی‌های قبلی و درج دسترسی‌های جدید در یک تراکنش؛ اگر چیزی خطا بدهد دسترسی‌های قبلی از بین نمی‌روند
        runInTransaction($db, function () use ($db, $viewerId, $cleanTargets) {
            $db->prepare("DELETE FROM report_permissions WHERE viewer_id = :vid")->execute([':vid' => $viewerId]);

            if (!empty($cleanTargets)) {
                $stmtIns = $db->prepare("INSERT INTO report_permissions (viewer_id, target_id) VALUES (:vid, :tid)");
                foreach ($cleanTargets as $targetId) {
                    $stmtIns->execute([':vid' => $viewerId, ':tid' => $targetId]);
                }
            }
        });
    } catch (Exception $e) {
        logDebug("خطای دیتابیس در ذخیره دسترسی‌ها: " . $e->getMessage(), ['viewer_id' => $viewerId]);
        jsonResponse(['error' => 'خطا در ثبت پایگاه داده'], 500);
    }

    jsonResponse(['status' => 'success']);
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

    if ($targetId <= 0 || !is_array($sessions)) jsonResponse(['error' => 'داده‌های فایل نامعتبر است'], 400);

    $stmtUser = $db->prepare("SELECT 1 FROM users WHERE id = :id");
    $stmtUser->execute([':id' => $targetId]);
    if (!$stmtUser->fetchColumn()) jsonResponse(['error' => 'کاربر یافت نشد'], 404);

    // اعتبارسنجی کامل فایل پیش از هر تغییر؛ اگر ردیفی خراب باشد هیچ داده‌ای حذف نمی‌شود
    $rows = [];
    foreach ($sessions as $s) {
        $id = is_array($s) ? (int)($s['id'] ?? 0) : 0;
        $startTime = is_array($s) ? (int)($s['startTime'] ?? 0) : 0;
        if ($id <= 0 || $startTime <= 0) {
            jsonResponse(['error' => 'فایل پشتیبان شامل ردیف نامعتبر است'], 400);
        }
        $rows[] = [
            ':id' => $id,
            ':uid' => $targetId,
            ':startTime' => $startTime,
            ':endTime' => !empty($s['endTime']) ? (int)$s['endTime'] : null,
            ':task' => cleanTask(is_string($s['task'] ?? null) ? $s['task'] : ''),
            ':is_active' => (int)($s['is_active'] ?? 0) ? 1 : 0,
        ];
    }

    try {
        // حذف نوبت‌های قبلی و درج نوبت‌های فایل در یک تراکنش (قبلاً خطا در میانه کار باعث از دست رفتن داده‌ها می‌شد)
        runInTransaction($db, function () use ($db, $targetId, $rows) {
            $db->prepare("DELETE FROM sessions WHERE user_id = :uid")->execute([':uid' => $targetId]);
            $stmtIns = $db->prepare("INSERT INTO sessions (id, user_id, startTime, endTime, task, is_active) VALUES (:id, :uid, :startTime, :endTime, :task, :is_active)");
            foreach ($rows as $row) {
                $stmtIns->execute($row);
            }
        });
    } catch (PDOException $e) {
        logDebug("خطا در بازیابی بکاپ: " . $e->getMessage(), ['user_id' => $targetId]);
        if ($e->getCode() === '23000') {
            jsonResponse(['error' => 'بازیابی انجام نشد: شناسهٔ برخی نوبت‌ها با داده‌های دیگر تداخل دارد. داده‌های قبلی دست‌نخورده ماند'], 409);
        }
        jsonResponse(['error' => 'خطا در بازیابی داده‌ها'], 500);
    }

    jsonResponse(['status' => 'success', 'imported' => count($rows)]);
}
