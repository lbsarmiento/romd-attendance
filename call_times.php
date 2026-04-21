<?php
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

// Get month and year from URL parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$monthName = $months[$month];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

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
    $conn->query($create_call_times_sql);
}

// Load existing call times for this month
$callTimesByDate = [];
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
$stmt = $conn->prepare("SELECT call_date, call_time FROM call_times WHERE call_date >= ? AND call_date <= ? ORDER BY call_date");
if ($stmt) {
    $stmt->bind_param('ss', $start_date, $end_date);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $callTimesByDate[$row['call_date']] = substr($row['call_time'], 0, 5);
            }
            $result->free();
        }
    }
    $stmt->close();
}
$conn->close();

$page_title = 'Manage Call Times - ROMD Attendance';
$current_page = 'call_times';
include 'includes/header.php';
?>
    <div class="container">
        <div class="month-header">
            <h2>Manage Call Times (<?php echo htmlspecialchars($monthName . ' ' . $year); ?>)</h2>
            <p style="margin-top: 5px; color: #555; font-size: 14px;">
                Set custom call times for specific dates (e.g., events). Any time strictly after the call time will be marked as <strong>Late</strong>.
            </p>
            <div class="navigation" style="margin-top: 15px;">
                <?php
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear--;
                }

                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                ?>
                <a href="call_times.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="nav-btn">← Previous Month</a>
                <a href="index.php" class="nav-btn">Back to Dashboard</a>
                <a href="month.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="nav-btn">View Attendance</a>
                <a href="call_times.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="nav-btn">Next Month →</a>
            </div>
        </div>

        <div class="attendance-table-container" style="margin-top: 20px;">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Date</th>
                        <th style="width: 20%;">Day</th>
                        <th style="width: 20%;">Call Time</th>
                        <th style="width: 40%;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    for ($day = 1; $day <= $daysInMonth; $day++):
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $dayOfWeek = date('w', strtotime($date));
                        $dayName = $dayNames[$dayOfWeek];
                        $currentCallTime = $callTimesByDate[$date] ?? '';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($monthName . ' ' . $day . ', ' . $year); ?></td>
                        <td><?php echo htmlspecialchars($dayName); ?></td>
                        <td>
                            <input 
                                type="time" 
                                class="editable-input" 
                                value="<?php echo htmlspecialchars($currentCallTime); ?>" 
                                data-date="<?php echo $date; ?>"
                                onchange="saveCallTime(this)"
                            >
                        </td>
                        <td style="font-size: 13px; color: #666;">
                            <?php if (!empty($currentCallTime)): ?>
                                Custom call time set. Anything after <?php echo htmlspecialchars($currentCallTime); ?> is late.
                            <?php else: ?>
                                Using default call time (08:00). Set a time here to override for this day.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <p style="margin-top: 15px; font-size: 13px; color: #555;">
                Leave a date empty to use the default call time of <strong>08:00</strong>. To remove a custom call time, clear the field.
            </p>
        </div>
    </div>

    <script>
        function saveCallTime(input) {
            const date = input.getAttribute('data-date');
            let time = input.value || '';

            const formData = new FormData();
            formData.append('call_date', date);
            formData.append('call_time', time);

            fetch('save_call_time.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Error saving call time: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error saving call time: ' + error.message);
            });
        }
    </script>
<?php include 'includes/footer.php'; ?>

