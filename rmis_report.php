<?php

require_once 'config.php';
require_once 'includes/rmis_config.php';
require_once 'includes/rmis_repository.php';

requireAdmin();

$conn = getDBConnection();
ensureRmisTables($conn);

$filters = rmisParseDocumentFilters([
    'document_type' => $_GET['document_type'] ?? '',
    'year_issued' => $_GET['year_issued'] ?? 0,
    'origin_office' => $_GET['origin_office'] ?? ($_GET['origin'] ?? ''),
    'q' => $_GET['q'] ?? '',
]);

$app_settings = getAppSettings($conn);
$orgName = $app_settings['organization_name'] ?? 'Regional Operations Management Division';
$systemName = $app_settings['system_name'] ?? 'ROMD Attendance';
$baseUrl = rtrim(getRmisConfig()['base_url'], '/');

$report = rmisGetMonitoringReport($conn, $filters, 500);
$conn->close();

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];

$generatedAt = date('F j, Y g:i A');
$filterSummary = empty($report['filter_labels'])
    ? 'All stored documents'
    : implode(' Â· ', $report['filter_labels']);

$maxTypeCount = 0;
foreach ($report['by_type'] as $t) {
    $maxTypeCount = max($maxTypeCount, $t['count']);
}
$maxOriginCount = 0;
foreach ($report['by_origin'] as $o) {
    $maxOriginCount = max($maxOriginCount, $o['count']);
}
$maxMonthCount = 0;
foreach ($report['by_month'] as $m) {
    $maxMonthCount = max($maxMonthCount, $m['count']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wRMIS Monitoring Report â€” <?php echo htmlspecialchars($filterSummary); ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo file_exists(__DIR__ . '/assets/css/app.css') ? filemtime(__DIR__ . '/assets/css/app.css') : time(); ?>">
    <style>
        body.rmis-report-body {
            margin: 0;
            background: #e2e8f0;
            font-family: "Segoe UI", system-ui, sans-serif;
            color: #0f172a;
        }
        .rmis-report-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 20px 48px;
        }
        .rmis-report-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .rmis-report-sheet {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
            overflow: hidden;
        }
        .rmis-report-hero {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%);
            color: #fff;
            padding: 32px 36px;
        }
        .rmis-report-hero .kicker {
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: 0.85;
            margin-bottom: 8px;
        }
        .rmis-report-hero h1 {
            margin: 0 0 8px;
            font-size: 1.75rem;
            font-weight: 700;
        }
        .rmis-report-hero .sub {
            margin: 0;
            opacity: 0.92;
            font-size: 1rem;
            max-width: 640px;
        }
        .rmis-report-hero .meta {
            margin-top: 20px;
            font-size: 0.85rem;
            opacity: 0.8;
        }
        .rmis-report-body-inner {
            padding: 28px 36px 36px;
        }
        .rmis-report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .rmis-stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            background: #f8fafc;
        }
        .rmis-stat-card.accent {
            background: linear-gradient(145deg, #eff6ff, #dbeafe);
            border-color: #bfdbfe;
        }
        .rmis-stat-card .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            font-weight: 600;
        }
        .rmis-stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            color: #1e40af;
            line-height: 1.2;
            margin-top: 4px;
        }
        .rmis-report-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }
        @media (max-width: 768px) {
            .rmis-report-grid { grid-template-columns: 1fr; }
        }
        .rmis-report-panel {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }
        .rmis-report-panel h2 {
            margin: 0 0 16px;
            font-size: 1rem;
            color: #334155;
        }
        .rmis-bar-row {
            display: grid;
            grid-template-columns: minmax(100px, 1fr) 1fr 40px;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.82rem;
        }
        .rmis-bar-row .bar-wrap {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .rmis-bar-row .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563eb, #38bdf8);
            border-radius: 4px;
        }
        .rmis-bar-row .count {
            text-align: right;
            font-weight: 700;
            color: #475569;
        }
        .rmis-report-table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .rmis-report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }
        .rmis-report-table th {
            background: #1e293b;
            color: #f8fafc;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
        }
        .rmis-report-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .rmis-report-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .rmis-report-table .subject {
            color: #64748b;
            font-size: 0.78rem;
            margin-top: 2px;
        }
        .rmis-report-footer {
            margin-top: 20px;
            font-size: 0.8rem;
            color: #64748b;
            text-align: center;
        }
        @media print {
            body.rmis-report-body { background: #fff; }
            .rmis-report-actions { display: none !important; }
            .rmis-report-wrap { padding: 0; max-width: none; }
            .rmis-report-sheet { box-shadow: none; border-radius: 0; }
            .rmis-report-hero { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="rmis-report-body">
    <div class="rmis-report-wrap">
        <div class="rmis-report-actions no-print">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print report</button>
            <a href="rmis_monitor.php" class="btn btn-secondary">Back to monitor</a>
        </div>

        <article class="rmis-report-sheet">
            <header class="rmis-report-hero">
                <p class="kicker"><?php echo htmlspecialchars($orgName); ?></p>
                <h1>wRMIS Monitoring Report</h1>
                <p class="sub"><?php echo htmlspecialchars($filterSummary); ?></p>
                <p class="meta">
                    Generated <?php echo htmlspecialchars($generatedAt); ?> Â·
                    <?php echo htmlspecialchars($systemName); ?> Â·
                    Data source: TESDA wRMIS
                </p>
            </header>

            <div class="rmis-report-body-inner">
                <div class="rmis-report-stats">
                    <div class="rmis-stat-card accent">
                        <div class="label">Total documents</div>
                        <div class="value"><?php echo number_format($report['total']); ?></div>
                    </div>
                    <div class="rmis-stat-card">
                        <div class="label">Listed in report</div>
                        <div class="value"><?php echo number_format($report['listed']); ?></div>
                    </div>
                    <div class="rmis-stat-card">
                        <div class="label">Document types</div>
                        <div class="value"><?php echo count($report['by_type']); ?></div>
                    </div>
                    <div class="rmis-stat-card">
                        <div class="label">Origins (top 15)</div>
                        <div class="value"><?php echo count($report['by_origin']); ?></div>
                    </div>
                </div>

                <div class="rmis-report-grid">
                    <section class="rmis-report-panel">
                        <h2>By document type</h2>
                        <?php if (empty($report['by_type'])): ?>
                            <p style="color:#64748b;margin:0;">No data for current filters.</p>
                        <?php else: ?>
                            <?php foreach ($report['by_type'] as $item): ?>
                                <?php
                                $pct = $maxTypeCount > 0 ? round(100 * $item['count'] / $maxTypeCount) : 0;
                                ?>
                                <div class="rmis-bar-row">
                                    <span><?php echo htmlspecialchars($item['document_type']); ?></span>
                                    <div class="bar-wrap"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                    <span class="count"><?php echo (int) $item['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <section class="rmis-report-panel">
                        <h2>By origin office</h2>
                        <?php if (empty($report['by_origin'])): ?>
                            <p style="color:#64748b;margin:0;">No data for current filters.</p>
                        <?php else: ?>
                            <?php foreach ($report['by_origin'] as $item): ?>
                                <?php
                                $pct = $maxOriginCount > 0 ? round(100 * $item['count'] / $maxOriginCount) : 0;
                                $label = $item['origin_office'];
                                if (strlen($label) > 42) {
                                    $label = substr($label, 0, 40) . 'â€¦';
                                }
                                ?>
                                <div class="rmis-bar-row">
                                    <span title="<?php echo htmlspecialchars($item['origin_office']); ?>"><?php echo htmlspecialchars($label); ?></span>
                                    <div class="bar-wrap"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                    <span class="count"><?php echo (int) $item['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                </div>

                <?php if (!empty($report['by_month'])): ?>
                    <section class="rmis-report-panel" style="margin-bottom:28px;">
                        <h2>Monthly distribution (<?php echo (int) $filters['year_issued']; ?>)</h2>
                        <?php foreach ($report['by_month'] as $item): ?>
                            <?php
                            $pct = $maxMonthCount > 0 ? round(100 * $item['count'] / $maxMonthCount) : 0;
                            $mLabel = $monthNames[$item['month']] ?? ('Month ' . $item['month']);
                            ?>
                            <div class="rmis-bar-row">
                                <span><?php echo htmlspecialchars($mLabel); ?></span>
                                <div class="bar-wrap"><div class="bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                                <span class="count"><?php echo (int) $item['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <h2 style="font-size:1.1rem;margin:0 0 12px;color:#334155;">Document register</h2>
                <?php if ($report['list_truncated']): ?>
                    <p style="font-size:0.85rem;color:#b45309;margin:0 0 12px;">
                        Showing first <?php echo number_format($report['listed']); ?> of <?php echo number_format($report['total']); ?> matching records.
                    </p>
                <?php endif; ?>

                <div class="rmis-report-table-wrap">
                    <table class="rmis-report-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Issued</th>
                                <th>Series</th>
                                <th>No.</th>
                                <th>Origin / Subject</th>
                                <th>Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($report['rows'])): ?>
                                <tr><td colspan="7">No documents match the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($report['rows'] as $i => $row): ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td><?php echo htmlspecialchars($row['document_type'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['date_issued'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_series'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_number'] ?? ''); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['origin_office'] ?? ''); ?></strong>
                                            <?php if (!empty($row['subject'])): ?>
                                                <div class="subject"><?php echo htmlspecialchars($row['subject']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['date_received'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p class="rmis-report-footer">
                    This report reflects filters applied on the RMIS Monitor (search, origin office, year, and document type).
                </p>
            </div>
        </article>
    </div>
</body>
</html>

