<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require_method('POST');
check_csrf();
$me = require_user(null, true);

$in = input();
$current = (string)($in['current_password'] ?? '');
$new = (string)($in['new_password'] ?? '');

$pdo = db();
$st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$st->execute([$me['id']]);
$hash = (string)$st->fetchColumn();

if (!password_verify($current, $hash)) json_out(422, ['error' => 'Your current password isn’t right.', 'field' => 'current_password']);
if (strlen($new) < 10) json_out(422, ['error' => 'Use at least 10 characters.', 'field' => 'new_password']);
if (password_verify($new, $hash)) json_out(422, ['error' => 'Pick a password different from the current one.', 'field' => 'new_password']);

$pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?')
    ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);

// Other devices get signed out; this one carries on with a fresh session.
$st = $pdo->prepare('SELECT id, email, name, role, must_change_password, password_changed_at FROM users WHERE id = ?');
$st->execute([$me['id']]);
$u = $st->fetch();
sign_in($u);
json_out(200, ['user' => public_user($u), 'csrf' => csrf_token()]);
