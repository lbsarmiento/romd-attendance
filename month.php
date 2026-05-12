<?php
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

// Get month and year from URL parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$monthName = $months[$month];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Get all employees
$conn = getDBConnection();
$employees = [];
$attendance_data = [];

// Check if attendance table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($table_check === false || $table_check->num_rows == 0) {
    // Create attendance table
    $create_table_sql = "CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        time_in TIME,
        time_out TIME,
        is_wfh TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (employee_id, attendance_date),
        INDEX idx_date (attendance_date),
        INDEX idx_employee_date (employee_id, attendance_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($create_table_sql);
}
$wfh_column_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'is_wfh'");
if ($wfh_column_check === false || $wfh_column_check->num_rows === 0) {
    $conn->query("ALTER TABLE attendance ADD COLUMN is_wfh TINYINT(1) NOT NULL DEFAULT 0 AFTER time_out");
}
if ($wfh_column_check !== false) {
    $wfh_column_check->free();
}

// Check if employees table exists and get employees
$employees_query = "SELECT id, employee_name FROM employees WHERE status = 'active' ORDER BY employee_name";
$employees_result = $conn->query($employees_query);

if ($employees_result !== false) {
    while ($row = $employees_result->fetch_assoc()) {
        $employees[] = $row;
    }
    $employees_result->free();
}

// Prepare employee name and grade arrays
$employee_points = [];
$employee_attendance_days = []; // Track number of days with attendance records
$employee_names = [];
foreach ($employees as $emp) {
    $employee_points[$emp['id']] = 0;
    $employee_attendance_days[$emp['id']] = 0;
    $employee_names[$emp['id']] = $emp['employee_name'];
}

// Get attendance data for the month
if (!empty($employees)) {
    $employee_ids = array_column($employees, 'id');
    
    if (!empty($employee_ids)) {
        $placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        
        $attendance_query = "SELECT employee_id, attendance_date, time_in, status, is_wfh
                             FROM attendance
                             WHERE employee_id IN ($placeholders)
                             AND attendance_date >= ? AND attendance_date <= ?
                             ORDER BY attendance_date";
        
        $stmt = $conn->prepare($attendance_query);
        
        if ($stmt !== false) {
            $types = str_repeat('i', count($employee_ids)) . 'ss';
            $params = array_merge($employee_ids, [$start_date, $end_date]);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $attendance_result = $stmt->get_result();
                
                if ($attendance_result !== false) {
                    while ($row = $attendance_result->fetch_assoc()) {
                        $date_key = date('j', strtotime($row['attendance_date']));
                        $attendance_data[$row['employee_id']][$date_key] = [
                            'time_in' => $row['time_in'],
                            'status' => $row['status'],
                            'is_wfh' => (int)($row['is_wfh'] ?? 0)
                        ];
                    }
                    $attendance_result->free();
                }
            }
            $stmt->close();
        }
    }
}

