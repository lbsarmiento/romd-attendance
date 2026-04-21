<?php
require_once 'config.php';
requireAdmin();

function getProvinceOptions() {
    return [
        1 => 'Manila',
        2 => 'PaMaMariSan',
        3 => 'Quezon City',
        4 => 'CaMaNaVa',
        5 => 'PasMak',
        6 => 'MuntiParLasTaPat',
        7 => 'Apayao',
        8 => 'Ifugao',
        9 => 'Abra',
        10 => 'Benguet',
        11 => 'Kalinga',
        12 => 'Mt. Province',
        13 => 'La Union',
        14 => 'Ilocos Sur',
        15 => 'Pangasinan',
        16 => 'Ilocos Norte',
        17 => 'Cagayan',
        18 => 'Isabela',
        19 => 'Quirino',
        20 => 'Batanes',
        21 => 'Nueva Vizcaya',
        22 => 'Bataan',
        23 => 'Aurora',
        24 => 'Nueva Ecija',
        25 => 'Bulacan',
        26 => 'Tarlac',
        27 => 'Pampanga',
        28 => 'Zambales',
        29 => 'Rizal',
        30 => 'Laguna',
        31 => 'Batangas',
        32 => 'Cavite',
        33 => 'Quezon',
        34 => 'Marinduque',
        35 => 'Oriental Mindoro',
        36 => 'Palawan',
        37 => 'Occidental Mindoro',
        38 => 'Romblon',
        39 => 'Camarines Norte',
        40 => 'Sorsogon',
        41 => 'Catanduanes',
        42 => 'Albay',
        43 => 'Camarines Sur',
        44 => 'Masbate',
        45 => 'Iloilo',
        46 => 'Aklan',
        47 => 'Capiz',
        48 => 'Guimaras',
        49 => 'Antique',
        50 => 'Negros Occidental',
        51 => 'Negros Oriental',
        52 => 'Siquijor',
        53 => 'Cebu',
        54 => 'Bohol',
        55 => 'Leyte',
        56 => 'Southern Leyte',
        57 => 'Eastern Samar',
        58 => 'Samar',
        59 => 'Biliran',
        60 => 'Northern Samar',
        61 => 'Zamboanga del Sur',
        62 => 'Zamboanga Sibugay',
        63 => 'Zamboanga del Norte',
        64 => 'Lanao del Norte',
        65 => 'Misamis Occidental',
        66 => 'Misamis Oriental',
        67 => 'Bukidnon',
        68 => 'Camiguin',
        69 => 'Davao City',
        70 => 'Davao Occidental',
        71 => 'Davao Del Sur',
        72 => 'Davao Del Norte',
        73 => 'Davao Oriental',
        74 => 'Davao De Oro',
        75 => 'South Cotabato',
        76 => 'Sultan Kudarat',
        77 => 'Sarangani',
        78 => 'North Cotabato',
        79 => 'Surigao del Norte',
        80 => 'Surigao del Sur',
        81 => 'Agusan del Sur',
        82 => 'Dinagat Island',
        83 => 'Agusan del Norte'
    ];
}

