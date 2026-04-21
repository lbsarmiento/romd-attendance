<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$employee_id = (int)($_POST['employee_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$resigned_at = trim($_POST['resigned_at'] ?? '');

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

if (!in_array($status, ['active', 'inactive'], true)) {
    echo json_encode(['success' => false, 'message' => 'Status must be active or inactive']);
    exit();
}

// Optional date resigned: YYYY-MM-DD (only used when status = inactive)
$resigned_at_value = null;
if ($status === 'inactive' && $resigned_at !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $resigned_at);
    if ($d && $d->format('Y-m-d') === $resigned_at) {
        $resigned_at_value = $resigned_at;
    }
}

try {
    $conn = getDBConnection();

    // Ensure resigned_at column exists
    $check = $conn->query("SHOW COLUMNS FROM employees LIKE 'resigned_at'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE employees ADD COLUMN resigned_at DATE NULL DEFAULT NULL AFTER status");
    }
    if ($check) {
        $check->free();
    }

    if ($status === 'active') {
        $stmt = $conn->prepare("UPDATE employees SET status = ?, resigned_at = NULL WHERE id = ?");
        $stmt->bind_param("si", $status, $employee_id);
    } else {
        $stmt = $conn->prepare("UPDATE employees SET status = ?, resigned_at = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $resigned_at_value, $employee_id);
    }

    if ($stmt === false) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        if ($affected > 0) {
            $action = $status === 'inactive' ? 'archived' : 'restored';
            echo json_encode(['success' => true, 'message' => "Employee {$action} successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee not found or no change.']);
        }
    } else {
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $err]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
