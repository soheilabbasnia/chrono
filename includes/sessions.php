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
    $task = trim($input['task'] ?? '');
    $id = (int)($input['id'] ?? (microtime(true) * 1000));
    $startTime = (int)($input['startTime'] ?? (microtime(true) * 1000));

    $db->prepare("DELETE FROM sessions WHERE user_id = :uid AND is_active = 1")->execute([':uid' => $userId]);

    $stmt = $db->prepare("INSERT INTO sessions (id, user_id, startTime, endTime, task, is_active) VALUES (:id, :uid, :startTime, NULL, :task, 1)");
    $stmt->execute([':id' => $id, ':uid' => $userId, ':startTime' => $startTime, ':task' => $task]);

    jsonResponse(['status' => 'success', 'session' => ['id' => $id, 'startTime' => $startTime, 'task' => $task]]);
}

function handleStopSession(PDO $db, int $userId, array $input): void {
    $endTime = (int)($input['endTime'] ?? (microtime(true) * 1000));
    $stmt = $db->prepare("UPDATE sessions SET endTime = :endTime, is_active = 0 WHERE user_id = :uid AND is_active = 1");
    $stmt->execute([':endTime' => $endTime, ':uid' => $userId]);

    jsonResponse(['status' => 'success']);
}

function handleUpdateSession(PDO $db, int $userId, string $role, array $input): void {
    $id = (int)($input['id'] ?? 0);
    $startTime = (int)($input['startTime'] ?? 0);
    $endTime = (int)($input['endTime'] ?? 0);
    $task = trim($input['task'] ?? '');

    if ($role === 'manager') {
        $stmt = $db->prepare("UPDATE sessions SET startTime = :startTime, endTime = :endTime, task = :task WHERE id = :id");
        $stmt->execute([':id' => $id, ':startTime' => $startTime, ':endTime' => $endTime, ':task' => $task]);
    } else {
        $stmt = $db->prepare("UPDATE sessions SET startTime = :startTime, endTime = :endTime, task = :task WHERE id = :id AND user_id = :uid");
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

    $stmt = $db->prepare("SELECT id, startTime, endTime, task FROM sessions WHERE user_id = :uid AND is_active = 0 ORDER BY startTime ASC");
    $stmt->execute([':uid' => $targetId]);
    $sessions = $stmt->fetchAll();

    jsonResponse(['user' => $targetUser, 'sessions' => $sessions]);
}