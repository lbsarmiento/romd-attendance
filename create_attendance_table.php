<?php
/**
 * Quick fix: Create attendance table if it doesn't exist
 * Run this file once to create the attendance table
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Create Attendance Table - ROMD Attendance</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #b3d7ff;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Create Attendance Table</h1>";

try {
    $conn = getDBConnection();
    
    // Check if table already exists
    $table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
    
    if ($table_check && $table_check->num_rows > 0) {
        echo "<div class='info'>ℹ The attendance table already exists.</div>";
        echo "<div style='margin-top: 20px;'><a href='index.php'>← Go to Dashboard</a></div>";
    } else {
        // Create attendance table
        echo "<div class='info'>Creating attendance table...</div>";
        
        $sql = "CREATE TABLE IF NOT EXISTS attendance (
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
        
        if ($conn->query($sql) === TRUE) {
            echo "<div class='success'>✓ Attendance table created successfully!</div>";
            echo "<div style='margin-top: 20px;'><a href='index.php'>← Go to Dashboard</a></div>";
        } else {
            throw new Exception("Error creating attendance table: " . $conn->error);
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>
        <strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "
    </div>";
    
    echo "<div class='info' style='margin-top: 20px;'>
        <strong>Troubleshooting:</strong><br>
        • Make sure MySQL/MariaDB is running in XAMPP<br>
        • Verify that the employees table exists (attendance table requires it)<br>
        • Check database connection settings in config.php
    </div>";
}

echo "</div></body></html>";
?>

