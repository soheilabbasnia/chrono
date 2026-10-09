<?php
declare(strict_types=1);

function handleLogin(PDO $db, array $input): void {
    $username = inputString($input, 'username');
    $password = inputString($input, 'password', false);

    if ($username === '' || $password === '') {
        jsonResponse(['error' => 'نام کاربری و رمز عبور الزامی است'], 400);
    }

    $stmt = $db->prepare("SELECT id, username, password_hash, full_name, role, created_at FROM users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    // حتی اگر کاربر وجود نداشته باشد یک عملیات bcrypt انجام می‌شود تا زمان پاسخ، وجود/نبود نام کاربری را لو ندهد
    $hashToCheck = $user ? $user['password_hash'] : password_hash('dummy-password', PASSWORD_BCRYPT);
    $passwordOk = password_verify($password, $hashToCheck);

    if (!$user || !$passwordOk) {
        logDebug("تلاش ناموفق برای ورود به سیستم", ['username' => $username]);
        jsonResponse(['error' => 'نام کاربری یا رمز عبور اشتباه است'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];

    logUserActivity((int)$user['id'], 'LOGIN', "ورود موفق کاربر: {$user['username']}");

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

function handleLogout(): void {
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

function handleGetCurrentUser(PDO $db, int $currentUserId, string $currentUserRole): void {
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
