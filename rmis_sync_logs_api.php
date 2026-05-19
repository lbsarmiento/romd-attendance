<?php

require_once 'config.php';
require_once 'includes/rmis_repository.php';

requireAdmin();
header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
ensureRmisTables($conn);

$limit = max(1, min(50, (int) ($_GET['limit'] ?? 15)));
$logs = rmisGetLatestSyncLogs($conn, $limit);
$cursor = rmisGetSyncState($conn, 'cursor', ['next_page' => 1, 'total_pages' => null]);
$total = rmisCountDocuments($conn);

$conn->close();

echo json_encode([
    'ok' => true,
    'logs' => $logs,
    'documents_total' => $total,
    'cursor' => $cursor,
]);
