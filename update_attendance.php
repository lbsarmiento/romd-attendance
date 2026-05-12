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
$is_wfh = isset($_POST['is_wfh']) && $_POST['is_wfh'] === '1' ? 1 : 0;

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
$validStatuses = ['present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended', 'clear'];
if (!in_array($status, $validStatuses)) {
    $status = 'present';
}

// Store original status for special handling
$originalStatus = $status;
$clearEntry = ($status === 'clear');
if ($clearEntry) {
    $status = '';
}

// Helper: get call time for a specific date (from call_times table), default 08:00
function getCallTimeForDateFromDb(mysqli $conn, string $date): string {
    $default = '08:00';
    $stmt = $conn->prepare("SELECT call_time FROM call_times WHERE call_date = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('s', $date);
    if (!$stmt->execute()) {
        $stmt->close();
        return $default;
    }
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stmt->close();
        return substr($row['call_time'], 0, 5);
    }
    $stmt->close();
    return $default;
}

// If time is provided, process it and determine status
if (!empty($time_in)) {
    // Ensure time format is correct (HH:MM:SS)
    if (strlen($time_in) === 5) {
        $time_in .= ':00';
    }
    
    // If time is entered, status should be present or late (not absent/offset/leave)
    // Override absent/offset/leave status if time is provided
    if (in_array($originalStatus, ['absent', 'offset', 'leave', 'ob', 'holiday', 'suspended'])) {
        $status = 'present'; // Change to present, will check if late below
    }

    // Get call time for this date (default 08:00 if none set)
    $conn_for_call = getDBConnection();
    $callTime = getCallTimeForDateFromDb($conn_for_call, $date);
    $conn_for_call->close();

    // Automatically mark as 'late' if time is LATER than the call time (not equal)
    $time_parts = explode(':', $time_in);
    $hour = (int)$time_parts[0];
    $minute = (int)$time_parts[1];

    $call_parts = explode(':', $callTime);
    $callHour = (int)$call_parts[0];
    $callMinute = (int)$call_parts[1];

    $arrivalMinutes = $hour * 60 + $minute;
    $callMinutes = $callHour * 60 + $callMinute;

    if ($arrivalMinutes > $callMinutes) {
        $status = 'late';
    } else {
        // If not late, set to present
        $status = 'present';
    }
} else {
    // No time provided - respect the selected status
    if (in_array($originalStatus, ['offset', 'absent', 'leave', 'ob', 'holiday', 'suspended'])) {
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

if (empty($time_in) || in_array($status, ['absent', 'offset', 'leave', 'ob', 'holiday', 'suspended', ''], true)) {
    $is_wfh = 0;
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
            is_wfh TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance (employee_id, attendance_date),
            INDEX idx_date (attendance_date),
            INDEX idx_employee_date (employee_id, attendance_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_table_sql) === FALSE) {
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create attendance table: ' . $conn->error]);
            exit();
        }
    } else {
        // Ensure existing installations can save Official Business entries.
        @$conn->query("ALTER TABLE attendance MODIFY COLUMN status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present'");
        $wfh_column_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'is_wfh'");
        if ($wfh_column_check === false || $wfh_column_check->num_rows === 0) {
            $conn->query("ALTER TABLE attendance ADD COLUMN is_wfh TINYINT(1) NOT NULL DEFAULT 0 AFTER time_out");
        }
        if ($wfh_column_check !== false) {
            $wfh_column_check->free();
        }
    }
    
    // Handle clearing entry (delete record)
    if ($clearEntry) {
        $delete_stmt = $conn->prepare("DELETE FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $delete_stmt->bind_param("is", $employee_id, $date);
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $conn->close();
            echo json_encode([
                'success' => true,
                'message' => 'Attendance cleared successfully',
                'status' => '',
                'time_in' => null
            ]);
            exit();
        } else {
            $error = $conn->error;
            $delete_stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Failed to clear attendance: ' . $error]);
            exit();
        }
    }
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to ensure atomic operation
    // This prevents duplicate records and always updates existing records
    // This ensures that once a record exists for a date, it will be updated, not duplicated
    $upsert_stmt = $conn->prepare("
        INSERT INTO attendance (employee_id, attendance_date, time_in, status, is_wfh, updated_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            time_in = ?,
            status = ?,
            is_wfh = ?,
            updated_at = NOW()
    ");
    $upsert_stmt->bind_param("isssissi", $employee_id, $date, $time_in, $status, $is_wfh, $time_in, $status, $is_wfh);
    
    if ($upsert_stmt->execute()) {
        // If Holiday or Suspended: apply same status to ALL active employees for this date
        if ($status === 'holiday' || $status === 'suspended') {
            $all_emp = $conn->query("SELECT id FROM employees WHERE status = 'active'");
            if ($all_emp && $all_emp->num_rows > 0) {
                $placeholders = [];
                $types = '';
                $params = [];
                while ($row = $all_emp->fetch_assoc()) {
                    $placeholders[] = "(?, ?, NULL, ?, 0, NOW())";
                    $types .= 'iss';
                    $params[] = (int)$row['id'];
                    $params[] = $date;
                    $params[] = $status;
                }
                $all_emp->free();
                $sql = "INSERT INTO attendance (employee_id, attendance_date, time_in, status, is_wfh, updated_at) VALUES " . implode(', ', $placeholders) . " ON DUPLICATE KEY UPDATE time_in = NULL, status = VALUES(status), is_wfh = 0, updated_at = NOW()";
                $bulk_stmt = $conn->prepare($sql);
                if ($bulk_stmt) {
                    $bulk_stmt->bind_param($types, ...$params);
                    $bulk_stmt->execute();
                    $bulk_stmt->close();
                }
            }
        }
        
        // Get the actual saved status from database to ensure it's correct
        $check_stmt = $conn->prepare("SELECT status, time_in, is_wfh FROM attendance WHERE employee_id = ? AND attendance_date = ?");
        $check_stmt->bind_param("is", $employee_id, $date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $saved_record = $check_result->fetch_assoc();
        $check_stmt->close();
        
        $upsert_stmt->close();
        $conn->close();
        
        $final_status = $saved_record ? $saved_record['status'] : $status;
        $final_time = $saved_record ? $saved_record['time_in'] : $time_in;
        $final_is_wfh = $saved_record ? (int)$saved_record['is_wfh'] : $is_wfh;
        
        echo json_encode([
            'success' => true, 
            'message' => $status === 'holiday' || $status === 'suspended' ? 'Attendance saved and applied to all employees for this date.' : 'Attendance saved successfully',
            'status' => $final_status,
            'time_in' => $final_time,
            'is_wfh' => $final_is_wfh
        ]);
    } else {
        $error_msg = $conn->error;
        $upsert_stmt->close();
        $conn->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save attendance: ' . $error_msg]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

