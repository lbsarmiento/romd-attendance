<?php
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

// Get month and year from URL parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
// Optional filter by employee
$selected_employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

// Validate month and year
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

// Ensure both tables exist (mirror logic from month.php / month_dtr.php)
$conn->query("CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present', 'absent', 'offset', 'leave', 'late', 'holiday', 'suspended') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (employee_id, attendance_date),
    INDEX idx_date (attendance_date),
    INDEX idx_employee_date (employee_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS attendance_dtr (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present', 'absent', 'offset', 'leave', 'late', 'holiday', 'suspended') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance_dtr (employee_id, attendance_date),
    INDEX idx_date (attendance_date),
    INDEX idx_employee_date (employee_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Get list of active employees for filter dropdown
$employees = [];
$empResult = $conn->query("SELECT id, employee_name FROM employees WHERE status = 'active' ORDER BY employee_name");
if ($empResult) {
    while ($erow = $empResult->fetch_assoc()) {
        $employees[] = $erow;
    }
    $empResult->free();
}

$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Fetch per-day differences between main attendance and DTR attendance
$sql = "
    SELECT 
        e.id AS employee_id,
        e.employee_name,
        a.attendance_date,
        a.time_in AS system_time_in,
        d.time_in AS dtr_time_in,
        TIMESTAMPDIFF(MINUTE, a.time_in, d.time_in) AS diff_minutes
    FROM employees e
    LEFT JOIN attendance a 
        ON e.id = a.employee_id 
        AND a.attendance_date >= ? AND a.attendance_date <= ?
    LEFT JOIN attendance_dtr d 
        ON e.id = d.employee_id 
        AND d.attendance_date = a.attendance_date
    WHERE e.status = 'active'
      AND a.attendance_date IS NOT NULL
      AND (? = 0 OR e.id = ?)
    ORDER BY e.employee_name, a.attendance_date
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssii', $start_date, $end_date, $selected_employee_id, $selected_employee_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();
$conn->close();

$page_title = $monthName . ' ' . $year . ' - DTR Difference Report';
$current_page = 'dashboard';
$show_back_btn = true;
include 'includes/header.php';
?>
    <div class="container">
        <div class="month-header">
            <h2><?php echo htmlspecialchars($monthName . ' ' . $year); ?> - DTR vs System Time-in</h2>
            <p style="margin-top: 5px; color: #555;">
                This report compares the main attendance time-in (system) with the DTR time-in and shows the difference in minutes.
                Positive minutes mean DTR is later than the system time; negative means DTR is earlier.
            </p>
            <div class="navigation" style="margin-bottom: 15px;">
                <?php
                // Previous month
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear--;
                }
                
                // Next month
                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                $employeeQueryParam = $selected_employee_id > 0 ? '&employee_id=' . $selected_employee_id : '';
                ?>
                <a href="dtr_difference.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear . $employeeQueryParam; ?>" class="nav-btn">← Previous Month</a>
                <a href="index.php" class="nav-btn">Back to Dashboard</a>
                <a href="dtr_difference.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear . $employeeQueryParam; ?>" class="nav-btn">Next Month →</a>
            </div>

            <form method="get" action="dtr_difference.php" style="margin-top: 10px; padding: 10px; background: #f9fafb; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <div>
                    <label for="employee_id" style="font-size: 13px; color: #374151;">Employee:</label><br>
                    <select name="employee_id" id="employee_id" style="min-width: 220px; padding: 6px 8px;">
                        <option value="0">All employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>" <?php echo $selected_employee_id === (int)$emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['employee_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="month" style="font-size: 13px; color: #374151;">Month:</label><br>
                    <select name="month" id="month" style="padding: 6px 8px;">
                        <?php foreach ($months as $mNum => $mName): ?>
                            <option value="<?php echo $mNum; ?>" <?php echo $mNum === $month ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="year" style="font-size: 13px; color: #374151;">Year:</label><br>
                    <select name="year" id="year" style="padding: 6px 8px;">
                        <?php
                        $currentYear = (int)date('Y');
                        for ($y = $currentYear - 2; $y <= $currentYear + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div style="margin-top: 18px;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <div class="attendance-table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th class="employee-header">Employee Name</th>
                        <th>Date</th>
                        <th>System Time-in</th>
                        <th>DTR Time-in</th>
                        <th>Difference (minutes)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #666;">
                                No records found for this month.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $date_display = date('F j, Y', strtotime($row['attendance_date']));
                                $system_time = $row['system_time_in'] ? substr($row['system_time_in'], 0, 5) : '';
                                $dtr_time = $row['dtr_time_in'] ? substr($row['dtr_time_in'], 0, 5) : '';
                                $diff = $row['diff_minutes'];
                                
                                // Only compute diff label if both times exist
                                if ($row['system_time_in'] === null || $row['dtr_time_in'] === null) {
                                    $diff_label = '';
                                    $diff_class = 'diff-empty';
                                } else {
                                    $diff_label = (string)$diff;
                                    if ($diff > 0) {
                                        $diff_label .= ' (DTR later)';
                                        $diff_class = 'diff-late';
                                    } elseif ($diff < 0) {
                                        $diff_label .= ' (DTR earlier)';
                                        $diff_class = 'diff-early';
                                    } else {
                                        $diff_label .= ' (same time)';
                                        $diff_class = 'diff-same';
                                    }
                                }
                            ?>
                            <tr>
                                <td class="employee-name"><?php echo htmlspecialchars($row['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($date_display); ?></td>
                                <td><?php echo htmlspecialchars($system_time); ?></td>
                                <td><?php echo htmlspecialchars($dtr_time); ?></td>
                                <td class="<?php echo $diff_class; ?>"><?php echo htmlspecialchars($diff_label); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .diff-late {
            color: #b91c1c;
            font-weight: 600;
        }
        .diff-early {
            color: #15803d;
            font-weight: 600;
        }
        .diff-same {
            color: #1f2937;
            font-weight: 500;
        }
        .diff-empty {
            color: #9ca3af;
        }
    </style>

<?php include 'includes/footer.php'; ?>

