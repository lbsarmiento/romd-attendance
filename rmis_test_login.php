<?php

require_once __DIR__ . '/includes/rmis_config.php';
require_once __DIR__ . '/includes/RmisClient.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/config.php';
    requireAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

if (!rmisConfigIsReady()) {
    echo "Configure includes/rmis_config.local.php first.\n";
    exit(1);
}

$config = getRmisConfig();
$client = new RmisClient($config);
$client->clearSession();

echo "Logging in...\n";
$login = $client->login($config['email'], $config['password']);
echo json_encode($login, JSON_PRETTY_PRINT) . "\n";

if (!$login['ok']) {
    echo "\nNote: http_code 0 means PHP never got an HTTP response (often TLS / network).\n";
    if (stripos($login['message'] ?? '', 'SSL certificate') !== false || stripos($login['message'] ?? '', 'error 60') !== false) {
        echo "\n--- cURL error 60 (TLS) ---\n";
        echo "Firefox uses the Windows certificate store; PHP/cURL often does not, unless configured.\n";
        echo "This project enables Windows native CA by default on Windows (PHP 8.2+). Update XAMPP PHP if needed.\n";
        echo "Or download cacert.pem from https://curl.se/ca/cacert.pem and set 'ca_bundle' in rmis_config.local.php.\n";
        echo "Or set openssl.cafile / curl.cainfo in php.ini to that same cacert.pem path.\n";
        echo "Last resort (less secure): 'verify_ssl' => false in rmis_config.local.php.\n";
    } else {
        echo "Confirm this machine can open https://intranet.tesda.gov.ph in a browser.\n";
    }
    exit(1);
}

echo "Fetching RMIS page 1...\n";
$html = $client->fetchRmisPage(1);
if ($html === null) {
    echo "Failed to fetch RMIS.\n";
    exit(1);
}

$parsed = RmisClient::parseRmisListHtml($html);
echo 'Rows: ' . count($parsed['rows']) . PHP_EOL;
echo 'Pages: ' . $parsed['current_page'] . ' of ' . $parsed['total_pages'] . PHP_EOL;

if (!empty($parsed['rows'][0])) {
    echo 'First document: ' . json_encode($parsed['rows'][0], JSON_PRETTY_PRINT) . PHP_EOL;
}
