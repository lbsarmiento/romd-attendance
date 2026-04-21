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
    <div class="container">
        <div class="card no-print">
            <h2>First to Arrive — Monthly Report</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                For each day, this report shows both the employee(s) with the <strong>earliest time-in</strong> and the employee(s) with the <strong>latest time-in</strong> among those with recorded time-in entries. If two or more employees share the same earliest or latest time, they all count for that day.
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

        <!-- Overall / As of today ranking -->
        <div id="overallRanking" class="card" style="margin-top: 20px;">
            <h3 style="color: var(--primary); margin-bottom: 8px;">Ranking — Overall / As of today</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 12px;">Cumulative count of days each person was first to arrive and last to arrive among checked-in employees.</p>
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

        <div id="reportContent" class="card" style="margin-top: 20px;">
            <div class="message">Select month and year, then click "Load Report".</div>
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
            const asOfDate = data.as_of_date || '';
            const asOfStr = asOfDate ? new Date(asOfDate + 'T12:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
            const filterLabel = data.filter_label || '';

            let html = '';
            if (filterLabel) {
                html += '<p style="font-size: 13px; color: #64748b; margin-bottom: 8px;">' + (filterLabel === 'all time' ? 'All time' : 'Year ' + escapeHtml(filterLabel)) + (asOfStr ? ' · As of ' + escapeHtml(asOfStr) : '') + '</p>';
            } else if (asOfStr) {
                html += '<p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">As of ' + escapeHtml(asOfStr) + '</p>';
            }
            if (ranking.length > 0 || lastRanking.length > 0) {
                html += '<div style="display: grid; gap: 24px;">';

                html += '<div>';
                html += '<h4 style="margin-bottom: 12px; color: #334155;">Overall first to arrive</h4>';
                if (ranking.length > 0) {
                    html += '<div style="overflow-x: auto;"><table class="summary-table">';
                    html += '<thead><tr><th class="text-left">Rank</th><th class="text-left">Employee</th><th>Days first to arrive</th></tr></thead><tbody>';
                    ranking.forEach(function(emp, i) {
                        const rank = i + 1;
                        const medal = rank === 1 ? ' 🥇' : (rank === 2 ? ' 🥈' : (rank === 3 ? ' 🥉' : ''));
                        html += '<tr>';
                        html += '<td class="text-left"><strong>' + rank + medal + '</strong></td>';
                        html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                        html += '<td>' + (emp.days_first_count || 0) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p style="color: #666;">No first-arrival data yet.</p>';
                }
                html += '</div>';

                html += '<div>';
                html += '<h4 style="margin-bottom: 12px; color: #334155;">Overall last to arrive</h4>';
                if (lastRanking.length > 0) {
                    html += '<div style="overflow-x: auto;"><table class="summary-table">';
                    html += '<thead><tr><th class="text-left">Rank</th><th class="text-left">Employee</th><th>Days last to arrive</th></tr></thead><tbody>';
                    lastRanking.forEach(function(emp, i) {
                        const rank = i + 1;
                        const medal = rank === 1 ? ' 🥇' : (rank === 2 ? ' 🥈' : (rank === 3 ? ' 🥉' : ''));
                        html += '<tr>';
                        html += '<td class="text-left"><strong>' + rank + medal + '</strong></td>';
                        html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                        html += '<td>' + (emp.days_last_count || 0) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p style="color: #666;">No last-arrival data yet.</p>';
                }
                html += '</div>';

                html += '</div>';
            } else {
                html += '<p style="color: #666;">No data yet.</p>';
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

            let html = '<div style="margin-bottom: 24px;">';
            html += '<h3 style="color: var(--primary); margin-bottom: 8px;">' + escapeHtml(monthName) + ' ' + year + '</h3>';
            html += '<p style="color: #666; font-size: 14px;">Per day: the report shows both the earliest and latest recorded time-in among employees who checked in. Ties (same time) all count.</p>';
            html += '</div>';

            // Summary: total "first to arrive" credits (one per day, or sum of count per day)
            const totalCredits = days.reduce((sum, d) => sum + (d.count || 0), 0);
            const totalLastCredits = days.reduce((sum, d) => sum + (d.last_count || 0), 0);
            html += '<div style="display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">';
            html += '<div class="stat-card success" style="max-width: 320px; margin-bottom: 24px; padding: 20px;">';
            html += '<div class="stat-number" style="font-size: 2rem;">' + totalCredits + '</div>';
            html += '<div class="stat-label">Total "First to Arrive" count this month</div>';
            html += '<p style="margin-top: 8px; font-size: 13px; color: #666;">Sum of counts per day (each day: only earliest time-in person(s) counted)</p>';
            html += '</div>';
            html += '<div class="stat-card" style="max-width: 320px; margin-bottom: 24px; padding: 20px;">';
            html += '<div class="stat-number" style="font-size: 2rem;">' + totalLastCredits + '</div>';
            html += '<div class="stat-label">Total "Last to Arrive" count this month</div>';
            html += '<p style="margin-top: 8px; font-size: 13px; color: #666;">Sum of counts per day (each day: only latest time-in person(s) counted)</p>';
            html += '</div>';
            html += '</div>';

            // Table 1: By date — Date | Day | First to arrive (names) | Time | Count
            html += '<h4 style="margin-bottom: 12px; color: #334155;">By date — Who was first to arrive</h4>';
            if (days.length > 0) {
                html += '<div style="overflow-x: auto; margin-bottom: 32px;"><table class="summary-table">';
                html += '<thead><tr><th class="text-left">Date</th><th class="text-left">Day</th><th class="text-left">First to arrive</th><th>Time In</th><th>Count</th></tr></thead><tbody>';
                days.forEach(function(d) {
                    const dateObj = new Date(d.date + 'T12:00:00');
                    const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                    const names = (d.first_arrivers || []).map(function(fa) { return fa.employee_name; }).join(', ');
                    const time = d.earliest_time || '—';
                    const count = d.count || 0;
                    html += '<tr>';
                    html += '<td class="text-left">' + escapeHtml(dateStr) + '</td>';
                    html += '<td class="text-left">' + escapeHtml(dayName) + '</td>';
                    html += '<td class="text-left">' + (names ? escapeHtml(names) : '—') + '</td>';
                    html += '<td>' + escapeHtml(time) + '</td>';
                    html += '<td>' + count + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p style="color: #666; margin-bottom: 32px;">No attendance with time-in for this month.</p>';
            }

            // Table 2: By date — Date | Day | Last to arrive (names) | Time | Count
            html += '<h4 style="margin-bottom: 12px; color: #334155;">By date — Who was last to arrive</h4>';
            if (days.length > 0) {
                html += '<div style="overflow-x: auto; margin-bottom: 32px;"><table class="summary-table">';
                html += '<thead><tr><th class="text-left">Date</th><th class="text-left">Day</th><th class="text-left">Last to arrive</th><th>Time In</th><th>Count</th></tr></thead><tbody>';
                days.forEach(function(d) {
                    const dateObj = new Date(d.date + 'T12:00:00');
                    const dateStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                    const names = (d.last_arrivers || []).map(function(la) { return la.employee_name; }).join(', ');
                    const time = d.latest_time || '—';
                    const count = d.last_count || 0;
                    html += '<tr>';
                    html += '<td class="text-left">' + escapeHtml(dateStr) + '</td>';
                    html += '<td class="text-left">' + escapeHtml(dayName) + '</td>';
                    html += '<td class="text-left">' + (names ? escapeHtml(names) : '—') + '</td>';
                    html += '<td>' + escapeHtml(time) + '</td>';
                    html += '<td>' + count + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p style="color: #666; margin-bottom: 32px;">No attendance with time-in for this month.</p>';
            }

            // Table 3: By employee — Employee | # of days first to arrive
            html += '<h4 style="margin-bottom: 12px; color: #334155;">By employee — Number of days they were first to arrive</h4>';
            if (employeeTotals.length > 0) {
                html += '<div style="overflow-x: auto;"><table class="summary-table">';
                html += '<thead><tr><th class="text-left">#</th><th class="text-left">Employee</th><th>Days first to arrive</th></tr></thead><tbody>';
                employeeTotals.forEach(function(emp, i) {
                    html += '<tr>';
                    html += '<td class="text-left">' + (i + 1) + '</td>';
                    html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                    html += '<td>' + (emp.days_first_count || 0) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p style="color: #666; margin-bottom: 32px;">No data.</p>';
            }

            // Table 4: By employee — Employee | # of days last to arrive
            html += '<h4 style="margin-bottom: 12px; color: #334155;">By employee — Number of days they were last to arrive</h4>';
            if (lastEmployeeTotals.length > 0) {
                html += '<div style="overflow-x: auto;"><table class="summary-table">';
                html += '<thead><tr><th class="text-left">#</th><th class="text-left">Employee</th><th>Days last to arrive</th></tr></thead><tbody>';
                lastEmployeeTotals.forEach(function(emp, i) {
                    html += '<tr>';
                    html += '<td class="text-left">' + (i + 1) + '</td>';
                    html += '<td class="text-left">' + escapeHtml(emp.employee_name || '') + '</td>';
                    html += '<td>' + (emp.days_last_count || 0) + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p style="color: #666;">No data.</p>';
            }

            contentDiv.innerHTML = html;
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
