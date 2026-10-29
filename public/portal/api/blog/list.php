<?php
declare(strict_types=1);
require __DIR__ . '/../_lib/bootstrap.php';
require __DIR__ . '/../_lib/posts.php';
require_method('GET');
require_user();

$tab = (string)($_GET['tab'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;

$where = [];
$args = [];
if ($tab === 'published') $where[] = LIVE_SQL;
elseif ($tab === 'drafts') $where[] = "status = 'draft'";
elseif ($tab === 'scheduled') $where[] = "(status = 'scheduled' AND publish_at > UTC_TIMESTAMP())";
if ($q !== '') {
    $where[] = '(title LIKE ? OR slug LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    array_push($args, $like, $like);
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = db();
$st = $pdo->prepare("SELECT COUNT(*) FROM posts $sqlWhere");
$st->execute($args);
$total = (int)$st->fetchColumn();

// Drafts and upcoming first, then newest published.
$st = $pdo->prepare(
    "SELECT id, slug, title, image, status,
            UNIX_TIMESTAMP(publish_at) AS publish_at_ts, UNIX_TIMESTAMP(published_at) AS published_at_ts,
            UNIX_TIMESTAMP(updated_at) AS updated_at_ts,
            " . LIVE_SQL . " AS live
       FROM posts $sqlWhere
      ORDER BY (status = 'draft') DESC, (status = 'scheduled' AND publish_at > UTC_TIMESTAMP()) DESC,
               COALESCE(published_at, publish_at, updated_at) DESC, id DESC
      LIMIT $per OFFSET " . (($page - 1) * $per)
);
$st->execute($args);

$counts = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(" . LIVE_SQL . ") AS published,
            SUM(status = 'draft') AS drafts,
            SUM(status = 'scheduled' AND publish_at > UTC_TIMESTAMP()) AS scheduled
       FROM posts"
)->fetch();

$posts = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'slug' => $r['slug'],
    'title' => $r['title'],
    'image' => $r['image'],
    'state' => (int)$r['live'] === 1 ? 'published' : $r['status'],
    'publish_at' => $r['publish_at_ts'] !== null ? (int)$r['publish_at_ts'] : null,
    'published_at' => $r['published_at_ts'] !== null ? (int)$r['published_at_ts'] : ($r['publish_at_ts'] !== null && (int)$r['live'] === 1 ? (int)$r['publish_at_ts'] : null),
    'updated_at' => (int)$r['updated_at_ts'],
], $st->fetchAll());

json_out(200, [
    'posts' => $posts,
    'total' => $total,
    'page' => $page,
    'pages' => max(1, (int)ceil($total / $per)),
    'counts' => [
        'all' => (int)$counts['total'],
        'published' => (int)$counts['published'],
        'drafts' => (int)$counts['drafts'],
        'scheduled' => (int)$counts['scheduled'],
    ],
]);
