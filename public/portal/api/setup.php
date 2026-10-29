<?php
/*
 * One-time: creates the first owner account. Needs the migrate key from the
 * config file. Closes itself for good once any user exists.
 */
declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';
require_method('POST');

$pdo = db();
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    json_out(410, ['error' => 'Setup is already done. Sign in instead.']);
}

$in = input();
$key = (string)($in['setup_key'] ?? '');
$name = trim((string)($in['name'] ?? ''));
$email = strtolower(trim((string)($in['email'] ?? '')));
$password = (string)($in['password'] ?? '');

$cfgKey = config()['migrate_key'] ?? '';
if (!is_string($cfgKey) || strlen($cfgKey) < 32 || !hash_equals($cfgKey, $key)) json_out(403, ['error' => 'Setup key is wrong.']);
if ($name === '' || mb_strlen($name) > 120) json_out(422, ['error' => 'Enter a name.']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(422, ['error' => 'Enter a valid email.']);
if (strlen($password) < 10) json_out(422, ['error' => 'Use at least 10 characters for the password.']);

$pdo->prepare('INSERT INTO users (email, name, role, password_hash) VALUES (?, ?, \'owner\', ?)')
    ->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);

json_out(201, ['ok' => true]);
