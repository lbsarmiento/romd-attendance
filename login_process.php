<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        header('Location: login.php?error=' . urlencode('Please fill in all fields'));
        exit();
    }
    
    $conn = getDBConnection();
    
    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND status = 'active'");
    
    // Check if prepare() failed
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        $conn->close();
        header('Location: login.php?error=' . urlencode('Database error. Please try again later.'));
        exit();
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Set remember me cookie if requested
            if ($remember_me) {
                $cookie_value = base64_encode($user['id'] . ':' . hash('sha256', $user['password']));
                setcookie('remember_token', $cookie_value, time() + (86400 * 30), '/'); // 30 days
            }
            
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            
            // Check if prepare() failed
            if ($update_stmt === false) {
                error_log("Prepare failed for update: " . $conn->error);
                // Continue anyway - login should still succeed
            } else {
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            $stmt->close();
            $conn->close();
            
            // Redirect to dashboard
            header('Location: index.php');
            exit();
        } else {
            $stmt->close();
            $conn->close();
            header('Location: login.php?error=' . urlencode('Invalid username or password'));
            exit();
        }
    } else {
        $stmt->close();
        $conn->close();
        header('Location: login.php?error=' . urlencode('Invalid username or password'));
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>

