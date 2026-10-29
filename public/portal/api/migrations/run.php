<?php
/**
 * Migration runner. Applies any NNN_name.sql in this folder not yet recorded
 * in schema_migrations, in filename order.
 *
 *   POST /portal/api/migrations/run.php   header X-Migrate-Key: <migrate_key>
 *   POST ...?dry=1                        lists pending without applying
 *
 * Self-contained on purpose: the Migrate workflow uploads only this folder,
 * so it must not depend on app code that hasn't been deployed yet.
 */
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');

function out(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405, ['error' => 'POST only']);

$configPath = getenv('PORTAL_CONFIG') ?: dirname($_SERVER['DOCUMENT_ROOT'] ?: __DIR__ . '/../../../..') . '/portal-config.php';
if (!is_file($configPath)) out(500, ['error' => 'Config file not found', 'expected' => $configPath]);
$config = require $configPath;

$key = $_SERVER['HTTP_X_MIGRATE_KEY'] ?? '';
if (!is_string($config['migrate_key'] ?? null) || strlen($config['migrate_key']) < 32 || !hash_equals($config['migrate_key'], $key)) {
    out(403, ['error' => 'Bad migrate key']);
}

try {
    $db = $config['db'];
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
        $db['user'], $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    out(500, ['error' => 'Database connection failed', 'detail' => $e->getMessage()]);
}

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(__DIR__ . '/[0-9][0-9][0-9]_*.sql') ?: [];
sort($files, SORT_STRING);

$pending = [];
foreach ($files as $f) {
    $v = basename($f, '.sql');
    if (!in_array($v, $applied, true)) $pending[] = $f;
}

if (isset($_GET['dry'])) {
    out(200, ['applied' => $applied, 'pending' => array_map(fn($f) => basename($f, '.sql'), $pending)]);
}

$done = [];
foreach ($pending as $f) {
    $v = basename($f, '.sql');
    $sql = file_get_contents($f);
    // Split on semicolons at line ends. Migrations here are plain DDL/DML, no procedures.
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)), fn($s) => $s !== '' && !preg_match('/^(--[^\n]*\s*)+$/', $s));
    try {
        foreach ($statements as $i => $stmt) {
            $pdo->exec($stmt);
        }
        $ins = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $ins->execute([$v]);
        $done[] = $v;
    } catch (Throwable $e) {
        out(500, [
            'error' => "Migration $v failed at statement " . ((int)$i + 1),
            'detail' => $e->getMessage(),
            'applied_this_run' => $done,
            'note' => 'MySQL commits each CREATE/ALTER immediately. Statements before the failing one stayed applied; fix the file so it can re-run safely (IF NOT EXISTS) and run again.',
        ]);
    }
}

out(200, ['applied_this_run' => $done, 'already_applied' => count($applied), 'status' => $done ? 'migrated' : 'up to date']);
