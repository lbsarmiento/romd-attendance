<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'romdattendance');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection function
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        $error_code = $conn->connect_errno;
        $error_message = $conn->connect_error;
        
        // Provide helpful error messages
        if ($error_code == 2002 || strpos($error_message, 'refused') !== false) {
            die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Database Connection Error</title>
                <style>
                    body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                    .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb; }
                    .solution { background: #d1ecf1; color: #0c5460; padding: 20px; border-radius: 5px; margin-top: 20px; border: 1px solid #bee5eb; }
                    h2 { margin-top: 0; }
                    ol { margin: 10px 0; padding-left: 25px; }
                    a { color: #667eea; }
                </style>
            </head>
            <body>
                <div class='error'>
                    <h2>❌ Database Connection Failed</h2>
                    <p><strong>Error:</strong> MySQL service is not running.</p>
                    <p>The MySQL server refused the connection. This usually means MySQL/MariaDB is not started in XAMPP.</p>
                </div>
                <div class='solution'>
                    <h2>🔧 How to Fix:</h2>
                    <ol>
                        <li>Open <strong>XAMPP Control Panel</strong></li>
                        <li>Find <strong>MySQL</strong> in the services list</li>
                        <li>Click the <strong>Start</strong> button next to MySQL</li>
                        <li>Wait until the status shows <strong>Running</strong> (should turn green)</li>
                        <li>Refresh this page or go back to the previous page</li>
                    </ol>
                    <p style='margin-top: 15px;'>
                        <strong>Need help?</strong> <a href='test_connection.php'>Run Connection Diagnostic Tool</a>
                    </p>
                </div>
            </body>
            </html>
            ");
        } else {
            die("Connection failed: " . $error_message . " (Error Code: $error_code)");
        }
    }
    
    return $conn;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Check if current user is an admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Restrict access to admin users only
function requireAdmin() {
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Access Denied</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
                .card { max-width: 520px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); }
                h1 { margin-top: 0; color: #991b1b; }
                a { color: #1d4ed8; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h1>Access denied</h1>
                <p>This page is only available to admin accounts.</p>
                <p><a href='index.php'>Return to dashboard</a></p>
            </div>
        </body>
        </html>";
        exit();
    }
}

function getDefaultAppSettings() {
    return [
        'system_name' => 'ROMD Attendance',
        'organization_name' => 'Regional Operations Management Division',
        'public_subtitle' => 'Attendance Monitoring System',
        'public_welcome_message' => "Welcome to Regional Operations Management Division's Attendance Monitoring System. Easily review and track daily attendance records. Use the date selector to navigate through each working day and view real-time, color-coded status updates in a clear and simple interface.",
        'late_time_threshold' => '08:00'
    ];
}

function ensureAppSettingsTable($conn) {
    static $table_ready = false;

    if ($table_ready) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $table_ready = $conn->query($sql) === true;
    return $table_ready;
}

function getAppSettings($conn = null) {
    if (isset($GLOBALS['app_settings_cache']) && is_array($GLOBALS['app_settings_cache'])) {
        return $GLOBALS['app_settings_cache'];
    }

    $defaults = getDefaultAppSettings();
    $owns_connection = false;

    if ($conn === null) {
        $conn = getDBConnection();
        $owns_connection = true;
    }

    if (!ensureAppSettingsTable($conn)) {
        if ($owns_connection) {
            $conn->close();
        }
        $GLOBALS['app_settings_cache'] = $defaults;
        return $GLOBALS['app_settings_cache'];
    }

    $result = $conn->query("SELECT setting_key, setting_value FROM app_settings");
    if ($result !== false) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        }
        $result->free();
    }

    if ($owns_connection) {
        $conn->close();
    }

    $GLOBALS['app_settings_cache'] = $defaults;
    return $GLOBALS['app_settings_cache'];
}

function getAppSetting($key, $default = null, $conn = null) {
    $settings = getAppSettings($conn);

    if (array_key_exists($key, $settings)) {
        return $settings[$key];
    }

    return $default;
}

function saveAppSettings($conn, $settings) {
    if (!ensureAppSettingsTable($conn)) {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO app_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP"
    );

    if ($stmt === false) {
        return false;
    }

    foreach ($settings as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
    }

    $stmt->close();
    unset($GLOBALS['app_settings_cache']);

    return true;
}
?>

