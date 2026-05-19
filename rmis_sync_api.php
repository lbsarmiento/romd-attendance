<?php

require_once 'config.php';
require_once 'includes/rmis_sync.php';

requireAdmin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$mode = $payload['mode'] ?? 'incremental';
if (!in_array($mode, ['incremental', 'full'], true)) {
    $mode = 'incremental';
}

$conn = getDBConnection();
ensureRmisTables($conn);
$cursor = rmisGetSyncState($conn, 'cursor', ['next_page' => 1]);

$startPage = isset($payload['start_page'])
    ? (int) $payload['start_page']
    : (int) ($cursor['next_page'] ?? 1);

if (!empty($payload['reset'])) {
    $startPage = 1;
    rmisSetSyncState($conn, 'cursor', ['next_page' => 1, 'mode' => $mode, 'completed' => false]);
}

$result = rmisRunSyncBatch($conn, [
    'mode' => $mode,
    'start_page' => max(1, $startPage),
    'max_pages' => (int) ($payload['max_pages'] ?? 10),
    'force_login' => !empty($payload['force_login']),
]);

$conn->close();
echo json_encode($result);
