<?php
declare(strict_types=1);

/*
 * Shared setup for every portal API endpoint.
 * Config lives OUTSIDE public_html (one level above the web root) so it never
 * ships with a deploy and is never served:  /home/<account>/portal-config.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $path = getenv('PORTAL_CONFIG') ?: dirname($_SERVER['DOCUMENT_ROOT']) . '/portal-config.php';
        if (!is_file($path)) json_out(500, ['error' => 'Portal is not configured yet.']);
        $cfg = require $path;
    }
    return $cfg;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        try {
            $pdo = new PDO(
                "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
                $c['user'], $c['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (Throwable $e) {
            error_log('portal db: ' . $e->getMessage());
            json_out(500, ['error' => 'Database unavailable.']);
        }
    }
    return $pdo;
}

function json_out(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) json_out(405, ['error' => "$method only"]);
}

function input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ---------- sessions ---------- */

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('ddc_portal');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/portal',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 12));
    session_start();
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    start_session();
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf']) || !is_string($sent) || !hash_equals($_SESSION['csrf'], $sent)) {
        json_out(403, ['error' => 'Session expired. Reload the page.']);
    }
}

function current_user(): ?array {
    start_session();
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) return null;
    // Idle timeout: 12 hours.
    if (($_SESSION['seen'] ?? 0) < time() - 60 * 60 * 12) {
        $_SESSION = [];
        return null;
    }
    $st = db()->prepare('SELECT id, email, name, role, must_change_password, password_changed_at FROM users WHERE id = ? AND active = 1');
    $st->execute([$uid]);
    $u = $st->fetch();
    // Deactivated, deleted, or password changed since this session began: sign out.
    if (!$u || (string)($u['password_changed_at'] ?? '') !== (string)($_SESSION['pwv'] ?? '')) {
        $_SESSION = [];
        return null;
    }
    $_SESSION['seen'] = time();
    return $u;
}

/**
 * $role 'owner' = owners only. $allowPending = true lets someone who still has to
 * set their own password through (only the account/password endpoints use it).
 */
function require_user(?string $role = null, bool $allowPending = false): array {
    $u = current_user();
    if (!$u) json_out(401, ['error' => 'Sign in required.']);
    if (!$allowPending && (int)$u['must_change_password'] === 1) {
        json_out(403, ['error' => 'Set your own password first.', 'must_change_password' => true]);
    }
    if ($role === 'owner' && $u['role'] !== 'owner') json_out(403, ['error' => 'Owners only.']);
    return $u;
}

/** Start a fresh signed-in session for $u (call after verifying the password). */
function sign_in(array $u): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$u['id'], 'seen' => time(), 'pwv' => (string)($u['password_changed_at'] ?? '')];
}

function public_user(array $u): array {
    return [
        'id' => (int)$u['id'],
        'email' => $u['email'],
        'name' => $u['name'],
        'role' => $u['role'],
        'must_change_password' => (int)($u['must_change_password'] ?? 0) === 1,
    ];
}

/** Readable temporary password: 4 groups of 4, no look-alike characters. */
function temp_password(): string {
    $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    $groups = [];
    for ($g = 0; $g < 4; $g++) {
        $s = '';
        for ($i = 0; $i < 4; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $groups[] = $s;
    }
    return implode('-', $groups);
}

function active_owner_count(): int {
    return (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'owner' AND active = 1")->fetchColumn();
}
