<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$start_date_input = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
$end_date_input = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

function isValidDateString($value) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $parts = explode('-', $value);
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee.']);
    exit;
}
if (!isValidDateString($start_date_input) || !isValidDateString($end_date_input)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
    exit;
}
if (strtotime($start_date_input) > strtotime($end_date_input)) {
    echo json_encode(['success' => false, 'message' => 'Start date must be on or before end date.']);
    exit;
}

try {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT employee_name FROM employees WHERE id = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee_result = $stmt->get_result();
    if (!$employee_result || $employee_result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Employee not found.']);
        exit;
    }
    $employee = $employee_result->fetch_assoc();
    $stmt->close();

    $start_date = $start_date_input;
    $end_date = $end_date_input;

    $attendance = [];
    $table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
    if ($table_check !== false && $table_check->num_rows > 0) {
        $attendance_stmt = $conn->prepare("
            SELECT attendance_date, status, time_in
            FROM attendance
            WHERE employee_id = ? AND attendance_date >= ? AND attendance_date <= ?
            ORDER BY attendance_date
        ");
        if (!$attendance_stmt) {
            throw new Exception($conn->error);
        }
        $attendance_stmt->bind_param("iss", $employee_id, $start_date, $end_date);
        $attendance_stmt->execute();
        $attendance_result = $attendance_stmt->get_result();
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance[] = [
                'attendance_date' => $row['attendance_date'],
                'status' => $row['status'] ?? '',
                'time_in' => $row['time_in']
            ];
        }
        $attendance_stmt->close();
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'employee_id' => $employee_id,
        'employee_name' => $employee['employee_name'],
        'start_date' => $start_date,
        'end_date' => $end_date,
        'attendance' => $attendance
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
