<?php
/*
 * Create or update a post, and optionally change what happens to it:
 *   action: 'save'      keep its current state (a live post's changes go live)
 *           'publish'   live now
 *           'schedule'  live at publish_at (Unix seconds)
 *           'unpublish' back to draft (taken off the site)
 * Any change that affects the public site asks GitHub to rebuild it.
 */
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require __DIR__ . '/../_lib/posts.php';
require_method('POST');
check_csrf();
$me = require_user();

$in = input();
$id = isset($in['id']) && $in['id'] ? (int)$in['id'] : null;
$action = (string)($in['action'] ?? 'save');
if (!in_array($action, ['save', 'publish', 'schedule', 'unpublish'], true)) json_out(422, ['error' => 'Unknown action.']);

$pdo = db();
$old = $id ? load_post($id) : null;
if ($id && !$old) json_out(404, ['error' => 'That post no longer exists.']);
$wasLive = $old && ($old['status'] === 'published' || ($old['status'] === 'scheduled' && $old['publish_at_ts'] !== null && (int)$old['publish_at_ts'] <= time()));

$title = trim((string)($in['title'] ?? ''));
$body = clean_html((string)($in['body_html'] ?? ''));
$description = trim((string)($in['description'] ?? ''));
$metaTitle = trim((string)($in['meta_title'] ?? ''));
$image = trim((string)($in['image'] ?? ''));
$author = trim((string)($in['author'] ?? 'Dr. Isaiah Davis'));
$slug = strtolower(trim((string)($in['slug'] ?? '')));

if ($title === '') json_out(422, ['error' => 'Give the post a title.', 'field' => 'title']);
if (mb_strlen($title) > 255) json_out(422, ['error' => 'That title is too long.', 'field' => 'title']);
if (mb_strlen($description) > 1000) json_out(422, ['error' => 'That Google description is too long.', 'field' => 'description']);
if ($image !== '' && !preg_match('#^/(uploads|images)/[A-Za-z0-9._/-]+$#', $image)) json_out(422, ['error' => 'That featured image isn’t valid. Upload it again.']);
if (!in_array($author, ['Dr. Isaiah Davis', 'Design Dentistry Team', 'Isaiah Davis', 'Progressive Dental'], true)) $author = 'Dr. Isaiah Davis';

// The web address is fixed once a post has been live, so links and Google rankings don't break.
if ($old && ($old['published_at_ts'] !== null || $wasLive)) {
    $slug = $old['slug'];
} else {
    if ($slug === '') $slug = slugify($title);
    if ($problem = slug_problem($slug, $id)) json_out(422, ['error' => $problem, 'field' => 'slug']);
}

$goingLive = in_array($action, ['publish', 'schedule'], true) || ($action === 'save' && $wasLive);
if ($goingLive && trim(strip_tags($body)) === '') json_out(422, ['error' => 'Write something before publishing.', 'field' => 'body']);

// Work out the new status.
$status = $old['status'] ?? 'draft';
$publishAt = $old['publish_at_ts'] ?? null;
if ($action === 'publish') { $status = 'published'; $publishAt = time(); }
if ($action === 'schedule') {
    $publishAt = (int)($in['publish_at'] ?? 0);
    if ($publishAt < time() + 60) json_out(422, ['error' => 'Pick a time in the future to schedule it.', 'field' => 'publish_at']);
    if ($publishAt > time() + 366 * 86400) json_out(422, ['error' => 'Schedule it within the next year.', 'field' => 'publish_at']);
    $status = 'scheduled';
}
if ($action === 'unpublish') { $status = 'draft'; $publishAt = null; }

$publishedAt = $old['published_at_ts'] ?? null;
if ($action === 'publish' && !$publishedAt) $publishedAt = time();
if ($action === 'schedule' && !$publishedAt) $publishedAt = null; // set when it goes live (feed fills it in)

$params = [
    $slug, $title, $metaTitle !== '' ? $metaTitle : null, $description !== '' ? $description : null, $body,
    $image !== '' ? $image : null, $author, $status,
    $publishAt !== null ? gmdate('Y-m-d H:i:s', (int)$publishAt) : null,
    $publishedAt !== null ? gmdate('Y-m-d H:i:s', (int)$publishedAt) : null,
    (int)$me['id'],
];

if ($old) {
    $pdo->prepare('UPDATE posts SET slug=?, title=?, meta_title=?, description=?, body_html=?, image=?, author=?, status=?, publish_at=?, published_at=?, updated_by=? WHERE id=?')
        ->execute([...$params, $id]);
} else {
    $pdo->prepare('INSERT INTO posts (slug, title, meta_title, description, body_html, image, author, status, publish_at, published_at, updated_by, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([...$params, (int)$me['id']]);
    $id = (int)$pdo->lastInsertId();
}

// Rebuild when the public site changes now. A future schedule is picked up by the cron job.
$siteChanges = $action === 'publish' || ($action === 'save' && $wasLive) || ($action === 'unpublish' && $wasLive)
    || ($action === 'schedule' && $wasLive);
$rebuildProblem = $siteChanges ? trigger_rebuild("$action: $title") : null;

json_out(200, [
    'post' => post_for_editor(load_post($id)),
    'site_update' => $siteChanges ? ($rebuildProblem === null ? 'started' : 'failed') : 'none',
    'site_update_problem' => $rebuildProblem,
]);
