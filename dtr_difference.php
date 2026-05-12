<?php
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

// Get month and year from URL parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$allowedYears = [2026, 2027];
$year = isset($_GET['year']) ? (int)$_GET['year'] : 2026;
// Optional filter by employee
$selected_employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$hasFilter = isset($_GET['employee_id']) || isset($_GET['month']) || isset($_GET['year']);

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if (!in_array($year, $allowedYears, true)) {
    $year = 2026;
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
    is_wfh TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present',
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
    status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance_dtr (employee_id, attendance_date),
    INDEX idx_date (attendance_date),
    INDEX idx_employee_date (employee_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
@$conn->query("ALTER TABLE attendance MODIFY COLUMN status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present'");
@$conn->query("ALTER TABLE attendance_dtr MODIFY COLUMN status ENUM('present', 'absent', 'offset', 'leave', 'ob', 'late', 'holiday', 'suspended') DEFAULT 'present'");
$wfh_column_check = $conn->query("SHOW COLUMNS FROM attendance LIKE 'is_wfh'");
if ($wfh_column_check === false || $wfh_column_check->num_rows === 0) {
    $conn->query("ALTER TABLE attendance ADD COLUMN is_wfh TINYINT(1) NOT NULL DEFAULT 0 AFTER time_out");
}
if ($wfh_column_check !== false) {
    $wfh_column_check->free();
}

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

$rows = [];
if ($hasFilter) {
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

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
}

$totalCompared = count($rows);
$sameCount = 0;
$dtrLaterCount = 0;
$dtrEarlierCount = 0;
$missingDtrCount = 0;
foreach ($rows as $summaryRow) {
    if ($summaryRow['system_time_in'] === null || $summaryRow['dtr_time_in'] === null) {
        $missingDtrCount++;
        continue;
    }
    $diffMinutes = (int)$summaryRow['diff_minutes'];
    if ($diffMinutes > 0) {
        $dtrLaterCount++;
    } elseif ($diffMinutes < 0) {
        $dtrEarlierCount++;
    } else {
        $sameCount++;
    }
}

$conn->close();

$page_title = $monthName . ' ' . $year . ' - DTR Difference Report';
$current_page = 'dashboard';
$show_back_btn = true;
include 'includes/header.php';
?>
    <div class="container month-page dtr-difference-page">
        <div class="month-header">
            <div class="month-hero">
                <div class="month-hero-copy">
                    <span class="month-eyebrow">DTR Comparison</span>
                    <h2><?php echo htmlspecialchars($monthName . ' ' . $year); ?> - DTR vs System Time-in</h2>
                    <p>Compare system attendance against DTR records. Positive minutes mean DTR is later; negative means DTR is earlier.</p>
                </div>
                <div class="month-hero-stats">
                    <div class="month-stat">
                        <strong><?php echo $totalCompared; ?></strong>
                        <span>Records</span>
                    </div>
                    <div class="month-stat">
                        <strong><?php echo $sameCount; ?></strong>
                        <span>Same Time</span>
                    </div>
                    <div class="month-stat">
                        <strong><?php echo $missingDtrCount; ?></strong>
                        <span>Missing DTR</span>
                    </div>
                </div>
            </div>
            <div class="navigation month-navigation">
                <?php
                // Previous month
                $prevMonth = $month - 1;
                $prevYear = $year;
                if ($prevMonth < 1) {
                    $prevMonth = 12;
                    $prevYear--;
                }
                if ($prevYear < min($allowedYears)) {
                    $prevMonth = 1;
                    $prevYear = min($allowedYears);
                }
                
                // Next month
                $nextMonth = $month + 1;
                $nextYear = $year;
                if ($nextMonth > 12) {
                    $nextMonth = 1;
                    $nextYear++;
                }
                if ($nextYear > max($allowedYears)) {
                    $nextMonth = 12;
                    $nextYear = max($allowedYears);
                }
                $employeeQueryParam = $selected_employee_id > 0 ? '&employee_id=' . $selected_employee_id : '';
                ?>
                <a href="dtr_difference.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear . $employeeQueryParam; ?>" class="nav-btn">Previous</a>
                <a href="index.php" class="nav-btn nav-btn-muted">Dashboard</a>
                <a href="dtr_difference.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear . $employeeQueryParam; ?>" class="nav-btn">Next</a>
            </div>

            <form method="get" action="dtr_difference.php" class="dtr-filter-form">
                <div>
                    <label for="employee_id">Employee</label>
                    <select name="employee_id" id="employee_id">
                        <option value="0">All employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo (int)$emp['id']; ?>" <?php echo $selected_employee_id === (int)$emp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['employee_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="month">Month</label>
                    <select name="month" id="month">
                        <?php foreach ($months as $mNum => $mName): ?>
                            <option value="<?php echo $mNum; ?>" <?php echo $mNum === $month ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="year">Year</label>
                    <select name="year" id="year">
                        <?php foreach ($allowedYears as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dtr-filter-actions">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>

        <?php if (!$hasFilter): ?>
            <div class="dtr-start-card">
                <h3>Filter first to view details</h3>
                <p>Select the month, year, and employee scope above, then click <strong>Filter</strong>. The employee DTR comparison details will appear here after filtering.</p>
            </div>
        <?php else: ?>
        <div class="dtr-summary-grid">
            <div class="dtr-summary-card exact">
                <strong><?php echo $sameCount; ?></strong>
                <span>Same Time</span>
            </div>
            <div class="dtr-summary-card later">
                <strong><?php echo $dtrLaterCount; ?></strong>
                <span>DTR Later</span>
            </div>
            <div class="dtr-summary-card earlier">
                <strong><?php echo $dtrEarlierCount; ?></strong>
                <span>DTR Earlier</span>
            </div>
            <div class="dtr-summary-card missing">
                <strong><?php echo $missingDtrCount; ?></strong>
                <span>Missing Time</span>
            </div>
        </div>

        <div class="attendance-table-container">
            <div class="attendance-table-toolbar">
                <div>
                    <h3>Difference Details</h3>
                    <p>Rows are based on system attendance records and matched against DTR entries for the same date.</p>
                </div>
                <div class="attendance-legend">
                    <span><i class="legend-dot present"></i> Same</span>
                    <span><i class="legend-dot late"></i> DTR Later</span>
                    <span><i class="legend-dot absent"></i> Missing</span>
                </div>
            </div>
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
                            <td colspan="5" class="dtr-empty-state">
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
                                    $diff_label = 'Missing time';
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
                                <td><span class="diff-pill <?php echo $diff_class; ?>"><?php echo htmlspecialchars($diff_label); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
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

