<?php

require_once __DIR__ . '/../config.php';

function ensureRmisTables(mysqli $conn): bool
{
    $sqlDocuments = "CREATE TABLE IF NOT EXISTS rmis_documents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        serve_id VARCHAR(64) NOT NULL,
        document_type VARCHAR(80) NOT NULL DEFAULT '',
        date_issued DATE NULL,
        document_series VARCHAR(20) NOT NULL DEFAULT '',
        document_number VARCHAR(20) NOT NULL DEFAULT '',
        origin_office VARCHAR(255) NOT NULL DEFAULT '',
        subject TEXT,
        effectivity VARCHAR(120) NOT NULL DEFAULT '',
        date_received DATE NULL,
        pdf_url VARCHAR(255) NOT NULL DEFAULT '',
        first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_rmis_serve_id (serve_id),
        KEY idx_rmis_date_received (date_received),
        KEY idx_rmis_date_issued (date_issued),
        KEY idx_rmis_document_type (document_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sqlSyncLog = "CREATE TABLE IF NOT EXISTS rmis_sync_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mode VARCHAR(20) NOT NULL DEFAULT 'incremental',
        status VARCHAR(20) NOT NULL DEFAULT 'running',
        start_page INT UNSIGNED NOT NULL DEFAULT 1,
        end_page INT UNSIGNED NULL,
        pages_fetched INT UNSIGNED NOT NULL DEFAULT 0,
        rows_parsed INT UNSIGNED NOT NULL DEFAULT 0,
        rows_inserted INT UNSIGNED NOT NULL DEFAULT 0,
        rows_updated INT UNSIGNED NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sqlState = "CREATE TABLE IF NOT EXISTS rmis_sync_state (
        state_key VARCHAR(50) PRIMARY KEY,
        state_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $ok = $conn->query($sqlDocuments) === true
        && $conn->query($sqlSyncLog) === true
        && $conn->query($sqlState) === true;

    if ($ok) {
        rmisRepairStoredPdfUrls($conn);
    }

    return $ok;
}

function rmisNormalizeServeId(string $raw): string
{
    // Strip all Unicode whitespace (wRMIS HTML often has trailing spaces in href).
    $raw = preg_replace('/[\p{Z}\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $raw) ?? '';
    return trim($raw);
}

function rmisBuildPdfPath(string $serveId): string
{
    $id = rmisNormalizeServeId($serveId);
    return $id === '' ? '' : '/RMIS/Serve/' . $id;
}

function rmisPublicPdfUrl(string $baseUrl, string $serveId): string
{
    $path = rmisBuildPdfPath($serveId);
    if ($path === '') {
        return '';
    }

    return rtrim($baseUrl, '/') . $path;
}

/** Rebuild serve_id and pdf_url for every row (fixes %20%20 broken PDF links). */
function rmisRepairStoredPdfUrls(mysqli $conn): int
{
    $result = $conn->query('SELECT id, serve_id, pdf_url FROM rmis_documents');
    if ($result === false) {
        return 0;
    }

    $stmt = $conn->prepare(
        'UPDATE rmis_documents SET serve_id = ?, pdf_url = ? WHERE id = ?'
    );
    if ($stmt === false) {
        $result->free();
        return 0;
    }

    $fixed = 0;
    while ($row = $result->fetch_assoc()) {
        $cleanId = rmisNormalizeServeId((string) ($row['serve_id'] ?? ''));
        if ($cleanId === '') {
            continue;
        }

        $cleanPath = rmisBuildPdfPath($cleanId);
        if ($cleanId === ($row['serve_id'] ?? '') && $cleanPath === ($row['pdf_url'] ?? '')) {
            continue;
        }

        $id = (int) $row['id'];
        $stmt->bind_param('ssi', $cleanId, $cleanPath, $id);
        if ($stmt->execute()) {
            $fixed++;
        }
    }

    $stmt->close();
    $result->free();

    return $fixed;
}

function rmisGetSyncState(mysqli $conn, string $key, $default = null)
{
    $stmt = $conn->prepare('SELECT state_value FROM rmis_sync_state WHERE state_key = ? LIMIT 1');
    if ($stmt === false) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $default;
    }

    $decoded = json_decode($row['state_value'], true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $row['state_value'];
}

function rmisSetSyncState(mysqli $conn, string $key, $value): bool
{
    $encoded = is_string($value) ? $value : json_encode($value);
    $stmt = $conn->prepare(
        'INSERT INTO rmis_sync_state (state_key, state_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE state_value = VALUES(state_value), updated_at = CURRENT_TIMESTAMP'
    );

    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('ss', $key, $encoded);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array{inserted:int,updated:int}
 */
function rmisUpsertDocuments(mysqli $conn, array $rows): array
{
    $inserted = 0;
    $updated = 0;

    $stmt = $conn->prepare(
        'INSERT INTO rmis_documents (
            serve_id, document_type, date_issued, document_series, document_number,
            origin_office, subject, effectivity, date_received, pdf_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            document_type = VALUES(document_type),
            date_issued = VALUES(date_issued),
            document_series = VALUES(document_series),
            document_number = VALUES(document_number),
            origin_office = VALUES(origin_office),
            subject = VALUES(subject),
            effectivity = VALUES(effectivity),
            date_received = VALUES(date_received),
            pdf_url = VALUES(pdf_url),
            last_seen_at = CURRENT_TIMESTAMP'
    );

    if ($stmt === false) {
        return ['inserted' => 0, 'updated' => 0];
    }

    foreach ($rows as $row) {
        $serveId = rmisNormalizeServeId((string) ($row['serve_id'] ?? ''));
        if ($serveId === '') {
            continue;
        }

        $documentType = (string) ($row['document_type'] ?? '');
        $dateIssued = $row['date_issued'] ?? null;
        $documentSeries = (string) ($row['document_series'] ?? '');
        $documentNumber = (string) ($row['document_number'] ?? '');
        $originOffice = (string) ($row['origin_office'] ?? '');
        $subject = (string) ($row['subject'] ?? '');
        $effectivity = (string) ($row['effectivity'] ?? '');
        $dateReceived = $row['date_received'] ?? null;
        $pdfUrl = rmisBuildPdfPath($serveId);

        $stmt->bind_param(
            'ssssssssss',
            $serveId,
            $documentType,
            $dateIssued,
            $documentSeries,
            $documentNumber,
            $originOffice,
            $subject,
            $effectivity,
            $dateReceived,
            $pdfUrl
        );

        if (!$stmt->execute()) {
            continue;
        }

        if ($stmt->affected_rows === 1) {
            $inserted++;
        } elseif ($stmt->affected_rows === 2) {
            $updated++;
        }
    }

    $stmt->close();

    return ['inserted' => $inserted, 'updated' => $updated];
}

function rmisCountDocuments(mysqli $conn): int
{
    $result = $conn->query('SELECT COUNT(*) AS total FROM rmis_documents');
    if ($result === false) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (int) ($row['total'] ?? 0);
}

function rmisGetRecentDocuments(mysqli $conn, int $limit = 50): array
{
    $result = rmisSearchDocuments($conn, [], 1, $limit);
    return $result['rows'];
}

/**
 * @param array{document_type?:string,year_issued?:int,origin_office?:string,origin?:string,q?:string} $filters
 * @return array{document_type:string,year_issued:int,origin_office:string,q:string}
 */
function rmisParseDocumentFilters(array $filters): array
{
    $originOffice = trim((string) ($filters['origin_office'] ?? ''));
    if ($originOffice === '' && isset($filters['origin'])) {
        $originOffice = trim((string) $filters['origin']);
    }

    $year = (int) ($filters['year_issued'] ?? 0);
    if ($year < 1990 || $year > 2100) {
        $year = 0;
    }

    return [
        'document_type' => trim((string) ($filters['document_type'] ?? '')),
        'year_issued' => $year,
        'origin_office' => $originOffice,
        'q' => trim((string) ($filters['q'] ?? '')),
    ];
}

/**
 * @return array{where:string,types:string,params:array<int,mixed>,labels:array<string,string>}
 */
function rmisBuildFilterWhere(array $filters): array
{
    $f = rmisParseDocumentFilters($filters);
    $clauses = [];
    $types = '';
    $params = [];
    $labels = [];

    if ($f['document_type'] !== '') {
        $clauses[] = 'document_type = ?';
        $types .= 's';
        $params[] = $f['document_type'];
        $labels[] = 'Type: ' . $f['document_type'];
    }

    if ($f['year_issued'] > 0) {
        $clauses[] = 'YEAR(date_issued) = ?';
        $types .= 'i';
        $params[] = $f['year_issued'];
        $labels[] = 'Year issued: ' . $f['year_issued'];
    }

    if ($f['origin_office'] !== '') {
        $clauses[] = 'origin_office = ?';
        $types .= 's';
        $params[] = $f['origin_office'];
        $labels[] = 'Office: ' . $f['origin_office'];
    }

    if ($f['q'] !== '') {
        $like = '%' . $f['q'] . '%';
        $clauses[] = '(
            document_type LIKE ?
            OR document_series LIKE ?
            OR document_number LIKE ?
            OR origin_office LIKE ?
            OR subject LIKE ?
            OR serve_id LIKE ?
        )';
        $types .= 'ssssss';
        $params = array_merge($params, array_fill(0, 6, $like));
        $labels[] = 'Search: ' . $f['q'];
    }

    $where = $clauses !== [] ? ' WHERE ' . implode(' AND ', $clauses) : '';

    return [
        'where' => $where,
        'types' => $types,
        'params' => $params,
        'labels' => $labels,
        'parsed' => $f,
    ];
}

function rmisGetOriginOffices(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT DISTINCT origin_office FROM rmis_documents
         WHERE origin_office <> ""
         ORDER BY origin_office ASC'
    );
    if ($result === false) {
        return [];
    }

    $offices = [];
    while ($row = $result->fetch_assoc()) {
        $name = trim((string) ($row['origin_office'] ?? ''));
        if ($name !== '') {
            $offices[] = $name;
        }
    }
    $result->free();

    return $offices;
}

function rmisGetIssuedYears(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT DISTINCT YEAR(date_issued) AS y FROM rmis_documents
         WHERE date_issued IS NOT NULL
         ORDER BY y DESC'
    );
    if ($result === false) {
        return [];
    }

    $years = [];
    while ($row = $result->fetch_assoc()) {
        $y = (int) ($row['y'] ?? 0);
        if ($y > 0) {
            $years[] = $y;
        }
    }
    $result->free();

    return $years;
}