function normalizeLookupValue($value) {
    $value = strtolower(trim((string) $value));
    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function getProvinceLookupMap($province_options) {
    $lookup = [];
    foreach ($province_options as $province_id => $province_name) {
        $lookup[(string) $province_id] = $province_id;
        $lookup[normalizeLookupValue($province_name)] = $province_id;
    }
    return $lookup;
}

function resolveProvinceId($value, $province_lookup) {
    $value = trim((string) $value);
    if ($value === '') {
        return [null, 'Province is required.'];
    }

    if (isset($province_lookup[$value])) {
        return [$province_lookup[$value], null];
    }

    $normalized = normalizeLookupValue($value);
    if ($normalized !== '' && isset($province_lookup[$normalized])) {
        return [$province_lookup[$normalized], null];
    }

    return [null, 'Province "' . $value . '" is not in the reference list.'];
}

function detectDelimiter($rows_text) {
    $lines = preg_split('/\r\n|\r|\n/', $rows_text);
    $first_non_empty = '';
    foreach ($lines as $line) {
        if (trim($line) !== '') {
            $first_non_empty = $line;
            break;
        }
    }

    $counts = [
        "\t" => substr_count($first_non_empty, "\t"),
        ',' => substr_count($first_non_empty, ','),
        ';' => substr_count($first_non_empty, ';'),
        '|' => substr_count($first_non_empty, '|')
    ];

    $delimiter = "\t";
    $max_count = -1;
    foreach ($counts as $candidate => $count) {
        if ($count > $max_count) {
            $delimiter = $candidate;
            $max_count = $count;
        }
    }

    return $delimiter;
}

function getHeaderAliases() {
    return [
        'name' => ['name', 'center name', 'office name', 'school name', 'ttis name'],
        'province' => ['province', 'province name', 'province id', 'province_id', 'provinceid'],
        'classification' => ['classification', 'type', 'category'],
        'address' => ['address', 'location', 'site address'],
        'email' => ['email', 'email address', 'e-mail']
    ];
}

function detectHeaderMapping($first_row) {
    $aliases = getHeaderAliases();
    $mapping = [];

    foreach ($first_row as $index => $cell) {
        $normalized = normalizeLookupValue($cell);
        if ($normalized === '') {
            continue;
        }

        foreach ($aliases as $field => $labels) {
            foreach ($labels as $label) {
                if ($normalized === normalizeLookupValue($label)) {
                    $mapping[$field] = $index;
                    break 2;
                }
            }
        }
    }

    $has_name = isset($mapping['name']);
    $has_province = isset($mapping['province']);
    $match_count = count($mapping);

    if (($has_name && $has_province) || $match_count >= 3) {
        return $mapping;
    }

    return [];
}

function delimiterLabel($delimiter) {
    if ($delimiter === "\t") {
        return 'Tab';
    }
    if ($delimiter === ',') {
        return 'Comma';
    }
    if ($delimiter === ';') {
        return 'Semicolon';
    }
    if ($delimiter === '|') {
        return 'Pipe';
    }
    return 'Unknown';
}

function parseSpreadsheetInput($input, $province_lookup) {
    $input = trim((string) $input);
    if ($input === '') {
        return [
            'entries' => [],
            'delimiter' => "\t",
            'header_mapping' => [],
            'error' => 'Paste your spreadsheet rows first.'
        ];
    }

    $delimiter = detectDelimiter($input);
    $lines = preg_split('/\r\n|\r|\n/', $input);
    $rows = [];

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $rows[] = str_getcsv($line, $delimiter);
    }

    if (empty($rows)) {
        return [
            'entries' => [],
            'delimiter' => $delimiter,
            'header_mapping' => [],
            'error' => 'No usable rows were found.'
        ];
    }

    $header_mapping = detectHeaderMapping($rows[0]);
    $start_index = empty($header_mapping) ? 0 : 1;
    $entries = [];

    for ($row_index = $start_index; $row_index < count($rows); $row_index++) {
        $columns = $rows[$row_index];
        $name = '';
        $province_value = '';
        $classification = '';
        $address = '';
        $email = '';

        if (!empty($header_mapping)) {
            $name_index = $header_mapping['name'] ?? null;
            $province_index = $header_mapping['province'] ?? null;
            $classification_index = $header_mapping['classification'] ?? null;
            $address_index = $header_mapping['address'] ?? null;
            $email_index = $header_mapping['email'] ?? null;

            $name = trim((string) ($name_index !== null ? ($columns[$name_index] ?? '') : ''));
            $province_value = trim((string) ($province_index !== null ? ($columns[$province_index] ?? '') : ''));
            $classification = trim((string) ($classification_index !== null ? ($columns[$classification_index] ?? '') : ''));
            $address = trim((string) ($address_index !== null ? ($columns[$address_index] ?? '') : ''));
            $email = trim((string) ($email_index !== null ? ($columns[$email_index] ?? '') : ''));
        } else {
            $first_value = trim((string) ($columns[0] ?? ''));
            $second_value = trim((string) ($columns[1] ?? ''));
            list($first_province_id, $first_province_error) = resolveProvinceId($first_value, $province_lookup);
            list($second_province_id, $second_province_error) = resolveProvinceId($second_value, $province_lookup);

            if ($first_province_error === null && $second_province_error !== null) {
                $province_value = $first_value;
                $name = $second_value;
            } elseif ($second_province_error === null && $first_province_error !== null) {
                $name = $first_value;
                $province_value = $second_value;
            } elseif ($first_province_error === null && $second_province_error === null) {
                $province_value = $first_value;
                $name = $second_value;
            } else {
                $province_value = $first_value;
                $name = $second_value;
            }

            $classification = trim((string) ($columns[2] ?? ''));
            $address = trim((string) ($columns[3] ?? ''));
            $email = trim((string) ($columns[4] ?? ''));
        }

        $errors = [];
        if ($name === '') {
            $errors[] = 'Missing name.';
        }

        list($province_id, $province_error) = resolveProvinceId($province_value, $province_lookup);
        if ($province_error !== null) {
            $errors[] = $province_error;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email format looks invalid.';
        }

        $payload = [
            'name' => $name,
            'province_id' => $province_id,
            'classification' => $classification,
            'address' => $address,
            'email' => $email
        ];

        $entries[] = [
            'row_number' => $row_index + 1,
            'source' => $columns,
            'province_value' => $province_value,
            'payload' => $payload,
            'errors' => $errors
        ];
    }

    return [
        'entries' => $entries,
        'delimiter' => $delimiter,
        'header_mapping' => $header_mapping,
        'error' => null
    ];
}

