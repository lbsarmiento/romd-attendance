<?php
/**
 * Quick script to create missing tables (employees and attendance)
 * Run this if you already have the database but missing these tables
 */
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tables - ROMD Attendance</title>
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
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            margin-top: 20px;
            cursor: pointer;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Missing Tables</h1>
        <p>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></p>
        
        <?php
        try {
            $conn = getDBConnection();
            
            // Step 1: Create employees table
            echo "<div class='step'><strong>Step 1:</strong> Creating employees table...</div>";
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
            
            // Step 2: Create attendance table
            echo "<div class='step'><strong>Step 2:</strong> Creating attendance table...</div>";
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
                echo "<div class='success'>✓ Attendance table created successfully</div>";
            } else {
                throw new Exception("Error creating attendance table: " . $conn->error);
            }
            
            // Step 3: Add sample employees if table is empty
            echo "<div class='step'><strong>Step 3:</strong> Checking for sample employees...</div>";
            $check_emp = $conn->query("SELECT COUNT(*) as count FROM employees");
            $emp_count = $check_emp->fetch_assoc()['count'];
            
            if ($emp_count == 0) {
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
                    $insert_emp = $conn->prepare("INSERT INTO employees (employee_name, status) VALUES (?, 'active')");
                    $insert_emp->bind_param("s", $emp_name);
                    if ($insert_emp->execute()) {
                        $employees_added++;
                    }
                    $insert_emp->close();
                }
                
                if ($employees_added > 0) {
                    echo "<div class='success'>✓ Added $employees_added sample employees</div>";
                }
            } else {
                echo "<div class='info'>ℹ Employees table already has $emp_count employee(s)</div>";
            }
            
            $conn->close();
            
            echo "<div class='success' style='margin-top: 30px; padding: 20px; font-size: 16px;'>
                <strong>✓ Tables created successfully!</strong><br><br>
                You can now add employees and record attendance.
            </div>";
            
            echo "<div style='margin-top: 20px;'>
                <a href='index.php' class='btn'>Go to Dashboard</a>
            </div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>
                <strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "
            </div>";
            
            echo "<div class='info' style='margin-top: 20px;'>
                <strong>Troubleshooting:</strong><br>
                • Make sure MySQL/MariaDB is running in XAMPP<br>
                • Check that the database 'romd_attendance' exists<br>
                • Verify database credentials in config.php
            </div>";
        }
        ?>
    </div>
</body>
</html>