/**
 * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
 */
function rmisSearchDocuments(
    mysqli $conn,
    array $filters,
    int $page = 1,
    int $perPage = 25
): array {
    $page = max(1, $page);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $built = rmisBuildFilterWhere($filters);
    $where = $built['where'];
    $types = $built['types'];
    $params = $built['params'];

    $countSql = 'SELECT COUNT(*) AS total FROM rmis_documents' . $where;
    $stmt = $conn->prepare($countSql);
    if ($stmt === false) {
        return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 0];
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $countResult = $stmt->get_result();
    $total = (int) (($countResult->fetch_assoc()['total'] ?? 0));
    $stmt->close();

    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $sql = "SELECT serve_id, document_type, date_issued, document_series, document_number,
                   origin_office, subject, effectivity, date_received, pdf_url,
                   first_seen_at, last_seen_at
            FROM rmis_documents"
        . $where
        . ' ORDER BY COALESCE(date_received, date_issued, first_seen_at) DESC, id DESC
            LIMIT ? OFFSET ?';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return ['rows' => [], 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => $totalPages];
    }

    if ($types !== '') {
        $bindTypes = $types . 'ii';
        $bindParams = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($bindTypes, ...$bindParams);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['serve_id'] = rmisNormalizeServeId((string) ($row['serve_id'] ?? ''));
        $row['pdf_url'] = rmisBuildPdfPath($row['serve_id']);
        $rows[] = $row;
    }
    $stmt->close();

    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

