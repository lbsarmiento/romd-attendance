<?php
require_once 'config.php';
requireLogin();

$page_title = 'Early Bird / Early Time-In Report - ROMD Attendance';
$current_page = 'early_bird';
$show_back_btn = true;
include 'includes/header.php';
?>
    <div class="container">
        <div class="card no-print">
            <h2>Early Bird / Early Time-In Report</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                Employees who time in at or before <strong>8:00 AM</strong> are counted as Early Bird (Early Time-In).
            </p>
            <div class="controls" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                <label for="reportDate">Date:</label>
                <input type="date" id="reportDate" value="<?php echo date('Y-m-d'); ?>">
                <button type="button" class="btn btn-primary" id="btnLoad">Load Report</button>
            </div>
        </div>

        <div id="reportContent" class="card" style="margin-top: 20px;">
            <div class="message">Select a date and click "Load Report" to view Early Bird count and list.</div>
        </div>
    </div>

    <script>
        document.getElementById('btnLoad').addEventListener('click', loadReport);

        function loadReport() {
            const date = document.getElementById('reportDate').value;
            if (!date) {
                document.getElementById('reportContent').innerHTML = '<div class="message error">Please select a date.</div>';
                return;
            }
            const contentDiv = document.getElementById('reportContent');
            contentDiv.innerHTML = '<div class="message loading">Loading report...</div>';

            fetch('get_early_bird.php?date=' + encodeURIComponent(date))
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
            const d = new Date(data.date + 'T12:00:00');
            const dateFormatted = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            const count = data.early_bird_count || 0;
            const list = data.early_bird_list || [];
            const totalCheckedIn = data.total_checked_in || 0;
            const totalEmployees = data.total_employees || 0;

            let html = '<div style="margin-bottom: 20px;">';
            html += '<h3 style="color: var(--primary); margin-bottom: 8px;">' + dateFormatted + '</h3>';
            html += '<p style="color: #666; font-size: 14px;">Total active employees: ' + totalEmployees + ' &nbsp;|&nbsp; Checked in this day: ' + totalCheckedIn + '</p>';
            html += '</div>';

            html += '<div class="stat-card success" style="max-width: 320px; margin-bottom: 24px; padding: 20px;">';
            html += '<div class="stat-number" style="font-size: 2.5rem;">' + count + '</div>';
            html += '<div class="stat-label">Early Bird / Early Time-In</div>';
            html += '<p style="margin-top: 8px; font-size: 13px; color: #666;">Employees who timed in at or before 8:00 AM</p>';
            html += '</div>';

            if (list.length > 0) {
                html += '<h4 style="margin-bottom: 12px; color: #334155;">List of Early Bird Employees</h4>';
                html += '<div style="overflow-x: auto;"><table class="summary-table">';
                html += '<thead><tr><th class="text-left">#</th><th class="text-left">Employee Name</th><th>Time In</th></tr></thead><tbody>';
                list.forEach((emp, i) => {
                    html += '<tr>';
                    html += '<td class="text-left">' + (i + 1) + '</td>';
                    html += '<td class="text-left">' + escapeHtml(emp.name) + '</td>';
                    html += '<td>' + escapeHtml(emp.time_in || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            } else {
                html += '<p style="color: #666;">No Early Bird (early time-in) records for this date.</p>';
            }

            contentDiv.innerHTML = html;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Load today's report on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReport();
        });
    </script>
<?php include 'includes/footer.php'; ?>
