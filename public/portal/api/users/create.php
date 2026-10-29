<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require_method('POST');
check_csrf();
$me = require_user('owner');

$in = input();
$name = trim((string)($in['name'] ?? ''));
$email = strtolower(trim((string)($in['email'] ?? '')));
$role = (string)($in['role'] ?? 'staff');

if ($name === '' || mb_strlen($name) > 120) json_out(422, ['error' => 'Enter a name.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) json_out(422, ['error' => 'Enter a valid email.']);
if (!in_array($role, ['owner', 'staff'], true)) json_out(422, ['error' => 'Pick a role.']);

$pdo = db();
$st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) json_out(409, ['error' => 'Someone already has a login with that email.']);

$temp = temp_password();
$pdo->prepare(
    'INSERT INTO users (email, name, role, password_hash, must_change_password, password_changed_at, created_by)
     VALUES (?, ?, ?, ?, 1, NOW(), ?)'
)->execute([$email, $name, $role, password_hash($temp, PASSWORD_DEFAULT), (int)$me['id']]);

// The temporary password is returned once and never stored in readable form.
json_out(201, ['id' => (int)$pdo->lastInsertId(), 'temp_password' => $temp]);
