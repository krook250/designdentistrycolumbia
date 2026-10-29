<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require __DIR__ . '/../_lib/posts.php';
require_method('POST');
check_csrf();
require_user();

$id = (int)(input()['id'] ?? 0);
$p = load_post($id);
if (!$p) json_out(404, ['error' => 'That post no longer exists.']);
// Only drafts can be deleted. A live post has to be unpublished first, on purpose.
if ($p['status'] !== 'draft') json_out(422, ['error' => 'Unpublish the post before deleting it.']);

db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
json_out(200, ['ok' => true]);
