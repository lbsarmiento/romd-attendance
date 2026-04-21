<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
ensureAppSettingsTable($conn);

$app_settings = getAppSettings($conn);
$system_name = $app_settings['system_name'] ?? 'ROMD Attendance';
$page_title = 'Admin Configuration - ' . $system_name;
$current_page = 'admin_config';
$show_back_btn = true;

$message = '';
$message_type = 'success';
$admin_user = null;

function loadCurrentAdminUser($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

$admin_user = loadCurrentAdminUser($conn, (int) $_SESSION['user_id']);

if (!$admin_user || $admin_user['role'] !== 'admin') {
    $conn->close();
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_app_settings') {
        $system_name_input = trim($_POST['system_name'] ?? '');
        $organization_name = trim($_POST['organization_name'] ?? '');
        $public_subtitle = trim($_POST['public_subtitle'] ?? '');
        $public_welcome_message = trim($_POST['public_welcome_message'] ?? '');
        $late_time_threshold = trim($_POST['late_time_threshold'] ?? '');
        $errors = [];

        if ($system_name_input === '') {
            $errors[] = 'System name is required.';
        } elseif (strlen($system_name_input) > 100) {
            $errors[] = 'System name must be 100 characters or less.';
        }

        if ($organization_name === '') {
            $errors[] = 'Organization name is required.';
        } elseif (strlen($organization_name) > 150) {
            $errors[] = 'Organization name must be 150 characters or less.';
        }

        if ($public_subtitle === '') {
            $errors[] = 'Public subtitle is required.';
        } elseif (strlen($public_subtitle) > 120) {
            $errors[] = 'Public subtitle must be 120 characters or less.';
        }

        if ($public_welcome_message === '') {
            $errors[] = 'Public welcome message is required.';
        } elseif (strlen($public_welcome_message) > 1500) {
            $errors[] = 'Public welcome message must be 1500 characters or less.';
        }

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $late_time_threshold)) {
            $errors[] = 'Late threshold must use 24-hour HH:MM format.';
        }

        if (empty($errors)) {
            $saved = saveAppSettings($conn, [
                'system_name' => $system_name_input,
                'organization_name' => $organization_name,
                'public_subtitle' => $public_subtitle,
                'public_welcome_message' => $public_welcome_message,
                'late_time_threshold' => $late_time_threshold
            ]);

            if ($saved) {
                $app_settings = getAppSettings($conn);
                $system_name = $app_settings['system_name'];
                $page_title = 'Admin Configuration - ' . $system_name;
                $message = 'Application settings updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to save application settings right now.';
                $message_type = 'error';
            }
        } else {
            $message = implode(' ', $errors);
            $message_type = 'error';
            $app_settings = [
                'system_name' => $system_name_input,
                'organization_name' => $organization_name,
                'public_subtitle' => $public_subtitle,
                'public_welcome_message' => $public_welcome_message,
                'late_time_threshold' => $late_time_threshold
            ];
        }
    } elseif ($action === 'save_admin_account') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $errors = [];

        if ($username === '') {
            $errors[] = 'Admin username is required.';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3 to 50 characters and may only use letters, numbers, dots, underscores, or hyphens.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid admin email address.';
        }

        if (strlen($email) > 100) {
            $errors[] = 'Admin email must be 100 characters or less.';
        }

        if ($username !== $admin_user['username']) {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
            if ($check_stmt !== false) {
                $check_stmt->bind_param("si", $username, $admin_user['id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result && $check_result->num_rows > 0) {
                    $errors[] = 'That username is already in use.';
                }
                $check_stmt->close();
            }
        }

        $password_will_change = ($new_password !== '' || $confirm_password !== '' || $current_password !== '');
        if ($password_will_change) {
            if ($current_password === '') {
                $errors[] = 'Enter your current password to set a new one.';
            } elseif (!password_verify($current_password, $admin_user['password'])) {
                $errors[] = 'Current password is incorrect.';
            }

            if ($new_password === '') {
                $errors[] = 'New password is required.';
            } elseif (strlen($new_password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }

            if ($confirm_password !== $new_password) {
                $errors[] = 'Password confirmation does not match.';
            }
        }

        if (empty($errors)) {
            $fields = ['username = ?', 'email = ?'];
            $params = [$username, $email];
            $types = 'ss';

            if ($password_will_change) {
                $fields[] = 'password = ?';
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                $types .= 's';
            }

            $params[] = $admin_user['id'];
            $types .= 'i';

            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                $message = 'Unable to update the admin account right now.';
                $message_type = 'error';
            } else {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $message = $password_will_change
                        ? 'Admin account and password updated successfully.'
                        : 'Admin account updated successfully.';
                    $message_type = 'success';
                    $admin_user = loadCurrentAdminUser($conn, (int) $_SESSION['user_id']);
                } else {
                    $message = 'Unable to update the admin account right now.';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        } else {
            $message = implode(' ', $errors);
            $message_type = 'error';
            $admin_user['username'] = $username;
            $admin_user['email'] = $email;
        }
    }
}

