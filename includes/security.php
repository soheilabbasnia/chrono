<?php
declare(strict_types=1);

const PASSWORD_MIN_LENGTH = 10;
const PASSWORD_MAX_BYTES = 72;

const MAX_IMPORT_ROWS = 20000;

const IDLE_TIMEOUT = 7200;

const LOGIN_RL_WINDOW = 900;
const LOGIN_RL_MAX_FAILS = 7;
const LOGIN_RL_LOCK = 900;

function validatePasswordStrength(string $password): ?string {
    if (strlen($password) > PASSWORD_MAX_BYTES) {
        return 'رمز عبور نباید بیش از ۷۲ کاراکتر باشد';
    }
    $length = function_exists('mb_strlen') ? mb_strlen($password) : strlen($password);
    if ($length < PASSWORD_MIN_LENGTH) {
        return 'رمز عبور باید حداقل ۱۰ کاراکتر باشد';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'رمز عبور باید شامل حداقل یک حرف و یک عدد باشد';
    }
    return null;
}

function ensureCsrfToken(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(): bool {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($provided) || $provided === '') {
        return false;
    }
    return hash_equals($sessionToken, $provided);
}

function loginThrottleKey(string $ip, string $username): string {
    $lower = function_exists('mb_strtolower') ? mb_strtolower(trim($username)) : strtolower(trim($username));
    return hash('sha256', $ip . '|' . $lower);
}

function withLockedJsonFile(string $file, callable $fn) {
    $fp = @fopen($file, 'c+');
    if (!$fp) {
        return $fn([], static function (array $d): void {});
    }
    try {
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];
        $save = static function (array $toSave) use ($fp): void {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($toSave, JSON_UNESCAPED_UNICODE));
            fflush($fp);
        };
        return $fn($data, $save);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function loginThrottleFile(): string {
    return getLogDir() . '/.login_throttle.json';
}

function loginThrottleRemaining(string $key): int {
    $now = time();
    return (int)withLockedJsonFile(loginThrottleFile(), static function (array $data) use ($key, $now) {
        $entry = $data[$key] ?? null;
        if (is_array($entry) && (int)($entry['lock_until'] ?? 0) > $now) {
            return (int)$entry['lock_until'] - $now;
        }
        return 0;
    });
}

function loginThrottleRecordFailure(string $key): void {
    $now = time();
    withLockedJsonFile(loginThrottleFile(), static function (array $data, callable $save) use ($key, $now): void {
        foreach ($data as $k => $entry) {
            $lockUntil = is_array($entry) ? (int)($entry['lock_until'] ?? 0) : 0;
            $first = is_array($entry) ? (int)($entry['first'] ?? 0) : 0;
            if ($lockUntil < $now && ($now - $first) > LOGIN_RL_WINDOW) {
                unset($data[$k]);
            }
        }
        $entry = $data[$key] ?? ['count' => 0, 'first' => $now, 'lock_until' => 0];
        if (!is_array($entry) || ($now - (int)($entry['first'] ?? $now)) > LOGIN_RL_WINDOW) {
            $entry = ['count' => 0, 'first' => $now, 'lock_until' => 0];
        }
        $entry['count'] = (int)($entry['count'] ?? 0) + 1;
        if ($entry['count'] >= LOGIN_RL_MAX_FAILS) {
            $entry['lock_until'] = $now + LOGIN_RL_LOCK;
        }
        $data[$key] = $entry;
        $save($data);
    });
}

function loginThrottleClear(string $key): void {
    withLockedJsonFile(loginThrottleFile(), static function (array $data, callable $save) use ($key): void {
        if (isset($data[$key])) {
            unset($data[$key]);
            $save($data);
        }
    });
}
