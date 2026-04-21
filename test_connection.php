<?php
/**
 * MySQL Connection Test Script
 * This script helps diagnose database connection issues
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'romd_attendance');

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>MySQL Connection Test - ROMD Attendance</title>
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
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #b3d7ff;
        }
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            border: 1px solid #ffeaa7;
        }
        .step {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        ul {
            margin: 10px 0;
            padding-left: 25px;
        }
        li {
            margin: 5px 0;
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
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔌 MySQL Connection Diagnostic Tool</h1>";

// Display current configuration
echo "<div class='step'>
    <strong>Current Configuration:</strong><br>
    <table>
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>Host</td><td><code>" . htmlspecialchars(DB_HOST) . "</code></td></tr>
        <tr><td>Username</td><td><code>" . htmlspecialchars(DB_USER) . "</code></td></tr>
        <tr><td>Password</td><td><code>" . (empty(DB_PASS) ? '(empty)' : '***') . "</code></td></tr>
        <tr><td>Database</td><td><code>" . htmlspecialchars(DB_NAME) . "</code></td></tr>
    </table>
</div>";

// Test 1: Check if MySQL extension is loaded
echo "<div class='step'><strong>Test 1:</strong> Checking PHP MySQL extension...</div>";
if (extension_loaded('mysqli')) {
    echo "<div class='success'>✓ mysqli extension is loaded</div>";
} else {
    echo "<div class='error'>✗ mysqli extension is NOT loaded. Please enable it in php.ini</div>";
}

// Test 2: Try to connect to MySQL server (without database)
echo "<div class='step'><strong>Test 2:</strong> Testing connection to MySQL server...</div>";
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    $error_code = $conn->connect_errno;
    $error_message = $conn->connect_error;
    
    echo "<div class='error'>
        <strong>✗ Connection Failed!</strong><br>
        Error Code: $error_code<br>
        Error Message: " . htmlspecialchars($error_message) . "
    </div>";
    
    // Provide specific solutions based on error
    if ($error_code == 2002 || strpos($error_message, 'refused') !== false) {
        echo "<div class='warning'>
            <strong>⚠ MySQL Service is Not Running</strong><br><br>
            <strong>Solution for XAMPP:</strong><br>
            <ol>
                <li>Open <strong>XAMPP Control Panel</strong></li>
                <li>Find <strong>MySQL</strong> in the list</li>
                <li>Click the <strong>Start</strong> button next to MySQL</li>
                <li>Wait until the status shows <strong>Running</strong> (green)</li>
                <li>Refresh this page to test again</li>
            </ol>
            <br>
            <strong>Alternative Solutions:</strong><ul>
                <li>Check if MySQL is running on a different port (default: 3306)</li>
                <li>Check Windows Services: Press Win+R, type <code>services.msc</code>, look for 'MySQL' or 'MariaDB'</li>
                <li>Check if another MySQL service is already running and conflicting</li>
                <li>Check Windows Firewall settings</li>
            </ul>
        </div>";
    } elseif ($error_code == 1045) {
        echo "<div class='warning'>
            <strong>⚠ Authentication Failed</strong><br><br>
            The username or password is incorrect. Check your config.php file.
        </div>";
    } else {
        echo "<div class='warning'>
            <strong>⚠ Connection Error</strong><br><br>
            Please check:<ul>
                <li>MySQL service is running</li>
                <li>Host address is correct (usually 'localhost' or '127.0.0.1')</li>
                <li>Port is correct (default: 3306)</li>
                <li>Firewall is not blocking the connection</li>
            </ul>
        </div>";
    }
    
    echo "<div class='info'>
        <strong>Quick Fix Steps:</strong><br>
        1. Open XAMPP Control Panel<br>
        2. Start MySQL service<br>
        3. <a href='test_connection.php' class='btn'>Refresh This Page</a>
    </div>";
    
} else {
    echo "<div class='success'>✓ Successfully connected to MySQL server!</div>";
    
    // Test 3: Check if database exists
    echo "<div class='step'><strong>Test 3:</strong> Checking if database '" . htmlspecialchars(DB_NAME) . "' exists...</div>";
    $db_check = $conn->query("SHOW DATABASES LIKE '" . $conn->real_escape_string(DB_NAME) . "'");
    
    if ($db_check && $db_check->num_rows > 0) {
        echo "<div class='success'>✓ Database '" . htmlspecialchars(DB_NAME) . "' exists</div>";
        
        // Test 4: Select database and check tables
        if ($conn->select_db(DB_NAME)) {
            echo "<div class='success'>✓ Successfully selected database</div>";
            
            echo "<div class='step'><strong>Test 4:</strong> Checking tables...</div>";
            $tables_result = $conn->query("SHOW TABLES");
            
            if ($tables_result && $tables_result->num_rows > 0) {
                echo "<div class='success'>✓ Found " . $tables_result->num_rows . " table(s):</div>";
                echo "<table><tr><th>Table Name</th></tr>";
                while ($row = $tables_result->fetch_array()) {
                    echo "<tr><td>" . htmlspecialchars($row[0]) . "</td></tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='warning'>⚠ Database exists but has no tables. Run <a href='setup_database.php'>setup_database.php</a> to create tables.</div>";
            }
        } else {
            echo "<div class='error'>✗ Failed to select database: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='warning'>⚠ Database '" . htmlspecialchars(DB_NAME) . "' does not exist</div>";
        echo "<div class='info'>Run <a href='setup_database.php'>setup_database.php</a> to create the database and tables.</div>";
    }
    
    // Display MySQL version
    $version_result = $conn->query("SELECT VERSION() as version");
    if ($version_result) {
        $version = $version_result->fetch_assoc()['version'];
        echo "<div class='info'>MySQL Version: <code>$version</code></div>";
    }
    
    $conn->close();
    
    echo "<div class='success' style='padding: 20px; font-size: 16px; margin-top: 20px;'>
        <strong>✓ All Connection Tests Passed!</strong><br>
        Your MySQL connection is working correctly.
    </div>";
    
    echo "<div style='margin-top: 20px;'>
        <a href='fix_database.php' class='btn'>Fix Database Tables</a>
        <a href='index.php' class='btn' style='background: #28a745;'>Go to Dashboard</a>
    </div>";
}

echo "</div></body></html>";
?>

