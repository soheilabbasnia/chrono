<?php
declare(strict_types=1);

function handleGetSessions(PDO $db, int $userId): void {
    $stmt = $db->prepare("SELECT id, startTime, endTime, task FROM sessions WHERE user_id = :uid AND is_active = 0 ORDER BY startTime ASC");
    $stmt->execute([':uid' => $userId]);
    $sessions = $stmt->fetchAll();

    $stmtActive = $db->prepare("SELECT id, startTime, task FROM sessions WHERE user_id = :uid AND is_active = 1 LIMIT 1");
    $stmtActive->execute([':uid' => $userId]);
    $activeSession = $stmtActive->fetch() ?: null;

    jsonResponse(['sessions' => $sessions, 'activeSession' => $activeSession]);
}

function handleStartSession(PDO $db, int $userId, array $input): void {
    $task = cleanTask(inputString($input, 'task'));
    $nowMs = (int)(microtime(true) * 1000);
    $id = (int)($input['id'] ?? $nowMs);
    $startTime = (int)($input['startTime'] ?? $nowMs);
    if ($id <= 0) $id = $nowMs;
    if ($startTime <= 0) $startTime = $nowMs;

    // حذف نوبت فعال قبلی و درج نوبت جدید در یک تراکنش؛ اگر شناسه با نوبت کاربر دیگری برخورد کند کمی جابه‌جا می‌شود
    runInTransaction($db, function () use ($db, $userId, &$id, $startTime, $task) {
        $db->prepare("DELETE FROM sessions WHERE user_id = :uid AND is_active = 1")->execute([':uid' => $userId]);

        $stmtExists = $db->prepare("SELECT 1 FROM sessions WHERE id = :id");
        for ($i = 0; $i < 5; $i++) {
            $stmtExists->execute([':id' => $id]);
            if (!$stmtExists->fetchColumn()) break;
            $id += random_int(1, 999);
        }

        $stmt = $db->prepare("INSERT INTO sessions (id, user_id, startTime, endTime, task, is_active) VALUES (:id, :uid, :startTime, NULL, :task, 1)");
        $stmt->execute([':id' => $id, ':uid' => $userId, ':startTime' => $startTime, ':task' => $task]);
    });

    jsonResponse(['status' => 'success', 'session' => ['id' => $id, 'startTime' => $startTime, 'task' => $task]]);
}

function handleStopSession(PDO $db, int $userId, array $input): void {
    $endTime = (int)($input['endTime'] ?? (microtime(true) * 1000));
    // GREATEST: اگر ساعت دستگاه کاربر عقب‌تر از شروع بود، مدت منفی ثبت نشود
    $stmt = $db->prepare("UPDATE sessions SET endTime = GREATEST(:endTime, startTime), is_active = 0 WHERE user_id = :uid AND is_active = 1");
    $stmt->bindValue(':endTime', $endTime, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();

    jsonResponse(['status' => 'success']);
}

function handleUpdateSession(PDO $db, int $userId, string $role, array $input): void {
    $id = (int)($input['id'] ?? 0);
    $startTime = (int)($input['startTime'] ?? 0);
    $endTime = (int)($input['endTime'] ?? 0);
    $task = cleanTask(inputString($input, 'task'));

    if ($id <= 0 || $startTime <= 0 || $endTime <= $startTime) {
        jsonResponse(['error' => 'اطلاعات نوبت نامعتبر است (زمان پایان باید بعد از شروع باشد)'], 400);
    }

    if ($role === 'manager') {
        $stmt = $db->prepare("UPDATE sessions SET startTime = :startTime, endTime = :endTime, task = :task WHERE id = :id AND is_active = 0");
        $stmt->execute([':id' => $id, ':startTime' => $startTime, ':endTime' => $endTime, ':task' => $task]);
    } else {
        $stmt = $db->prepare("UPDATE sessions SET startTime = :startTime, endTime = :endTime, task = :task WHERE id = :id AND user_id = :uid AND is_active = 0");
        $stmt->execute([':id' => $id, ':startTime' => $startTime, ':endTime' => $endTime, ':task' => $task, ':uid' => $userId]);
    }

    jsonResponse(['status' => 'success']);
}

function handleDeleteSession(PDO $db, int $userId, string $role, array $input): void {
    $id = (int)($input['id'] ?? 0);
    if ($role === 'manager') {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $db->prepare("DELETE FROM sessions WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    jsonResponse(['status' => 'success']);
}

function handleGetUserReport(PDO $db, int $currentUserId, string $currentUserRole): void {
    $targetId = (int)($_GET['target_id'] ?? $currentUserId);
    $hasAccess = ($targetId === $currentUserId || $currentUserRole === 'manager');

    if (!$hasAccess) {
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
    if (!$targetUser) {
        jsonResponse(['error' => 'کاربر یافت نشد'], 404);
    }

    $stmt = $db->prepare("SELECT id, startTime, endTime, task FROM sessions WHERE user_id = :uid AND is_active = 0 ORDER BY startTime ASC");
    $stmt->execute([':uid' => $targetId]);
    $sessions = $stmt->fetchAll();

    jsonResponse(['user' => $targetUser, 'sessions' => $sessions]);
}
