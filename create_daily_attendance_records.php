<?php
/**
 * This script creates attendance records at the end of the day for employees
 * who have tasks assigned but no attendance record for that day.
 * 
 * This can be run manually or scheduled via cron job to run daily at end of day.
 * Example cron: 0 23 * * * /path/to/php /path/to/create_daily_attendance_records.php
 */

require_once 'config.php';
requireLogin();

// Get the date (default to today, but can be set to yesterday for end-of-day processing)
$target_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    die("Invalid date format. Use YYYY-MM-DD format.\n");
}

try {
    $conn = getDBConnection();
    
    // Check if attendance table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
    if ($table_check === false || $table_check->num_rows == 0) {
        // Create attendance table
        $create_table_sql = "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME,
            time_out TIME,
            status ENUM('present', 'absent', 'offset', 'leave', 'late') DEFAULT 'present',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance (employee_id, attendance_date),
            INDEX idx_date (attendance_date),
            INDEX idx_employee_date (employee_id, attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->query($create_table_sql);
    }
    
    // Get all employees who have tasks assigned for the target date
    // but don't have an attendance record for that date
    $query = "SELECT DISTINCT e.id, e.employee_name, t.task_date
              FROM employees e
              INNER JOIN tasks t ON e.id = t.employee_id
              LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = t.task_date
              WHERE e.status = 'active'
              AND t.task_date = ?
              AND a.id IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $target_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $created_count = 0;
    $errors = [];
    
    while ($row = $result->fetch_assoc()) {
        $employee_id = $row['id'];
        $employee_name = $row['employee_name'];
        
        // Create attendance record with 'absent' status (since they didn't check in)
        // This can be updated later if they check in
        $insert_query = "INSERT INTO attendance (employee_id, attendance_date, status) 
                        VALUES (?, ?, 'absent')
                        ON DUPLICATE KEY UPDATE status = status";
        
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("is", $employee_id, $target_date);
        
        if ($insert_stmt->execute()) {
            $created_count++;
            echo "✓ Created attendance record for {$employee_name} on {$target_date}\n";
        } else {
            $errors[] = "Failed to create record for {$employee_name}: " . $conn->error;
            echo "✗ Error creating record for {$employee_name}: " . $conn->error . "\n";
        }
        
        $insert_stmt->close();
    }
    
    $stmt->close();
    $conn->close();
    
    echo "\n=== Summary ===\n";
    echo "Date: {$target_date}\n";
    echo "Records created: {$created_count}\n";
    if (count($errors) > 0) {
        echo "Errors: " . count($errors) . "\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    } else {
        echo "All records created successfully!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

