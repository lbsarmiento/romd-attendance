<?php
if (!isset($page_title)) {
    $page_title = 'ROMD Attendance';
}
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? 'user';
$current_page = $current_page ?? 'dashboard';
$app_system_name = getAppSetting('system_name', 'ROMD Attendance');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="app-main-wrapper">
        <header class="app-header">
            <a href="index.php" class="app-logo">
                <span class="app-logo-icon">R</span>
                <span><?php echo htmlspecialchars($app_system_name); ?></span>
            </a>
            <div class="app-header-right">
                <span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong> (<?php echo htmlspecialchars($role); ?>)</span>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <a href="admin_config.php" class="header-link <?php echo $current_page === 'admin_config' ? 'active' : ''; ?>">Admin Config</a>
                <?php endif; ?>
                <?php if (isset($show_back_btn) && $show_back_btn): ?>
                    <a href="index.php" class="back-btn">← Back</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </header>
        <main class="app-content">
