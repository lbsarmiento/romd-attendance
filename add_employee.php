<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$employee_name = trim($_POST['employee_name'] ?? '');

if (empty($employee_name)) {
    echo json_encode(['success' => false, 'message' => 'Employee name is required']);
    exit();
}

try {
    $conn = getDBConnection();

    // Check if employees table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'employees'");
    if ($table_check === false || $table_check->num_rows == 0) {
        $create_table_sql = "CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_name VARCHAR(100) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if ($conn->query($create_table_sql) === FALSE) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create employees table: ' . $conn->error]);
            exit();
        }
    }

    // Check if employee name already exists
    $check_stmt = $conn->prepare("SELECT id FROM employees WHERE employee_name = ?");
    if ($check_stmt === false) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    $check_stmt->bind_param("s", $employee_name);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $check_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Employee already exists']);
        exit();
    }
    $check_stmt->close();

    // Insert new employee
    $insert_stmt = $conn->prepare("INSERT INTO employees (employee_name, status) VALUES (?, 'active')");
    if ($insert_stmt === false) {
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    $insert_stmt->bind_param("s", $employee_name);

    if ($insert_stmt->execute()) {
        $insert_stmt->close();
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
    } else {
        $error_msg = $insert_stmt->error;
        $insert_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to add employee: ' . $error_msg]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
