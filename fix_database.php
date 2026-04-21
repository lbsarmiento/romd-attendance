<?php
/**
 * Database Repair/Fix Script for ROMD Attendance
 * This script checks and fixes all database tables, indexes, and constraints
 */

require_once 'config.php';

// Allow running without login for database repair
// requireLogin();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Repair - ROMD Attendance</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
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
        h2 {
            color: #495057;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 18px;
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
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
        .step {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
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
        tr:hover {
            background: #f8f9fa;
        }
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            text-decoration: none;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Database Repair & Fix Tool</h1>";

try {
    $conn = getDBConnection();
    $issues_found = [];
    $issues_fixed = [];
    
    // Step 1: Check database connection
    echo "<div class='step'><strong>Step 1:</strong> Checking database connection...</div>";
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    echo "<div class='success'>✓ Database connection successful</div>";
    
    // Step 2: Check and create users table
    echo "<div class='step'><strong>Step 2:</strong> Checking users table...</div>";
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($table_check === false || $table_check->num_rows == 0) {
        $create_users = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'user', 'manager') DEFAULT 'user',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_username (username),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_users)) {
            echo "<div class='success'>✓ Users table created</div>";
            $issues_fixed[] = "Created users table";
        } else {
            echo "<div class='error'>✗ Failed to create users table: " . $conn->error . "</div>";
            $issues_found[] = "Users table creation failed: " . $conn->error;
        }
    } else {
        echo "<div class='info'>ℹ Users table exists</div>";
        
        // Check if admin user exists
        $admin_check = $conn->query("SELECT id FROM users WHERE username = 'admin'");
        if ($admin_check->num_rows == 0) {
            $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
            $insert_admin = $conn->prepare("INSERT INTO users (username, password, email, role, status) VALUES ('admin', ?, 'admin@romd.com', 'admin', 'active')");
            $insert_admin->bind_param("s", $hashed_password);
            if ($insert_admin->execute()) {
                echo "<div class='success'>✓ Admin user created (username: admin, password: admin123)</div>";
                $issues_fixed[] = "Created admin user";
            }
            $insert_admin->close();
        }
    }
    
    // Step 3: Check and create employees table
    echo "<div class='step'><strong>Step 3:</strong> Checking employees table...</div>";
    $table_check = $conn->query("SHOW TABLES LIKE 'employees'");
    if ($table_check === false || $table_check->num_rows == 0) {
        $create_employees = "CREATE TABLE IF NOT EXISTS employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_name VARCHAR(100) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($create_employees)) {
            echo "<div class='success'>✓ Employees table created</div>";
            $issues_fixed[] = "Created employees table";
        } else {
            echo "<div class='error'>✗ Failed to create employees table: " . $conn->error . "</div>";
            $issues_found[] = "Employees table creation failed: " . $conn->error;
        }
    } else {
        echo "<div class='info'>ℹ Employees table exists</div>";
    }
    
    // Step 4: Check and create attendance table
    echo "<div class='step'><strong>Step 4:</strong> Checking attendance table...</div>";
    $table_check = $conn->query("SHOW TABLES LIKE 'attendance'");
    if ($table_check === false || $table_check->num_rows == 0) {
        $create_attendance = "CREATE TABLE IF NOT EXISTS attendance (
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
        
        if ($conn->query($create_attendance)) {
            echo "<div class='success'>✓ Attendance table created</div>";
            $issues_fixed[] = "Created attendance table";
        } else {
            echo "<div class='error'>✗ Failed to create attendance table: " . $conn->error . "</div>";
            $issues_found[] = "Attendance table creation failed: " . $conn->error;
        }
    } else {
        echo "<div class='info'>ℹ Attendance table exists</div>";
        
        // Check for unique constraint
        $constraint_check = $conn->query("SHOW INDEX FROM attendance WHERE Key_name = 'unique_attendance'");
        if ($constraint_check->num_rows == 0) {
            echo "<div class='warning'>⚠ Unique constraint missing, attempting to add...</div>";
            // Try to add unique constraint (may fail if duplicates exist)
            $add_constraint = "ALTER TABLE attendance ADD UNIQUE KEY unique_attendance (employee_id, attendance_date)";
            if ($conn->query($add_constraint)) {
                echo "<div class='success'>✓ Unique constraint added</div>";
                $issues_fixed[] = "Added unique constraint to attendance table";
            } else {
                echo "<div class='warning'>⚠ Could not add unique constraint (duplicates may exist): " . $conn->error . "</div>";
                $issues_found[] = "Unique constraint missing and could not be added";
            }
        }
    }
    
    // Step 5: Check and create tasks table (optional)
    echo "<div class='step'><strong>Step 5:</strong> Checking tasks table...</div>";
    $table_check = $conn->query("SHOW TABLES LIKE 'tasks'");
    if ($table_check === false || $table_check->num_rows == 0) {
        $create_tasks = "CREATE TABLE IF NOT EXISTS tasks (
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
        
        if ($conn->query($create_tasks)) {
            echo "<div class='success'>✓ Tasks table created</div>";
            $issues_fixed[] = "Created tasks table";
        } else {
            echo "<div class='warning'>⚠ Failed to create tasks table (optional): " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='info'>ℹ Tasks table exists</div>";
    }
    
    // Step 6: Check for orphaned attendance records
    echo "<div class='step'><strong>Step 6:</strong> Checking for orphaned records...</div>";
    $orphaned_check = $conn->query("SELECT COUNT(*) as count FROM attendance a LEFT JOIN employees e ON a.employee_id = e.id WHERE e.id IS NULL");
    if ($orphaned_check) {
        $orphaned = $orphaned_check->fetch_assoc()['count'];
        if ($orphaned > 0) {
            echo "<div class='warning'>⚠ Found $orphaned orphaned attendance record(s) (employee_id doesn't exist)</div>";
            $issues_found[] = "Found $orphaned orphaned attendance records";
            
            if (isset($_GET['fix_orphans']) && $_GET['fix_orphans'] == 'yes') {
                $delete_orphans = "DELETE FROM attendance WHERE employee_id NOT IN (SELECT id FROM employees)";
                if ($conn->query($delete_orphans)) {
                    echo "<div class='success'>✓ Orphaned records deleted</div>";
                    $issues_fixed[] = "Deleted $orphaned orphaned records";
                }
            } else {
                echo "<div class='info'>ℹ <a href='?fix_orphans=yes'>Click here to delete orphaned records</a></div>";
            }
        } else {
            echo "<div class='success'>✓ No orphaned records found</div>";
        }
    }
    
    // Step 7: Check for duplicate attendance records
    echo "<div class='step'><strong>Step 7:</strong> Checking for duplicate records...</div>";
    $duplicate_check = $conn->query("SELECT employee_id, attendance_date, COUNT(*) as count FROM attendance GROUP BY employee_id, attendance_date HAVING COUNT(*) > 1");
    if ($duplicate_check && $duplicate_check->num_rows > 0) {
        $dup_count = $duplicate_check->num_rows;
        echo "<div class='warning'>⚠ Found $dup_count duplicate record(s)</div>";
        $issues_found[] = "Found $dup_count duplicate attendance records";
        echo "<div class='info'>ℹ <a href='cleanup_duplicates.php'>Click here to clean up duplicates</a></div>";
    } else {
        echo "<div class='success'>✓ No duplicate records found</div>";
    }
    
    // Step 8: Summary
    echo "<h2>📊 Summary</h2>";
    
    if (empty($issues_fixed) && empty($issues_found)) {
        echo "<div class='success' style='padding: 20px; font-size: 16px;'>
            <strong>✓ Database is healthy!</strong><br>
            All tables exist and are properly configured.
        </div>";
    } else {
        if (!empty($issues_fixed)) {
            echo "<div class='success' style='padding: 15px;'>
                <strong>✓ Issues Fixed:</strong><ul>";
            foreach ($issues_fixed as $fix) {
                echo "<li>$fix</li>";
            }
            echo "</ul></div>";
        }
        
        if (!empty($issues_found)) {
            echo "<div class='warning' style='padding: 15px;'>
                <strong>⚠ Issues Found:</strong><ul>";
            foreach ($issues_found as $issue) {
                echo "<li>$issue</li>";
            }
            echo "</ul></div>";
        }
    }
    
    // Display table status
    echo "<h2>📋 Table Status</h2>";
    echo "<table>";
    echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";
    
    $tables = ['users', 'employees', 'attendance', 'tasks'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_result ? $count_result->fetch_assoc()['count'] : 'N/A';
            echo "<tr><td><strong>$table</strong></td><td><span style='color: green;'>✓ Exists</span></td><td>$count</td></tr>";
        } else {
            echo "<tr><td><strong>$table</strong></td><td><span style='color: red;'>✗ Missing</span></td><td>-</td></tr>";
        }
    }
    echo "</table>";
    
    $conn->close();
    
    echo "<div style='margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 6px;'>
        <strong>Next Steps:</strong><br>
        1. <a href='index.php'>Go to Dashboard</a><br>
        2. <a href='login.php'>Go to Login Page</a><br>
        3. Refresh this page to re-check database status
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>
        <strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "
    </div>";
    
    echo "<div class='info' style='margin-top: 20px;'>
        <strong>Troubleshooting:</strong><br>
        • Make sure MySQL/MariaDB is running in XAMPP<br>
        • Check that the database credentials in config.php are correct<br>
        • Verify that the MySQL user has CREATE TABLE privileges<br>
        • Ensure the database 'romd_attendance' exists
    </div>";
}

echo "</div></body></html>";
?>

