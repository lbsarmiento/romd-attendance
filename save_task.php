<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// Allow CORS preflight or bail out on non-POST
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Preflight OK']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? 'add'; // add, update, delete
$employee_id = (int)($_POST['employee_id'] ?? 0);
$task_date = $_POST['task_date'] ?? date('Y-m-d');
$task_description = trim($_POST['task_description'] ?? '');
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$status = $_POST['status'] ?? 'pending';

if ($employee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $task_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Validate status
if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
    $status = 'pending';
}

try {
    $conn = getDBConnection();
    
    // Check if tasks table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'tasks'");
    if ($table_check === false || $table_check->num_rows == 0) {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            task_date DATE NOT NULL,
            task_description TEXT NOT NULL,
            status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_employee_date (employee_id, task_date),
            INDEX idx_date (task_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_table_sql) === FALSE) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create tasks table: ' . $conn->error]);
            exit();
        }
    }
    
    if ($action === 'add') {
        if (empty($task_description)) {
            echo json_encode(['success' => false, 'message' => 'Task description is required']);
            exit();
        }
        
        $insert_stmt = $conn->prepare("INSERT INTO tasks (employee_id, task_date, task_description, status) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("isss", $employee_id, $task_date, $task_description, $status);
        
        if ($insert_stmt->execute()) {
            $task_id = $conn->insert_id;
            $insert_stmt->close();
            $conn->close();
            echo json_encode([
                'success' => true,
                'message' => 'Task added successfully',
                'task_id' => $task_id
            ]);
        } else {
            $error_msg = $conn->error;
            $insert_stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to add task: ' . $error_msg]);
        }
    } elseif ($action === 'update') {
        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }
        
        if (empty($task_description)) {
            echo json_encode(['success' => false, 'message' => 'Task description is required']);
            exit();
        }
        
        $update_stmt = $conn->prepare("UPDATE tasks SET task_description = ?, status = ? WHERE id = ? AND employee_id = ?");
        $update_stmt->bind_param("ssii", $task_description, $status, $task_id, $employee_id);
        
        if ($update_stmt->execute()) {
            $update_stmt->close();
            $conn->close();
            echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
        } else {
            $error_msg = $conn->error;
            $update_stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to update task: ' . $error_msg]);
        }
    } elseif ($action === 'delete') {
        if ($task_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit();
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND employee_id = ?");
        $delete_stmt->bind_param("ii", $task_id, $employee_id);
        
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $conn->close();
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
        } else {
            $error_msg = $conn->error;
            $delete_stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to delete task: ' . $error_msg]);
        }
    } else {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

