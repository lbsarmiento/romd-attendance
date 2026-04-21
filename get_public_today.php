<?php
require_once 'config.php';

header('Content-Type: application/json');

$requestedDate = $_GET['date'] ?? date('Y-m-d');
$parsedDate = DateTime::createFromFormat('Y-m-d', $requestedDate);
$targetDate = ($parsedDate && $parsedDate->format('Y-m-d') === $requestedDate)
    ? $requestedDate
    : date('Y-m-d');

try {
    $conn = getDBConnection();

    $query = "SELECT
        e.id,
        e.employee_name,
        a.time_in,
        a.status AS attendance_status,
        CASE
            WHEN a.status = 'absent' THEN 'absent'
            WHEN a.status = 'offset' THEN 'offset'
            WHEN a.status = 'leave' THEN 'leave'
            WHEN a.status = 'holiday' THEN 'holiday'
            WHEN a.status = 'suspended' THEN 'suspended'
            WHEN a.time_in IS NOT NULL AND a.time_in <> '' AND (HOUR(a.time_in) > 8 OR (HOUR(a.time_in) = 8 AND MINUTE(a.time_in) > 0)) THEN 'late'
            WHEN a.status = 'late' THEN 'late'
            WHEN a.time_in IS NOT NULL AND a.time_in <> '' THEN 'present'
            ELSE 'not_checked_in'
        END AS public_status
    FROM employees e
    LEFT JOIN attendance a
        ON e.id = a.employee_id
        AND a.attendance_date = ?
    WHERE e.status = 'active'
    ORDER BY
        CASE
            WHEN a.time_in IS NOT NULL AND a.time_in <> '' THEN 0
            WHEN a.status = 'offset' THEN 1
            WHEN a.status = 'leave' THEN 2
            WHEN a.status = 'holiday' THEN 3
            WHEN a.status = 'suspended' THEN 4
            WHEN a.status = 'absent' THEN 5
            ELSE 6
        END,
        a.time_in ASC,
        e.employee_name ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $targetDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => (int) $row['id'],
            'name' => $row['employee_name'],
            'time_in' => $row['time_in'],
            'status' => $row['public_status'],
            'attendance_status' => $row['attendance_status']
        ];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'date' => $targetDate,
        'generated_at' => date('c'),
        'employees' => $employees
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load public time-in data.'
    ]);
}
?>
