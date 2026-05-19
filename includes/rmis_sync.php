<?php

require_once __DIR__ . '/RmisClient.php';
require_once __DIR__ . '/rmis_repository.php';

/**
 * @return array<string,mixed>
 */
function rmisRunSyncBatch(mysqli $conn, array $options = []): array
{
    $mode = $options['mode'] ?? 'incremental';
    $startPage = max(1, (int) ($options['start_page'] ?? 1));
    $maxPages = max(1, min(100, (int) ($options['max_pages'] ?? 5)));

    // Incremental = wRMIS page 1 only (newest documents at the top of the list).
    if ($mode === 'incremental') {
        $startPage = 1;
        $maxPages = 1;
    }
    $forceLogin = !empty($options['force_login']);

    if (!rmisConfigIsReady()) {
        return [
            'ok' => false,
            'message' => 'RMIS credentials are not configured. Copy includes/rmis_config.local.php.example to includes/rmis_config.local.php.',
        ];
    }

    if (!ensureRmisTables($conn)) {
        return ['ok' => false, 'message' => 'Unable to create RMIS database tables.'];
    }

    $config = getRmisConfig();
    $client = new RmisClient($config);

    if ($forceLogin) {
        $client->clearSession();
    }

    $login = $client->login((string) $config['email'], (string) $config['password']);
    if (!$login['ok']) {
        return ['ok' => false, 'message' => $login['message']];
    }

    $logId = rmisStartSyncLog($conn, $mode, $startPage);

    $pagesFetched = 0;
    $rowsParsed = 0;
    $rowsInserted = 0;
    $rowsUpdated = 0;
    $currentPage = $startPage;
    $totalPages = null;
    $stopEarly = false;
    $lastError = null;

    for ($i = 0; $i < $maxPages; $i++) {
        $html = $client->fetchRmisPage($currentPage);
        if ($html === null) {
            $lastError = 'Failed to fetch RMIS page ' . $currentPage . '.';
            break;
        }

        $parsed = RmisClient::parseRmisListHtml($html);
        if (!$parsed['raw_found']) {
            $lastError = 'No document rows found on page ' . $currentPage . '. Session may have expired.';
            break;
        }

        $totalPages = $parsed['total_pages'];
        $upsert = rmisUpsertDocuments($conn, $parsed['rows']);

        $pagesFetched++;
        $rowsParsed += count($parsed['rows']);
        $rowsInserted += $upsert['inserted'];
        $rowsUpdated += $upsert['updated'];

        if ($mode === 'incremental' && $upsert['inserted'] === 0) {
            $stopEarly = true;
            break;
        }

        if ($totalPages !== null && $currentPage >= $totalPages) {
            $currentPage++;
            break;
        }

        $currentPage++;
    }

    $completed = $lastError === null
        && ($totalPages === null || ($currentPage - 1) >= $totalPages || $stopEarly);

    $nextPage = $completed ? 1 : $currentPage;
    rmisSetSyncState($conn, 'cursor', [
        'next_page' => $nextPage,
        'total_pages' => $totalPages,
        'mode' => $mode,
        'completed' => $completed,
        'updated_at' => date('c'),
    ]);

    $status = $lastError ? 'failed' : ($completed ? 'completed' : 'partial');
    rmisFinishSyncLog($conn, $logId, $status, [
        'end_page' => max($startPage, $currentPage - 1),
        'pages_fetched' => $pagesFetched,
        'rows_parsed' => $rowsParsed,
        'rows_inserted' => $rowsInserted,
        'rows_updated' => $rowsUpdated,
    ], $lastError);

    return [
        'ok' => $lastError === null,
        'message' => $lastError ?? ($completed ? 'Sync batch finished.' : 'Sync batch saved progress; run again to continue.'),
        'mode' => $mode,
        'start_page' => $startPage,
        'next_page' => $nextPage,
        'total_pages' => $totalPages,
        'completed' => $completed,
        'pages_fetched' => $pagesFetched,
        'rows_parsed' => $rowsParsed,
        'rows_inserted' => $rowsInserted,
        'rows_updated' => $rowsUpdated,
        'documents_total' => rmisCountDocuments($conn),
    ];
}
