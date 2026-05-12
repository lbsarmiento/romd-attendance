<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

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
    if (count($time_parts) < 2) return 0;
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

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if ($year < 2000 || $year > 2100) $year = date('Y');
$period = $_GET['period'] ?? 'full';
$start_month = 1;
$end_month = 12;
$period_label = 'Full Year';

if ($period === 'h1') {
    $start_month = 1;
    $end_month = 6;
    $period_label = 'January - June';
} elseif ($period === 'h2') {
    $start_month = 7;
    $end_month = 12;
    $period_label = 'July - December';
} elseif ($period === 'custom') {
    $requested_start = isset($_GET['start_month']) ? (int)$_GET['start_month'] : 1;
    $requested_end = isset($_GET['end_month']) ? (int)$_GET['end_month'] : 12;
    if ($requested_start >= 1 && $requested_start <= 12 && $requested_end >= 1 && $requested_end <= 12) {
        $start_month = min($requested_start, $requested_end);
        $end_month = max($requested_start, $requested_end);
    }
} elseif (preg_match('/^\d{1,2}$/', (string)$period)) {
    $selected_month = (int)$period;
    if ($selected_month >= 1 && $selected_month <= 12) {
        $start_month = $selected_month;
        $end_month = $selected_month;
    }
}

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee.']);
    exit;
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

    $stmt = $conn->prepare("SELECT employee_name FROM employees WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
        exit;
    }
    $employee_name = $res->fetch_assoc()['employee_name'];
    $stmt->close();

    $month_names = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    if ($start_month === $end_month) {
        $period_label = $month_names[$start_month];
    } elseif ($period === 'custom') {
        $period_label = $month_names[$start_month] . ' - ' . $month_names[$end_month];
    }

    $start_date = sprintf('%04d-%02d-01', $year, $start_month);
    $end_date = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $end_month)));

    $att_stmt = $conn->prepare("SELECT attendance_date, status, time_in FROM attendance WHERE employee_id = ? AND attendance_date >= ? AND attendance_date <= ? ORDER BY attendance_date");
    $att_stmt->bind_param("iss", $employee_id, $start_date, $end_date);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();
    $rows = [];
    while ($r = $att_result->fetch_assoc()) {
        $rows[] = $r;
    }
    $att_stmt->close();

    $call_times_by_date = [];
    $ct_stmt = $conn->prepare("SELECT call_date, call_time FROM call_times WHERE call_date >= ? AND call_date <= ?");
    if ($ct_stmt) {
        $ct_stmt->bind_param("ss", $start_date, $end_date);
        if ($ct_stmt->execute()) {
            $ct_result = $ct_stmt->get_result();
            while ($ct = $ct_result->fetch_assoc()) {
                $call_times_by_date[$ct['call_date']] = substr($ct['call_time'], 0, 5);
            }
        }
        $ct_stmt->close();
    }

    $hs_result = $conn->query("SELECT DISTINCT attendance_date FROM attendance WHERE status IN ('holiday','suspended') AND attendance_date >= '" . $conn->real_escape_string($start_date) . "' AND attendance_date <= '" . $conn->real_escape_string($end_date) . "'");
    $holiday_suspended_by_month = array_fill(1, 12, 0);
    if ($hs_result) {
        while ($r = $hs_result->fetch_assoc()) {
            $ts = strtotime($r['attendance_date']);
            $m = (int)date('n', $ts);
            $dow = (int)date('N', $ts);
            if ($dow >= 1 && $dow <= 5) {
                $holiday_suspended_by_month[$m]++;
            }
        }
    }

    $months = [];
    for ($m = $start_month; $m <= $end_month; $m++) {
        $m_start = sprintf('%04d-%02d-01', $year, $m);
        $m_end = date('Y-m-t', strtotime($m_start));
        $ts = strtotime($m_start);
        $end_ts = strtotime($m_end);
        $weekday_count = 0;
        while ($ts <= $end_ts) {
            if ((int)date('N', $ts) <= 5) $weekday_count++;
            $ts = strtotime('+1 day', $ts);
        }
        $working_days = max(0, $weekday_count - ($holiday_suspended_by_month[$m] ?? 0));

        $present = $late = $absent = $offset = $ob = $leave = $holiday = $suspended = 0;
        $points_sum = 0;
        $points_count = 0;
        foreach ($rows as $r) {
            $d = $r['attendance_date'];
            if (substr($d, 0, 4) != $year || (int)substr($d, 5, 2) != $m) continue;
            $st = $r['status'];
            $ti = $r['time_in'];
            if ($st === 'absent') { $absent++; $points_sum -= 1; $points_count++; continue; }
            if ($st === 'offset') { $offset++; $points_sum += 3; $points_count++; continue; }
            if ($st === 'ob') { $ob++; $points_sum += 3; $points_count++; continue; }
            if ($st === 'leave') { $leave++; continue; }
            if ($st === 'holiday') { $holiday++; continue; }
            if ($st === 'suspended') { $suspended++; continue; }
            if (!empty($ti) && $ti !== '00:00:00') {
                $call_time = $call_times_by_date[$d] ?? '08:00';
                $arrival_minutes = ((int)substr($ti, 0, 2) * 60) + (int)substr($ti, 3, 2);
                $call_minutes = ((int)substr($call_time, 0, 2) * 60) + (int)substr($call_time, 3, 2);
                if ($st === 'late' || $arrival_minutes > $call_minutes) $late++;
                else $present++;
                $points_sum += calculateGradePoints($st, $ti, $d);
                $points_count++;
            }
        }
        $absent_from_lates = (int)floor($late / 4);
        $remaining_tardiness = $late % 4;
        $points_sum -= $absent_from_lates;
        $present_total = $present + $late + $offset;
        $total_points = $points_sum;
        $average_points = $points_count > 0 ? round($points_sum / $points_count, 2) : 0;

        $months[] = [
            'month' => $m,
            'month_name' => $month_names[$m],
            'present' => $present,
            'present_total' => $present_total,
            'late' => $late,
            'absent' => $absent,
            'absent_from_lates' => $absent_from_lates,
            'remaining_tardiness' => $remaining_tardiness,
            'offset' => $offset,
            'ob' => $ob,
            'leave' => $leave,
            'holiday' => $holiday,
            'suspended' => $suspended,
            'working_days' => $working_days,
            'total_points' => $total_points,
            'attendance_days' => $points_count,
            'average_points' => $average_points
        ];
    }

    $conn->close();
    echo json_encode([
        'success' => true,
        'employee_id' => $employee_id,
        'employee_name' => $employee_name,
        'year' => $year,
        'period' => $period,
        'period_label' => $period_label,
        'start_month' => $start_month,
        'end_month' => $end_month,
        'months' => $months
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
