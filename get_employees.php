<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    $result = $conn->query("SELECT id, employee_name FROM employees WHERE status = 'active' ORDER BY employee_name");
    if ($result === false) {
        throw new Exception($conn->error);
    }
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = [
            'id' => (int)$row['id'],
            'name' => $row['employee_name']
        ];
    }
    $conn->close();
    echo json_encode(['success' => true, 'employees' => $employees]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
