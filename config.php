<?php
declare(strict_types=1);

// تنظیمات محلی/محرمانه: اگر فایل config.local.php کنار این فایل باشد، مقادیر آن اولویت دارد
// (این فایل در .gitignore است و نباید در گیت قرار بگیرد). نمونهٔ محتوا:
//   <?php
//   define('DB_HOST', 'localhost');
//   define('DB_NAME', '...');
//   define('DB_USER', '...');
//   define('DB_PASS', '...');
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'flowerm2_chrono');
defined('DB_USER') || define('DB_USER', 'flowerm2_chronoAdmin');
defined('DB_PASS') || define('DB_PASS', 'm5qX7Ax3yYm5rRU8');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');
