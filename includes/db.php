<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function getDbConnection(): PDO {
    static $db = null;
    if ($db === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
            initDatabaseTables($db);
        } catch (PDOException $e) {
            jsonResponse(['error' => 'خطا در ارتباط با دیتابیس'], 500);
        }
    }
    return $db;
}

function initDatabaseTables(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(191) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(191) NOT NULL,
            role ENUM('manager', 'partner') NOT NULL DEFAULT 'partner',
            created_at BIGINT NOT NULL
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
}