function buildAuthorizationHeader($token) {
    $token = trim((string) $token);
    $token = preg_replace('/^\s*Bearer\s+/i', '', $token);
    return 'Authorization: Bearer ' . $token;
}

function formatApiResponse($response) {
    $response = trim((string) $response);
    if ($response === '') {
        return '(Empty response)';
    }

    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    return $response;
}

$province_options = getProvinceOptions();
$province_lookup = getProvinceLookupMap($province_options);
$app_settings = getAppSettings();
$system_name = $app_settings['system_name'] ?? 'ROMD Attendance';
$page_title = 'TTIS Spreadsheet Import - ' . $system_name;
$current_page = 'ttis_import';
$show_back_btn = true;

$api_url_default = 'https://sienna-jay-693502.hostingersite.com/api/ttis';
$api_url = trim($_POST['api_url'] ?? $api_url_default);
$bearer_token = trim($_POST['bearer_token'] ?? '');
$spreadsheet_data = $_POST['spreadsheet_data'] ?? '';
$action = $_POST['action'] ?? '';

$message = '';
$message_type = 'success';
$parsed_entries = [];
$preview_payloads = [];
$send_results = [];
$delimiter_used = "\t";
$header_mapping = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parsed = parseSpreadsheetInput($spreadsheet_data, $province_lookup);
    $parsed_entries = $parsed['entries'];
    $delimiter_used = $parsed['delimiter'];
    $header_mapping = $parsed['header_mapping'];

    if ($parsed['error'] !== null) {
        $message = $parsed['error'];
        $message_type = 'error';
    } else {
        foreach ($parsed_entries as $entry) {
            if (empty($entry['errors'])) {
                $preview_payloads[] = $entry['payload'];
            }
        }

        $invalid_count = count($parsed_entries) - count($preview_payloads);

        if ($action === 'preview') {
            if (count($preview_payloads) === 0) {
                $message = 'No valid payloads were found. Fix the row errors below first.';
                $message_type = 'error';
            } else {
                $message = count($preview_payloads) . ' payload(s) are ready for sending.';
                if ($invalid_count > 0) {
                    $message .= ' ' . $invalid_count . ' row(s) still need fixing.';
                }
            }
        } elseif ($action === 'send') {
            if (!function_exists('curl_init')) {
                $message = 'cURL is not enabled on this PHP installation.';
                $message_type = 'error';
            } elseif ($bearer_token === '') {
                $message = 'Bearer token is required before sending.';
                $message_type = 'error';
            } elseif (count($preview_payloads) === 0) {
                $message = 'No valid payloads were found. Fix the row errors below first.';
                $message_type = 'error';
            } else {
                foreach ($parsed_entries as $entry) {
                    if (!empty($entry['errors'])) {
                        $send_results[] = [
                            'row_number' => $entry['row_number'],
                            'name' => $entry['payload']['name'],
                            'province_id' => $entry['payload']['province_id'],
                            'http_code' => null,
                            'success' => false,
                            'response' => implode(' ', $entry['errors'])
                        ];
                        continue;
                    }

                    $ch = curl_init($api_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($entry['payload']));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        buildAuthorizationHeader($bearer_token),
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'ROMD Attendance Import Tool');
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                    $response = curl_exec($ch);
                    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_errno($ch) ? curl_error($ch) : '';
                    curl_close($ch);

                    $success = ($curl_error === '' && $http_code >= 200 && $http_code < 300);
                    $send_results[] = [
                        'row_number' => $entry['row_number'],
                        'name' => $entry['payload']['name'],
                        'province_id' => $entry['payload']['province_id'],
                        'http_code' => $http_code,
                        'success' => $success,
                        'response' => $curl_error !== '' ? 'cURL error: ' . $curl_error : formatApiResponse($response)
                    ];
                }

                $sent_successfully = 0;
                foreach ($send_results as $result) {
                    if ($result['success']) {
                        $sent_successfully++;
                    }
                }

                $message = $sent_successfully . ' of ' . count($send_results) . ' row(s) sent successfully.';
                if ($sent_successfully !== count($send_results)) {
                    $message_type = 'error';
                }
            }
        }
    }
}