function rmisDocumentTypes(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT DISTINCT document_type FROM rmis_documents
         WHERE document_type <> ""
         ORDER BY document_type ASC'
    );
    if ($result === false) {
        return [];
    }

    $types = [];
    while ($row = $result->fetch_assoc()) {
        $types[] = $row['document_type'];
    }
    $result->free();

    return $types;
}

/**
 * Monitoring report for current filters (table + aggregates).
 *
 * @return array<string,mixed>
 */
function rmisGetMonitoringReport(mysqli $conn, array $filters, int $listLimit = 500): array
{
    $built = rmisBuildFilterWhere($filters);
    $where = $built['where'];
    $types = $built['types'];
    $params = $built['params'];

    $total = 0;
    $countSql = 'SELECT COUNT(*) AS total FROM rmis_documents' . $where;
    $stmt = $conn->prepare($countSql);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();
    }

    $byType = [];
    $sqlType = 'SELECT document_type, COUNT(*) AS cnt FROM rmis_documents' . $where
        . ' GROUP BY document_type ORDER BY cnt DESC';
    $stmt = $conn->prepare($sqlType);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $byType[] = [
                'document_type' => $row['document_type'] ?? '',
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }
        $stmt->close();
    }

    $byOrigin = [];
    $sqlOrigin = 'SELECT origin_office, COUNT(*) AS cnt FROM rmis_documents' . $where
        . ' GROUP BY origin_office ORDER BY cnt DESC LIMIT 15';
    $stmt = $conn->prepare($sqlOrigin);
    if ($stmt) {
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $byOrigin[] = [
                'origin_office' => $row['origin_office'] ?? '',
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }
        $stmt->close();
    }

    $byMonth = [];
    if (($built['parsed']['year_issued'] ?? 0) > 0) {
        $sqlMonth = 'SELECT MONTH(date_issued) AS m, COUNT(*) AS cnt FROM rmis_documents' . $where
            . ' AND date_issued IS NOT NULL GROUP BY MONTH(date_issued) ORDER BY m ASC';
        $stmt = $conn->prepare($sqlMonth);
        if ($stmt) {
            if ($types !== '') {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $byMonth[] = [
                    'month' => (int) ($row['m'] ?? 0),
                    'count' => (int) ($row['cnt'] ?? 0),
                ];
            }
            $stmt->close();
        }
    }

    $listLimit = max(1, min(2000, $listLimit));
    $rows = [];
    $sqlList = "SELECT serve_id, document_type, date_issued, document_series, document_number,
                       origin_office, subject, effectivity, date_received
                FROM rmis_documents"
        . $where
        . ' ORDER BY COALESCE(date_received, date_issued) DESC, id DESC LIMIT ?';
    $stmt = $conn->prepare($sqlList);
    if ($stmt) {
        if ($types !== '') {
            $bindTypes = $types . 'i';
            $bindParams = array_merge($params, [$listLimit]);
            $stmt->bind_param($bindTypes, ...$bindParams);
        } else {
            $stmt->bind_param('i', $listLimit);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['serve_id'] = rmisNormalizeServeId((string) ($row['serve_id'] ?? ''));
            $rows[] = $row;
        }
        $stmt->close();
    }

    return [
        'filter_labels' => $built['labels'],
        'parsed_filters' => $built['parsed'],
        'total' => $total,
        'listed' => count($rows),
        'list_truncated' => $total > count($rows),
        'by_type' => $byType,
        'by_origin' => $byOrigin,
        'by_month' => $byMonth,
        'rows' => $rows,
    ];
}

