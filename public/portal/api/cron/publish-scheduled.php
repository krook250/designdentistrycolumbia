<?php
/*
 * cPanel cron job (every 15 minutes):
 *   php /home/<account>/public_html/portal/api/cron/publish-scheduled.php
 * If a scheduled post's time has come and the site hasn't been rebuilt since, ask GitHub to rebuild.
 * Command line only; the web server refuses this folder.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../_lib/bootstrap.php';
require __DIR__ . '/../_lib/posts.php';

$pdo = db();
$last = $pdo->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(value, '$.at')) FROM settings WHERE name = 'last_build'")->fetchColumn();
$lastReq = $pdo->query("SELECT JSON_UNQUOTE(JSON_EXTRACT(value, '$.at')) FROM settings WHERE name = 'last_rebuild_request'")->fetchColumn();

// The build marks due posts as published, so anything still 'scheduled' and past due hasn't been built yet.
$due = (int)$pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'scheduled' AND publish_at <= UTC_TIMESTAMP()")->fetchColumn();

if ($due === 0) { echo "nothing due\n"; exit(0); }
// Don't pile up requests while a build is already on its way (allow 20 minutes).
if ($lastReq && strtotime($lastReq . ' UTC') > time() - 20 * 60 && (!$last || $lastReq > $last)) { echo "rebuild already requested\n"; exit(0); }

$problem = trigger_rebuild("scheduled: $due post(s)");
echo $problem === null ? "rebuild requested for $due post(s)\n" : "rebuild failed: $problem\n";
exit($problem === null ? 0 : 1);
