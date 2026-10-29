<?php
declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';
require_method('POST');

$in = input();
$email = strtolower(trim((string)($in['email'] ?? '')));
$password = (string)($in['password'] ?? '');
if ($email === '' || $password === '') json_out(422, ['error' => 'Enter your email and password.']);

$pdo = db();
$ip = client_ip();

// Rate limit: 5 failed tries per email+IP per 15 minutes.
$st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
$st->execute([$email, $ip]);
if ((int)$st->fetchColumn() >= 5) json_out(429, ['error' => 'Too many attempts. Try again in 15 minutes.']);

$st = $pdo->prepare('SELECT id, email, name, role, password_hash FROM users WHERE email = ? AND active = 1');
$st->execute([$email]);
$u = $st->fetch();

// Verify against a dummy hash when the user doesn't exist, so timing doesn't reveal valid emails.
$hash = $u['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
$ok = password_verify($password, $hash) && $u !== false;

$pdo->prepare('INSERT INTO login_attempts (email, ip, success) VALUES (?, ?, ?)')->execute([$email, $ip, $ok ? 1 : 0]);
if (!$ok) json_out(401, ['error' => 'That email and password don’t match.']);

if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
}
$pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$u['id']]);

start_session();
session_regenerate_id(true);
$_SESSION = ['uid' => (int)$u['id'], 'seen' => time()];

json_out(200, ['user' => public_user($u), 'csrf' => csrf_token()]);
