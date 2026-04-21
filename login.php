<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$app_settings = getAppSettings();
$system_name = $app_settings['system_name'] ?? 'ROMD Attendance';

$error = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($system_name); ?></title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1><?php echo htmlspecialchars($system_name); ?></h1>
                <p>Sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="login_process.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autofocus
                        placeholder="Enter your username"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="Enter your password"
                    >
                </div>
                
                <div class="form-group checkbox-group">
                    <label>
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                </div>
                
                <button type="submit" class="login-button">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($system_name); ?></p>
            </div>
        </div>
    </div>
</body>
</html>

