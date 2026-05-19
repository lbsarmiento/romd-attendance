<?php
/**
 * CLI / scheduled sync for wRMIS.
 *
 * Examples:
 *   php rmis_cron.php incremental
 *   php rmis_cron.php full 25
 *
 * HTTP (optional): /rmis_cron.php?secret=YOUR_CRON_SECRET&mode=incremental
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/rmis_config.php';
require_once __DIR__ . '/includes/rmis_sync.php';

$isCli = PHP_SAPI === 'cli';
$config = getRmisConfig();

if (!$isCli) {
    $secret = (string) ($config['cron_secret'] ?? '');
    $provided = (string) ($_GET['secret'] ?? '');
    if ($secret === '' || !hash_equals($secret, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Forbidden']);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
}

$mode = $isCli ? ($argv[1] ?? 'incremental') : ($_GET['mode'] ?? 'incremental');
if (!in_array($mode, ['incremental', 'full'], true)) {
    $mode = 'incremental';
}

$maxPages = $isCli ? (int) ($argv[2] ?? 10) : (int) ($_GET['max_pages'] ?? 10);
$maxPages = max(1, min(100, $maxPages));

if ($isCli) {
    set_time_limit(0);
}

$conn = getDBConnection();
ensureRmisTables($conn);

$cursor = rmisGetSyncState($conn, 'cursor', ['next_page' => 1]);
$startPage = (int) ($cursor['next_page'] ?? 1);

$result = rmisRunSyncBatch($conn, [
    'mode' => $mode,
    'start_page' => $startPage,
    'max_pages' => $maxPages,
    'force_login' => true,
]);

$conn->close();

$output = json_encode($result, JSON_PRETTY_PRINT);
if ($isCli) {
    echo $output . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}

echo $output;
