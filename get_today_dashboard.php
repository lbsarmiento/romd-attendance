<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$today = date('Y-m-d');

try {
    $conn = getDBConnection();
    
    // Check if tasks table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'tasks'");
    if ($table_check === false || $table_check->num_rows == 0) {
        // Create tasks table
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
            // Continue even if table creation fails (might be permission issue)
            error_log("Failed to create tasks table: " . $conn->error);
        }
    }
    
    // Get employees who checked in today
    $checkin_query = "SELECT 
        e.id,
        e.employee_name,
        a.time_in,
        a.status as attendance_status,
        CASE 
            WHEN a.status = 'absent' THEN 'absent'
            WHEN a.time_in IS NOT NULL AND (HOUR(a.time_in) > 8 OR (HOUR(a.time_in) = 8 AND MINUTE(a.time_in) > 0)) THEN 'late'
            WHEN a.status = 'late' THEN 'late'
            WHEN a.status = 'present' AND a.time_in IS NOT NULL THEN 'present'
            WHEN a.status = 'offset' THEN 'offset'
            WHEN a.status = 'leave' THEN 'leave'
            ELSE 'not_checked_in'
        END as actual_status
        FROM employees e
        LEFT JOIN attendance a ON e.id = a.employee_id AND a.attendance_date = ?
        WHERE e.status = 'active'
        ORDER BY 
            CASE 
                WHEN a.status = 'absent' THEN 0
                WHEN a.time_in IS NOT NULL THEN 1
                ELSE 2
            END,
            a.time_in ASC";
    
    $stmt = $conn->prepare($checkin_query);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $employees = [];
    $checkedInCount = 0;
    $absentCount = 0;
    $absentEmployees = [];
    $notCheckedIn = [];
    
    while ($row = $result->fetch_assoc()) {
        $hasCheckedIn = !empty($row['time_in']);
        $isAbsent = ($row['attendance_status'] === 'absent' || $row['actual_status'] === 'absent');
        
        if ($hasCheckedIn) {
            $checkedInCount++;
        }
        
        if ($isAbsent) {
            $absentCount++;
        }
        
        // Get tasks for this employee today
        $tasks_query = "SELECT task_description, status 
                       FROM tasks 
                       WHERE employee_id = ? AND task_date = ?
                       ORDER BY created_at ASC";
        $tasks_stmt = $conn->prepare($tasks_query);
        $tasks_stmt->bind_param("is", $row['id'], $today);
        $tasks_stmt->execute();
        $tasks_result = $tasks_stmt->get_result();
        
        $tasks = [];
        while ($task_row = $tasks_result->fetch_assoc()) {
            $tasks[] = [
                'description' => $task_row['task_description'],
                'status' => $task_row['status']
            ];
        }
        $tasks_stmt->close();
        
        $employee_data = [
            'id' => (int)$row['id'],
            'name' => $row['employee_name'],
            'time_in' => $row['time_in'],
            'status' => $row['actual_status'] ?: ($isAbsent ? 'absent' : 'not_checked_in'),
            'tasks' => $tasks
        ];
        
        if ($isAbsent) {
            $absentEmployees[] = $employee_data;
        } elseif ($hasCheckedIn) {
            $employees[] = $employee_data;
        } else {
            $notCheckedIn[] = $employee_data;
        }
    }
    
    // Combine all employees: checked in, absent, and not checked in
    $allEmployees = array_merge($employees, $absentEmployees, $notCheckedIn);
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'date' => $today,
        'checked_in_count' => $checkedInCount,
        'absent_count' => $absentCount,
        'total_employees' => count($allEmployees),
        'employees' => $allEmployees,
        'absent_employees' => $absentEmployees
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

