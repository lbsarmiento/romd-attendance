<?php

require_once __DIR__ . '/rmis_config.php';

/**
 * HTTP client for TESDA intranet wRMIS (ASP.NET MVC).
 *
 * __RequestVerificationToken is issued by the server on GET /Account/Login — it is not
 * generated in PHP. The hidden field value and the __RequestVerificationToken cookie are
 * different strings; both come from that GET and must be sent together on POST (cURL
 * cookie jar + form body), matching browser behavior.
 */
class RmisClient
{
    private string $baseUrl;
    private string $cookieFile;
    private int $requestDelayUs;
    private bool $verifySsl;
    private bool $curlIpv4Only;
    private string $httpProxy;
    private bool $curlHttp2;
    private string $caBundlePath;
    private bool $curlUseNativeCa;
    /** Last cURL transport failure (set when curl_exec fails or errno != 0). */
    private string $lastTransportError = '';
    private string $userAgent;

    public function __construct(?array $config = null)
    {
        $config = $config ?? getRmisConfig();
        $this->baseUrl = rtrim((string) $config['base_url'], '/');
        $this->requestDelayUs = (int) ($config['request_delay_us'] ?? 250000);
        $this->verifySsl = filter_var($config['verify_ssl'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->curlIpv4Only = filter_var($config['curl_ipv4_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $this->httpProxy = trim((string) ($config['http_proxy'] ?? ''));
        $this->curlHttp2 = filter_var($config['curl_http2'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $ua = trim((string) ($config['user_agent'] ?? ''));
        $this->userAgent = $ua !== '' ? $ua : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:150.0) Gecko/20100101 Firefox/150.0';
        $this->caBundlePath = trim((string) ($config['ca_bundle'] ?? ''));
        if (array_key_exists('curl_use_native_ca', $config)) {
            $this->curlUseNativeCa = filter_var($config['curl_use_native_ca'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $this->curlUseNativeCa = PHP_OS_FAMILY === 'Windows';
        }

        $cookieDir = (string) ($config['cookie_dir'] ?? dirname(__DIR__) . '/storage/rmis');
        if (!is_dir($cookieDir)) {
            mkdir($cookieDir, 0755, true);
        }
        $this->cookieFile = $cookieDir . '/session.cookies.txt';
    }

    public function getCookieFile(): string
    {
        return $this->cookieFile;
    }

    public function clearSession(): void
    {
        if (is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    public function getLastTransportError(): string
    {
        return $this->lastTransportError;
    }

    /**
     * @return array{ok:bool,message:string,http_code:int}
     */
    public function login(string $email, string $password, bool $rememberMe = false): array
    {
        $loginUrl = $this->baseUrl . '/Account/Login';
        $html = $this->request('GET', $loginUrl, null, [
            'Upgrade-Insecure-Requests: 1',
        ]);

        if ($html === false) {
            $detail = $this->lastTransportError !== ''
                ? $this->lastTransportError
                : 'No cURL error text (check PHP/OpenSSL, firewall, VPN, or DNS).';
            return [
                'ok' => false,
                'message' => 'Could not reach the login page. ' . $detail,
                'http_code' => 0,
            ];
        }

        $token = self::extractRequestVerificationToken($html);
        if ($token === null) {
            return ['ok' => false, 'message' => 'Anti-forgery token not found on login page.', 'http_code' => 200];
        }

        $body = http_build_query([
            '__RequestVerificationToken' => $token,
            'Email' => $email,
            'Password' => $password,
            'RememberMe' => $rememberMe ? 'true' : 'false',
        ]);

        $response = $this->request('POST', $loginUrl, $body, [
            'Content-Type: application/x-www-form-urlencoded',
            'Origin: ' . $this->baseUrl,
            'Referer: ' . $loginUrl,
            'Upgrade-Insecure-Requests: 1',
        ], true);

        if ($response === false) {
            $detail = $this->lastTransportError !== '' ? $this->lastTransportError : 'No cURL details.';
            return ['ok' => false, 'message' => 'Login request failed. ' . $detail, 'http_code' => 0];
        }

        $httpCode = $response['http_code'];
        $finalUrl = $response['effective_url'] ?? '';
        $bodyHtml = $response['body'] ?? '';

        if (stripos($finalUrl, '/Account/Login') !== false && self::looksLikeLoginPage($bodyHtml)) {
            return ['ok' => false, 'message' => 'Login rejected (still on login page). Check email/password.', 'http_code' => $httpCode];
        }

        $rmisProbe = $this->fetchRmisPage(1);
        if ($rmisProbe === null || stripos($rmisProbe, 'Web-based Records Management') === false) {
            return ['ok' => false, 'message' => 'Logged in but RMIS page could not be loaded.', 'http_code' => $httpCode];
        }

        return ['ok' => true, 'message' => 'Login successful.', 'http_code' => $httpCode];
    }

    public function fetchRmisPage(int $page = 1, array $filters = []): ?string
    {
        $query = array_merge(['page' => max(1, $page)], $filters);
        $url = $this->baseUrl . '/RMIS?' . http_build_query($query);
        $html = $this->request('GET', $url, null, [
            'Referer: ' . $this->baseUrl . '/',
            'Upgrade-Insecure-Requests: 1',
        ]);

        return $html === false ? null : $html;
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>,current_page:int,total_pages:int,raw_found:bool}
     */
    public static function parseRmisListHtml(string $html): array
    {
        $currentPage = 1;
        $totalPages = 1;

        if (preg_match('/Page\s+(\d+)\s+of\s+(\d+)/i', $html, $pageMatch)) {
            $currentPage = (int) $pageMatch[1];
            $totalPages = (int) $pageMatch[2];
        }

        $rows = [];
        $slice = self::extractRmisTableHtmlSlice($html);
        $htmlForDom = $slice !== null ? $slice : $html;

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlForDom, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $tableRows = $xpath->query("//div[contains(@class,'table-responsive')]//table//tbody/tr");

        if ($tableRows === false || $tableRows->length === 0) {
            $tableRows = $xpath->query("//table[contains(@class,'table-striped')]//tbody/tr");
        }

        if ($tableRows === false) {
            return [
                'rows' => [],
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'raw_found' => false,
            ];
        }

        foreach ($tableRows as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells === false || $cells->length < 8) {
                continue;
            }

            $originHtml = $dom->saveHTML($cells->item(4));
            $originOffice = '';
            $subject = '';

            if ($originHtml !== false) {
                if (preg_match('/<strong>\s*(.*?)\s*<\/strong>/is', $originHtml, $officeMatch)) {
                    $originOffice = trim(html_entity_decode(strip_tags($officeMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                if (preg_match('/<em>\s*(.*?)\s*<\/em>/is', $originHtml, $subjectMatch)) {
                    $subject = trim(html_entity_decode(strip_tags($subjectMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }

            $actionHtml = $dom->saveHTML($cells->item(7));
            $serveId = '';
            $pdfUrl = '';

            if ($actionHtml !== false && preg_match('/href="\/RMIS\/Serve\/([^"]+)"/i', $actionHtml, $serveMatch)) {
                $serveId = self::normalizeServeId($serveMatch[1]);
                $pdfUrl = $serveId !== '' ? '/RMIS/Serve/' . $serveId : '';
            }

            if ($serveId === '') {
                continue;
            }

            $rows[] = [
                'serve_id' => $serveId,
                'document_type' => self::cellText($cells->item(0)),
                'date_issued' => self::normalizeDate(self::cellText($cells->item(1))),
                'document_series' => self::cellText($cells->item(2)),
                'document_number' => self::cellText($cells->item(3)),
                'origin_office' => $originOffice,
                'subject' => $subject,
                'effectivity' => self::cellText($cells->item(5)),
                'date_received' => self::normalizeDate(self::cellText($cells->item(6))),
                'pdf_url' => $pdfUrl,
            ];
        }

        return [
            'rows' => $rows,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'raw_found' => count($rows) > 0,
        ];
    }

    public static function extractRequestVerificationToken(string $html): ?string
    {
        // Typical: <input name="__RequestVerificationToken" type="hidden" value="..." />
        if (preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', $html, $match)) {
            return $match[1];
        }

        if (preg_match('/<input[^>]+name="__RequestVerificationToken"[^>]+value="([^"]+)"/i', $html, $match)) {
            return $match[1];
        }

        // Alternate attribute order: value before name
        if (preg_match('/<input[^>]+value="([^"]+)"[^>]+name="__RequestVerificationToken"/i', $html, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Narrow HTML to the list region (same as <!-- START TABLE --> in wRMIS) for reliable DOM parse.
     */
    private static function extractRmisTableHtmlSlice(string $html): ?string
    {
        $start = stripos($html, '<!-- START TABLE -->');
        if ($start === false) {
            return null;
        }

        $endMarkers = ['<!-- START MODAL', '<div class="modal fade', '<!-- END MODAL'];
        $end = false;
        foreach ($endMarkers as $marker) {
            $p = stripos($html, $marker, $start + 20);
            if ($p !== false) {
                $end = $end === false ? $p : min($end, $p);
            }
        }

        if ($end === false) {
            return null;
        }

        return substr($html, $start, $end - $start);
    }

    /** Strip whitespace from wRMIS Serve tokens (HTML often has trailing spaces in href). */
    public static function normalizeServeId(string $raw): string
    {
        $raw = preg_replace('/[\p{Z}\s\x{00A0}\x{200B}\x{FEFF}]+/u', '', $raw) ?? '';
        return trim($raw);
    }

    private static function looksLikeLoginPage(string $html): bool
    {
        return stripos($html, 'Sign in to start your session') !== false
            || stripos($html, 'Only TESDA Corporate Account') !== false;
    }

    private static function cellText(?DOMNode $node): string
    {
        if ($node === null) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
    }

    private static function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $dt = DateTime::createFromFormat('m/d/Y', $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }

        return null;
    }

    /**
     * @param array<int,string> $headers
     * @return string|false|array{body:string,http_code:int,effective_url:string}
     */
    private function request(string $method, string $url, ?string $body = null, array $headers = [], bool $meta = false)
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $this->lastTransportError = '';

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ], $headers),
        ];

        if ($this->curlIpv4Only) {
            $opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        if ($this->httpProxy !== '') {
            $opts[CURLOPT_PROXY] = $this->httpProxy;
        }

        if ($this->curlHttp2) {
            if (defined('CURL_HTTP_VERSION_2TLS')) {
                $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2TLS;
            } elseif (defined('CURL_HTTP_VERSION_2_0')) {
                $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
            }
        }

        $method = strtoupper($method);
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        curl_setopt_array($ch, $opts);

        if ($this->verifySsl) {
            $caPath = $this->caBundlePath;
            if ($caPath !== '' && is_readable($caPath)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caPath);
            } elseif ($this->curlUseNativeCa && defined('CURLSSLOPT_NATIVE_CA')) {
                // PHP 8.2+ / libcurl 7.71+: use Windows certificate store (fixes error 60 when cacert.pem is missing).
                curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
            }
        }

        if ($this->requestDelayUs > 0) {
            usleep($this->requestDelayUs);
        }

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($responseBody === false || $errno !== 0) {
            $this->lastTransportError = $errno !== 0
                ? sprintf('cURL error %d: %s', $errno, $error !== '' ? $error : '(empty message)')
                : ($error !== '' ? $error : 'Unknown transport error');
            return false;
        }

        if ($meta) {
            return ['body' => $responseBody, 'http_code' => $httpCode, 'effective_url' => $effectiveUrl];
        }

        return $responseBody;
    }
}
