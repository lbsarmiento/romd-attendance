<?php
require_once 'config.php';
requireLogin();

$currentYear = (int) date('Y');
$currentMonth = (int) date('n');
$startYear = min(2025, $currentYear);
$endYear = $currentYear + 1;
$years = range($startYear, $endYear);
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$page_title = 'First to Arrive (Monthly) - ROMD Attendance';
$current_page = 'first_arrival';
$show_back_btn = true;
include 'includes/header.php';
?>
    <style>
        .arrival-page .page-intro {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 18px;
        }

        .arrival-section-title {
            color: #1e293b;
            margin: 0 0 10px;
        }

        .arrival-muted {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
        }

        .arrival-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }

        .arrival-summary-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
        }

        .arrival-summary-card.primary {
            border-color: #bfdbfe;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        }

        .arrival-summary-card.warning {
            border-color: #fed7aa;
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%);
        }

        .arrival-summary-card.highlight {
            border-color: #facc15;
            box-shadow: 0 12px 28px rgba(250, 204, 21, 0.22);
            background: linear-gradient(135deg, #fef9c3 0%, #ffffff 100%);
        }

        .arrival-summary-number {
            color: #0f172a;
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
        }

        .arrival-summary-label {
            color: #334155;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .arrival-table-wrap {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow-x: auto;
        }

        .arrival-table-wrap .summary-table {
            margin: 0;
        }

        .arrival-table-wrap .summary-table th {
            white-space: nowrap;
        }

        .arrival-rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            font-weight: 700;
        }

        .arrival-time {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }

        .arrival-name-list {
            color: #1e293b;
            font-weight: 600;
            line-height: 1.45;
        }

        .arrival-subsection {
            margin-top: 28px;
        }

        .arrival-streak-highlight {
            background: linear-gradient(135deg, #fef9c3 0%, #ffffff 100%);
        }

        .arrival-streak-label {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #facc15;
            color: #713f12;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
    </style>
    <div class="container arrival-page">
        <div class="card no-print">
            <h2>First to Arrive — Monthly Report</h2>
            <p class="page-intro">
                Shows the earliest and latest recorded time-in per day. If employees have the same earliest or latest time, they are counted together for that day.
            </p>
            <div class="controls" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                <label for="reportMonth">Month:</label>
                <select id="reportMonth">
                    <?php foreach ($months as $num => $name): ?>
                        <option value="<?php echo $num; ?>" <?php echo $num == $currentMonth ? 'selected' : ''; ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="reportYear">Year:</label>
                <select id="reportYear">
                    <?php foreach (array_reverse($years) as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary" id="btnLoad">Load Report</button>
            </div>
        </div>

        <div id="reportContent" class="card" style="margin-top: 20px;">
            <div class="message">Select month and year, then click "Load Report".</div>
        </div>

        <!-- Overall / As of today ranking -->
        <div id="overallRanking" class="card" style="margin-top: 20px;">
            <h3 class="arrival-section-title">Overall Ranking</h3>
            <p class="arrival-muted" style="margin-bottom: 14px;">Cumulative count of days each person was first or last to arrive among employees with recorded time-in entries.</p>
            <div class="controls" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 12px;">
                <label for="overallYear">Year:</label>
                <select id="overallYear">
                    <option value="all" selected>All years</option>
                    <?php foreach (array_reverse($years) as $y): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary btn-sm" id="btnLoadOverall">Apply</button>
            </div>
            <div id="overallRankingContent"><div class="message loading">Loading overall ranking...</div></div>
        </div>
    </div>

    <script>
        document.getElementById('btnLoad').addEventListener('click', loadReport);
        document.getElementById('btnLoadOverall').addEventListener('click', loadOverallRanking);
        document.getElementById('overallYear').addEventListener('change', loadOverallRanking);

        function loadOverallRanking() {
            const contentDiv = document.getElementById('overallRankingContent');
            const year = document.getElementById('overallYear').value;
            const url = 'get_first_arrival_overall.php' + (year && year !== 'all' ? '?year=' + encodeURIComponent(year) : '');
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderOverallRanking(data, contentDiv);
                    } else {
                        contentDiv.innerHTML = '<div class="message error">' + (data.message || 'Failed to load ranking.') + '</div>';
                    }
                })
                .catch(() => {
                    contentDiv.innerHTML = '<div class="message error">Error loading overall ranking.</div>';
                });
        }

        function renderOverallRanking(data, contentDiv) {
            const ranking = data.ranking || [];
            const lastRanking = data.last_ranking || [];
            const streakTop5 = data.streak_top5 || [];
            const asOfDate = data.as_of_date || '';
            const asOfStr = asOfDate ? new Date(asOfDate + 'T12:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
            const filterLabel = data.filter_label || '';

            let html = '';
            if (filterLabel) {
                html += '<p class="arrival-muted" style="margin-bottom: 14px;">' + (filterLabel === 'all time' ? 'All time' : 'Year ' + escapeHtml(filterLabel)) + (asOfStr ? ' | As of ' + escapeHtml(asOfStr) : '') + '</p>';
            } else if (asOfStr) {
                html += '<p class="arrival-muted" style="margin-bottom: 14px;">As of ' + escapeHtml(asOfStr) + '</p>';
            }

            if (ranking.length > 0 || lastRanking.length > 0) {
                html += '<div class="arrival-grid">';

                html += '<div>';
                html += '<h4 class="arrival-section-title">Most Days First to Arrive</h4>';
                if (ranking.length > 0) {
                    html += '<div class="arrival-table-wrap"><table class="summary-table">';
                    html += '<thead><tr><th>Rank</th><th class="text-left">Employee</th><th>Days</th></tr></thead><tbody>';
                    ranking.forEach(function(emp, i) {
                        const rank = i + 1;
                        html += '<tr>';
                        html += '<td><span class="arrival-rank-badge">#' + rank + '</span></td>';
                        html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                        html += '<td>' + (emp.days_first_count || 0) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="arrival-muted">No first-arrival data yet.</p>';
                }
                html += '</div>';

                html += '<div>';
                html += '<h4 class="arrival-section-title">Most Days Last to Arrive</h4>';
                if (lastRanking.length > 0) {
                    html += '<div class="arrival-table-wrap"><table class="summary-table">';
                    html += '<thead><tr><th>Rank</th><th class="text-left">Employee</th><th>Days</th></tr></thead><tbody>';
                    lastRanking.forEach(function(emp, i) {
                        const rank = i + 1;
                        html += '<tr>';
                        html += '<td><span class="arrival-rank-badge">#' + rank + '</span></td>';
                        html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                        html += '<td>' + (emp.days_last_count || 0) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="arrival-muted">No last-arrival data yet.</p>';
                }
                html += '</div>';

                html += '</div>';
            } else {
                html += '<p class="arrival-muted">No data yet.</p>';
            }

            if (streakTop5.length > 0) {
                html += '<div class="arrival-subsection">';
                html += renderStreakTable('Top 5 Early Bird Streaks', streakTop5);
                html += '</div>';
            }
            contentDiv.innerHTML = html;
        }

        function loadReport() {
            const month = document.getElementById('reportMonth').value;
            const year = document.getElementById('reportYear').value;
            const contentDiv = document.getElementById('reportContent');
            contentDiv.innerHTML = '<div class="message loading">Loading report...</div>';

            fetch('get_first_arrival_report.php?month=' + encodeURIComponent(month) + '&year=' + encodeURIComponent(year))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderReport(data, contentDiv);
                    } else {
                        contentDiv.innerHTML = '<div class="message error">' + (data.message || 'Failed to load report.') + '</div>';
                    }
                })
                .catch(() => {
                    contentDiv.innerHTML = '<div class="message error">Error loading report. Please try again.</div>';
                });
        }

        function renderReport(data, contentDiv) {
            const monthName = data.month_name || '';
            const year = data.year || '';
            const days = data.days || [];
            const employeeTotals = data.employee_totals || [];
            const lastEmployeeTotals = data.last_employee_totals || [];
            const streakTop5 = data.streak_top5 || [];

            let html = '<div style="margin-bottom: 24px;">';
            html += '<h3 class="arrival-section-title">' + escapeHtml(monthName) + ' ' + year + '</h3>';
            html += '<p class="arrival-muted">Daily breakdown and employee rankings for the selected month. Ties with the same time are counted together.</p>';
            html += '</div>';

            const totalCredits = days.reduce((sum, d) => sum + (d.count || 0), 0);
            const totalLastCredits = days.reduce((sum, d) => sum + (d.last_count || 0), 0);
            const daysWithTimeIn = days.length;
            html += '<div class="arrival-grid" style="margin-bottom: 28px;">';
            html += '<div class="arrival-summary-card primary">';
            html += '<div class="arrival-summary-number">' + totalCredits + '</div>';
            html += '<div class="arrival-summary-label">First Arrival Credits</div>';
            html += '<p class="arrival-muted">Total employees counted as earliest for the month.</p>';
            html += '</div>';
            html += '<div class="arrival-summary-card warning">';
            html += '<div class="arrival-summary-number">' + totalLastCredits + '</div>';
            html += '<div class="arrival-summary-label">Last Arrival Credits</div>';
            html += '<p class="arrival-muted">Total employees counted as latest for the month.</p>';
            html += '</div>';
            html += '<div class="arrival-summary-card">';
            html += '<div class="arrival-summary-number">' + daysWithTimeIn + '</div>';
            html += '<div class="arrival-summary-label">Days With Time-In</div>';
            html += '<p class="arrival-muted">Days with at least one recorded employee time-in.</p>';
            html += '</div>';
            html += '</div>';

            if (streakTop5.length > 0) {
                const longest = streakTop5[0];
                html += '<div class="arrival-summary-card highlight" style="margin-bottom: 28px;">';
                html += '<div class="arrival-streak-label">Longest Early Bird Streak</div>';
                html += '<div style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-top: 12px;">';
                html += '<div>';
                html += '<div class="arrival-summary-number">' + (longest.longest_streak || 0) + '</div>';
                html += '<div class="arrival-summary-label">' + escapeHtml(longest.employee_name || '') + '</div>';
                html += '<p class="arrival-muted">' + formatDateRange(longest.start_date, longest.end_date) + '</p>';
                html += '</div>';
                html += '<p class="arrival-muted">Sunod-sunod na days na siya ang earliest recorded time-in.</p>';
                html += '</div>';
                html += '</div>';
            }

            html += '<div class="arrival-grid arrival-subsection">';
            html += renderEmployeeRanking('First Arrival Ranking', employeeTotals, 'days_first_count');
            html += renderEmployeeRanking('Last Arrival Ranking', lastEmployeeTotals, 'days_last_count');
            html += '</div>';

            if (streakTop5.length > 0) {
                html += '<div class="arrival-subsection">';
                html += renderStreakTable('Top 5 Early Bird Streaks', streakTop5);
                html += '</div>';
            }

            html += '<div class="arrival-subsection">';
            html += '<h4 class="arrival-section-title">Daily Breakdown</h4>';
            if (days.length > 0) {
                html += '<div class="arrival-table-wrap"><table class="summary-table">';
                html += '<thead><tr><th class="text-left">Date</th><th class="text-left">Day</th><th class="text-left">First to Arrive</th><th>First Time</th><th class="text-left">Last to Arrive</th><th>Last Time</th></tr></thead><tbody>';
                days.forEach(function(d) {
                    const dateObj = new Date(d.date + 'T12:00:00');
                    const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                    const firstNames = (d.first_arrivers || []).map(function(fa) { return fa.employee_name; }).join(', ');
                    const lastNames = (d.last_arrivers || []).map(function(la) { return la.employee_name; }).join(', ');
                    html += '<tr>';
                    html += '<td class="text-left">' + escapeHtml(dateStr) + '</td>';
                    html += '<td class="text-left">' + escapeHtml(dayName) + '</td>';
                    html += '<td class="text-left"><div class="arrival-name-list">' + (firstNames ? escapeHtml(firstNames) : '—') + '</div></td>';
                    html += '<td><span class="arrival-time">' + escapeHtml(d.earliest_time || '—') + '</span></td>';
                    html += '<td class="text-left"><div class="arrival-name-list">' + (lastNames ? escapeHtml(lastNames) : '—') + '</div></td>';
                    html += '<td><span class="arrival-time">' + escapeHtml(d.latest_time || '—') + '</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p class="arrival-muted">No attendance with time-in for this month.</p>';
            }
            html += '</div>';

            contentDiv.innerHTML = html;
        }

        function renderEmployeeRanking(title, employees, countKey) {
            let html = '<div>';
            html += '<h4 class="arrival-section-title">' + escapeHtml(title) + '</h4>';
            if (employees.length > 0) {
                html += '<div class="arrival-table-wrap"><table class="summary-table">';
                html += '<thead><tr><th>Rank</th><th class="text-left">Employee</th><th>Days</th></tr></thead><tbody>';
                employees.forEach(function(emp, i) {
                    html += '<tr>';
                    html += '<td><span class="arrival-rank-badge">#' + (i + 1) + '</span></td>';
                    html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                    html += '<td>' + (emp[countKey] || 0) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p class="arrival-muted">No data.</p>';
            }
            html += '</div>';
            return html;
        }

        function renderStreakTable(title, streaks) {
            let html = '<div>';
            html += '<h4 class="arrival-section-title">' + escapeHtml(title) + '</h4>';
            html += '<div class="arrival-table-wrap"><table class="summary-table">';
            html += '<thead><tr><th>Rank</th><th class="text-left">Employee</th><th>Longest Streak</th><th class="text-left">Date Range</th><th>Total First Days</th></tr></thead><tbody>';
            streaks.forEach(function(item, index) {
                const isLongest = index === 0;
                html += '<tr class="' + (isLongest ? 'arrival-streak-highlight' : '') + '">';
                html += '<td><span class="arrival-rank-badge">#' + (index + 1) + '</span></td>';
                html += '<td class="text-left">' + (isLongest ? '<span class="arrival-streak-label" style="margin-right: 8px;">Longest</span>' : '') + escapeHtml(item.employee_name || '') + '</td>';
                html += '<td><strong>' + (item.longest_streak || 0) + '</strong></td>';
                html += '<td class="text-left">' + escapeHtml(formatDateRange(item.start_date, item.end_date)) + '</td>';
                html += '<td>' + (item.days_first_count || 0) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            html += '</div>';
            return html;
        }

        function formatDateRange(startDate, endDate) {
            if (!startDate && !endDate) return '';
            const start = startDate ? formatShortDate(startDate) : '';
            const end = endDate ? formatShortDate(endDate) : '';
            return start === end ? start : start + ' - ' + end;
        }

        function formatShortDate(dateStr) {
            const dateObj = new Date(dateStr + 'T12:00:00');
            return dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function escapeHtml(text) {
            if (text == null || text === '') return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadOverallRanking();
            loadReport();
        });
    </script>
<?php include 'includes/footer.php'; ?>
