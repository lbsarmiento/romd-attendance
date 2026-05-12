<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// Function to calculate grade points based on arrival time and status
// Optional $attendanceDate (Y-m-d) used to apply 2026+ time bands; before 2026 uses previous bands.
function calculateGradePoints($status, $time, $attendanceDate = null) {
    if ($status === 'leave' || $status === 'holiday' || $status === 'suspended') {
        return 0;
    }
    // Offset and Official Business are equivalent to 3 points.
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
        if ($minutes <= (6 * 60 + 44)) { // 6:00 - 6:44
            return 5;
        } elseif ($minutes <= (7 * 60 + 45)) { // 6:45 - 7:45
            return 4;
        } elseif ($minutes <= (8 * 60)) { // 7:46 - 8:00
            return 3;
        } elseif ($minutes <= (8 * 60 + 15)) { // 8:01 - 8:15
            return 2;
        } else { // 8:16 and later
            return 1;
        }
    }
    //6:00 - 6:44 = 5 points
    //6:45 - 7:45 = 4 points
    //7:46 - 8:00 = 3 points
    //8:01 - 8:15 = 2 points
    //8:16 and later = 1 point

    
    // Before 2026: previous time bands
    if ($minutes <= (7 * 60 + 44)) { // 7:00 - 7:44
        return 5;
    } elseif ($minutes <= (7 * 60 + 59)) { // 7:45 - 7:59
        return 4;
    } elseif ($minutes >= (8 * 60) && $minutes <= (8 * 60 + 29)) { // 8:00 - 8:29
        return 3;
    } elseif ($minutes == (8 * 60 + 30)) { // 8:30
        return 2;
    } else { // 8:31 and later
        return 1;
    }
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Support date range (e.g. for 6-month Jul-Dec report)
$use_range = isset($_GET['start_date']) && isset($_GET['end_date']);
if ($use_range) {
    $start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : null;
    if (!$start_date || !$end_date || strtotime($start_date) > strtotime($end_date)) {
        $use_range = false;
    }
}
if (!$use_range) {
    // Validate month and year
    if ($month < 1 || $month > 12) {
        $month = date('n');
    }
    if ($year < 2000 || $year > 2100) {
        $year = date('Y');
    }
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
}