include 'includes/header.php';
?>
<style>
    .import-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
        gap: 20px;
    }

    .import-card {
        margin-bottom: 0;
    }

    .code-preview {
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        font-family: Consolas, monospace;
        font-size: 13px;
        line-height: 1.5;
        overflow: auto;
        white-space: pre-wrap;
        word-break: break-word;
        min-height: 180px;
    }

    .result-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
    }

    .result-table th,
    .result-table td {
        border: 1px solid #e2e8f0;
        padding: 10px 12px;
        text-align: left;
        vertical-align: top;
        font-size: 13px;
    }

    .result-table th {
        background: #eff6ff;
        color: #1e3a8a;
    }

    .result-table tr:nth-child(even) {
        background: #f8fafc;
    }

    .status-pill {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-pill.ok {
        background: #d1fae5;
        color: #065f46;
    }

    .status-pill.bad {
        background: #fee2e2;
        color: #991b1b;
    }

    .province-reference {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 12px;
        margin-top: 12px;
        font-size: 13px;
    }

    .province-reference div {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 10px;
    }

    .helper-list {
        margin-top: 10px;
        padding-left: 18px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
    }

    .helper-list li + li {
        margin-top: 6px;
    }

    @media (max-width: 980px) {
        .import-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container">
    <div class="welcome-card">
        <h2>TTIS Spreadsheet Import</h2>
        <p>Paste rows copied from your spreadsheet, review the generated payload, then send the valid rows directly to the TTIS website.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="display: block;">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="import-grid">
        <section class="card import-card">
            <div class="settings-section-header">
                <div>
                    <h2>Paste Spreadsheet Data</h2>
                    <p>You can paste rows with headers like <strong>name</strong>, <strong>province</strong>, <strong>classification</strong>, <strong>address</strong>, and <strong>email</strong>. If there is no header row, the page will auto-detect whether the first two columns are <strong>province,name</strong> or <strong>name,province</strong>.</p>
                </div>
            </div>

            <form method="POST" action="ttis_import.php">
                <div class="form-group">
                    <label for="api_url">API URL</label>
                    <input type="url" id="api_url" name="api_url" value="<?php echo htmlspecialchars($api_url); ?>" required>
                </div>

                <div class="form-group">
                    <label for="bearer_token">Bearer Token</label>
                    <input type="password" id="bearer_token" name="bearer_token" value="<?php echo htmlspecialchars($bearer_token); ?>" placeholder="Paste token here only when you are ready to send">
                    <div class="helper-text">You can click Preview Payload first without a token.</div>
                </div>

                <div class="form-group">
                    <label for="spreadsheet_data">Spreadsheet Rows</label>
                    <textarea id="spreadsheet_data" name="spreadsheet_data" style="min-height: 280px;" placeholder="Example with header:&#10;name	province	classification	address	email&#10;PTC Benguet	Benguet	PTC	Wangal, La Trinidad	pptc-benguet@tesda.gov.ph&#10;&#10;Example without header:&#10;Pangasinan	Provincial Training Center - Urdaneta	PTC	Government Center, Brgy. Anonas, Urdaneta City, Pangasinan	urdaneta.ptc@tesda.gov.ph"><?php echo htmlspecialchars($spreadsheet_data); ?></textarea>
                </div>

                <div class="settings-actions" style="justify-content: flex-start;">
                    <button type="submit" name="action" value="preview" class="btn btn-secondary">Preview Payload</button>
                    <button type="submit" name="action" value="send" class="btn btn-primary">Send to Website</button>
                </div>
            </form>

            <ul class="helper-list">
                <li>Spreadsheet copy-paste usually works best directly from Excel or Google Sheets.</li>
                <li>Province can be pasted as the province name or the numeric `province_id`.</li>
                <li>Rows with validation errors are skipped and shown below.</li>
                <li>Detected delimiter: <strong><?php echo htmlspecialchars(delimiterLabel($delimiter_used)); ?></strong><?php echo empty($header_mapping) ? ' | Header row: Not detected' : ' | Header row: Detected'; ?></li>
            </ul>
        </section>

        <aside class="card import-card">
            <div class="settings-section-header">
                <div>
                    <h2>Province Reference</h2>
                    <p>Accepted names for `province_id` lookup.</p>
                </div>
            </div>

            <div class="province-reference">
                <?php foreach ($province_options as $province_id => $province_name): ?>
                    <div><strong><?php echo (int) $province_id; ?></strong> <?php echo htmlspecialchars($province_name); ?></div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>

    <?php if (!empty($preview_payloads)): ?>
        <section class="card">
            <div class="settings-section-header">
                <div>
                    <h2>Payload Preview</h2>
                    <p>Only rows without validation errors are included here.</p>
                </div>
            </div>
            <div class="code-preview"><?php echo htmlspecialchars(json_encode($preview_payloads, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
        </section>
    <?php endif; ?>

    <?php if (!empty($parsed_entries)): ?>
        <section class="card">
            <div class="settings-section-header">
                <div>
                    <h2>Parsed Rows</h2>
                    <p>Review each row before sending. Error rows are not posted to the API.</p>
                </div>
            </div>

            <table class="result-table">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Province</th>
                        <th>Name</th>
                        <th>Classification</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parsed_entries as $entry): ?>
                        <tr>
                            <td><?php echo (int) $entry['row_number']; ?></td>
                            <td><?php echo htmlspecialchars($entry['province_value']); ?></td>
                            <td><?php echo htmlspecialchars($entry['payload']['name']); ?></td>
                            <td><?php echo htmlspecialchars($entry['payload']['classification']); ?></td>
                            <td><?php echo htmlspecialchars($entry['payload']['email']); ?></td>
                            <td>
                                <?php if (empty($entry['errors'])): ?>
                                    <span class="status-pill ok">Ready</span>
                                <?php else: ?>
                                    <span class="status-pill bad"><?php echo htmlspecialchars(implode(' ', $entry['errors'])); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if (!empty($send_results)): ?>
        <section class="card">
            <div class="settings-section-header">
                <div>
                    <h2>Send Results</h2>
                    <p>API response for each attempted row.</p>
                </div>
            </div>

            <table class="result-table">
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Name</th>
                        <th>Province ID</th>
                        <th>HTTP</th>
                        <th>Status</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($send_results as $result): ?>
                        <tr>
                            <td><?php echo (int) $result['row_number']; ?></td>
                            <td><?php echo htmlspecialchars($result['name']); ?></td>
                            <td><?php echo htmlspecialchars((string) $result['province_id']); ?></td>
                            <td><?php echo $result['http_code'] !== null ? (int) $result['http_code'] : '-'; ?></td>
                            <td>
                                <span class="status-pill <?php echo $result['success'] ? 'ok' : 'bad'; ?>">
                                    <?php echo $result['success'] ? 'Sent' : 'Failed'; ?>
                                </span>
                            </td>
                            <td><div class="code-preview" style="min-height: 0; padding: 10px; font-size: 12px;"><?php echo htmlspecialchars($result['response']); ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
