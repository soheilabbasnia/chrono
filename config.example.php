<?php
declare(strict_types=1);

defined('DB_DRIVER') || define('DB_DRIVER', 'mysql');

defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_NAME') || define('DB_NAME', 'your_database_name');
defined('DB_USER') || define('DB_USER', 'your_database_user');
defined('DB_PASS') || define('DB_PASS', 'your_strong_password');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

defined('DB_SQLITE_PATH') || define('DB_SQLITE_PATH', __DIR__ . '/data/chrono.sqlite');

defined('TRUST_PROXY') || define('TRUST_PROXY', false);

// defined('INITIAL_ADMIN_PASSWORD') || define('INITIAL_ADMIN_PASSWORD', '');
