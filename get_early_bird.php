<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD.']);
    exit;
}

try {
    $conn = getDBConnection();

    // Early Bird = time_in at or before 8:00 AM (same as dashboard: not late)
    // Late = HOUR > 8 OR (HOUR = 8 AND MINUTE > 0)
    $query = "SELECT
        e.id,
        e.employee_name AS name,
        a.time_in
        FROM employees e
        INNER JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ?
        WHERE e.status = 'active'
        AND a.time_in IS NOT NULL
        AND a.time_in != ''
        AND a.time_in != '00:00:00'
        AND NOT (HOUR(a.time_in) > 8 OR (HOUR(a.time_in) = 8 AND MINUTE(a.time_in) > 0))
        ORDER BY a.time_in ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $early_bird_list = [];
    while ($row = $result->fetch_assoc()) {
        $early_bird_list[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'time_in' => $row['time_in'] ? substr($row['time_in'], 0, 5) : ''
        ];
    }
    $stmt->close();

    // Total checked in that day (with any time_in) for context
    $count_stmt = $conn->prepare("SELECT COUNT(DISTINCT e.id) AS total
        FROM employees e
        INNER JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ?
        WHERE e.status = 'active' AND a.time_in IS NOT NULL AND a.time_in != '' AND a.time_in != '00:00:00'");
    $count_stmt->bind_param('s', $date);
    $count_stmt->execute();
    $total_checked_in = (int) $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $total_employees = (int) $conn->query("SELECT COUNT(*) AS c FROM employees WHERE status = 'active'")->fetch_assoc()['c'];
    $conn->close();

    echo json_encode([
        'success' => true,
        'date' => $date,
        'early_bird_count' => count($early_bird_list),
        'early_bird_list' => $early_bird_list,
        'total_checked_in' => $total_checked_in,
        'total_employees' => $total_employees
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
