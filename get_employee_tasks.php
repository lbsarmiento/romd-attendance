<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$task_date = $_GET['task_date'] ?? date('Y-m-d');

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $task_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

try {
    $conn = getDBConnection();
    
    // Get employee name
    $emp_stmt = $conn->prepare("SELECT employee_name FROM employees WHERE id = ?");
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    
    if ($emp_result->num_rows === 0) {
        $emp_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit();
    }
    
    $employee = $emp_result->fetch_assoc();
    $emp_stmt->close();
    
    // Get tasks for this employee on this date
    $tasks_query = "SELECT id, task_description, status, created_at 
                   FROM tasks 
                   WHERE employee_id = ? AND task_date = ?
                   ORDER BY created_at ASC";
    $tasks_stmt = $conn->prepare($tasks_query);
    $tasks_stmt->bind_param("is", $employee_id, $task_date);
    $tasks_stmt->execute();
    $tasks_result = $tasks_stmt->get_result();
    
    $tasks = [];
    while ($row = $tasks_result->fetch_assoc()) {
        $tasks[] = [
            'id' => (int)$row['id'],
            'description' => $row['task_description'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
    $tasks_stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'employee_id' => $employee_id,
        'employee_name' => $employee['employee_name'],
        'task_date' => $task_date,
        'tasks' => $tasks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

