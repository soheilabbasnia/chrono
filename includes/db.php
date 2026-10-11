<?php
declare(strict_types=1);

(function (): void {
    foreach ([__DIR__ . '/../config.php', __DIR__ . '/../config.local.php'] as $candidate) {
        if (is_file($candidate)) {
            require_once $candidate;
            return;
        }
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":"فایل پیکربندی یافت نشد (config.php)"}';
    exit;
})();

function dbDriver(): string {
    $driver = defined('DB_DRIVER') ? strtolower((string)DB_DRIVER) : 'mysql';
    return $driver === 'sqlite' ? 'sqlite' : 'mysql';
}

function sqlGreatest(string $a, string $b): string {
    return dbDriver() === 'sqlite' ? "MAX($a, $b)" : "GREATEST($a, $b)";
}

function getDbConnection(): PDO {
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        if (dbDriver() === 'sqlite') {
            $path = defined('DB_SQLITE_PATH') ? (string)DB_SQLITE_PATH : __DIR__ . '/../data/chrono.sqlite';
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $htaccess = $dir . '/.htaccess';
            if (is_dir($dir) && !is_file($htaccess)) {
                @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
            }
            $db = new PDO('sqlite:' . $path, null, null, $options);
            $db->exec('PRAGMA foreign_keys = ON');
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        }

        if (!isDatabaseReady($db)) {
            initDatabaseTables($db);
        }
        ensureSchemaMigrations($db);
    } catch (PDOException $e) {
        $db = null;
        if (function_exists('logDebug')) {
            logDebug('خطا در اتصال/آماده‌سازی دیتابیس: ' . $e->getMessage());
        }
        jsonResponse(['error' => 'خطا در ارتباط با دیتابیس'], 500);
    }
    return $db;
}

function isDatabaseReady(PDO $db): bool {
    try {
        $row = $db->query("
            SELECT EXISTS(SELECT 1 FROM users) AS has_users,
                   (SELECT COUNT(*) FROM report_permissions WHERE 1 = 0) AS perm_ok,
                   (SELECT COUNT(*) FROM sessions WHERE 1 = 0) AS sess_ok
        ")->fetch();
        return $row && (int)$row['has_users'] === 1;
    } catch (PDOException $e) {
        return false;
    }
}

function runInTransaction(PDO $db, callable $fn) {
    $db->beginTransaction();
    try {
        $result = $fn();
        $db->commit();
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function ensureSchemaMigrations(PDO $db): void {
    try {
        $db->query("SELECT must_change_password FROM users LIMIT 1");
    } catch (PDOException $e) {
        $type = dbDriver() === 'sqlite' ? 'INTEGER NOT NULL DEFAULT 0' : 'TINYINT NOT NULL DEFAULT 0';
        try {
            $db->exec("ALTER TABLE users ADD COLUMN must_change_password $type");
        } catch (PDOException $e2) {
            if (function_exists('logDebug')) {
                logDebug('خطا در مهاجرت ساختار دیتابیس: ' . $e2->getMessage());
            }
        }
    }
}

function initDatabaseTables(PDO $db): void {
    if (dbDriver() === 'sqlite') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'partner' CHECK (role IN ('manager', 'partner')),
                created_at INTEGER NOT NULL,
                must_change_password INTEGER NOT NULL DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS report_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                viewer_id INTEGER NOT NULL,
                target_id INTEGER NOT NULL,
                UNIQUE (viewer_id, target_id),
                FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE
            );
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY,
                user_id INTEGER NOT NULL,
                startTime INTEGER NOT NULL,
                endTime INTEGER NULL,
                task TEXT NULL,
                is_active INTEGER DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(191) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(191) NOT NULL,
                role ENUM('manager', 'partner') NOT NULL DEFAULT 'partner',
                created_at BIGINT NOT NULL,
                must_change_password TINYINT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS report_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                viewer_id INT NOT NULL,
                target_id INT NOT NULL,
                UNIQUE KEY uq_viewer_target (viewer_id, target_id),
                FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (target_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS sessions (
                id BIGINT PRIMARY KEY,
                user_id INT NOT NULL,
                startTime BIGINT NOT NULL,
                endTime BIGINT NULL,
                task TEXT NULL,
                is_active TINYINT DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    $userCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount === 0) {
        createInitialAdmin($db);
    }
}

function createInitialAdmin(PDO $db): void {
    $password = (defined('INITIAL_ADMIN_PASSWORD') && is_string(INITIAL_ADMIN_PASSWORD) && INITIAL_ADMIN_PASSWORD !== '')
        ? (string)INITIAL_ADMIN_PASSWORD
        : bin2hex(random_bytes(12));

    $stmt = $db->prepare("
        INSERT INTO users (username, password_hash, full_name, role, created_at, must_change_password)
        VALUES (:username, :hash, :full_name, 'manager', :time, 1)
    ");
    $stmt->execute([
        ':username' => 'admin',
        ':hash' => password_hash($password, PASSWORD_BCRYPT),
        ':full_name' => 'مدیر سیستم',
        ':time' => time(),
    ]);

    persistInitialAdminPassword($password);
}

function persistInitialAdminPassword(string $password): void {
    $dir = function_exists('getLogDir') ? getLogDir() : __DIR__ . '/../logs';
    $file = $dir . '/INITIAL_ADMIN_PASSWORD.txt';
    $body = "Chrono - initial administrator credentials\n"
        . "Generated at: " . date('Y-m-d H:i:s') . "\n\n"
        . "username: admin\n"
        . "password: " . $password . "\n\n"
        . "این رمز موقت است. پس از نخستین ورود باید تغییر کند و سپس این فایل را حذف کنید.\n"
        . "This is a temporary password. Change it on first login, then delete this file.\n";
    @file_put_contents($file, $body, LOCK_EX);
    @chmod($file, 0600);
    if (function_exists('logDebug')) {
        logDebug('کاربر مدیر اولیه ساخته شد؛ رمز موقت در فایل logs/INITIAL_ADMIN_PASSWORD.txt ذخیره شد');
    }
}
