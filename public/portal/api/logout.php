<?php
declare(strict_types=1);
require __DIR__ . '/_lib/bootstrap.php';
require_method('POST');
check_csrf();

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
json_out(200, ['ok' => true]);
