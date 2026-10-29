<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require_method('GET');
$me = require_user('owner');

$rows = db()->query(
    "SELECT id, name, email, role, active, must_change_password,
            UNIX_TIMESTAMP(last_login_at) AS last_login_ts, UNIX_TIMESTAMP(created_at) AS created_ts
       FROM users
      ORDER BY active DESC, role = 'owner' DESC, name"
)->fetchAll();

$users = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'name' => $r['name'],
    'email' => $r['email'],
    'role' => $r['role'],
    'active' => (int)$r['active'] === 1,
    'pending' => (int)$r['must_change_password'] === 1,
    'last_login' => $r['last_login_ts'] === null ? null : (int)$r['last_login_ts'],
    'created' => (int)$r['created_ts'],
    'is_me' => (int)$r['id'] === (int)$me['id'],
], $rows);

json_out(200, ['users' => $users]);
