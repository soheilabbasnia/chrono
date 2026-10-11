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
- PHP: 8.0 or higher (`pdo_mysql` for MySQL or `pdo_sqlite` for SQLite; `mbstring` recommended)
- Database: MySQL 5.7+ / MariaDB 10.3+ or SQLite 3

## Deployment & Usage

### 1. Upload Files
Upload all project files to your web server root or subfolder (e.g., `public_html`).

### 2. Configure Database
Copy the template configuration to `config.php`:
```bash
cp config.example.php config.php
```

Open `config.php` and set the driver and credentials.

For MySQL:
```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');
```

For SQLite (good for testing and small deployments):
```php
define('DB_DRIVER', 'sqlite');
define('DB_SQLITE_PATH', __DIR__ . '/data/chrono.sqlite');
```

Note: Database tables and the initial administrator account are created automatically on the first visit.

> `config.php` holds sensitive data and is in `.gitignore` — never commit it.

### 3. File Permissions
Ensure the following permissions on the server:

- Directories: `755`
- Files: `644`
- `logs/` and `data/` directories: writable (`755` or `775`)

### 4. First Login
Open the site in your browser and sign in with username `admin`.

The initial password is randomly generated and written to `logs/INITIAL_ADMIN_PASSWORD.txt` (unless you set `INITIAL_ADMIN_PASSWORD` in `config.php`). It is temporary: on **first login** the app forces you to change it. Delete `INITIAL_ADMIN_PASSWORD.txt` afterwards.

Password policy: at least 10 characters, including at least one letter and one digit (max 72 characters).

### 5. Security Notes
- The `logs/`, `includes/`, and `data/` directories are protected only by `.htaccess`, which works on **Apache**. On **Nginx/LiteSpeed** you must block direct access to these directories and to `config.php` in the server config; ideally keep `logs/`, `data/`, and `config.php` outside the web root.
- If you run behind a trusted reverse proxy/load balancer, set `TRUST_PROXY` to `true` in `config.php` so HTTPS is detected from `X-Forwarded-Proto`. Otherwise keep it `false`.
- The app uses CSRF tokens and login rate limiting; the `logs/` directory must be writable for these to work.

### License
This project is licensed under the MIT License.