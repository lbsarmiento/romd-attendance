<?php
/**
 * Create tasks table for employee daily tasks
 */
require_once 'config.php';
requireLogin();

try {
    $conn = getDBConnection();
    
    // Create tasks table
    $sql = "CREATE TABLE IF NOT EXISTS tasks (
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
    
    if ($conn->query($sql) === TRUE) {
        echo "Tasks table created successfully!";
    } else {
        echo "Error creating tasks table: " . $conn->error;
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

