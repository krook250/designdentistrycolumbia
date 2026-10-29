<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require_method('POST');
check_csrf();
$me = require_user('owner');

$in = input();
$id = (int)($in['id'] ?? 0);
$pdo = db();
$st = $pdo->prepare('SELECT id, name, role, active FROM users WHERE id = ?');
$st->execute([$id]);
$u = $st->fetch();
if (!$u) json_out(404, ['error' => 'That login no longer exists.']);

$name = array_key_exists('name', $in) ? trim((string)$in['name']) : $u['name'];
$role = array_key_exists('role', $in) ? (string)$in['role'] : $u['role'];
$active = array_key_exists('active', $in) ? ($in['active'] ? 1 : 0) : (int)$u['active'];

if ($name === '' || mb_strlen($name) > 120) json_out(422, ['error' => 'Enter a name.']);
if (!in_array($role, ['owner', 'staff'], true)) json_out(422, ['error' => 'Pick a role.']);

$isMe = (int)$u['id'] === (int)$me['id'];
if ($isMe && ($role !== 'owner' || $active !== 1)) {
    json_out(422, ['error' => 'You can’t remove your own owner access. Ask another owner.']);
}
$losingOwner = $u['role'] === 'owner' && (int)$u['active'] === 1 && ($role !== 'owner' || $active !== 1);
if ($losingOwner && active_owner_count() <= 1) {
    json_out(422, ['error' => 'There has to be at least one active owner.']);
}

$pdo->prepare('UPDATE users SET name = ?, role = ?, active = ? WHERE id = ?')->execute([$name, $role, $active, $id]);
json_out(200, ['ok' => true]);
