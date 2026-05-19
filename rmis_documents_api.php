<?php

require_once 'config.php';
require_once 'includes/rmis_config.php';
require_once 'includes/rmis_repository.php';

requireAdmin();
header('Content-Type: application/json; charset=utf-8');

$conn = getDBConnection();
ensureRmisTables($conn);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = max(5, min(100, (int) ($_GET['per_page'] ?? 25)));

$filters = rmisParseDocumentFilters([
    'document_type' => $_GET['document_type'] ?? '',
    'year_issued' => $_GET['year_issued'] ?? 0,
    'origin_office' => $_GET['origin_office'] ?? ($_GET['origin'] ?? ''),
    'q' => $_GET['q'] ?? '',
]);

$result = rmisSearchDocuments($conn, $filters, $page, $perPage);
$baseUrl = rtrim(getRmisConfig()['base_url'], '/');

$payload = [
    'ok' => true,
    'base_url' => $baseUrl,
    'filters' => $filters,
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'total' => $result['total'],
    'total_pages' => $result['total_pages'],
    'rows' => array_map(static function ($row) use ($baseUrl) {
        $row['serve_id'] = rmisNormalizeServeId((string) ($row['serve_id'] ?? ''));
        $row['pdf_url'] = rmisBuildPdfPath($row['serve_id']);
        $row['pdf_href'] = rmisPublicPdfUrl($baseUrl, $row['serve_id']);
        return $row;
    }, $result['rows']),
    'document_types' => rmisDocumentTypes($conn),
    'issued_years' => rmisGetIssuedYears($conn),
    'origin_offices' => rmisGetOriginOffices($conn),
];

$conn->close();
echo json_encode($payload);