// Ensure call_times table exists
$call_times_check = $conn->query("SHOW TABLES LIKE 'call_times'");
if ($call_times_check === false || $call_times_check->num_rows == 0) {
    $create_call_times_sql = "CREATE TABLE IF NOT EXISTS call_times (
        id INT AUTO_INCREMENT PRIMARY KEY,
        call_date DATE NOT NULL UNIQUE,
        call_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_call_date (call_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($create_call_times_sql);
}

// Load call times for this month
$callTimesByDate = [];
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$call_times_stmt = $conn->prepare("SELECT call_date, call_time FROM call_times WHERE call_date >= ? AND call_date <= ? ORDER BY call_date");
if ($call_times_stmt !== false) {
    $call_times_stmt->bind_param('ss', $start_date, $end_date);
    if ($call_times_stmt->execute()) {
        $call_times_result = $call_times_stmt->get_result();
        if ($call_times_result !== false) {
            while ($row = $call_times_result->fetch_assoc()) {
                // Store as HH:MM
                $callTimesByDate[$row['call_date']] = substr($row['call_time'], 0, 5);
            }
            $call_times_result->free();
        }
    }
    $call_times_stmt->close();
}

$conn->close();

// Track the earliest valid office time-in per day for badge display.
$first_time_in_by_day = [];
foreach ($attendance_data as $employeeAttendance) {
    foreach ($employeeAttendance as $day => $attendance) {
        $status = $attendance['status'] ?? '';
        $timeIn = $attendance['time_in'] ?? '';
        if (
            empty($timeIn)
            || in_array($status, ['absent', 'offset', 'leave', 'ob', 'holiday', 'suspended'], true)
        ) {
            continue;
        }

        if (!isset($first_time_in_by_day[$day]) || strcmp($timeIn, $first_time_in_by_day[$day]) < 0) {
            $first_time_in_by_day[$day] = $timeIn;
        }
    }
}

// Default call time (HH:MM). Can be overridden per date.
$defaultCallTime = '08:00';

// Helper to get call time for a specific date
function getCallTimeForDate($date)
{
    global $defaultCallTime, $callTimesByDate;
    return isset($callTimesByDate[$date]) ? $callTimesByDate[$date] : $defaultCallTime;
}

// Function to check if time is late based on a configurable call time.
// Anything strictly AFTER the call time is considered late.
function isLate($time, $callTime = '08:00') {
    if (empty($time) || $time == '00:00:00') return false;

    // Normalize to HH:MM
    $time_parts = explode(':', $time);
    if (count($time_parts) < 2) return false;
    $hour = (int)$time_parts[0];
    $minute = (int)$time_parts[1];
    $minutes = $hour * 60 + $minute;

    $call_parts = explode(':', $callTime);
    if (count($call_parts) < 2) return false;
    $callHour = (int)$call_parts[0];
    $callMinute = (int)$call_parts[1];
    $callMinutes = $callHour * 60 + $callMinute;

    // Late if arrival minutes > call time minutes
    return $minutes > $callMinutes;
}

// Function to format time for display
function formatTime($time) {
    if (empty($time) || $time == '00:00:00') return '';
    $time_parts = explode(':', $time);
    return $time_parts[0] . ':' . $time_parts[1];
}

// Function to calculate grade points based on arrival time
// Optional $attendanceDate (Y-m-d) used to apply 2026+ time bands; before 2026 uses previous bands.
function calculateGradePoints($status, $time, $attendanceDate = null) {
    if ($status === 'leave' || $status === 'holiday' || $status === 'suspended') {
        return 0;
    }
    if ($status === 'offset' || $status === 'ob') {
        return 3;
    }
    if ($status === 'absent' || empty($time) || $time === '00:00:00') {
        return 0;
    }
    
    $time_parts = explode(':', $time);
    if (count($time_parts) < 2) {
        return 0;
    }
    
    $hour = (int)$time_parts[0];
    $minute = (int)$time_parts[1];
    $minutes = ($hour * 60) + $minute;
    $year = $attendanceDate ? (int)date('Y', strtotime($attendanceDate)) : (int)date('Y');
    $use_2026_bands = ($year >= 2026);

    if ($use_2026_bands) {
        // 2026 onwards: 6:00-6:44=5, 6:45-7:45=4, 7:46-8:00=3, 8:01-8:15=2, 8:16+=1
        if ($minutes <= (6 * 60 + 44)) return 5;
        if ($minutes <= (7 * 60 + 45)) return 4;
        if ($minutes <= (8 * 60)) return 3;
        if ($minutes <= (8 * 60 + 15)) return 2;
        return 1;
    }

    // Before 2026
    if ($minutes <= (7 * 60 + 44)) return 5;
    if ($minutes <= (7 * 60 + 59)) return 4;
    if ($minutes >= (8 * 60) && $minutes <= (8 * 60 + 29)) return 3;
    if ($minutes == (8 * 60 + 30)) return 2;
    return 1;
}

// Get day names
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$weekdaysInMonth = 0;
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $dayOfWeek = (int)date('w', strtotime($date));
    if ($dayOfWeek !== 0 && $dayOfWeek !== 6) {
        $weekdaysInMonth++;
    }
}
?>
<?php
$page_title = $monthName . ' ' . $year . ' - ROMD Attendance';
$current_page = 'dashboard';
$show_back_btn = true;
include 'includes/header.php';
?>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            body {
                margin: 0;
                padding: 0;
            }
            #printView {
                visibility: visible !important;
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background: white;
                margin: 0;
            }
            #printView * {
                visibility: visible !important;
            }
            #printContent {
                display: block !important;
                visibility: visible !important;
            }
            .app-header, .attendance-table-container .modal, .no-print {
                display: none !important;
                visibility: hidden !important;
            }
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 3px solid #333;
                padding-bottom: 20px;
            }
            .print-header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                color: #333;
            }
            .print-header h2 {
                font-size: 20px;
                color: #666;
            }
            .print-stats {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 15px;
                margin: 30px 0;
            }
            .print-stat-box {
                border: 2px solid #333;
                padding: 15px;
                text-align: center;
            }
            .print-stat-box .number {
                font-size: 32px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .print-stat-box .label {
                font-size: 14px;
                text-transform: uppercase;
            }
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .print-table th,
            .print-table td {
                border: 1px solid #333;
                padding: 10px;
                text-align: left;
            }
            .print-table th {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            .print-table tbody tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .print-section {
                margin: 30px 0;
                page-break-inside: avoid;
            }
            .print-section h3 {
                font-size: 18px;
                margin-bottom: 15px;
                border-bottom: 2px solid #333;
                padding-bottom: 5px;
            }
            .print-footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #333;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            .no-print {
                display: none !important;
            }
        }

        /* Per-cell points badge (top-right) */
        .time-cell {
            position: relative;
        }
        .time-cell .cell-points {
            position: absolute;
            top: 2px;
            right: 3px;
            font-size: 9px;
            line-height: 1;
            font-weight: 700;
            color: #1e40af;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.75);
            padding: 1px 3px;
            border-radius: 3px;
        }
        .time-cell.status-absent .cell-points {
            color: #b91c1c;
        }
        .time-cell .cell-crown {
            position: absolute;
            top: 2px;
            left: 3px;
            font-size: 11px;
            line-height: 1;
            filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.2));
        }
        @media (max-width: 768px) {
            .attendance-table-container {
                padding: 10px;
            }
            .month-header h2 {
                font-size: 22px;
            }
            .navigation {
                flex-direction: column;
            }
            .nav-btn {
                width: 100%;
            }
        }
    </style>
    <div class="container month-page">
        <div class="month-header">
            <div class="month-hero">
                <div class="month-hero-copy">
                    <span class="month-eyebrow">Attendance Month</span>
                    <h2><?php echo htmlspecialchars($monthName . ' ' . $year); ?></h2>
                    <p>Click any working-day cell to update time-in or status. Weekends are shown but locked.</p>
                </div>
                <div class="month-hero-stats">
                    <div class="month-stat">
                        <strong><?php echo count($employees); ?></strong>
                        <span>Employees</span>
                    </div>
                    <div class="month-stat">
                        <strong><?php echo $daysInMonth; ?></strong>
                        <span>Calendar Days</span>
                    </div>
                    <div class="month-stat">
                        <strong><?php echo $weekdaysInMonth; ?></strong>
                        <span>Weekdays</span>
                    </div>
                </div>
            </div>
            <div class="month-actions">
                <button class="add-employee-btn" onclick="openAddEmployeeModal()">+ Add Employee</button>
                <button class="add-employee-btn btn-results" onclick="openResultsModal()">View Results</button>
            </div>
            <div class="navigation month-navigation">
                <?php
                // Previous month
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear--;
                }
                
                // Next month
                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                ?>
                <a href="month.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="nav-btn">Previous</a>
                <a href="index.php" class="nav-btn nav-btn-muted">Dashboard</a>
                <a href="month.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="nav-btn">Next</a>
            </div>
        </div>
        
        <div class="attendance-table-container">
            <div class="attendance-table-toolbar">
                <div>
                    <h3>Attendance Grid</h3>
                    <p>Tip: points appear in the top-right corner of each recorded cell.</p>
                </div>
                <div class="attendance-legend">
                    <span><i class="legend-dot present"></i> Present</span>
                    <span><i class="legend-dot late"></i> Late</span>
                    <span><i class="legend-dot absent"></i> Absent</span>
                    <span><i class="legend-dot excused"></i> Offset / OB / Leave</span>
                </div>
            </div>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th class="employee-header">Employee Name</th>
                        <?php
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $dayOfWeek = date('w', strtotime($date));
                            $dayName = $dayNames[$dayOfWeek];
                            $isWeekendHeader = ($dayOfWeek == 0 || $dayOfWeek == 6);
                            echo '<th class="' . ($isWeekendHeader ? 'weekend-day-header' : '') . '">';
                            echo '<div class="day-short">' . htmlspecialchars(substr($dayName, 0, 3)) . '</div>';
                            echo '<div class="day-number">' . $day . '</div>';
                            echo '</th>';
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="<?php echo $daysInMonth + 1; ?>" style="text-align: center; padding: 30px; color: #666;">
                                No employees found. Please add employees to view attendance records.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td class="employee-name"><?php echo htmlspecialchars($employee['employee_name']); ?></td>
                                <?php
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $dayOfWeek = date('w', strtotime($date));
                                    $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
                                    
                                    $cell_class = $isWeekend ? 'weekend-cell' : 'empty-cell';
                                    $cell_content = '';
                                    $cell_points = null;
                                    
                                    if (isset($attendance_data[$employee['id']][$day])) {
                                        $attendance = $attendance_data[$employee['id']][$day];
                                        $cell_points = calculateGradePoints($attendance['status'], $attendance['time_in'], $date);
                                        if ($attendance['status'] == 'absent') {
                                            // Absent is displayed as -1 point per day (deduction) in totals
                                            $cell_points = -1;
                                        }
                                        
                                        // Accumulate grade points and count attendance days
                                        if (isset($employee_points[$employee['id']])) {
                                            $employee_points[$employee['id']] += calculateGradePoints($attendance['status'], $attendance['time_in'], $date);
                                            // Deduct 1 point for each recorded absence
                                            if ($attendance['status'] == 'absent') {
                                                $employee_points[$employee['id']] -= 1;
                                            }
                                            // Do not include non-scoring excused statuses in average divisor
                                            if (!in_array($attendance['status'], ['leave', 'holiday', 'suspended'], true)) {
                                                $employee_attendance_days[$employee['id']]++;
                                            }
                                        }
                                        
                                        if ($attendance['status'] == 'offset') {
                                            $cell_class = 'status-offset';
                                            $cell_content = 'Offset';
                                        } elseif ($attendance['status'] == 'leave') {
                                            $cell_class = 'status-leave';
                                            $cell_content = 'Leave';
                                        } elseif ($attendance['status'] == 'ob') {
                                            $cell_class = 'status-ob';
                                            $cell_content = 'OB';
                                        } elseif ($attendance['status'] == 'holiday') {
                                            $cell_class = 'status-holiday';
                                            $cell_content = '';
                                        } elseif ($attendance['status'] == 'suspended') {
                                            $cell_class = 'status-suspended';
                                            $cell_content = '';
                                        } elseif ($attendance['status'] == 'absent') {
                                            $cell_class = 'status-absent';
                                            $cell_content = 'Absent';
                                        } elseif ($attendance['status'] == 'late' || (!empty($attendance['time_in']) && isLate($attendance['time_in'], getCallTimeForDate($date)))) {
                                            $time_display = formatTime($attendance['time_in']);
                                            $cell_content = $time_display;
                                            $cell_class = 'time-late';
                                        } elseif (!empty($attendance['time_in'])) {
                                            $time_display = formatTime($attendance['time_in']);
                                            $cell_content = $time_display;
                                            $cell_class = 'time-normal';
                                        }
                                    }
                                    
                                    $date_full = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $employee_id = $employee['id'];
                                    $current_time = isset($attendance_data[$employee_id][$day]) ? formatTime($attendance_data[$employee_id][$day]['time_in']) : '';
                                    $current_status = isset($attendance_data[$employee_id][$day]) ? $attendance_data[$employee_id][$day]['status'] : '';
                                    $current_is_wfh = isset($attendance_data[$employee_id][$day]) ? (int)($attendance_data[$employee_id][$day]['is_wfh'] ?? 0) : 0;
                                    $call_time_for_date = getCallTimeForDate($date_full);
                                    $has_first_time_in_crown = isset($attendance_data[$employee_id][$day]['time_in'], $first_time_in_by_day[$day])
                                        && !in_array($current_status, ['absent', 'offset', 'leave', 'ob', 'holiday', 'suspended'], true)
                                        && $attendance_data[$employee_id][$day]['time_in'] === $first_time_in_by_day[$day];
                                    
                                    // Make cell editable if not weekend
                                    if (!$isWeekend) {
                                        $cell_inner = '';
                                        if ($has_first_time_in_crown) {
                                            $cell_inner .= '<span class="cell-crown" title="First time-in in the office">&#128081;</span>';
                                        }
                                        if ($cell_points !== null) {
                                            $cell_inner .= '<span class="cell-points">' . htmlspecialchars((string)$cell_points) . '</span>';
                                        }
                                        if ($current_is_wfh === 1 && !empty($current_time)) {
                                            $cell_inner .= '<span class="cell-wfh">WFH</span>';
                                        }
                                        $cell_inner .= htmlspecialchars($cell_content);
                                        echo '<td class="time-cell ' . $cell_class . ' editable-cell" 
                                              data-employee-id="' . $employee_id . '" 
                                              data-date="' . $date_full . '" 
                                              data-time="' . htmlspecialchars($current_time) . '"
                                              data-status="' . htmlspecialchars($current_status) . '"
                                              data-wfh="' . $current_is_wfh . '"
                                              data-call-time="' . htmlspecialchars($call_time_for_date) . '"
                                              onclick="editCell(this)">' . $cell_inner . '</td>';
                                    } else {
                                        $cell_inner = '';
                                        if ($has_first_time_in_crown) {
                                            $cell_inner .= '<span class="cell-crown" title="First time-in in the office">&#128081;</span>';
                                        }
                                        if ($cell_points !== null) {
                                            $cell_inner .= '<span class="cell-points">' . htmlspecialchars((string)$cell_points) . '</span>';
                                        }
                                        if ($current_is_wfh === 1 && !empty($current_time)) {
                                            $cell_inner .= '<span class="cell-wfh">WFH</span>';
                                        }
                                        $cell_inner .= htmlspecialchars($cell_content);
                                        echo '<td class="time-cell ' . $cell_class . '">' . $cell_inner . '</td>';
                                    }
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php if (!empty($employee_points)): 
        // Calculate averages and sort by average
        $averages = [];
        foreach ($employee_points as $empId => $points) {
            $attendanceDays = $employee_attendance_days[$empId] ?? 0;
            $averages[$empId] = $attendanceDays > 0 ? round($points / $attendanceDays, 2) : 0;
        }
        arsort($averages);
        $topAverages = array_slice($averages, 0, 3, true);
        $bestAverage = !empty($averages) ? reset($averages) : 0;
        $totalGradePoints = array_sum($employee_points);
        $totalRecordedDays = array_sum($employee_attendance_days);
        $overallAverage = $totalRecordedDays > 0 ? round($totalGradePoints / $totalRecordedDays, 2) : 0;
    ?>
    <div class="container month-page grade-summary-container">
        <section class="grade-summary">
            <div class="grade-summary-header">
                <div>
                    <span class="month-eyebrow">Performance Snapshot</span>
                    <h3>Monthly Grade Points (Average)</h3>
                    <p>Compact view of total points, counted attendance days, and average points for <?php echo htmlspecialchars($monthName . ' ' . $year); ?>.</p>
                </div>
                <div class="grade-summary-metrics">
                    <div class="grade-mini-stat">
                        <strong><?php echo number_format($bestAverage, 2); ?></strong>
                        <span>Highest Avg</span>
                    </div>
                    <div class="grade-mini-stat">
                        <strong><?php echo number_format($overallAverage, 2); ?></strong>
                        <span>Overall Avg</span>
                    </div>
                    <div class="grade-mini-stat">
                        <strong><?php echo (int)$totalRecordedDays; ?></strong>
                        <span>Counted Days</span>
                    </div>
                </div>
            </div>

            <?php if (!empty($topAverages)): ?>
                <div class="grade-top-grid">
                    <?php
                    $topRank = 1;
                    foreach ($topAverages as $empId => $average):
                        $employeeName = $employee_names[$empId] ?? 'Employee';
                        $totalPts = $employee_points[$empId] ?? 0;
                        $attendanceDays = $employee_attendance_days[$empId] ?? 0;
                    ?>
                        <article class="grade-top-card rank-<?php echo $topRank; ?>">
                            <div class="grade-rank-pill">Top <?php echo $topRank; ?></div>
                            <h4><?php echo htmlspecialchars($employeeName); ?></h4>
                            <div class="grade-score"><?php echo number_format($average, 2); ?></div>
                            <div class="grade-card-meta">
                                <span><?php echo (int)$totalPts; ?> pts</span>
                                <span><?php echo (int)$attendanceDays; ?> days</span>
                            </div>
                        </article>
                    <?php
                        $topRank++;
                    endforeach;
                    ?>
                </div>
            <?php endif; ?>

            <details class="grade-ranking-panel" open>
                <summary>View Complete Ranking</summary>
                <div class="grade-table-wrap">
                    <table class="grade-table">
                        <thead>
                            <tr>
                                <th class="rank-col">Rank</th>
                                <th>Employee Name</th>
                                <th class="numeric-col">Total Points</th>
                                <th class="numeric-col">Counted Days</th>
                                <th class="numeric-col">Average Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($averages as $empId => $average):
                                $employeeName = $employee_names[$empId] ?? 'Employee';
                                $totalPts = $employee_points[$empId] ?? 0;
                                $attendanceDays = $employee_attendance_days[$empId] ?? 0;
                            ?>
                            <tr>
                                <td><span class="grade-rank-badge"><?php echo $rank++; ?></span></td>
                                <td><?php echo htmlspecialchars($employeeName); ?></td>
                                <td class="grade-points numeric-col"><?php echo (int)$totalPts; ?></td>
                                <td class="numeric-col"><?php echo (int)$attendanceDays; ?></td>
                                <td class="grade-points numeric-col"><?php echo number_format($average, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
    </div>
    <?php endif; ?>
    
    <!-- Results Modal -->
    <div id="resultsModal" class="modal month-modal">
        <div class="modal-content results-modal-content">
            <div class="modal-header">
                <div>
                    <span class="modal-kicker">Monthly Report</span>
                    <h3>Attendance Results</h3>
                    <p><?php echo htmlspecialchars($monthName . ' ' . $year); ?> summary, rankings, and printable report.</p>
                </div>
                <span class="close" onclick="closeResultsModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="results-toolbar">
                    <button class="btn btn-primary" onclick="printResults()">
                        Print Report
                    </button>
                    <input type="text" id="employeeSearch" placeholder="Search employee..."
                           onkeyup="filterEmployees()">
                </div>
                <div id="resultsContent" class="results-content">
                    <div class="results-loading">
                        <p>Loading statistics...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Print View (Hidden) -->
    <div id="printView" style="display: none;">
        <div id="printContent"></div>
    </div>
    
    <!-- Add Employee Modal -->
    <div id="addEmployeeModal" class="modal month-modal">
        <div class="modal-content add-employee-modal-content">
            <div class="modal-header">
                <div>
                    <span class="modal-kicker">Employee Setup</span>
                    <h3>Add New Employee</h3>
                    <p>Create a new active employee record for the attendance grid.</p>
                </div>
                <span class="close" onclick="closeAddEmployeeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="addEmployeeMessage" class="message"></div>
                <form id="addEmployeeForm" onsubmit="addEmployee(event)">
                    <div class="form-group">
                        <label for="employeeName">Employee Name</label>
                        <input type="text" id="employeeName" name="employee_name" required
                               placeholder="Enter employee full name">
                        <p class="field-help">Use the complete name as it should appear in monthly attendance reports.</p>
                    </div>
                    <div class="modal-note">
                        New employees will appear in this month and future attendance views after saving.
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddEmployeeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Open Add Employee Modal
        function openAddEmployeeModal() {
            document.getElementById('addEmployeeModal').style.display = 'block';
            document.getElementById('addEmployeeForm').reset();
            document.getElementById('addEmployeeMessage').style.display = 'none';
        }
        
        // Close Add Employee Modal
        function closeAddEmployeeModal() {
            document.getElementById('addEmployeeModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addEmployeeModal');
            const resultsModal = document.getElementById('resultsModal');
            if (event.target == addModal) {
                closeAddEmployeeModal();
            }
            if (event.target == resultsModal) {
                closeResultsModal();
            }
        }
        
        // Open Results Modal
        function openResultsModal() {
            document.getElementById('resultsModal').style.display = 'block';
            loadMonthlyStats();
        }
        
        // Close Results Modal
        function closeResultsModal() {
            document.getElementById('resultsModal').style.display = 'none';
            // Clear search when closing
            const searchInput = document.getElementById('employeeSearch');
            if (searchInput) {
                searchInput.value = '';         
                filterEmployees();  
            }
        }
        
        // Filter Employees
        function filterEmployees() {
            const searchInput = document.getElementById('employeeSearch');
            const filter = searchInput ? searchInput.value.toLowerCase() : '';
            const rows = document.querySelectorAll('#employeesTableBody .employee-row');
            
            let visibleCount = 0;
            rows.forEach((row, index) => {
                const name = row.getAttribute('data-name');
                if (name.includes(filter)) {
                    row.style.display = '';
                    visibleCount++;
                    // Update row number
                    const numCell = row.querySelector('td:first-child');
                    if (numCell) {
                        numCell.textContent = visibleCount;
                    }
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update total count if exists
            const totalDiv = document.querySelector('#employeesTableContainer').nextElementSibling;
            if (totalDiv && totalDiv.textContent.includes('Total:')) {
                totalDiv.innerHTML = `<strong>Showing: ${visibleCount} of ${rows.length} employees</strong>${filter ? ` (filtered by "${searchInput.value}")` : ''}`;
            }
        }
        
        // Store stats data globally for printing
        let monthlyStatsData = null;
        
        // Load Monthly Statistics
        function loadMonthlyStats() {
            const month = <?php echo $month; ?>;
            const year = <?php echo $year; ?>;
            const contentDiv = document.getElementById('resultsContent');
            
            fetch(`get_monthly_stats.php?month=${month}&year=${year}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    monthlyStatsData = data; // Store for printing
                    
                    let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">';
                    html += `<div style="background: #e7f3ff; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: bold; color: #667eea;">${data.total_employees}</div>
                        <div style="color: #666; margin-top: 5px;">Number of Employees</div>
                    </div>`;
                    html += `<div style="background: #fff3cd; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: bold; color: #856404;">${data.total_late}</div>
                        <div style="color: #666; margin-top: 5px;">Tardiness</div>
                    </div>`;
                    html += `<div style="background: #f8d7da; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: bold; color: #721c24;">${data.total_absent}</div>
                        <div style="color: #666; margin-top: 5px;">Actual Absences</div>
                    </div>`;
                    const equivAbsent = data.total_absent_from_lates !== undefined ? data.total_absent_from_lates : 0;
                    html += `<div style="background: #ffe6e6; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: bold; color: #b21f2d;">${equivAbsent}</div>
                        <div style="color: #666; margin-top: 5px;">Equivalent from Late</div>
                        <div style="font-size: 11px; color: #999; margin-top: 3px;">4 late = 1 absent</div>
                    </div>`;
                    const workingDays = data.working_days !== undefined ? data.working_days : (data.effective_days_in_month !== undefined ? data.effective_days_in_month : 22);
                    html += `<div style="background: #d4edda; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 32px; font-weight: bold; color: #155724;">${workingDays}</div>
                        <div style="color: #666; margin-top: 5px;">Working Days</div>
                    </div>`;
                    html += '</div>';
                    
                    // Show all employees with late and absent counts
                    if (data.all_employees && data.all_employees.length > 0) {
                        html += '<h4 style="margin-top: 30px; margin-bottom: 15px; color: #333;">All Employees - Late & Absent Summary</h4>';
                        html += '<div id="employeesTableContainer" style="max-height: 60vh; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px;">';
                        html += '<table id="employeesTable" style="width: 100%; border-collapse: collapse;">';
                        html += '<thead><tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: sticky; top: 0; z-index: 10;">';
                        html += '<th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.3); width: 60px;">No.</th>';
                        html += '<th style="padding: 15px; text-align: left; border-right: 1px solid rgba(255,255,255,0.3); min-width: 250px;">Employee Name</th>';
                        html += '<th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.3); width: 120px; background: rgba(255, 193, 7, 0.3);">Late Count</th>';
                        html += '<th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.3); width: 110px; background: rgba(220, 53, 69, 0.25);">Actual Absent</th>';
                        html += '<th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.3); width: 140px;">Equivalent Absent (from Late)</th>';
                        html += '<th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.3); width: 80px;">OB</th>';
                        html += '<th style="padding: 15px; text-align: center; border-right: 1px solid rgba(255,255,255,0.3); width: 100px;">Total Points</th>';
                        html += '<th style="padding: 15px; text-align: center; width: 120px;">Remaining Tardiness</th>';
                        html += '</tr></thead>';
                        html += '<tbody id="employeesTableBody">';
                        
                        data.all_employees.forEach((emp, index) => {
                            const lateCount = emp.late || 0;
                            const actualAbsentCount = emp.absent || 0;
                            const absentFromLates = emp.absent_from_lates !== undefined ? emp.absent_from_lates : Math.floor(lateCount / 4);
                            const remainingTardiness = emp.remaining_tardiness !== undefined ? emp.remaining_tardiness : (lateCount % 4);
                            const totalPts = emp.total_points !== undefined ? emp.total_points : 0;
                            const obCount = emp.ob || 0;
                            const rowStyle = (index % 2 === 0) ? 'background: #ffffff;' : 'background: #f8f9fa;';
                            
                            html += `<tr class="employee-row" data-name="${emp.name.toLowerCase()}" style="${rowStyle}">`;
                            html += `<td style="padding: 12px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; text-align: center; font-weight: 500;">${index + 1}</td>`;
                            html += `<td style="padding: 12px; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; font-weight: 500; font-size: 14px;">${emp.name}</td>`;
                            html += `<td style="padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; color: ${lateCount > 0 ? '#856404' : '#666'}; font-weight: ${lateCount > 0 ? '700' : '400'}; font-size: 15px;">${lateCount}</td>`;
                            html += `<td style="padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; color: ${actualAbsentCount > 0 ? '#721c24' : '#666'}; font-weight: ${actualAbsentCount > 0 ? '600' : '400'}; font-size: 14px;">${actualAbsentCount}</td>`;
                            html += `<td style="padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; color: ${absentFromLates > 0 ? '#b21f2d' : '#666'}; font-weight: ${absentFromLates > 0 ? '600' : '400'}; font-size: 14px;">${absentFromLates}</td>`;
                            html += `<td style="padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; color: ${obCount > 0 ? '#0369a1' : '#666'}; font-weight: ${obCount > 0 ? '600' : '400'}; font-size: 14px;">${obCount}</td>`;
                            html += `<td style="padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6; border-right: 1px solid #dee2e6; font-weight: 600; color: #0066cc;">${totalPts}</td>`;
                            html += `<td style="padding: 12px; text-align: center; border-bottom: 1px solid #dee2e6; color: ${remainingTardiness > 0 ? '#856404' : '#666'}; font-weight: ${remainingTardiness > 0 ? '600' : '400'}; font-size: 14px;">${remainingTardiness}</td>`;
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                        html += `<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-radius: 6px; text-align: center; color: #004085;">
                            <strong>Total: ${data.all_employees.length} employees</strong>
                        </div>`;
                    } else {
                        html += '<div style="padding: 20px; text-align: center; color: #666;">No employees found.</div>';
                    }
                    
                    contentDiv.innerHTML = html;
                } else {
                    contentDiv.innerHTML = `<div class="message error">${data.message}</div>`;
                }
            })
            .catch(error => {
                contentDiv.innerHTML = '<div class="message error">Error loading statistics. Please try again.</div>';
                console.error('Error:', error);
            });
        }
        
        // Print Results
        function printResults() {
            if (!monthlyStatsData) {
                alert('Please wait for statistics to load.');
                return;
            }
            
            const monthName = '<?php echo $monthName; ?>';
            const year = <?php echo $year; ?>;
            const printDate = new Date().toLocaleDateString();
            
            const effectiveDays = monthlyStatsData.effective_days_in_month !== undefined ? monthlyStatsData.effective_days_in_month : monthlyStatsData.days_in_month;
            const daysInMonth = monthlyStatsData.days_in_month !== undefined ? monthlyStatsData.days_in_month : 31;
            const holidayDates = monthlyStatsData.holiday_dates !== undefined ? monthlyStatsData.holiday_dates : 0;
            const suspendedDates = monthlyStatsData.suspended_dates !== undefined ? monthlyStatsData.suspended_dates : 0;
            let printHTML = '<div class="print-header">';
            printHTML += '<h1>ROMD Attendance System</h1>';
            printHTML += `<h2>Monthly Attendance Report - ${monthName} ${year}</h2>`;
            printHTML += `<p style="margin-top: 10px; font-size: 14px;">Generated on: ${printDate}</p>`;
            printHTML += `<p style="margin-top: 5px; font-size: 12px; color: #666;">Total days in month: ${daysInMonth} (excluding Holiday: ${holidayDates}, Suspended: ${suspendedDates}) = <strong>${effectiveDays} effective working days</strong></p>`;
            printHTML += '</div>';
            
            // Statistics Summary
            printHTML += '<div class="print-stats">';
            printHTML += `<div class="print-stat-box">
                <div class="number">${monthlyStatsData.total_employees}</div>
                <div class="label">Number of Employees</div>
            </div>`;
            printHTML += `<div class="print-stat-box">
                <div class="number">${monthlyStatsData.total_late}</div>
                <div class="label">Tardiness</div>
            </div>`;
            printHTML += `<div class="print-stat-box">
                <div class="number">${monthlyStatsData.total_absent}</div>
                <div class="label">Actual Absences</div>
            </div>`;
            const printEquivAbsent = monthlyStatsData.total_absent_from_lates !== undefined ? monthlyStatsData.total_absent_from_lates : 0;
            printHTML += `<div class="print-stat-box">
                <div class="number">${printEquivAbsent}</div>
                <div class="label">Equivalent from Late</div>
                <div class="label" style="font-size: 10px; opacity: 0.8;">4 late = 1 absent</div>
            </div>`;
            const printWorkingDays = monthlyStatsData.working_days !== undefined ? monthlyStatsData.working_days : (monthlyStatsData.effective_days_in_month !== undefined ? monthlyStatsData.effective_days_in_month : 22);
            printHTML += `<div class="print-stat-box">
                <div class="number">${printWorkingDays}</div>
                <div class="label">Working Days</div>
            </div>`;
            printHTML += '</div>';
            
            // Top 5 Employees by Average Points
            if (monthlyStatsData.all_employees && monthlyStatsData.all_employees.length > 0) {
                // Sort employees by average points (descending)
                const sortedByPoints = [...monthlyStatsData.all_employees].sort((a, b) => {
                    const avgA = a.average_points !== undefined ? a.average_points : 0;
                    const avgB = b.average_points !== undefined ? b.average_points : 0;
                    return avgB - avgA;
                });
                
                // Get top 5
                const top5 = sortedByPoints.slice(0, 5).filter(emp => (emp.average_points !== undefined && emp.average_points > 0));
                
                if (top5.length > 0) {
                    printHTML += '<div class="print-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px; margin: 20px 0; color: white;">';
                    printHTML += '<h3 style="margin-top: 0; color: white; text-align: center;">🏆 Top 5 Employees - Highest Average Points</h3>';
                    printHTML += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">';
                    
                    top5.forEach((emp, index) => {
                        const rank = index + 1;
                        const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : '⭐';
                        const avgPoints = emp.average_points !== undefined ? emp.average_points.toFixed(2) : '0.00';
                        
                        printHTML += `<div style="background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 6px; text-align: center; backdrop-filter: blur(10px);">`;
                        printHTML += `<div style="font-size: 24px; margin-bottom: 8px;">${medal}</div>`;
                        printHTML += `<div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">${emp.name}</div>`;
                        printHTML += `<div style="font-size: 20px; font-weight: bold; color: #ffd700;">${avgPoints} pts</div>`;
                        printHTML += `<div style="font-size: 12px; margin-top: 5px; opacity: 0.9;">Rank #${rank}</div>`;
                        printHTML += `</div>`;
                    });
                    
                    printHTML += '</div>';
                    printHTML += '</div>';
                }
            }
            
            // Find employees with most lates, absences (effective: 4 late = 1 absent), and lowest average points
            let maxLate = 0;
            let maxAbsent = 0;
            let minAveragePoints = Number.POSITIVE_INFINITY;
            let employeesWithMostLate = [];
            let employeesWithMostAbsent = [];
            let employeesWithLowestAverage = [];
            
            if (monthlyStatsData.all_employees && monthlyStatsData.all_employees.length > 0) {
                // Find maximum late and effective absent counts
                monthlyStatsData.all_employees.forEach(emp => {
                    if (emp.late > maxLate) {
                        maxLate = emp.late;
                    }
                    const effectiveAbsent = emp.effective_absent !== undefined ? emp.effective_absent : emp.absent;
                    if (effectiveAbsent > maxAbsent) {
                        maxAbsent = effectiveAbsent;
                    }
                    if (emp.average_points !== undefined) {
                        if (emp.average_points < minAveragePoints) {
                            minAveragePoints = emp.average_points;
                        }
                    }
                });
                
                // Find all employees with max counts (in case of ties)
                monthlyStatsData.all_employees.forEach(emp => {
                    if (emp.late === maxLate && maxLate > 0) {
                        employeesWithMostLate.push(emp.name);
                    }
                    const effectiveAbsent = emp.effective_absent !== undefined ? emp.effective_absent : emp.absent;
                    if (effectiveAbsent === maxAbsent && maxAbsent > 0) {
                        employeesWithMostAbsent.push(emp.name);
                    }
                    if (emp.average_points !== undefined && minAveragePoints !== Number.POSITIVE_INFINITY) {
                        if (emp.average_points === minAveragePoints) {
                            employeesWithLowestAverage.push({
                                name: emp.name,
                                average_points: emp.average_points
                            });
                        }
                    }
                });
                
                // Highlight section for most lates and absences
                if (employeesWithMostLate.length > 0 || employeesWithMostAbsent.length > 0 || employeesWithLowestAverage.length > 0) {
                    printHTML += '<div class="print-section" style="background: #fff3cd; padding: 15px; border: 2px solid #856404; border-radius: 8px; margin: 20px 0;">';
                    printHTML += '<h3 style="margin-top: 0; color: #856404;">⚠️ Highest Attendance Issues</h3>';
                    
                    if (employeesWithMostLate.length > 0) {
                        printHTML += `<p style="margin: 10px 0; font-weight: bold; color: #856404;">`;
                        printHTML += `Most Number of Tardiness: <span style="background: #ffcccc; padding: 3px 8px; border-radius: 4px;">${employeesWithMostLate.join(', ')}</span> `;
                        printHTML += `(${maxLate} ${maxLate === 1 ? 'time' : 'times'})`;
                        printHTML += `</p>`;
                    }
                    
                    if (employeesWithMostAbsent.length > 0) {
                        printHTML += `<p style="margin: 10px 0; font-weight: bold; color: #721c24;">`;
                        printHTML += `Most Number of Absences: <span style="background: #f8d7da; padding: 3px 8px; border-radius: 4px;">${employeesWithMostAbsent.join(', ')}</span> `;
                        printHTML += `(${maxAbsent} ${maxAbsent === 1 ? 'time' : 'times'})`;
                        printHTML += `</p>`;
                    }

                    if (employeesWithLowestAverage.length > 0 && minAveragePoints !== Number.POSITIVE_INFINITY) {
                        const names = employeesWithLowestAverage.map(emp => `${emp.name} (${emp.average_points.toFixed(2)} pts)`);
                        printHTML += `<p style="margin: 10px 0; font-weight: bold; color: #b21f24;">`;
                        printHTML += `Lowest Average Rate: <span style="background: #f5c6cb; padding: 3px 8px; border-radius: 4px;">${names.join(', ')}</span>`;
                        printHTML += `</p>`;
                    }
                    
                    printHTML += '</div>';
                }
                
                // All Employees Attendance Breakdown
                printHTML += '<div class="print-section">';
                printHTML += '<h3>Complete Employee Attendance Summary</h3>';
                printHTML += '<table class="print-table">';
                printHTML += '<thead><tr>';
                printHTML += '<th style="width: 3%;">No.</th>';
                printHTML += '<th style="width: 18%;">Employee Name</th>';
                printHTML += '<th style="width: 6%; text-align: center;">Days Present</th>';
                printHTML += '<th style="width: 6%; text-align: center;">On-Time</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Offset</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Tardiness</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Actual Absent</th>';
                printHTML += '<th style="width: 6%; text-align: center;">Equiv Absent (Late)</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Rem. Tardiness</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Leave</th>';
                printHTML += '<th style="width: 5%; text-align: center;">OB</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Holiday</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Suspended</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Total Days</th>';
                printHTML += '<th style="width: 5%; text-align: center;">Total Pts</th>';
                printHTML += '<th style="width: 6%; text-align: center;">Avg Points</th>';
                printHTML += '<th style="width: 6%; text-align: center;">Status</th>';
                printHTML += '</tr></thead>';
                printHTML += '<tbody>';
                
                // Sort employees by average points (descending), then by name for ties
                const sortedEmployees = [...monthlyStatsData.all_employees].sort((a, b) => {
                    const avgA = a.average_points !== undefined ? a.average_points : 0;
                    const avgB = b.average_points !== undefined ? b.average_points : 0;
                    if (avgB !== avgA) return avgB - avgA;
                    const nameA = (a.name || '').toLowerCase();
                    const nameB = (b.name || '').toLowerCase();
                    return nameA.localeCompare(nameB);
                });
                
                sortedEmployees.forEach((emp, index) => {
                    const totalDays = emp.present + emp.late + emp.absent + emp.offset + (emp.ob || 0);
                    const hasLate = emp.late > 0;
                    const effectiveAbsentVal = emp.effective_absent !== undefined ? emp.effective_absent : emp.absent;
                    const hasAbsent = effectiveAbsentVal > 0;
                    const isMostLate = emp.late === maxLate && maxLate > 0;
                    const isMostAbsent = effectiveAbsentVal === maxAbsent && maxAbsent > 0;
                    
                    // Determine row background color
                    let rowStyle = '';
                    if (isMostLate && isMostAbsent) {
                        rowStyle = 'background-color: #ffe6e6; border-left: 4px solid #dc3545;';
                    } else if (isMostLate) {
                        rowStyle = 'background-color: #fff3cd; border-left: 4px solid #ffc107;';
                    } else if (isMostAbsent) {
                        rowStyle = 'background-color: #f8d7da; border-left: 4px solid #dc3545;';
                    } else if (hasLate || hasAbsent) {
                        rowStyle = 'background-color: #fff9e6;';
                    }
                    
                    // Status indicators
                    let statusBadges = [];
                    if (isMostLate) {
                        statusBadges.push('<span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">MOST TARDY</span>');
                    }
                    if (isMostAbsent) {
                        statusBadges.push('<span style="background: #dc3545; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: bold;">MOST ABSENCES</span>');
                    }
                    if (hasLate && !isMostLate) {
                        statusBadges.push('<span style="background: #ffcccc; color: #856404; padding: 2px 6px; border-radius: 3px; font-size: 10px;">With TARDINESS</span>');
                    }
                    if (hasAbsent && !isMostAbsent) {
                        statusBadges.push('<span style="background: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 3px; font-size: 10px;">With ABSENCE</span>');
                    }
                    if (statusBadges.length === 0) {
                        statusBadges.push('<span style="color: #28a745; font-size: 10px;">✓ Good</span>');
                    }
                    
                    // Days Present = on-time + late + offset (offset included in present)
                    const presentCount = emp.present_total !== undefined ? emp.present_total : ((emp.present || 0) + (emp.late || 0) + (emp.offset || 0));
                    const absentFromLates = emp.absent_from_lates !== undefined ? emp.absent_from_lates : Math.floor((emp.late || 0) / 4);
                    const remainingTardiness = emp.remaining_tardiness !== undefined ? emp.remaining_tardiness : ((emp.late || 0) % 4);
                    
                    printHTML += `<tr style="${rowStyle}">`;
                    printHTML += `<td style="text-align: center; font-weight: ${(isMostLate || isMostAbsent) ? 'bold' : 'normal'};">${index + 1}</td>`;
                    printHTML += `<td style="font-weight: ${(isMostLate || isMostAbsent) ? 'bold' : 'normal'};">
                        ${emp.name}
                        ${(isMostLate || isMostAbsent) ? ' ⚠️' : ''}
                    </td>`;
                    printHTML += `<td style="text-align: center; color: #155724; font-weight: bold; font-size: 14px;">${presentCount}</td>`;
                    printHTML += `<td style="text-align: center; color: #155724; font-weight: 600;">${emp.present || 0}</td>`;
                    printHTML += `<td style="text-align: center; color: #666; font-weight: 600; font-size: 13px;">${emp.offset || 0}</td>`;
                    const actualAbsentVal = emp.absent || 0;
                    printHTML += `<td style="text-align: center; color: ${isMostLate ? '#dc3545' : '#856404'}; font-weight: ${isMostLate ? 'bold' : '600'}; font-size: ${isMostLate ? '16px' : '14px'};">${emp.late || 0}</td>`;
                    printHTML += `<td style="text-align: center; color: ${actualAbsentVal > 0 ? '#721c24' : '#666'}; font-weight: 600; font-size: 13px;">${actualAbsentVal}</td>`;
                    printHTML += `<td style="text-align: center; color: ${absentFromLates > 0 ? '#b21f2d' : '#666'}; font-weight: 600; font-size: 13px;">${absentFromLates}</td>`;
                    printHTML += `<td style="text-align: center; color: ${remainingTardiness > 0 ? '#856404' : '#666'}; font-weight: 600; font-size: 13px;">${remainingTardiness}</td>`;
                    printHTML += `<td style="text-align: center; color: #666; font-weight: 600; font-size: 13px;">${emp.leave || 0}</td>`;
                    printHTML += `<td style="text-align: center; color: #666; font-weight: 600; font-size: 13px;">${emp.ob || 0}</td>`;
                    printHTML += `<td style="text-align: center; color: #666; font-weight: 600; font-size: 13px;">${emp.holiday || 0}</td>`;
                    printHTML += `<td style="text-align: center; color: #666; font-weight: 600; font-size: 13px;">${emp.suspended || 0}</td>`;
                    printHTML += `<td style="text-align: center; font-weight: bold;">${totalDays}</td>`;
                    const totalPts = emp.total_points !== undefined ? emp.total_points : 0;
                    const avgPoints = emp.average_points !== undefined ? emp.average_points.toFixed(2) : '0.00';
                    printHTML += `<td style="text-align: center; font-weight: bold; color: #0066cc;">${totalPts}</td>`;
                    printHTML += `<td style="text-align: center; font-weight: bold; color: #0066cc;">${avgPoints}</td>`;
                    printHTML += `<td style="text-align: center;">${statusBadges.join(' ')}</td>`;
                    printHTML += `</tr>`;
                });
                
                printHTML += '</tbody></table>';
                printHTML += '</div>';
                
                // Summary of employees with issues
                const employeesWithIssues = monthlyStatsData.all_employees.filter(emp => {
                    const effAbs = emp.effective_absent !== undefined ? emp.effective_absent : emp.absent;
                    return emp.late > 0 || effAbs > 0;
                });
                if (employeesWithIssues.length > 0) {
                    printHTML += '<div class="print-section" style="margin-top: 30px;">';
                    printHTML += '<h3>Employees with Late or Absent Records</h3>';
                    printHTML += '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">';
                    
                    // Late employees - sorted by late count (descending)
                    const lateEmployees = employeesWithIssues.filter(emp => emp.late > 0)
                        .sort((a, b) => (b.late || 0) - (a.late || 0));
                    if (lateEmployees.length > 0) {
                        printHTML += '<div style="border: 1px solid #ffc107; padding: 15px; border-radius: 6px; background: #fff9e6;">';
                        printHTML += '<h4 style="margin-top: 0; color: #856404;">Employees with Tardiness Records</h4>';
                        printHTML += '<ul style="margin: 0; padding-left: 20px;">';
                        lateEmployees.forEach(emp => {
                            const badge = emp.late === maxLate ? ' 💩 MOST' : '';
                            const lateLabel = (emp.late === 1) ? 'late' : 'lates';
                            printHTML += `<li style="margin: 5px 0;">${emp.name}: <strong>${emp.late}</strong> ${lateLabel}${badge}</li>`;
                        });
                        printHTML += '</ul>';
                        printHTML += '</div>';
                    }
                    
                    // Absent employees - sorted by effective absent count (descending), 4 late = 1 absent
                    const absentEmployees = employeesWithIssues.filter(emp => {
                        const eff = emp.effective_absent !== undefined ? emp.effective_absent : emp.absent;
                        return eff > 0;
                    }).sort((a, b) => {
                        const effA = a.effective_absent !== undefined ? a.effective_absent : a.absent;
                        const effB = b.effective_absent !== undefined ? b.effective_absent : b.absent;
                        return (effB || 0) - (effA || 0);
                    });
                    if (absentEmployees.length > 0) {
                        printHTML += '<div style="border: 1px solid #dc3545; padding: 15px; border-radius: 6px; background: #f8d7da;">';
                        printHTML += '<h4 style="margin-top: 0; color: #721c24;">Employees with Absence Records (4 late = 1 absent)</h4>';
                        printHTML += '<ul style="margin: 0; padding-left: 20px;">';
                        absentEmployees.forEach(emp => {
                            const effAbs = emp.effective_absent !== undefined ? emp.effective_absent : emp.absent;
                            const badge = effAbs === maxAbsent ? ' 💩 MOST' : '';
                            printHTML += `<li style="margin: 5px 0;">${emp.name}: <strong>${effAbs}</strong> absent${badge}</li>`;
                        });
                        printHTML += '</ul>';
                        printHTML += '</div>';
                    }
                    
                    printHTML += '</div>';
                    printHTML += '</div>';
                }
            } else {
                printHTML += '<div class="print-section">';
                printHTML += '<h3>Employee Attendance Summary</h3>';
                printHTML += '<p style="padding: 20px; text-align: center;">No employees found.</p>';
                printHTML += '</div>';
            }
            
            // Footer
            printHTML += '<div class="print-footer">';
            printHTML += '<p>This is a computer-generated report from ROMD Attendance System</p>';
            printHTML += `<p>Report Period: ${monthName} ${year} &nbsp;|&nbsp; Effective working days: ${effectiveDays}</p>`;
            printHTML += '</div>';
            
            // Create a complete HTML document for printing
            const fullPrintHTML = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Report - ${monthName} ${year}</title>
    <style>
        @media print {
            @page {
                margin: 1cm;
            }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
        }
        .print-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #333;
        }
        .print-header h2 {
            font-size: 20px;
            color: #666;
        }
        .print-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin: 30px 0;
        }
        .print-stat-box {
            border: 2px solid #333;
            padding: 15px;
            text-align: center;
        }
        .print-stat-box .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .print-stat-box .label {
            font-size: 14px;
            text-transform: uppercase;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .print-table th,
        .print-table td {
            border: 1px solid #333;
            padding: 10px;
            text-align: left;
        }
        .print-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .print-table td {
            text-align: center;
        }
        .print-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .print-section {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        .print-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
        }
        .print-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #333;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    ${printHTML}
</body>
</html>`;
            
            // Open print window
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            if (!printWindow) {
                alert('Please allow popups to print the report.');
                return;
            }
            
            printWindow.document.write(fullPrintHTML);
            printWindow.document.close();
            
            // Wait for content to load, then print
            setTimeout(() => {
                printWindow.focus();
                printWindow.print();
                // Note: Window will close automatically after user prints or cancels
            }, 500);
        }
        
        // Add Employee
        function addEmployee(event) {
            event.preventDefault();
            const form = document.getElementById('addEmployeeForm');
            const formData = new FormData(form);
            const messageDiv = document.getElementById('addEmployeeMessage');
            
            // Show loading state
            messageDiv.className = 'message';
            messageDiv.textContent = 'Adding employee...';
            messageDiv.style.display = 'block';
            
            fetch('add_employee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        messageDiv.className = 'message success';
                        messageDiv.textContent = data.message;
                        messageDiv.style.display = 'block';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        messageDiv.className = 'message error';
                        messageDiv.textContent = data.message || 'Failed to add employee';
                        messageDiv.style.display = 'block';
                    }
                } catch (e) {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = 'Server error: ' + text.substring(0, 200);
                    messageDiv.style.display = 'block';
                    console.error('Response text:', text);
                }
            })
            .catch(error => {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'An error occurred: ' + error.message + '. Please check if the database tables exist.';
                messageDiv.style.display = 'block';
                console.error('Error:', error);
            });
        }
        
        // Edit Cell (Time-in)
        function editCell(cell) {
            const employeeId = cell.getAttribute('data-employee-id');
            const date = cell.getAttribute('data-date');
            const currentTime = cell.getAttribute('data-time') || '';
            const currentStatus = cell.getAttribute('data-status') || '';
            const currentWfh = cell.getAttribute('data-wfh') === '1';
            const callTime = cell.getAttribute('data-call-time') || '08:00';
            
            // Store original values for cancel
            const originalTime = currentTime;
            const originalStatus = currentStatus;
            const originalWfh = currentWfh ? '1' : '0';
            const originalContent = cell.innerHTML;
            const originalClassName = cell.className;
            
            // Create a container with both time input and status select
            const container = document.createElement('div');
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '5px';
            
            // Time input
            const input = document.createElement('input');
            input.type = 'time';
            input.className = 'editable-input';
            input.value = currentTime ? (currentTime.length === 5 ? currentTime + ':00' : currentTime) : '';
            input.placeholder = 'Time In';
            
            // Status select
            const select = document.createElement('select');
            select.className = 'status-select';
            const statusOptions = [
                { value: 'clear', label: '-- Clear / Empty --' },
                { value: 'present', label: 'Present (On Time)' },
                { value: 'late', label: 'Late' },
                { value: 'absent', label: 'Absent' },
                { value: 'offset', label: 'Offset' },
                { value: 'leave', label: 'Leave' },
                { value: 'ob', label: 'Official Business (OB)' },
                { value: 'holiday', label: 'Holiday' },
                { value: 'suspended', label: 'Suspended' }
            ];
            
            let selectHTML = '';
            statusOptions.forEach(option => {
                const isSelected = currentStatus ? (currentStatus === option.value) : (option.value === 'clear');
                selectHTML += `<option value="${option.value}" ${isSelected ? 'selected' : ''}>${option.label}</option>`;
            });
            select.innerHTML = selectHTML;

            const wfhLabel = document.createElement('label');
            wfhLabel.className = 'wfh-toggle';
            const wfhCheckbox = document.createElement('input');
            wfhCheckbox.type = 'checkbox';
            wfhCheckbox.checked = currentWfh;
            const wfhText = document.createElement('span');
            wfhText.textContent = 'WFH setup';
            wfhLabel.appendChild(wfhCheckbox);
            wfhLabel.appendChild(wfhText);
            wfhLabel.onclick = function(e) {
                e.stopPropagation();
            };
            wfhCheckbox.onchange = function(e) {
                e.stopPropagation();
                if (saveTimeout) clearTimeout(saveTimeout);
                saveTimeout = setTimeout(save, 100);
            };

            const updateWfhAvailability = function() {
                const statusWithoutTime = ['absent', 'offset', 'leave', 'ob', 'holiday', 'suspended', 'clear'].includes(select.value);
                const hasTime = Boolean(input.value);
                wfhCheckbox.disabled = statusWithoutTime || !hasTime;
                if (wfhCheckbox.disabled) {
                    wfhCheckbox.checked = false;
                }
            };
            
            // Prevent dropdown from closing when clicking
            select.onmousedown = function(e) {
                e.stopPropagation();
            };
            select.onclick = function(e) {
                e.stopPropagation();
            };
            
            // Auto-update status when time is entered
            const handleTimeChange = function() {
                const timeValue = input.value;
                // If time is entered and status is absent/offset/leave/OB/clear, change to present
                if (timeValue && ['absent', 'offset', 'leave', 'ob', 'holiday', 'suspended', 'clear'].includes(select.value)) {
                    select.value = 'present';
                }
                // Check if time is late (after configured call time)
                if (timeValue) {
                    const timeParts = timeValue.split(':');
                    const hour = parseInt(timeParts[0]);
                    const minute = parseInt(timeParts[1]);
                    const callParts = callTime.split(':');
                    const callHour = parseInt(callParts[0]);
                    const callMinute = parseInt(callParts[1]);
                    if (hour > callHour || (hour === callHour && minute > callMinute)) {
                        select.value = 'late';
                    } else if (select.value === 'late') {
                        // If time is not late but status is late, change to present
                        select.value = 'present';
                    }
                }
                updateWfhAvailability();
            };
            input.onchange = handleTimeChange;
            input.oninput = function() {
                if (!input.value && !['offset', 'leave', 'ob', 'holiday', 'suspended'].includes(select.value)) {
                    select.value = 'clear';
                }
                updateWfhAvailability();
            };

            // Save function
            let isSaving = false;
            const save = function() {
                if (isSaving) return;
                isSaving = true;
                
                let timeValue = input.value;
                let statusValue = select.value;
                
                timeValue = timeValue ? timeValue : '';
                
                // Handle clear option
                if (statusValue === 'clear') {
                    timeValue = '';
                    statusValue = 'clear';
                    wfhCheckbox.checked = false;
                } 
                // Handle status options that don't require time (absent, offset, leave, OB, holiday, suspended)
                else if (statusValue === 'absent' || statusValue === 'offset' || statusValue === 'leave' || statusValue === 'ob' || statusValue === 'holiday' || statusValue === 'suspended') {
                    timeValue = '';
                    wfhCheckbox.checked = false;
                    // Keep the selected status as-is - don't override it
                }
                // If time is entered, determine if it's late or present
                else if (timeValue) {
                    const timeParts = timeValue.split(':');
                    const hour = parseInt(timeParts[0]);
                    const minute = parseInt(timeParts[1]);
                    const callParts = callTime.split(':');
                    const callHour = parseInt(callParts[0]);
                    const callMinute = parseInt(callParts[1]);
                    // Only override status if it's present or late
                    if (statusValue === 'present' || statusValue === 'late') {
                        statusValue = (hour > callHour || (hour === callHour && minute > callMinute)) ? 'late' : 'present';
                    }
                }
                // If no time and present/late selected, default to absent
                else if (!timeValue && (statusValue === 'present' || statusValue === 'late')) {
                    statusValue = 'absent';
                }
                
                const isWfh = timeValue && wfhCheckbox.checked ? 1 : 0;
                saveCell(employeeId, date, timeValue, statusValue, isWfh, cell);
                
                setTimeout(() => { isSaving = false; }, 500);
            };
            
            // Update behavior based on status selection - save immediately when status changes
            select.onchange = function(e) {
                e.stopPropagation();
                const value = select.value;
                if (value === 'clear') {
                    input.value = '';
                } else if (value === 'absent' || value === 'offset' || value === 'leave' || value === 'ob' || value === 'holiday' || value === 'suspended') {
                    input.value = '';
                }
                updateWfhAvailability();
                // Save immediately when status is changed (for all status types)
                // Clear any pending save timeout
                if (saveTimeout) clearTimeout(saveTimeout);
                // Save immediately with a small delay to ensure the value is set
                setTimeout(() => {
                    save();
                }, 50);
            };
            
            // Use setTimeout to prevent immediate blur when clicking select
            let saveTimeout;
            input.onblur = function() {
                // Only save on blur if status hasn't been explicitly changed
                if (select.value === 'present' || select.value === 'late' || select.value === 'clear') {
                    saveTimeout = setTimeout(save, 500);
                }
            };
            // Don't save on select blur - let onchange handle it
            select.onblur = function() {
                // Do nothing - let onchange handle saving
            };
            input.onfocus = function() {
                if (saveTimeout) clearTimeout(saveTimeout);
            };
            select.onfocus = function() {
                if (saveTimeout) clearTimeout(saveTimeout);
            };
            
            input.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (saveTimeout) clearTimeout(saveTimeout);
                    save();
                } else if (e.key === 'Escape') {
                    if (saveTimeout) clearTimeout(saveTimeout);
                    // Restore original content (including points badge)
                    cell.innerHTML = originalContent;
                    cell.className = originalClassName;
                    cell.setAttribute('data-time', originalTime);
                    cell.setAttribute('data-status', originalStatus);
                    cell.setAttribute('data-wfh', originalWfh);
                }
            };
            select.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (saveTimeout) clearTimeout(saveTimeout);
                    save();
                } else if (e.key === 'Escape') {
                    if (saveTimeout) clearTimeout(saveTimeout);
                    // Restore original content (including points badge)
                    cell.innerHTML = originalContent;
                    cell.className = originalClassName;
                    cell.setAttribute('data-time', originalTime);
                    cell.setAttribute('data-status', originalStatus);
                    cell.setAttribute('data-wfh', originalWfh);
                }
            };
            
            container.appendChild(input);
            container.appendChild(select);
            container.appendChild(wfhLabel);
            cell.innerHTML = '';
            cell.appendChild(container);
            updateWfhAvailability();
            input.focus();
        }
        
        // Save Cell
        function calculateCellPoints(status, time, dateStr) {
            if (!status) return '';
            if (status === 'absent') return -1;
            if (status === 'offset' || status === 'ob') return 3;
            if (status === 'leave' || status === 'holiday' || status === 'suspended') return 0;
            if (!time) return '';
            const parts = time.split(':');
            if (parts.length < 2) return '';
            const hour = parseInt(parts[0], 10);
            const minute = parseInt(parts[1], 10);
            if (Number.isNaN(hour) || Number.isNaN(minute)) return '';
            const minutes = (hour * 60) + minute;
            const year = dateStr ? parseInt(dateStr.substring(0, 4), 10) : new Date().getFullYear();

            if (year >= 2026) {
                // 2026 onwards: 6:00-6:44=5, 6:45-7:45=4, 7:46-8:00=3, 8:01-8:15=2, 8:16+=1
                if (minutes <= (6 * 60 + 44)) return 5;
                if (minutes <= (7 * 60 + 45)) return 4;
                if (minutes <= (8 * 60)) return 3;
                if (minutes <= (8 * 60 + 15)) return 2;
                return 1;
            }

            // Before 2026
            if (minutes <= (7 * 60 + 44)) return 5;
            if (minutes <= (7 * 60 + 59)) return 4;
            if (minutes >= (8 * 60) && minutes <= (8 * 60 + 29)) return 3;
            if (minutes === (8 * 60 + 30)) return 2;
            return 1;
        }

        function setCellDisplayWithPoints(cell, mainText, points, isWfh = false) {
            cell.innerHTML = '';
            if (points !== '' && points !== null && points !== undefined) {
                const p = document.createElement('span');
                p.className = 'cell-points';
                p.textContent = String(points);
                cell.appendChild(p);
            }
            if (isWfh) {
                const wfh = document.createElement('span');
                wfh.className = 'cell-wfh';
                wfh.textContent = 'WFH';
                cell.appendChild(wfh);
            }
            if (mainText) cell.appendChild(document.createTextNode(mainText));
        }

        function saveCell(employeeId, date, time, status, isWfh, cell) {
            const formData = new FormData();
            formData.append('employee_id', employeeId);
            formData.append('date', date);
            formData.append('time_in', time);
            formData.append('status', status);
            formData.append('is_wfh', isWfh ? '1' : '0');
            
            // Debug logging
            console.log('Saving attendance:', { employeeId, date, time, status, isWfh });
            
            fetch('update_attendance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    // Get the actual status returned from server (may have been auto-corrected)
                    const actualStatus = data.status || status;
                    const actualTime = data.time_in || time;
                    const actualIsWfh = data.is_wfh === 1 || data.is_wfh === '1';
                    
                    // Holiday/Suspended are applied to all employees for this date - reload to show every cell
                    if (actualStatus === 'holiday' || actualStatus === 'suspended') {
                        location.reload();
                        return;
                    }
                    
                    // Update cell display based on actual status from server
                    const points = calculateCellPoints(actualStatus, actualTime ? actualTime.substring(0, 5) : '', date);
                    if (actualStatus === 'offset') {
                        setCellDisplayWithPoints(cell, 'Offset', points);
                        cell.setAttribute('data-status', 'offset');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell status-offset editable-cell';
                    } else if (actualStatus === 'leave') {
                        setCellDisplayWithPoints(cell, 'Leave', points);
                        cell.setAttribute('data-status', 'leave');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell status-leave editable-cell';
                    } else if (actualStatus === 'ob') {
                        setCellDisplayWithPoints(cell, 'OB', points);
                        cell.setAttribute('data-status', 'ob');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell status-ob editable-cell';
                    } else if (actualStatus === 'holiday') {
                        setCellDisplayWithPoints(cell, '', points);
                        cell.setAttribute('data-status', 'holiday');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell status-holiday editable-cell';
                    } else if (actualStatus === 'suspended') {
                        setCellDisplayWithPoints(cell, '', points);
                        cell.setAttribute('data-status', 'suspended');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell status-suspended editable-cell';
                    } else if (actualStatus === 'absent') {
                        setCellDisplayWithPoints(cell, 'Absent', points);
                        cell.setAttribute('data-status', 'absent');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell status-absent editable-cell';
                    } else if (actualStatus === 'late') {
                        const timeDisplay = actualTime ? actualTime.substring(0, 5) : '';
                        setCellDisplayWithPoints(cell, timeDisplay, points, actualIsWfh);
                        cell.setAttribute('data-time', timeDisplay);
                        cell.setAttribute('data-status', 'late');
                        cell.setAttribute('data-wfh', actualIsWfh ? '1' : '0');
                        cell.className = 'time-cell time-late editable-cell';
                    } else if (actualTime) {
                        const timeDisplay = actualTime.substring(0, 5); // HH:MM format
                        setCellDisplayWithPoints(cell, timeDisplay, points, actualIsWfh);
                        cell.setAttribute('data-time', timeDisplay);
                        cell.setAttribute('data-status', 'present');
                        cell.setAttribute('data-wfh', actualIsWfh ? '1' : '0');
                        cell.className = 'time-cell time-normal editable-cell';
                    } else {
                        setCellDisplayWithPoints(cell, '', '');
                        cell.setAttribute('data-time', '');
                        cell.setAttribute('data-status', '');
                        cell.setAttribute('data-wfh', '0');
                        cell.className = 'time-cell empty-cell editable-cell';
                    }
                } else {
                    alert('Error: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                location.reload();
            });
        }
    </script>
<?php include 'includes/footer.php'; ?>