try {
    $conn = getDBConnection();

    $conn->query("CREATE TABLE IF NOT EXISTS call_times (
        id INT AUTO_INCREMENT PRIMARY KEY,
        call_date DATE NOT NULL UNIQUE,
        call_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_call_date (call_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
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
    } else {
        // Ensure existing table has all supported statuses in ENUM
        @$conn->query("ALTER TABLE attendance MODIFY COLUMN status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present'");
    }
    
    // Get total employees
    $total_employees = 0;
    $emp_result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    if ($emp_result !== false) {
        $total_employees = $emp_result->fetch_assoc()['count'];
    }
    
    // Get attendance statistics
    // Count as late if: status = 'late' OR time_in is after 8:00 AM (even if status is not 'late')
    // Count as present: any record with time_in OR status = 'offset' (offset counts as present)
    // Count as absent: only status = 'absent' (blank records count as 0)
    $stats_query = "SELECT 
        COUNT(CASE WHEN a.status = 'late' OR (
            a.time_in IS NOT NULL
            AND a.time_in != '00:00:00'
            AND a.time_in != ''
            AND TIME(a.time_in) > COALESCE(ct.call_time, '08:00:00')
        ) THEN 1 END) as total_late,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as total_absent,
        COUNT(CASE WHEN a.status = 'ob' THEN 1 END) as total_ob,
        COUNT(CASE WHEN (a.time_in IS NOT NULL AND a.time_in != '00:00:00' AND a.time_in != '') OR a.status = 'offset' THEN 1 END) as total_present
        FROM attendance a
        LEFT JOIN call_times ct ON ct.call_date = a.attendance_date
        WHERE a.attendance_date >= ? AND a.attendance_date <= ?";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    
    $total_late = (int)$stats['total_late'];
    $total_absent = (int)$stats['total_absent'];
    $total_ob = (int)$stats['total_ob'];
    $total_present = (int)$stats['total_present'];
    
    // Get late employees with counts
    // Count as late if: status = 'late' OR time_in is after 8:00 AM (even if status is not 'late')
    $late_query = "SELECT e.employee_name, COUNT(*) as late_count
                   FROM attendance a
                   JOIN employees e ON a.employee_id = e.id
                   LEFT JOIN call_times ct ON ct.call_date = a.attendance_date
                   WHERE a.attendance_date >= ? AND a.attendance_date <= ?
                   AND (a.status = 'late' OR (
                        a.time_in IS NOT NULL
                        AND a.time_in != '00:00:00'
                        AND a.time_in != ''
                        AND TIME(a.time_in) > COALESCE(ct.call_time, '08:00:00')
                   ))
                   GROUP BY e.id, e.employee_name
                   ORDER BY late_count DESC, e.employee_name";
    
    $late_stmt = $conn->prepare($late_query);
    $late_stmt->bind_param("ss", $start_date, $end_date);
    $late_stmt->execute();
    $late_result = $late_stmt->get_result();
    
    $late_employees = [];
    while ($row = $late_result->fetch_assoc()) {
        $late_employees[] = [
            'name' => $row['employee_name'],
            'count' => (int)$row['late_count']
        ];
    }
    $late_stmt->close();
    
    // Get absent employees with counts
    $absent_query = "SELECT e.employee_name, COUNT(*) as absent_count
                     FROM attendance a
                     JOIN employees e ON a.employee_id = e.id
                     WHERE a.attendance_date >= ? AND a.attendance_date <= ?
                     AND a.status = 'absent'
                     GROUP BY e.id, e.employee_name
                     ORDER BY absent_count DESC, e.employee_name";
    
    $absent_stmt = $conn->prepare($absent_query);
    $absent_stmt->bind_param("ss", $start_date, $end_date);
    $absent_stmt->execute();
    $absent_result = $absent_stmt->get_result();
    
    $absent_employees = [];
    while ($row = $absent_result->fetch_assoc()) {
        $absent_employees[] = [
            'name' => $row['employee_name'],
            'count' => (int)$row['absent_count']
        ];
    }
    $absent_stmt->close();
    
    // Get all employees with complete attendance breakdown
    // Count as late if: status = 'late' OR time_in is after 8:00 AM (even if status is not 'late')
    // Count as present: any record with time_in (regardless of late status) OR status = 'offset'
    // Count as absent: only when status = 'absent' (blank records count as 0)
    $all_employees_query = "SELECT 
    e.id,
    e.employee_name,

    COUNT(CASE 
        WHEN a.time_in IS NOT NULL 
        AND a.time_in != '00:00:00' 
        AND a.time_in != '' 
        AND a.status != 'late'
        AND NOT (TIME(a.time_in) > COALESCE(ct.call_time, '08:00:00'))
        THEN 1 
    END) as on_time_count,

    COUNT(CASE 
        WHEN a.time_in IS NOT NULL 
        AND a.time_in != '00:00:00' 
        AND a.time_in != '' 
        AND (a.status = 'late' OR TIME(a.time_in) > COALESCE(ct.call_time, '08:00:00'))
        THEN 1 
    END) as late_count,

    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count,
    COUNT(CASE WHEN a.status = 'offset' THEN 1 END) as offset_count,
    COUNT(CASE WHEN a.status = 'ob' THEN 1 END) as ob_count,
    COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as leave_count,
    COUNT(CASE WHEN a.status = 'holiday' THEN 1 END) as holiday_count,
    COUNT(CASE WHEN a.status = 'suspended' THEN 1 END) as suspended_count

    FROM employees e
    LEFT JOIN attendance a 
        ON e.id = a.employee_id 
        AND a.attendance_date >= ? 
        AND a.attendance_date <= ?
    LEFT JOIN call_times ct ON ct.call_date = a.attendance_date
    WHERE e.status = 'active'
    GROUP BY e.id, e.employee_name
    ORDER BY e.employee_name";

    
    $all_emp_stmt = $conn->prepare($all_employees_query);
    $all_emp_stmt->bind_param("ss", $start_date, $end_date);
    $all_emp_stmt->execute();
    $all_emp_result = $all_emp_stmt->get_result();
    
    $all_employees = [];
    $total_absent_from_lates = 0;
    while ($row = $all_emp_result->fetch_assoc()) {
        $on_time = (int)$row['on_time_count']; // Includes offset (counted as present)
        $late = (int)$row['late_count'];
        $offset = (int)$row['offset_count'];
        $ob = (int)$row['ob_count'];
        $absent = (int)$row['absent_count']; // Only explicit 'absent' status (blank = 0)
        // 4 lates = 1 absent (for reporting)
        $absent_from_lates = (int)floor($late / 4);
        $remaining_tardiness = $late % 4; // lates not yet converted to absent (e.g. 10 late → 2 absent, 2 remaining)
        $effective_absent = $absent + $absent_from_lates;
        $total_absent_from_lates += $absent_from_lates;
        // Present = anyone with time_in (on-time + late) OR offset status
        $present_total = $on_time + $late + $offset;
        
        $all_employees[] = [
            'id' => (int)$row['id'],
            'name' => $row['employee_name'],
            'present' => $on_time,
            'present_total' => $present_total,
            'late' => $late,
            'absent' => $absent,
            'absent_from_lates' => $absent_from_lates,
            'remaining_tardiness' => $remaining_tardiness,
            'effective_absent' => $effective_absent,
            'offset' => $offset,
            'ob' => $ob,
            'leave' => (int)$row['leave_count'],
            'holiday' => (int)$row['holiday_count'],
            'suspended' => (int)$row['suspended_count']
        ];
    }
    $all_emp_stmt->close();
    
    // Effective days in month = calendar days minus days with holiday/suspended (distinct dates)
    $holiday_dates_result = $conn->query("SELECT COUNT(DISTINCT attendance_date) as c FROM attendance WHERE status = 'holiday' AND attendance_date >= '" . $conn->real_escape_string($start_date) . "' AND attendance_date <= '" . $conn->real_escape_string($end_date) . "'");
    $suspended_dates_result = $conn->query("SELECT COUNT(DISTINCT attendance_date) as c FROM attendance WHERE status = 'suspended' AND attendance_date >= '" . $conn->real_escape_string($start_date) . "' AND attendance_date <= '" . $conn->real_escape_string($end_date) . "'");
    $holiday_dates = ($holiday_dates_result && $row = $holiday_dates_result->fetch_assoc()) ? (int)$row['c'] : 0;
    $suspended_dates = ($suspended_dates_result && $row = $suspended_dates_result->fetch_assoc()) ? (int)$row['c'] : 0;
    $days_in_month_cal = $use_range ? (int)((strtotime($end_date) - strtotime($start_date)) / 86400) + 1 : cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $effective_days_in_month = max(0, $days_in_month_cal - $holiday_dates - $suspended_dates);
    
    // Working days = weekdays (Mon–Fri) in range, minus holiday/suspended dates that fall on weekdays
    $weekday_count = 0;
    $ts = strtotime($start_date);
    $end_ts = strtotime($end_date);
    while ($ts <= $end_ts) {
        $dow = (int)date('N', $ts); // 1=Mon .. 7=Sun
        if ($dow >= 1 && $dow <= 5) $weekday_count++;
        $ts = strtotime('+1 day', $ts);
    }
    $holiday_suspended_dates_result = $conn->query("SELECT DISTINCT attendance_date FROM attendance WHERE status IN ('holiday','suspended') AND attendance_date >= '" . $conn->real_escape_string($start_date) . "' AND attendance_date <= '" . $conn->real_escape_string($end_date) . "'");
    $non_work_dates_on_weekdays = 0;
    if ($holiday_suspended_dates_result) {
        while ($row = $holiday_suspended_dates_result->fetch_assoc()) {
            $dow = (int)date('N', strtotime($row['attendance_date']));
            if ($dow >= 1 && $dow <= 5) $non_work_dates_on_weekdays++;
        }
    }
    $working_days = max(0, $weekday_count - $non_work_dates_on_weekdays);
    
    // Calculate average grade points for each employee
    // Fetch all attendance records with time_in for the month
    $points_query = "SELECT 
        a.employee_id,
        a.attendance_date,
        a.status,
        a.time_in
        FROM attendance a
        WHERE a.attendance_date >= ? AND a.attendance_date <= ?
        ORDER BY a.employee_id, a.attendance_date";
    
    $points_stmt = $conn->prepare($points_query);
    $points_stmt->bind_param("ss", $start_date, $end_date);
    $points_stmt->execute();
    $points_result = $points_stmt->get_result();
    
    // Calculate total points and attendance days for each employee
    $employee_points = [];
    $employee_attendance_days = [];
    
    while ($row = $points_result->fetch_assoc()) {
        $emp_id = (int)$row['employee_id'];
        if (!isset($employee_points[$emp_id])) {
            $employee_points[$emp_id] = 0;
            $employee_attendance_days[$emp_id] = 0;
        }
        $status = $row['status'] ?? '';
        // Do not include leave/holiday/suspended in average divisor (they don't contribute to score)
        if ($status === 'leave' || $status === 'holiday' || $status === 'suspended') {
            continue;
        }

        $points = calculateGradePoints($status, $row['time_in'], $row['attendance_date']);
        $employee_points[$emp_id] += $points;
        // Deduct 1 point for each recorded absence
        if ($status === 'absent') {
            $employee_points[$emp_id] -= 1;
        }
        $employee_attendance_days[$emp_id]++;
    }
    // 4 lates = 1 absent: deduct 1 point per 4 lates (per employee)
    foreach ($all_employees as $emp) {
        $emp_id = $emp['id'];
        $absent_from_lates = $emp['absent_from_lates'] ?? 0;
        if ($absent_from_lates > 0 && isset($employee_points[$emp_id])) {
            $employee_points[$emp_id] -= $absent_from_lates;
        }
    }
    $points_stmt->close();
    
    // Add average points to each employee
    foreach ($all_employees as &$emp) {
        $emp_id = $emp['id'];
        $total_points = $employee_points[$emp_id] ?? 0;
        $attendance_days = $employee_attendance_days[$emp_id] ?? 0;
        $average_points = $attendance_days > 0 ? round($total_points / $attendance_days, 2) : 0;
        $emp['average_points'] = $average_points;
        $emp['total_points'] = $total_points;
    }
    unset($emp);
    
    $stmt->close();
    $conn->close();
    
    $total_effective_absent = $total_absent + $total_absent_from_lates;
    $report_period = null;
    if ($use_range) {
        $report_period = date('F j', strtotime($start_date)) . ' - ' . date('F j, Y', strtotime($end_date));
    }
    echo json_encode([
        'success' => true,
        'total_employees' => $total_employees,
        'total_late' => $total_late,
        'total_absent' => $total_absent,
        'total_ob' => $total_ob,
        'total_absent_from_lates' => $total_absent_from_lates,
        'total_effective_absent' => $total_effective_absent,
        'total_present' => $total_present,
        'late_employees' => $late_employees,
        'absent_employees' => $absent_employees,
        'all_employees' => $all_employees,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'report_period' => $report_period,
        'days_in_month' => $days_in_month_cal,
        'holiday_dates' => $holiday_dates,
        'suspended_dates' => $suspended_dates,
        'effective_days_in_month' => $effective_days_in_month,
        'working_days' => $working_days
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

