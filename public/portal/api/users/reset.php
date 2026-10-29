<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require_method('POST');
check_csrf();
$me = require_user('owner');

$id = (int)(input()['id'] ?? 0);
if ($id === (int)$me['id']) json_out(422, ['error' => 'Change your own password under My account.']);

$pdo = db();
$st = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$st->execute([$id]);
if (!$st->fetch()) json_out(404, ['error' => 'That login no longer exists.']);

$temp = temp_password();
// Stamping password_changed_at signs them out everywhere.
$pdo->prepare('UPDATE users SET password_hash = ?, must_change_password = 1, password_changed_at = NOW() WHERE id = ?')
    ->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);

json_out(200, ['temp_password' => $temp]);
