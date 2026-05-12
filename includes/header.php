<?php
if (!isset($page_title)) {
    $page_title = 'ROMD Attendance';
}
$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? 'user';
$current_page = $current_page ?? 'dashboard';
$app_system_name = function_exists('getAppSetting') ? getAppSetting('system_name', 'ROMD Attendance') : 'ROMD Attendance';
$app_initials = 'RA';
if (!empty($app_system_name)) {
    $words = preg_split('/\s+/', trim($app_system_name));
    $initials = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    $app_initials = $initials !== '' ? $initials : 'RA';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?php echo file_exists(__DIR__ . '/../assets/css/app.css') ? filemtime(__DIR__ . '/../assets/css/app.css') : time(); ?>">
</head>
<body>
    <div class="app-main-wrapper">
        <header class="app-header" role="banner">
            <a href="index.php" class="app-logo">
                <span class="app-logo-icon" aria-hidden="true"><?php echo htmlspecialchars($app_initials); ?></span>
                <span class="app-logo-text"><?php echo htmlspecialchars($app_system_name); ?></span>
            </a>
            <nav class="app-header-right" aria-label="Main navigation">
                <div class="app-user-pill" title="<?php echo htmlspecialchars($username . ' - ' . $role); ?>">
                    <span class="app-user-label">Signed in as</span>
                    <strong><?php echo htmlspecialchars($username); ?></strong>
                    <span class="app-role-badge"><?php echo htmlspecialchars($role); ?></span>
                </div>
                <?php if (function_exists('isAdmin') && isAdmin()): ?>
                    <a href="admin_config.php" class="header-link <?php echo $current_page === 'admin_config' ? 'active' : ''; ?>">Admin Config</a>
                <?php endif; ?>
                <?php if (isset($show_back_btn) && $show_back_btn): ?>
                    <a href="index.php" class="back-btn">Back</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">Logout</a>
            </nav>
        </header>
        <main class="app-content">
