<?php
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
$currentYear = (int) date('Y');
$startYear = min(2025, $currentYear);
$endYear = $currentYear + 1;
$years = range($startYear, $endYear);

$period = isset($_GET['period']) && $_GET['period'] === 'janjun' ? 'janjun' : 'juldec';
$periodLabel = $period === 'janjun' ? 'January - June' : 'July - December';
$page_title = '6-Month Report (' . $periodLabel . ') - ROMD Attendance';
$current_page = $period === 'janjun' ? 'semester_janjun' : 'semester_juldec';
include 'includes/header.php';
?>
    <div class="container">
        <div class="card">
            <h2>Select Year</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Generate attendance summary for <?php echo $period === 'janjun' ? 'January through June' : 'July through December'; ?> of the selected year.</p>
            <div class="controls">
                <label for="reportYear">Year:</label>
                <select id="reportYear">
                    <?php foreach (array_reverse($years) as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-primary" id="btnLoad">Load Report</button>
                <button type="button" class="btn btn-print" id="btnPrint" disabled>Print Report</button>
            </div>
        </div>

        <div class="card" id="reportContent">
            <div class="message">Select a year and click "Load Report" to view the 6-month summary (<?php echo htmlspecialchars($periodLabel); ?>).</div>
        </div>
    </div>

    <script>
        let reportData = null;
        let reportPeriodLabel = '';
        const reportPeriod = '<?php echo $period === 'janjun' ? 'janjun' : 'juldec'; ?>';

        document.getElementById('btnLoad').addEventListener('click', loadReport);
        document.getElementById('btnPrint').addEventListener('click', printReport);
        document.getElementById('reportYear').addEventListener('change', loadReport);
        // Auto-load report for current year on page load
        document.addEventListener('DOMContentLoaded', function() { loadReport(); });

        function loadReport() {
            const year = document.getElementById('reportYear').value;
            const startDate = reportPeriod === 'janjun' ? (year + '-01-01') : (year + '-07-01');
            const endDate = reportPeriod === 'janjun' ? (year + '-06-30') : (year + '-12-31');
            const contentDiv = document.getElementById('reportContent');
            contentDiv.innerHTML = '<div class="message loading">Loading report...</div>';
            document.getElementById('btnPrint').disabled = true;

            fetch(`get_monthly_stats.php?start_date=${startDate}&end_date=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        reportData = data;
                        reportPeriodLabel = data.report_period || (reportPeriod === 'janjun' ? `January - June ${year}` : `July - December ${year}`);
                        renderReport(data, contentDiv);
                        document.getElementById('btnPrint').disabled = false;
                    } else {
                        contentDiv.innerHTML = '<div class="message error">' + (data.message || 'Failed to load report.') + '</div>';
                    }
                })
                .catch(error => {
                    contentDiv.innerHTML = '<div class="message error">Error loading report. Please try again.</div>';
                    console.error('Error:', error);
                });
        }

        function renderReport(data, contentDiv) {
            let html = '<div style="margin-bottom: 20px;"><h3 style="color: #1e40af;">' + reportPeriodLabel + '</h3></div>';
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">';
            html += '<div style="background: #eff6ff; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 32px; font-weight: bold; color: #1e40af;">' + data.total_employees + '</div><div style="color: #666; margin-top: 5px;">Number of Employees</div></div>';
            html += '<div style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 32px; font-weight: bold; color: #856404;">' + data.total_late + '</div><div style="color: #666; margin-top: 5px;">Tardiness</div></div>';
            html += '<div style="background: #f8d7da; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 32px; font-weight: bold; color: #721c24;">' + data.total_absent + '</div><div style="color: #666; margin-top: 5px;">Actual Absences</div></div>';
            const equivAbsent = data.total_absent_from_lates !== undefined ? data.total_absent_from_lates : 0;
            html += '<div style="background: #ffe6e6; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 32px; font-weight: bold; color: #b21f2d;">' + equivAbsent + '</div><div style="color: #666; margin-top: 5px;">Equivalent from Late</div><div style="font-size: 11px; color: #999;">4 late = 1 absent</div></div>';
            const workingDaysVal = data.working_days !== undefined ? data.working_days : (data.effective_days_in_month !== undefined ? data.effective_days_in_month : 0);
            html += '<div style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 32px; font-weight: bold; color: #155724;">' + workingDaysVal + '</div><div style="color: #666; margin-top: 5px;">Working Days</div><div style="font-size: 11px; color: #999;">Mon–Fri, excl. holiday/suspended</div></div>';
            html += '</div>';

            if (data.all_employees && data.all_employees.length > 0) {
                const sortedByPoints = [...data.all_employees].sort((a, b) => {
                    const avgA = a.average_points !== undefined ? a.average_points : 0;
                    const avgB = b.average_points !== undefined ? b.average_points : 0;
                    return avgB - avgA;
                });
                const top5 = sortedByPoints.slice(0, 5).filter(emp => (emp.average_points !== undefined && emp.average_points > 0));
                if (top5.length > 0) {
                    html += '<div style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); padding: 20px; border-radius: 8px; margin-bottom: 25px; color: white;">';
                    html += '<h3 style="margin-top: 0; color: white; text-align: center;">🏆 Top 5 Employees - Highest Average Points</h3>';
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">';
                    top5.forEach((emp, index) => {
                        const rank = index + 1;
                        const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : '⭐';
                        const avgPoints = emp.average_points !== undefined ? emp.average_points.toFixed(2) : '0.00';
                        html += '<div style="background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 6px; text-align: center; backdrop-filter: blur(10px);">';
                        html += '<div style="font-size: 24px; margin-bottom: 8px;">' + medal + '</div>';
                        html += '<div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">' + emp.name + '</div>';
                        html += '<div style="font-size: 20px; font-weight: bold; color: #ffd700;">' + avgPoints + ' pts</div>';
                        html += '<div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">Rank #' + rank + '</div>';
                        html += '</div>';
                    });
                    html += '</div></div>';
                }
                html += '<h4 style="margin: 25px 0 15px; color: #333;">All Employees - Summary (6 Months)</h4>';
                html += '<div style="max-height: 60vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;"><table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); color: white;">';
                html += '<th style="padding: 12px; text-align: center; width: 50px;">No.</th>';
                html += '<th style="padding: 12px; text-align: left;">Employee Name</th>';
                html += '<th style="padding: 12px; text-align: center;">Late</th>';
                html += '<th style="padding: 12px; text-align: center;">Actual Absent</th>';
                html += '<th style="padding: 12px; text-align: center;">Equivalent Absent (from Late)</th>';
                html += '<th style="padding: 12px; text-align: center;">Remaining Tardiness</th>';
                html += '</tr></thead><tbody>';
                data.all_employees.forEach((emp, i) => {
                    const lateCount = emp.late || 0;
                    const actualAbsentCount = emp.absent || 0;
                    const absentFromLates = emp.absent_from_lates !== undefined ? emp.absent_from_lates : Math.floor(lateCount / 4);
                    const remainingTardiness = emp.remaining_tardiness !== undefined ? emp.remaining_tardiness : (lateCount % 4);
                    const rowBg = (i % 2 === 0) ? '#fff' : '#f8f9fa';
                    html += '<tr style="background: ' + rowBg + '">';
                    html += '<td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">' + (i + 1) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #dee2e6;">' + emp.name + '</td>';
                    html += '<td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">' + lateCount + '</td>';
                    html += '<td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">' + actualAbsentCount + '</td>';
                    html += '<td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">' + absentFromLates + '</td>';
                    html += '<td style="padding: 10px; text-align: center; border-bottom: 1px solid #dee2e6;">' + remainingTardiness + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                html += '<p style="margin-top: 15px; color: #666; font-size: 14px;"><strong>Total: ' + data.all_employees.length + ' employees</strong></p>';
            } else {
                html += '<div class="message">No employee data for this period.</div>';
            }
            contentDiv.innerHTML = html;
        }

        function printReport() {
            if (!reportData) {
                alert('Please load the report first.');
                return;
            }
            const printDate = new Date().toLocaleDateString();
            let printHTML = '<div class="print-header">';
            printHTML += '<h1>ROMD Attendance System</h1>';
            printHTML += '<h2>6-Month Attendance Report - ' + reportPeriodLabel + '</h2>';
            printHTML += '<p style="margin-top: 10px; font-size: 14px;">Generated on: ' + printDate + '</p>';
            const printWorkingDays = reportData.working_days !== undefined ? reportData.working_days : (reportData.effective_days_in_month !== undefined ? reportData.effective_days_in_month : 0);
            if (reportData.days_in_month !== undefined || printWorkingDays > 0) {
                const dm = reportData.days_in_month !== undefined ? reportData.days_in_month : 0;
                const hd = reportData.holiday_dates || 0;
                const sd = reportData.suspended_dates || 0;
                printHTML += '<p style="margin-top: 5px; font-size: 12px; color: #666;">Working days (Mon–Fri, excluding holiday/suspended): <strong>' + printWorkingDays + '</strong>. Total calendar days in period: ' + dm + '; Holiday: ' + hd + ', Suspended: ' + sd + '.</p>';
            }
            printHTML += '</div>';
            printHTML += '<div class="print-stats">';
            printHTML += '<div class="print-stat-box"><div class="number">' + reportData.total_employees + '</div><div class="label">Number of Employees</div></div>';
            printHTML += '<div class="print-stat-box"><div class="number">' + reportData.total_late + '</div><div class="label">Tardiness</div></div>';
            printHTML += '<div class="print-stat-box"><div class="number">' + reportData.total_absent + '</div><div class="label">Actual Absences</div></div>';
            const printEquivAbsent = reportData.total_absent_from_lates !== undefined ? reportData.total_absent_from_lates : 0;
            printHTML += '<div class="print-stat-box"><div class="number">' + printEquivAbsent + '</div><div class="label">Equivalent from Late</div><div class="label" style="font-size: 10px;">4 late = 1 absent</div></div>';
            printHTML += '<div class="print-stat-box"><div class="number">' + printWorkingDays + '</div><div class="label">Working Days</div></div>';
            printHTML += '</div>';

            if (reportData.all_employees && reportData.all_employees.length > 0) {
                const sortedByPointsPrint = [...reportData.all_employees].sort((a, b) => {
                    const avgA = a.average_points !== undefined ? a.average_points : 0;
                    const avgB = b.average_points !== undefined ? b.average_points : 0;
                    return avgB - avgA;
                });
                const top5Print = sortedByPointsPrint.slice(0, 5).filter(emp => (emp.average_points !== undefined && emp.average_points > 0));
                if (top5Print.length > 0) {
                    printHTML += '<div class="print-section" style="background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%); padding: 20px; border-radius: 8px; margin: 20px 0; color: white;">';
                    printHTML += '<h3 style="margin-top: 0; color: white; text-align: center;">🏆 Top 5 Employees - Highest Average Points</h3>';
                    printHTML += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">';
                    top5Print.forEach((emp, index) => {
                        const rank = index + 1;
                        const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : '⭐';
                        const avgPoints = emp.average_points !== undefined ? emp.average_points.toFixed(2) : '0.00';
                        printHTML += '<div style="background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 6px; text-align: center; backdrop-filter: blur(10px);">';
                        printHTML += '<div style="font-size: 24px; margin-bottom: 8px;">' + medal + '</div>';
                        printHTML += '<div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">' + emp.name + '</div>';
                        printHTML += '<div style="font-size: 20px; font-weight: bold; color: #ffd700;">' + avgPoints + ' pts</div>';
                        printHTML += '<div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">Rank #' + rank + '</div>';
                        printHTML += '</div>';
                    });
                    printHTML += '</div></div>';
                }
                printHTML += '<div class="print-section"><h3>Complete Employee Attendance Summary (6 Months)</h3>';
                printHTML += '<table class="print-table"><thead><tr>';
                printHTML += '<th>No.</th><th>Employee Name</th><th style="text-align: center;">Days Present</th><th style="text-align: center;">On-Time</th><th style="text-align: center;">Offset</th><th style="text-align: center;">Tardiness</th>';
                printHTML += '<th style="text-align: center;">Actual Absent</th><th style="text-align: center;">Equiv Absent (Late)</th><th style="text-align: center;">Rem. Tardiness</th><th style="text-align: center;">Leave</th><th style="text-align: center;">Holiday</th><th style="text-align: center;">Suspended</th><th style="text-align: center;">Total Days</th><th style="text-align: center;">Avg Points</th>';
                printHTML += '</tr></thead><tbody>';
                const sorted = [...reportData.all_employees].sort((a, b) => {
                    const avgA = a.average_points !== undefined ? a.average_points : 0;
                    const avgB = b.average_points !== undefined ? b.average_points : 0;
                    if (avgB !== avgA) return avgB - avgA;
                    return (a.name || '').toLowerCase().localeCompare((b.name || '').toLowerCase());
                });
                sorted.forEach((emp, i) => {
                    const presentCount = emp.present_total !== undefined ? emp.present_total : ((emp.present || 0) + (emp.late || 0) + (emp.offset || 0));
                    const totalDays = (emp.present || 0) + (emp.late || 0) + (emp.absent || 0) + (emp.offset || 0);
                    const actualAbsentVal = emp.absent || 0;
                    const absentFromLates = emp.absent_from_lates !== undefined ? emp.absent_from_lates : Math.floor((emp.late || 0) / 4);
                    const remainingTardiness = emp.remaining_tardiness !== undefined ? emp.remaining_tardiness : ((emp.late || 0) % 4);
                    const avgPoints = emp.average_points !== undefined ? emp.average_points.toFixed(2) : '0.00';
                    printHTML += '<tr>';
                    printHTML += '<td style="text-align: center;">' + (i + 1) + '</td><td>' + emp.name + '</td>';
                    printHTML += '<td style="text-align: center;">' + presentCount + '</td><td style="text-align: center;">' + (emp.present || 0) + '</td><td style="text-align: center;">' + (emp.offset || 0) + '</td>';
                    printHTML += '<td style="text-align: center;">' + (emp.late || 0) + '</td><td style="text-align: center;">' + actualAbsentVal + '</td><td style="text-align: center;">' + absentFromLates + '</td><td style="text-align: center;">' + remainingTardiness + '</td>';
                    printHTML += '<td style="text-align: center;">' + (emp.leave || 0) + '</td><td style="text-align: center;">' + (emp.holiday || 0) + '</td><td style="text-align: center;">' + (emp.suspended || 0) + '</td><td style="text-align: center;">' + totalDays + '</td><td style="text-align: center;">' + avgPoints + '</td>';
                    printHTML += '</tr>';
                });
                printHTML += '</tbody></table></div>';
            }
            printHTML += '<div class="print-footer"><p>This is a computer-generated report from ROMD Attendance System</p><p>Report Period: ' + reportPeriodLabel + '</p></div>';

            const fullPrintHTML = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>6-Month Report - ' + reportPeriodLabel + '</title><style>@media print{@page{margin:1cm}} body{font-family:Arial,sans-serif;margin:0;padding:20px;color:#333} .print-header{text-align:center;margin-bottom:30px;border-bottom:3px solid #333;padding-bottom:20px} .print-header h1{font-size:28px} .print-header h2{font-size:20px;color:#666} .print-stats{display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin:30px 0} .print-stat-box{border:2px solid #333;padding:15px;text-align:center} .print-stat-box .number{font-size:32px;font-weight:bold} .print-stat-box .label{font-size:14px;text-transform:uppercase} .print-table{width:100%;border-collapse:collapse;margin:20px 0} .print-table th,.print-table td{border:1px solid #333;padding:10px} .print-table th{background:#f0f0f0;font-weight:bold;text-align:center} .print-section{margin:30px 0;page-break-inside:avoid} .print-section h3{font-size:18px;margin-bottom:15px;border-bottom:2px solid #333;padding-bottom:5px} .print-footer{margin-top:40px;padding-top:20px;border-top:2px solid #333;text-align:center;font-size:12px;color:#666}</style></head><body>' + printHTML + '</body></html>';
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            if (!printWindow) {
                alert('Please allow popups to print the report.');
                return;
            }
            printWindow.document.write(fullPrintHTML);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
        }
    </script>
<?php include 'includes/footer.php'; ?>
