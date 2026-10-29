<?php
/*
 * Read by the website build (GitHub Action, or `npm run build` locally): every live post.
 * Public content only. Sending X-Build-Key (the migrate key) also records when the
 * site was last built, so the scheduler knows which scheduled posts still need a rebuild.
 */
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require __DIR__ . '/../_lib/posts.php';
require_method('GET');

$pdo = db();
$rows = $pdo->query(
    "SELECT slug, title, meta_title, description, body_html, image, author,
            UNIX_TIMESTAMP(COALESCE(published_at, publish_at, created_at)) AS date_ts,
            UNIX_TIMESTAMP(updated_at) AS updated_ts
       FROM posts
      WHERE " . LIVE_SQL . "
      ORDER BY COALESCE(published_at, publish_at, created_at) DESC, id DESC"
)->fetchAll();

$key = $_SERVER['HTTP_X_BUILD_KEY'] ?? '';
$cfgKey = config()['migrate_key'] ?? '';
if (is_string($key) && $key !== '' && is_string($cfgKey) && strlen($cfgKey) >= 32 && hash_equals($cfgKey, $key)) {
    // Scheduled posts that just went live: stamp their published date.
    $pdo->exec("UPDATE posts SET status = 'published', published_at = COALESCE(published_at, publish_at) WHERE status = 'scheduled' AND publish_at <= UTC_TIMESTAMP()");
    $pdo->exec("INSERT INTO settings (name, value) VALUES ('last_build', JSON_OBJECT('at', UTC_TIMESTAMP())) ON DUPLICATE KEY UPDATE value = VALUES(value)");
}

json_out(200, ['posts' => array_map(fn($r) => [
    'slug' => $r['slug'],
    'title' => $r['title'],
    'metaTitle' => $r['meta_title'],
    'description' => $r['description'] ?: plain_excerpt($r['body_html']),
    'html' => $r['body_html'],
    'image' => $r['image'],
    'author' => $r['author'],
    'date' => gmdate('c', (int)$r['date_ts']),
    'updated' => gmdate('c', (int)$r['updated_ts']),
], $rows)]);
