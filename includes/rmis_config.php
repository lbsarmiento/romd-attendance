<?php

function getRmisConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'base_url' => 'https://intranet.tesda.gov.ph',
        'email' => '',
        'password' => '',
        'cron_secret' => '',
        'request_delay_us' => 250000,
        'cookie_dir' => dirname(__DIR__) . '/storage/rmis',
        // If true, force IPv4 (helps when IPv6 routes are broken).
        'curl_ipv4_only' => false,
        // Keep true unless corporate SSL inspection breaks verification (less secure).
        'verify_ssl' => true,
        // Windows/XAMPP: PHP often lacks a CA bundle; true uses the OS trust store (PHP 8.2+).
        'curl_use_native_ca' => PHP_OS_FAMILY === 'Windows',
        // Optional absolute path to cacert.pem from https://curl.se/ca/cacert.pem (best if not on PHP 8.2+).
        'ca_bundle' => '',
        // Optional: e.g. http://proxy.tesda.gov.ph:8080
        'http_proxy' => '',
        // Match browser: use HTTP/2 when libcurl supports it (same as Firefox trace).
        'curl_http2' => true,
        // Override to match your browser exactly if the server is picky.
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0',
    ];

    $localFile = __DIR__ . '/rmis_config.local.php';
    if (is_readable($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $defaults = array_merge($defaults, $local);
        }
    }

    $defaults['base_url'] = rtrim((string) $defaults['base_url'], '/');

    $config = $defaults;
    return $config;
}

function rmisConfigIsReady(): bool
{
    $config = getRmisConfig();
    return $config['email'] !== '' && $config['password'] !== '';
}
