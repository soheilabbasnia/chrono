<?php
declare(strict_types=1);

// حداکثر حجم هر فایل لاگ؛ بعد از آن فایل به «.1» منتقل و لاگ تازه شروع می‌شود
const LOG_MAX_BYTES = 2 * 1024 * 1024;

function getLogDir(): string {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    return $logDir;
}

// جلوگیری از تزریق خط جدید به لاگ (جعل ردیف) و محدود کردن طول
function sanitizeLogText(string $text, int $maxLength = 500): string {
    $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength) . '…' : $text;
    }
    return strlen($text) > $maxLength ? substr($text, 0, $maxLength) . '…' : $text;
}

function appendLog(string $file, string $line): void {
    if (@is_file($file) && (int)@filesize($file) > LOG_MAX_BYTES) {
        @rename($file, $file . '.1');
    }
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// ثبت لاگ خطاهای سیستمی و فنی
function logDebug(string $message, array $context = []): void {
    $logFile = getLogDir() . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : '';
    appendLog($logFile, '[' . $timestamp . '] ' . sanitizeLogText($message, 1000) . $contextStr . "\n");
}

// ثبت لاگ رفتاری و فعالیت کاربران در فایل متنی مستقل
function logUserActivity(int $userId, string $action, string $details = ''): void {
    $logFile = getLogDir() . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
    $entry = "[{$timestamp}] [IP: {$ip}] [UID: {$userId}] [ACTION: {$action}] " . sanitizeLogText($details) . "\n";
    appendLog($logFile, $entry);
}