include 'includes/header.php';
?>
    <div class="container">
        <div class="welcome-card">
            <h2>Admin Configuration</h2>
            <p>Manage the main admin account and the live display settings used by the dashboard, login page, and public view.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="display: block;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <section class="card settings-section">
                <div class="settings-section-header">
                    <div>
                        <h2>Application Settings</h2>
                        <p>These values update the visible labels shown across the system.</p>
                    </div>
                </div>

                <form method="POST" action="admin_config.php">
                    <input type="hidden" name="action" value="save_app_settings">

                    <div class="form-group">
                        <label for="system_name">System Name</label>
                        <input
                            type="text"
                            id="system_name"
                            name="system_name"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($app_settings['system_name'] ?? 'ROMD Attendance'); ?>"
                            required
                        >
                        <div class="helper-text">Used in the admin header, dashboard title, and login page.</div>
                    </div>

                    <div class="form-group">
                        <label for="organization_name">Organization Name</label>
                        <input
                            type="text"
                            id="organization_name"
                            name="organization_name"
                            maxlength="150"
                            value="<?php echo htmlspecialchars($app_settings['organization_name'] ?? 'Regional Operations Management Division'); ?>"
                            required
                        >
                        <div class="helper-text">Shown on the public page branding and footer.</div>
                    </div>

                    <div class="form-group">
                        <label for="public_subtitle">Public Page Subtitle</label>
                        <input
                            type="text"
                            id="public_subtitle"
                            name="public_subtitle"
                            maxlength="120"
                            value="<?php echo htmlspecialchars($app_settings['public_subtitle'] ?? 'Attendance Monitoring System'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="public_welcome_message">Public Welcome Message</label>
                        <textarea
                            id="public_welcome_message"
                            name="public_welcome_message"
                            maxlength="1500"
                            required
                        ><?php echo htmlspecialchars($app_settings['public_welcome_message'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="late_time_threshold">Late Time Threshold</label>
                        <input
                            type="time"
                            id="late_time_threshold"
                            name="late_time_threshold"
                            value="<?php echo htmlspecialchars($app_settings['late_time_threshold'] ?? '08:00'); ?>"
                            required
                        >
                        <div class="helper-text">Employees who time in after this will be treated as late in dashboard summaries.</div>
                    </div>

                    <div class="settings-actions">
                        <button type="submit" class="btn btn-primary">Save Application Settings</button>
                    </div>
                </form>
            </section>

            <section class="card settings-section">
                <div class="settings-section-header">
                    <div>
                        <h2>Admin Account</h2>
                        <p>Update the currently signed-in admin account details.</p>
                    </div>
                </div>

                <form method="POST" action="admin_config.php" autocomplete="off">
                    <input type="hidden" name="action" value="save_admin_account">

                    <div class="form-group">
                        <label for="username">Admin Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            maxlength="50"
                            value="<?php echo htmlspecialchars($admin_user['username'] ?? ''); ?>"
                            required
                        >
                        <div class="helper-text">If you change this, you must use the new username on your next login.</div>
                    </div>

                    <div class="form-group">
                        <label for="email">Admin Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="100"
                            value="<?php echo htmlspecialchars($admin_user['email'] ?? ''); ?>"
                            placeholder="admin@example.com"
                        >
                    </div>

                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            placeholder="Required only when changing password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            placeholder="Leave blank to keep the current password"
                        >
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter the new password"
                        >
                    </div>

                    <div class="settings-actions">
                        <button type="submit" class="btn btn-primary">Save Admin Account</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
<?php
$conn->close();
include 'includes/footer.php';
?>
