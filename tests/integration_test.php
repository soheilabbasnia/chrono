<?php
declare(strict_types=1);

/**
 * Chrono integration test harness (SQLite).
 * Requires the app to be served (e.g. php -S 127.0.0.1:8099) and `curl` on PATH.
 * Usage: CHRONO_BASE=http://127.0.0.1:8099 php tests/integration_test.php
 */

$BASE = getenv('CHRONO_BASE') ?: 'http://127.0.0.1:8099';
$JAR = sys_get_temp_dir() . '/chrono_test_cookies_' . getmypid() . '.txt';
@unlink($JAR);

$ROOT = dirname(__DIR__);
@unlink($ROOT . '/logs/.login_throttle.json');

$CSRF = '';
$PASS = 0;
$FAIL = 0;

function out(string $s): void { fwrite(STDOUT, $s . "\n"); }

function check(string $label, bool $cond): void {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; out("  \e[32mPASS\e[0m  $label"); }
    else { $FAIL++; out("  \e[31mFAIL\e[0m  $label"); }
}

function req(string $method, string $action, ?array $data = null, array $headers = []): array {
    global $JAR, $BASE;
    $cmd = 'curl -s -o - -w "\n%{http_code}" -b ' . escapeshellarg($JAR) . ' -c ' . escapeshellarg($JAR);
    $cmd .= ' -X ' . escapeshellarg($method);
    foreach ($headers as $h) {
        $cmd .= ' -H ' . escapeshellarg($h);
    }
    if ($data !== null) {
        $cmd .= ' -H ' . escapeshellarg('Content-Type: application/json');
        $cmd .= ' --data ' . escapeshellarg(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    $cmd .= ' ' . escapeshellarg($BASE . '/api.php?action=' . $action) . ' 2>/dev/null';
    $raw = (string)shell_exec($cmd);
    $pos = strrpos($raw, "\n");
    $status = $pos === false ? 0 : (int)substr($raw, $pos + 1);
    $body = $pos === false ? $raw : substr($raw, 0, $pos);
    $json = json_decode($body, true);
    return ['status' => $status, 'body' => is_array($json) ? $json : [], 'raw' => $body];
}

$csrfHeader = static function () use (&$CSRF): array { return ['X-CSRF-Token: ' . $CSRF]; };

out("\n== 1. Unauthenticated access ==");
$r = req('GET', 'me');
check('me without session returns 401', $r['status'] === 401);

$pwFile = dirname(__DIR__) . '/logs/INITIAL_ADMIN_PASSWORD.txt';
$initialPassword = '';
if (is_file($pwFile) && preg_match('/password:\s*(\S+)/', (string)file_get_contents($pwFile), $m)) {
    $initialPassword = $m[1];
}
check('initial admin password file was generated', $initialPassword !== '');

out("\n== 2. No default password / forced change ==");
$r = req('POST', 'login', ['username' => 'admin', 'password' => 'admin1234']);
check('legacy default password admin1234 is rejected', $r['status'] === 401);

$r = req('POST', 'login', ['username' => 'admin', 'password' => $initialPassword]);
check('login with generated password succeeds', $r['status'] === 200);
check('login response carries a CSRF token', !empty($r['body']['csrf_token']));
check('admin is flagged must_change_password', ($r['body']['user']['must_change_password'] ?? false) === true);
$CSRF = $r['body']['csrf_token'] ?? '';

out("\n== 3. must_change_password gating ==");
$r = req('GET', 'get_sessions');
check('actions are blocked until password change (403)', $r['status'] === 403 && ($r['body']['must_change_password'] ?? false) === true);

out("\n== 4. Password policy ==");
$r = req('POST', 'change_password', ['user_id' => 1, 'old_password' => $initialPassword, 'new_password' => 'short1'], $csrfHeader());
check('weak password rejected (too short)', $r['status'] === 400);
$r = req('POST', 'change_password', ['user_id' => 1, 'old_password' => $initialPassword, 'new_password' => 'onlyletters'], $csrfHeader());
check('weak password rejected (no digit)', $r['status'] === 400);
$r = req('POST', 'change_password', ['user_id' => 1, 'old_password' => 'wrongold', 'new_password' => 'StrongPass123'], $csrfHeader());
check('wrong current password rejected', $r['status'] === 400);
$r = req('POST', 'change_password', ['user_id' => 1, 'old_password' => $initialPassword, 'new_password' => 'StrongPass123'], $csrfHeader());
check('strong password accepted', $r['status'] === 200);

out("\n== 5. Access restored after change ==");
$r = req('GET', 'get_sessions');
check('get_sessions works after password change', $r['status'] === 200);

out("\n== 6. CSRF protection ==");
$now = (int)(microtime(true) * 1000);
$r = req('POST', 'start_session', ['id' => $now, 'startTime' => $now, 'task' => 'بدون توکن']);
check('mutating request without CSRF token is rejected (403)', $r['status'] === 403);
$r = req('GET', 'start_session', ['task' => 'x'], $csrfHeader());
check('mutating action over GET is rejected (405)', $r['status'] === 405);

out("\n== 7. Session CRUD ==");
$r = req('POST', 'start_session', ['id' => $now, 'startTime' => $now, 'task' => 'تسک تست'], $csrfHeader());
check('start_session succeeds', $r['status'] === 200);
$r = req('GET', 'get_sessions');
check('active session is reported', ($r['body']['activeSession']['id'] ?? null) == $now);
$r = req('POST', 'stop_session', ['endTime' => $now + 60000], $csrfHeader());
check('stop_session succeeds', $r['status'] === 200);
$r = req('GET', 'get_sessions');
$finished = $r['body']['sessions'][0] ?? null;
check('finished session stored with endTime >= startTime', $finished && (int)$finished['endTime'] >= (int)$finished['startTime']);
$r = req('POST', 'update_session', ['id' => $now, 'startTime' => $now, 'endTime' => $now + 120000, 'task' => 'ویرایش شد'], $csrfHeader());
check('update_session succeeds', $r['status'] === 200);
$r = req('POST', 'delete_session', ['id' => $now], $csrfHeader());
check('delete_session succeeds', $r['status'] === 200);
$r = req('GET', 'get_sessions');
check('session list empty after delete', count($r['body']['sessions'] ?? [null]) === 0);

out("\n== 8. User management + policy on create ==");
$r = req('POST', 'admin_create_user', ['full_name' => 'کاربر تست', 'username' => 'tester', 'password' => 'weak', 'role' => 'partner'], $csrfHeader());
check('create user with weak password rejected', $r['status'] === 400);
$r = req('POST', 'admin_create_user', ['full_name' => 'کاربر تست', 'username' => 'tester', 'password' => 'TesterPass123', 'role' => 'partner'], $csrfHeader());
check('create user with strong password succeeds', $r['status'] === 200);

out("\n== 9. New user must change password ==");
$r = req('POST', 'login', ['username' => 'tester', 'password' => 'TesterPass123']);
check('new user can log in', $r['status'] === 200);
check('new user is flagged must_change_password', ($r['body']['user']['must_change_password'] ?? false) === true);
$r = req('POST', 'login', ['username' => 'admin', 'password' => 'StrongPass123']);
$CSRF = $r['body']['csrf_token'] ?? '';
check('admin re-login succeeds', $r['status'] === 200);

out("\n== 10. Import row cap ==");
$r = req('POST', 'import_user_json', ['user_id' => 1, 'sessions' => [['id' => 0, 'startTime' => 0]]], $csrfHeader());
check('import with invalid row rejected (400)', $r['status'] === 400);

out("\n== 11. Logout requires POST + CSRF ==");
$r = req('GET', 'logout');
check('logout over GET is rejected (403)', $r['status'] === 403);
$r = req('POST', 'logout', [], $csrfHeader());
check('logout over POST succeeds', $r['status'] === 200);
$r = req('GET', 'me');
check('session cleared after logout (401)', $r['status'] === 401);

out("\n== 12. Login brute-force rate limiting ==");
$locked = false;
for ($i = 0; $i < 9; $i++) {
    $r = req('POST', 'login', ['username' => 'nobody_rl', 'password' => 'bad-password']);
    if ($r['status'] === 429) { $locked = true; break; }
}
check('repeated failed logins get rate-limited (429)', $locked);

@unlink($JAR);
out("\n==================================================");
out(sprintf("RESULT: %d passed, %d failed", $PASS, $FAIL));
exit($FAIL === 0 ? 0 : 1);

