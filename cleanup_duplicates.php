<?php
/**
 * Cleanup script to remove duplicate attendance records
 * This script keeps only the most recent record for each employee_id and attendance_date combination
 */

require_once 'config.php';
requireLogin();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cleanup Duplicate Records</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .message {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cleanup Duplicate Attendance Records</h1>
        
        <?php
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
            
            // Check for duplicates
            $check_duplicates = "
                SELECT employee_id, attendance_date, COUNT(*) as count
                FROM attendance
                GROUP BY employee_id, attendance_date
                HAVING COUNT(*) > 1
            ";
            
            $result = $conn->query($check_duplicates);
            
            if ($result && $result->num_rows > 0) {
                echo '<div class="message info">';
                echo '<strong>Found ' . $result->num_rows . ' duplicate record(s):</strong><br><br>';
                echo '<table>';
                echo '<tr><th>Employee ID</th><th>Date</th><th>Duplicate Count</th></tr>';
                
                $duplicates = [];
                while ($row = $result->fetch_assoc()) {
                    $duplicates[] = $row;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['employee_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['attendance_date']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['count']) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
                
                // Cleanup duplicates
                if (isset($_POST['cleanup'])) {
                    $deleted_count = 0;
                    
                    foreach ($duplicates as $dup) {
                        $emp_id = $dup['employee_id'];
                        $date = $dup['attendance_date'];
                        
                        // Get all records for this employee_id and date, ordered by updated_at DESC
                        // Keep the most recent one, delete the rest
                        $get_records = $conn->prepare("
                            SELECT id, updated_at 
                            FROM attendance 
                            WHERE employee_id = ? AND attendance_date = ?
                            ORDER BY updated_at DESC, id DESC
                        ");
                        $get_records->bind_param("is", $emp_id, $date);
                        $get_records->execute();
                        $records_result = $get_records->get_result();
                        
                        $records = [];
                        while ($r = $records_result->fetch_assoc()) {
                            $records[] = $r;
                        }
                        $get_records->close();
                        
                        // Delete all except the first (most recent) one
                        if (count($records) > 1) {
                            $ids_to_delete = [];
                            for ($i = 1; $i < count($records); $i++) {
                                $ids_to_delete[] = $records[$i]['id'];
                            }
                            
                            if (!empty($ids_to_delete)) {
                                $ids_str = implode(',', array_map('intval', $ids_to_delete));
                                $delete_stmt = $conn->prepare("DELETE FROM attendance WHERE id IN ($ids_str)");
                                if ($delete_stmt->execute()) {
                                    $deleted_count += $delete_stmt->affected_rows;
                                }
                                $delete_stmt->close();
                            }
                        }
                    }
                    
                    echo '<div class="message success">';
                    echo '<strong>✓ Cleanup completed!</strong><br>';
                    echo "Deleted $deleted_count duplicate record(s).";
                    echo '</div>';
                    
                    // Verify no more duplicates
                    $verify_result = $conn->query($check_duplicates);
                    if ($verify_result && $verify_result->num_rows == 0) {
                        echo '<div class="message success">';
                        echo '<strong>✓ Verification:</strong> No duplicate records found. Database is clean.';
                        echo '</div>';
                    }
                } else {
                    echo '<form method="POST">';
                    echo '<button type="submit" name="cleanup" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete duplicate records? This action cannot be undone.\');">Cleanup Duplicates</button>';
                    echo '</form>';
                }
            } else {
                echo '<div class="message success">';
                echo '<strong>✓ No duplicate records found.</strong><br>';
                echo 'Your database is clean. The UNIQUE constraint on (employee_id, attendance_date) should prevent future duplicates.';
                echo '</div>';
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            echo '<div class="message error">';
            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>

