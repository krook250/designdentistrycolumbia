<?php
declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';
require_method('GET');

$u = current_user();
if (!$u) {
    // Tell the client whether first-time setup is still open.
    $needsSetup = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    json_out(401, ['error' => 'Sign in required.', 'needs_setup' => $needsSetup]);
}
json_out(200, ['user' => public_user($u), 'csrf' => csrf_token()]);
