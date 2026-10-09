# Chrono

A lightweight, modular time and task tracking web application built with PHP, Vanilla JavaScript, and Tailwind CSS.

## Features

- **Session Tracking:** Start, stop, and edit work sessions with task descriptions.
- **Reporting:** Daily, weekly, and average hour statistics with CSV export and print view.
- **Role-Based Access Control:** Distinct roles for managers and partners, including granular user report permissions.
- **Dual-Layer Logging:** File-based technical debug logging and user activity audit trail.
- **Backup & Restore:** JSON data export and import for user session records.

## Requirements

- Web Server: Apache, Nginx, or LiteSpeed
- PHP: 8.0 or higher (with `pdo_mysql` and `mbstring` extensions)
- Database: MySQL 5.7+ or MariaDB 10.3+

## Deployment & Usage

### 1. Upload Files
Upload all project files to your web server root or subfolder (e.g., `public_html`).

### 2. Configure Database
Copy the template configuration file:
```bash
cp config.example.php config.local.php
```

Note: You can rename `config.example.php` to `config.local.php`

Open `config.local.php` and set your database credentials:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');
```

Note: Database tables and the initial administrator account will be created automatically on the first visit.

### 3. File Permissions
Ensure the following permissions on the server:

- Directories: `755`
- Files: `644`
- `logs/` directory: writable (`755` or `775`)

### 4. Default Login
Access the site in your browser:

- Username: `admin`
- Password: `admin1234`

Make sure to change the default admin password immediately after logging in.

### License
This project is licensed under the MIT License.