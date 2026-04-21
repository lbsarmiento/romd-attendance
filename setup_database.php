<?php
/**
 * Database Setup Script for ROMD Attendance
 * Run this file once to create the database and tables
 */

// Database Configuration (without database name for initial connection)
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'romd_attendance';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - ROMD Attendance</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
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
        .step {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
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
        <h1>ROMD Attendance - Database Setup</h1>";

try {
    // Step 1: Connect to MySQL server (without database)
    echo "<div class='step'><strong>Step 1:</strong> Connecting to MySQL server...</div>";
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "<div class='success'>✓ Successfully connected to MySQL server</div>";
    
    // Step 2: Create database
    echo "<div class='step'><strong>Step 2:</strong> Creating database '$dbname'...</div>";
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✓ Database '$dbname' created successfully (or already exists)</div>";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Step 3: Select the database
    $conn->select_db($dbname);
    echo "<div class='success'>✓ Database selected</div>";
    
    // Step 4: Create users table
    echo "<div class='step'><strong>Step 3:</strong> Creating users table...</div>";
    $sql = "CREATE TABLE IF NOT EXISTS users (
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
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✓ Users table created successfully</div>";
    } else {
        throw new Exception("Error creating users table: " . $conn->error);
    }
    
    // Step 5: Create employees table
    echo "<div class='step'><strong>Step 4:</strong> Creating employees table...</div>";
    $sql = "CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_name VARCHAR(100) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✓ Employees table created successfully</div>";
    } else {
        throw new Exception("Error creating employees table: " . $conn->error);
    }
    
    // Step 6: Create attendance table
    echo "<div class='step'><strong>Step 5:</strong> Creating attendance table...</div>";
    $sql = "CREATE TABLE IF NOT EXISTS attendance (
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
    
    if ($conn->query($sql) === TRUE) {
        echo "<div class='success'>✓ Attendance table created successfully</div>";
    } else {
        throw new Exception("Error creating attendance table: " . $conn->error);
    }
    
    // Step 7: Insert sample employees
    echo "<div class='step'><strong>Step 6:</strong> Adding sample employees...</div>";
    $sample_employees = [
        'Froilan M. Guial',
        'Jonathan R. Villarama',
        'Manico V. Bercasio',
        'Xynan Ylijah B. Supnet',
        'Nick Angelo R. Ruiz',
        'John Martin B. Galvan',
        'June Dionelle B. Flores',
        'Jhonnel L. Baron',
        'Lester B. Sarmiento',
        'Carlos Felix M. Borromed',
        'Jhon Russel E. Cabalo'
    ];
    
    $employees_added = 0;
    foreach ($sample_employees as $emp_name) {
        $check_emp = $conn->prepare("SELECT id FROM employees WHERE employee_name = ?");
        $check_emp->bind_param("s", $emp_name);
        $check_emp->execute();
        $emp_result = $check_emp->get_result();
        
        if ($emp_result->num_rows == 0) {
            $insert_emp = $conn->prepare("INSERT INTO employees (employee_name, status) VALUES (?, 'active')");
            $insert_emp->bind_param("s", $emp_name);
            if ($insert_emp->execute()) {
                $employees_added++;
            }
            $insert_emp->close();
        }
        $check_emp->close();
    }
    
    if ($employees_added > 0) {
        echo "<div class='success'>✓ Added $employees_added sample employees</div>";
    } else {
        echo "<div class='info'>ℹ Sample employees already exist</div>";
    }
    
    // Step 8: Insert/Update default admin user
    echo "<div class='step'><strong>Step 7:</strong> Setting up admin user (username: admin, password: admin123)...</div>";
    
    // Hash password for 'admin123'
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Check if admin user already exists
    $check_sql = "SELECT id FROM users WHERE username = 'admin'";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows == 0) {
        // Create new admin user
        $sql = "INSERT INTO users (username, password, email, role, status) 
                VALUES ('admin', ?, 'admin@romd.com', 'admin', 'active')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $hashed_password);
        
        if ($stmt->execute()) {
            echo "<div class='success'>✓ Admin user created successfully</div>";
        } else {
            throw new Exception("Error creating admin user: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Update existing admin user password to ensure it's admin123
        $sql = "UPDATE users SET password = ?, email = 'admin@romd.com', role = 'admin', status = 'active' WHERE username = 'admin'";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $hashed_password);
        
        if ($stmt->execute()) {
            echo "<div class='success'>✓ Admin user password updated to 'admin123'</div>";
        } else {
            throw new Exception("Error updating admin user: " . $stmt->error);
        }
        $stmt->close();
    }
    
    $conn->close();
    
    echo "<div class='success' style='margin-top: 30px; padding: 20px; font-size: 16px;'>
        <strong>✓ Database setup completed successfully!</strong><br><br>
        Default login credentials:<br>
        <strong>Username:</strong> admin<br>
        <strong>Password:</strong> admin123<br><br>
        <strong style='color: #dc3545;'>⚠ Please change the default password after first login!</strong>
    </div>";
    
    echo "<div style='margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 6px;'>
        <strong>Next Steps:</strong><br>
        1. <a href='login.php'>Go to Login Page</a><br>
        2. Delete or secure this setup file (setup_database.php) for security
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>
        <strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "
    </div>";
    
    echo "<div class='info' style='margin-top: 20px;'>
        <strong>Troubleshooting:</strong><br>
        • Make sure MySQL/MariaDB is running in XAMPP<br>
        • Check that the database credentials in config.php are correct<br>
        • Verify that the MySQL user has CREATE DATABASE privileges
    </div>";
}

echo "</div></body></html>";
?>

