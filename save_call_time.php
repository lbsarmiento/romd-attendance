<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$call_date = $_POST['call_date'] ?? '';
$call_time = $_POST['call_time'] ?? '';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $call_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Normalize time or clear
$clear = false;
if ($call_time === '' || $call_time === null) {
    $clear = true;
} else {
    // Accept HH:MM and convert to HH:MM:SS
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $call_time)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        exit();
    }
    if (strlen($call_time) === 5) {
        $call_time .= ':00';
    }
}

try {
    $conn = getDBConnection();

    // Ensure call_times table exists
    $call_times_check = $conn->query("SHOW TABLES LIKE 'call_times'");
    if ($call_times_check === false || $call_times_check->num_rows == 0) {
        $create_call_times_sql = "CREATE TABLE IF NOT EXISTS call_times (
            id INT AUTO_INCREMENT PRIMARY KEY,
            call_date DATE NOT NULL UNIQUE,
            call_time TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_call_date (call_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($conn->query($create_call_times_sql) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to create call_times table: ' . $conn->error]);
            $conn->close();
            exit();
        }
    }

    if ($clear) {
        // Delete existing record for this date
        $stmt = $conn->prepare("DELETE FROM call_times WHERE call_date = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare delete: ' . $conn->error]);
            $conn->close();
            exit();
        }
        $stmt->bind_param('s', $call_date);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();

        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Call time cleared (default will be used).']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear call time.']);
        }
        exit();
    }

    // Insert or update call time
    $stmt = $conn->prepare("
        INSERT INTO call_times (call_date, call_time, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            call_time = VALUES(call_time),
            updated_at = NOW()
    ");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare save: ' . $conn->error]);
        $conn->close();
        exit();
    }

    $stmt->bind_param('ss', $call_date, $call_time);
    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Call time saved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save call time.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
