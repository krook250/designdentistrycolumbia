<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require __DIR__ . '/../_lib/posts.php';
require_method('GET');
require_user();

$p = load_post((int)($_GET['id'] ?? 0));
if (!$p) json_out(404, ['error' => 'That post no longer exists.']);
json_out(200, ['post' => post_for_editor($p)]);
