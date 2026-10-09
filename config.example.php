<?php
declare(strict_types=1);

/**
 * فایل نمونه تنظیمات پایگاه‌داده
 * برای استفاده، این فایل را با نام config.local.php کپی کرده و اطلاعات معتبر را وارد کنید.
 */

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'your_database_name');
defined('DB_USER') || define('DB_USER', 'your_database_user');
defined('DB_PASS') || define('DB_PASS', 'your_strong_password');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');