function rmisStartSyncLog(mysqli $conn, string $mode, int $startPage): int
{
    $stmt = $conn->prepare(
        'INSERT INTO rmis_sync_log (mode, status, start_page) VALUES (?, "running", ?)'
    );
    if ($stmt === false) {
        return 0;
    }

    $stmt->bind_param('si', $mode, $startPage);
    $stmt->execute();
    $id = (int) $stmt->insert_id;
    $stmt->close();

    return $id;
}

function rmisFinishSyncLog(
    mysqli $conn,
    int $logId,
    string $status,
    array $stats,
    ?string $errorMessage = null
): void {
    if ($logId <= 0) {
        return;
    }

    $stmt = $conn->prepare(
        'UPDATE rmis_sync_log SET
            status = ?,
            end_page = ?,
            pages_fetched = ?,
            rows_parsed = ?,
            rows_inserted = ?,
            rows_updated = ?,
            error_message = ?,
            finished_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );

    if ($stmt === false) {
        return;
    }

    $endPage = (int) ($stats['end_page'] ?? 0);
    $pagesFetched = (int) ($stats['pages_fetched'] ?? 0);
    $rowsParsed = (int) ($stats['rows_parsed'] ?? 0);
    $rowsInserted = (int) ($stats['rows_inserted'] ?? 0);
    $rowsUpdated = (int) ($stats['rows_updated'] ?? 0);

    $stmt->bind_param(
        'siiiissi',
        $status,
        $endPage,
        $pagesFetched,
        $rowsParsed,
        $rowsInserted,
        $rowsUpdated,
        $errorMessage,
        $logId
    );
    $stmt->execute();
    $stmt->close();
}

function rmisGetLatestSyncLogs(mysqli $conn, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    $sql = "SELECT id, mode, status, start_page, end_page, pages_fetched, rows_parsed,
                   rows_inserted, rows_updated, error_message, started_at, finished_at
            FROM rmis_sync_log
            ORDER BY id DESC
            LIMIT {$limit}";

    $result = $conn->query($sql);
    if ($result === false) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}
