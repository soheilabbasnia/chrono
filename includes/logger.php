<?php
declare(strict_types=1);

function getLogDir(): string {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    return $logDir;
}

// ثبت لاگ خطاهای سیستمی و فنی
function logDebug(string $message, array $context = []): void {
    $logFile = getLogDir() . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    @file_put_contents($logFile, "[{$timestamp}] {$message}{$contextStr}\n", FILE_APPEND | LOCK_EX);
}

// ثبت لاگ رفتاری و فعالیت کاربران در فایل متنی مستقل
function logUserActivity(int $userId, string $action, string $details = ''): void {
    $logFile = getLogDir() . '/activity.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
    $entry = "[{$timestamp}] [IP: {$ip}] [UID: {$userId}] [ACTION: {$action}] {$details}\n";
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}