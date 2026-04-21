<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$employee_id = (int)($_POST['employee_id'] ?? 0);
$date = $_POST['date'] ?? '';
$time_in = $_POST['time_in'] ?? '';
$status = $_POST['status'] ?? 'present';

if ($employee_id <= 0 || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID or date']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Validate status
$validStatuses = ['present', 'absent', 'offset', 'leave', 'late', 'holiday', 'suspended', 'clear'];
if (!in_array($status, $validStatuses)) {
    $status = 'present';
}

// Store original status for special handling
$originalStatus = $status;
$clearEntry = ($status === 'clear');
if ($clearEntry) {
    $status = '';
}

// If time is provided, process it and determine status
if (!empty($time_in)) {
    // Ensure time format is correct (HH:MM:SS)
    if (strlen($time_in) === 5) {
        $time_in .= ':00';
    }
    
    // If time is entered, status should be present or late (not absent/offset/leave)
    // Override absent/offset/leave status if time is provided
    if (in_array($originalStatus, ['absent', 'offset', 'leave', 'holiday', 'suspended'])) {
        $status = 'present'; // Change to present, will check if late below
    }
    
    // Automatically mark as 'late' if time is LATER than 8:00 AM (not equal to 8:00)
    $time_parts = explode(':', $time_in);
    $hour = (int)$time_parts[0];
    $minute = (int)$time_parts[1];
    
    // Late if hour > 8 OR (hour == 8 AND minute > 0)
    // This means 8:00 AM is NOT late, but 8:01 AM and later IS late
    if ($hour > 8 || ($hour == 8 && $minute > 0)) {
        $status = 'late';
    } else {
        // If not late, set to present
        $status = 'present';
    }
} else {
    // No time provided - respect the selected status
    if (in_array($originalStatus, ['offset', 'absent', 'leave', 'holiday', 'suspended'])) {
        // Keep the original status as-is, just clear the time
        $status = $originalStatus;
        $time_in = null;
    } elseif ($originalStatus === 'present' || $originalStatus === 'late') {
        // If present/late selected but no time, change to absent
        $status = 'absent';
        $time_in = null;
    } elseif ($originalStatus === 'clear' || $originalStatus === '') {
        // Clear entry
        $status = '';
        $time_in = null;
    }
}

try {
    $conn = getDBConnection();
    
    // Check if attendance_dtr table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'attendance_dtr'");
    if ($table_check === false || $table_check->num_rows == 0) {
        // Create attendance_dtr table (schema mirrors attendance)
        $create_table_sql = "CREATE TABLE IF NOT EXISTS attendance_dtr (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME,
            time_out TIME,
            status ENUM('present', 'absent', 'offset', 'leave', 'late', 'holiday', 'suspended') DEFAULT 'present',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance_dtr (employee_id, attendance_date),
            INDEX idx_date (attendance_date),
            INDEX idx_employee_date (employee_id, attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_table_sql) === FALSE) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create attendance_dtr table: ' . $conn->error]);
            exit();
        }
    }
    
    // Handle clearing entry (delete record)
    if ($clearEntry) {
        $delete_stmt = $conn->prepare("DELETE FROM attendance_dtr WHERE employee_id = ? AND attendance_date = ?");
        $delete_stmt->bind_param("is", $employee_id, $date);
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $conn->close();
            echo json_encode([
                'success' => true,
                'message' => 'DTR attendance cleared successfully',
                'status' => '',
                'time_in' => null
            ]);
            exit();
        } else {
            $error = $conn->error;
            $delete_stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to clear DTR attendance: ' . $error]);
            exit();
        }
    }
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to ensure atomic operation
    $upsert_stmt = $conn->prepare("
        INSERT INTO attendance_dtr (employee_id, attendance_date, time_in, status, updated_at) 
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            time_in = ?,
            status = ?,
            updated_at = NOW()
    ");
    $upsert_stmt->bind_param("isssss", $employee_id, $date, $time_in, $status, $time_in, $status);
    
    if ($upsert_stmt->execute()) {
        // For DTR we do NOT broadcast holiday/suspended to all employees; it’s individual only.
        
        // Get the actual saved status from database to ensure it's correct
        $check_stmt = $conn->prepare("SELECT status, time_in FROM attendance_dtr WHERE employee_id = ? AND attendance_date = ?");
        $check_stmt->bind_param("is", $employee_id, $date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $saved_record = $check_result->fetch_assoc();
        $check_stmt->close();
        
        $upsert_stmt->close();
        $conn->close();
        
        $final_status = $saved_record ? $saved_record['status'] : $status;
        $final_time = $saved_record ? $saved_record['time_in'] : $time_in;
        
        echo json_encode([
            'success' => true, 
            'message' => 'DTR attendance saved successfully',
            'status' => $final_status,
            'time_in' => $final_time
        ]);
    } else {
        $error_msg = $conn->error;
        $upsert_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save DTR attendance: ' . $error_msg]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
