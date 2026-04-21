<?php
/**
 * Test script to verify admin login credentials
 */
require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Test Admin Login</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; background: #cce5ff; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Admin Login Test</h1>";

try {
    $conn = getDBConnection();
    
    // Check if admin user exists
    $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE username = 'admin'");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<div class='error'>✗ Admin user does not exist in database!</div>";
        echo "<div class='info'>Run setup_database.php to create the admin user.</div>";
    } else {
        $user = $result->fetch_assoc();
        echo "<div class='success'>✓ Admin user found in database</div>";
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Role: " . $user['role'] . "\n";
        echo "Status: " . $user['status'] . "\n";
        echo "Password Hash: " . substr($user['password'], 0, 30) . "...\n";
        echo "</pre>";
        
        // Test password verification
        $test_password = 'admin123';
        $password_match = password_verify($test_password, $user['password']);
        
        if ($password_match) {
            echo "<div class='success'>✓ Password 'admin123' matches the hash in database!</div>";
        } else {
            echo "<div class='error'>✗ Password 'admin123' does NOT match the hash in database!</div>";
            echo "<div class='info'>Updating admin password to 'admin123'...</div>";
            
            // Update the password
            $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
            $update_stmt->bind_param("s", $new_hash);
            
            if ($update_stmt->execute()) {
                echo "<div class='success'>✓ Admin password has been updated to 'admin123'</div>";
                
                // Verify the new password
                $verify_stmt = $conn->prepare("SELECT password FROM users WHERE username = 'admin'");
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $updated_user = $verify_result->fetch_assoc();
                
                if (password_verify('admin123', $updated_user['password'])) {
                    echo "<div class='success'>✓ Password verification successful! You can now login with:<br><strong>Username: admin<br>Password: admin123</strong></div>";
                }
                $verify_stmt->close();
            } else {
                echo "<div class='error'>✗ Error updating password: " . $update_stmt->error . "</div>";
            }
            $update_stmt->close();
        }
        
        // Test login process
        echo "<div class='info' style='margin-top: 20px;'><strong>Login Test:</strong></div>";
        if ($password_match || password_verify('admin123', $user['password'])) {
            echo "<div class='success'>✓ Login should work with username: 'admin' and password: 'admin123'</div>";
        }
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<div style='margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 4px;'>
    <strong>Next Steps:</strong><br>
    1. <a href='login.php'>Try logging in</a><br>
    2. Delete this test file (test_login.php) after testing
</div>";

echo "</div></body></html>";
?>

