<?php
require_once 'config.php';
requireLogin();

$currentYear = (int) date('Y');
$startYear = min(2025, $currentYear);
$endYear = $currentYear + 1;
$years = range($startYear, $endYear);
$page_title = 'Employee Summary Report (Per Month) - ROMD Attendance';
$current_page = 'employee_summary';
include 'includes/header.php';
?>
    <div class="container">
        <div class="card no-print">
            <h2>Select Employee, Year & Period</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">View attendance summary per month for the selected employee, year, and period.</p>
            <div class="controls">
                <label for="reportEmployee">Employee:</label>
                <select id="reportEmployee">
                    <option value="">-- Load employees --</option>
                </select>
                <label for="reportYear">Year:</label>
                <select id="reportYear">
                    <?php foreach (array_reverse($years) as $y): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="reportPeriod">Period:</label>
                <select id="reportPeriod">
                    <option value="full">Full Year</option>
                    <option value="h1">Jan - Jun</option>
                    <option value="h2">Jul - Dec</option>
                    <option value="custom">Custom Range</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
                <span id="customMonthControls" style="display: none;">
                    <label for="reportStartMonth">From:</label>
                    <select id="reportStartMonth">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <label for="reportEndMonth">To:</label>
                    <select id="reportEndMonth">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6" selected>June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </span>
                <button type="button" class="btn btn-primary" id="btnLoad">Load Report</button>
                <button type="button" class="btn btn-print" id="btnPrint" disabled>Print Report</button>
            </div>
        </div>

        <div class="card" id="reportContent">
            <div class="message">Select an employee and year, then click "Load Report" to view the monthly summary.</div>
        </div>
    </div>

    <script>
        let reportData = null;

        document.getElementById('btnLoad').addEventListener('click', loadReport);
        document.getElementById('btnPrint').addEventListener('click', printReport);
        document.getElementById('reportPeriod').addEventListener('change', toggleCustomMonths);
        toggleCustomMonths();

        fetch('get_employees.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.employees && data.employees.length > 0) {
                    const sel = document.getElementById('reportEmployee');
                    sel.innerHTML = '<option value="">-- Select employee --</option>';
                    data.employees.forEach(emp => {
                        const opt = document.createElement('option');
                        opt.value = emp.id;
                        opt.textContent = emp.name || '';
                        sel.appendChild(opt);
                    });
                }
            })
            .catch(() => {});

        function loadReport() {
            const employeeId = document.getElementById('reportEmployee').value;
            const year = document.getElementById('reportYear').value;
            const period = document.getElementById('reportPeriod').value;
            const startMonth = document.getElementById('reportStartMonth').value;
            const endMonth = document.getElementById('reportEndMonth').value;
            if (!employeeId) {
                document.getElementById('reportContent').innerHTML = '<div class="message error">Please select an employee.</div>';
                return;
            }
            const contentDiv = document.getElementById('reportContent');
            contentDiv.innerHTML = '<div class="message loading">Loading report...</div>';
            document.getElementById('btnPrint').disabled = true;

            let url = 'get_employee_summary.php?employee_id=' + encodeURIComponent(employeeId) + '&year=' + encodeURIComponent(year) + '&period=' + encodeURIComponent(period);
            if (period === 'custom') {
                url += '&start_month=' + encodeURIComponent(startMonth) + '&end_month=' + encodeURIComponent(endMonth);
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        reportData = data;
                        renderReport(data, contentDiv);
                        document.getElementById('btnPrint').disabled = false;
                    } else {
                        contentDiv.innerHTML = '<div class="message error">' + (data.message || 'Failed to load report.') + '</div>';
                    }
                })
                .catch(() => {
                    contentDiv.innerHTML = '<div class="message error">Error loading report. Please try again.</div>';
                });
        }

        function toggleCustomMonths() {
            const period = document.getElementById('reportPeriod').value;
            document.getElementById('customMonthControls').style.display = period === 'custom' ? 'inline-flex' : 'none';
        }

        function renderReport(data, contentDiv) {
            const periodLabel = data.period_label || 'Full Year';
            let html = '<div style="margin-bottom: 20px;"><h3 style="color: #1e40af;">' + (data.employee_name || '') + ' — ' + data.year + ' (' + periodLabel + ')</h3></div>';
            html += '<div style="overflow-x: auto;"><table class="summary-table">';
            html += '<thead><tr>';
            html += '<th class="text-left">Month</th>';
            html += '<th>Working Days</th>';
            html += '<th>On-Time</th>';
            html += '<th>Late</th>';
            html += '<th>Offset</th>';
            html += '<th>Actual Absent</th>';
            html += '<th>Equiv. Absent (Late)</th>';
            html += '<th>Rem. Tardiness</th>';
            html += '<th>OB</th>';
            html += '<th>Leave</th>';
            html += '<th>Holiday</th>';
            html += '<th>Suspended</th>';
            html += '<th>Total Days</th>';
            html += '<th>Total Points</th>';
            html += '<th>Avg Points</th>';
            html += '</tr></thead><tbody>';

            if (data.months && data.months.length > 0) {
                let totWorkingDays = 0, totPresent = 0, totLate = 0, totOffset = 0, totOb = 0, totAbsent = 0, totEquiv = 0, totLeave = 0, totHoliday = 0, totSuspended = 0, totPoints = 0, totAttendanceDays = 0;
                data.months.forEach(m => {
                    const totalDays = (m.present || 0) + (m.late || 0) + (m.absent || 0) + (m.offset || 0) + (m.ob || 0);
                    totWorkingDays += m.working_days || 0;
                    totPresent += m.present || 0;
                    totLate += m.late || 0;
                    totOffset += m.offset || 0;
                    totOb += m.ob || 0;
                    totAbsent += m.absent || 0;
                    totEquiv += m.absent_from_lates || 0;
                    totLeave += m.leave || 0;
                    totHoliday += m.holiday || 0;
                    totSuspended += m.suspended || 0;
                    totPoints += m.total_points || 0;
                    totAttendanceDays += m.attendance_days || 0;
                    html += '<tr>';
                    html += '<td class="text-left">' + (m.month_name || '') + '</td>';
                    html += '<td>' + (m.working_days !== undefined ? m.working_days : '') + '</td>';
                    html += '<td>' + (m.present || 0) + '</td>';
                    html += '<td>' + (m.late || 0) + '</td>';
                    html += '<td>' + (m.offset || 0) + '</td>';
                    html += '<td>' + (m.absent || 0) + '</td>';
                    html += '<td>' + (m.absent_from_lates !== undefined ? m.absent_from_lates : 0) + '</td>';
                    html += '<td>' + (m.remaining_tardiness !== undefined ? m.remaining_tardiness : 0) + '</td>';
                    html += '<td>' + (m.ob || 0) + '</td>';
                    html += '<td>' + (m.leave || 0) + '</td>';
                    html += '<td>' + (m.holiday || 0) + '</td>';
                    html += '<td>' + (m.suspended || 0) + '</td>';
                    html += '<td>' + totalDays + '</td>';
                    html += '<td>' + (m.total_points !== undefined ? m.total_points : 0) + '</td>';
                    html += '<td>' + (m.average_points !== undefined ? Number(m.average_points).toFixed(2) : '') + '</td>';
                    html += '</tr>';
                });
                const totalAveragePoints = totAttendanceDays > 0 ? (totPoints / totAttendanceDays).toFixed(2) : '0.00';
                html += '<tr style="font-weight: bold; background: #e7f1ff;">';
                html += '<td class="text-left">Total</td>';
                html += '<td>' + totWorkingDays + '</td>';
                html += '<td>' + totPresent + '</td>';
                html += '<td>' + totLate + '</td>';
                html += '<td>' + totOffset + '</td>';
                html += '<td>' + totAbsent + '</td>';
                html += '<td>' + totEquiv + '</td>';
                html += '<td>—</td>';
                html += '<td>' + totOb + '</td>';
                html += '<td>' + totLeave + '</td>';
                html += '<td>' + totHoliday + '</td>';
                html += '<td>' + totSuspended + '</td>';
                html += '<td>' + (totPresent + totLate + totAbsent + totOffset + totOb) + '</td>';
                html += '<td>' + totPoints + '</td>';
                html += '<td>' + totalAveragePoints + '</td>';
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            contentDiv.innerHTML = html;
        }

        function printReport() {
            if (!reportData) {
                alert('Please load the report first.');
                return;
            }
            const printDate = new Date().toLocaleDateString();
            let printHTML = '<div style="text-align:center;margin-bottom:20px;border-bottom:2px solid #333;padding-bottom:15px;">';
            printHTML += '<h1>ROMD Attendance System</h1>';
            printHTML += '<h2>Employee Summary Report (Per Month)</h2>';
            printHTML += '<p><strong>' + reportData.employee_name + '</strong> — Year ' + reportData.year + ' (' + (reportData.period_label || 'Full Year') + ')</p>';
            printHTML += '<p style="font-size:12px;color:#666;">Generated on: ' + printDate + '</p>';
            printHTML += '</div>';
            printHTML += '<table class="summary-table" style="width:100%;border-collapse:collapse;font-size:11px;">';
            printHTML += '<thead><tr>';
            printHTML += '<th class="text-left" style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Month</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Working Days</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">On-Time</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Late</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Offset</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Actual Absent</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Equiv. Absent (Late)</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Rem. Tardiness</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">OB</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Leave</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Holiday</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Suspended</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Total Days</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Total Points</th>';
            printHTML += '<th style="border:1px solid #333;padding:8px;background:#1e40af;color:white;">Avg Points</th>';
            printHTML += '</tr></thead><tbody>';
            if (reportData.months && reportData.months.length > 0) {
                let totWorkingDays = 0, totPresent = 0, totLate = 0, totOffset = 0, totOb = 0, totAbsent = 0, totEquiv = 0, totLeave = 0, totHoliday = 0, totSuspended = 0, totPoints = 0, totAttendanceDays = 0;
                reportData.months.forEach(m => {
                    const totalDays = (m.present || 0) + (m.late || 0) + (m.absent || 0) + (m.offset || 0) + (m.ob || 0);
                    totWorkingDays += m.working_days || 0;
                    totPresent += m.present || 0;
                    totLate += m.late || 0;
                    totOffset += m.offset || 0;
                    totOb += m.ob || 0;
                    totAbsent += m.absent || 0;
                    totEquiv += m.absent_from_lates || 0;
                    totLeave += m.leave || 0;
                    totHoliday += m.holiday || 0;
                    totSuspended += m.suspended || 0;
                    totPoints += m.total_points || 0;
                    totAttendanceDays += m.attendance_days || 0;
                    printHTML += '<tr>';
                    printHTML += '<td class="text-left" style="border:1px solid #333;padding:6px;">' + (m.month_name || '') + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.working_days !== undefined ? m.working_days : '') + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.present || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.late || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.offset || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.absent || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.absent_from_lates !== undefined ? m.absent_from_lates : 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.remaining_tardiness !== undefined ? m.remaining_tardiness : 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.ob || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.leave || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.holiday || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.suspended || 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + totalDays + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.total_points !== undefined ? m.total_points : 0) + '</td>';
                    printHTML += '<td style="border:1px solid #333;padding:6px;text-align:center;">' + (m.average_points !== undefined ? Number(m.average_points).toFixed(2) : '') + '</td>';
                    printHTML += '</tr>';
                });
                const totalAveragePoints = totAttendanceDays > 0 ? (totPoints / totAttendanceDays).toFixed(2) : '0.00';
                printHTML += '<tr style="font-weight:bold;background:#e7f1ff;">';
                printHTML += '<td class="text-left" style="border:1px solid #333;padding:6px;">Total</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totWorkingDays + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totPresent + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totLate + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totOffset + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totAbsent + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totEquiv + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">—</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totOb + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totLeave + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totHoliday + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totSuspended + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + (totPresent + totLate + totAbsent + totOffset + totOb) + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totPoints + '</td>';
                printHTML += '<td style="border:1px solid #333;padding:6px;">' + totalAveragePoints + '</td>';
                printHTML += '</tr>';
            }
            printHTML += '</tbody></table>';
            const win = window.open('', '_blank', 'width=900,height=700');
            if (!win) { alert('Please allow popups to print.'); return; }
            win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Employee Summary - ' + reportData.employee_name + ' ' + reportData.year + '</title></head><body>' + printHTML + '</body></html>');
            win.document.close();
            win.focus();
            setTimeout(() => { win.print(); win.close(); }, 500);
        }
    </script>
<?php include 'includes/footer.php'; ?>
