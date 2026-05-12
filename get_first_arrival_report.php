<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');

if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    echo json_encode(['success' => false, 'message' => 'Invalid month or year.']);
    exit;
}

function calculateEarlyBirdStreaks(array $days): array {
    $current_streaks = [];
    $best_streaks = [];
    $first_day_counts = [];
    $names_by_id = [];

    foreach ($days as $day) {
        $date = $day['date'];
        $today_first_ids = [];

        foreach ($day['first_arrivers'] as $first_arriver) {
            $employee_id = (int)$first_arriver['employee_id'];
            $employee_name = $first_arriver['employee_name'];
            $today_first_ids[$employee_id] = true;
            $names_by_id[$employee_id] = $employee_name;
            $first_day_counts[$employee_id] = ($first_day_counts[$employee_id] ?? 0) + 1;

            if (isset($current_streaks[$employee_id])) {
                $current_streaks[$employee_id]['streak']++;
                $current_streaks[$employee_id]['end_date'] = $date;
            } else {
                $current_streaks[$employee_id] = [
                    'streak' => 1,
                    'start_date' => $date,
                    'end_date' => $date
                ];
            }

            if (
                !isset($best_streaks[$employee_id])
                || $current_streaks[$employee_id]['streak'] > $best_streaks[$employee_id]['streak']
            ) {
                $best_streaks[$employee_id] = $current_streaks[$employee_id];
            }
        }

        foreach (array_keys($current_streaks) as $employee_id) {
            if (!isset($today_first_ids[$employee_id])) {
                unset($current_streaks[$employee_id]);
            }
        }
    }

    $streaks = [];
    foreach ($best_streaks as $employee_id => $streak) {
        $streaks[] = [
            'employee_id' => $employee_id,
            'employee_name' => $names_by_id[$employee_id] ?? '',
            'longest_streak' => $streak['streak'],
            'start_date' => $streak['start_date'],
            'end_date' => $streak['end_date'],
            'days_first_count' => $first_day_counts[$employee_id] ?? 0
        ];
    }

    usort($streaks, function ($a, $b) {
        $streak_compare = $b['longest_streak'] - $a['longest_streak'];
        if ($streak_compare !== 0) return $streak_compare;
        $count_compare = $b['days_first_count'] - $a['days_first_count'];
        return $count_compare !== 0 ? $count_compare : strcasecmp($a['employee_name'], $b['employee_name']);
    });

    return array_slice($streaks, 0, 5);
}

$start_date = sprintf('%04d-%02d-01', $year, $month);
$last_day   = (int) date('t', strtotime($start_date));
$end_date   = sprintf('%04d-%02d-%02d', $year, $month, $last_day);

try {
    $conn = getDBConnection();

    // All attendance in the month with time_in (active employees only)
    $query = "SELECT a.employee_id, e.employee_name, a.attendance_date, a.time_in
        FROM attendance a
        INNER JOIN employees e ON e.id = a.employee_id AND e.status = 'active'
        WHERE a.attendance_date >= ? AND a.attendance_date <= ?
        AND a.time_in IS NOT NULL AND a.time_in != '' AND a.time_in != '00:00:00'
        ORDER BY a.attendance_date, a.time_in";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    // Group by date: date => [ { employee_id, employee_name, time_in }, ... ]
    $by_date = [];
    while ($row = $result->fetch_assoc()) {
        $d = $row['attendance_date'];
        if (!isset($by_date[$d])) {
            $by_date[$d] = [];
        }
        $by_date[$d][] = [
            'employee_id'   => (int) $row['employee_id'],
            'employee_name' => $row['employee_name'],
            'time_in'       => $row['time_in'],
            'time_display'  => substr($row['time_in'], 0, 5)
        ];
    }
    $stmt->close();
    $conn->close();

    // For each day: among checked-in employees ("present" in this report),
    // Pasok lang dapat sa 8 am yung last rrival count 
    // find both earliest and latest time_in values. Ties are included.
    $days = [];
    $employee_days = []; // employee_id => count of days they were first
    $last_employee_days = []; // employee_id => count of days they were last

    foreach ($by_date as $date => $records) {
        $min_time = null;
        $max_time = null;
        foreach ($records as $r) {
            $t = $r['time_in'];
            if ($min_time === null || $t < $min_time) {
                $min_time = $t;
            }
            if ($max_time === null || $t > $max_time) {
                $max_time = $t;
            }
        }
        $first_arrivers = array_filter($records, function ($r) use ($min_time) {
            return $r['time_in'] === $min_time;
        });
        $first_arrivers = array_values($first_arrivers);
        $last_arrivers = array_filter($records, function ($r) use ($max_time) {
            return $r['time_in'] === $max_time;
        });
        $last_arrivers = array_values($last_arrivers);

        $days[] = [
            'date'            => $date,
            'earliest_time'   => $min_time ? substr($min_time, 0, 5) : '',
            'first_arrivers'  => $first_arrivers,
            'count'           => count($first_arrivers),
            'latest_time'     => $max_time ? substr($max_time, 0, 5) : '',
            'last_arrivers'   => $last_arrivers,
            'last_count'      => count($last_arrivers)
        ];

        foreach ($first_arrivers as $fa) {
            $eid = $fa['employee_id'];
            $employee_days[$eid] = ($employee_days[$eid] ?? 0) + 1;
        }

        foreach ($last_arrivers as $la) {
            $eid = $la['employee_id'];
            $last_employee_days[$eid] = ($last_employee_days[$eid] ?? 0) + 1;
        }
    }

    // Build employee totals: names from both first/last arrival lists in $days
    $employee_totals = [];
    $last_employee_totals = [];
    $names_by_id = [];
    foreach ($days as $day) {
        foreach ($day['first_arrivers'] as $fa) {
            $eid = $fa['employee_id'];
            if (!isset($names_by_id[$eid])) {
                $names_by_id[$eid] = $fa['employee_name'];
            }
        }
        foreach ($day['last_arrivers'] as $la) {
            $eid = $la['employee_id'];
            if (!isset($names_by_id[$eid])) {
                $names_by_id[$eid] = $la['employee_name'];
            }
        }
    }
    foreach ($employee_days as $eid => $count) {
        $employee_totals[] = [
            'employee_id'      => $eid,
            'employee_name'    => $names_by_id[$eid] ?? '',
            'days_first_count' => $count
        ];
    }
    foreach ($last_employee_days as $eid => $count) {
        $last_employee_totals[] = [
            'employee_id'     => $eid,
            'employee_name'   => $names_by_id[$eid] ?? '',
            'days_last_count' => $count
        ];
    }
    usort($employee_totals, function ($a, $b) {
        $count_compare = $b['days_first_count'] - $a['days_first_count'];
        return $count_compare !== 0 ? $count_compare : strcasecmp($a['employee_name'], $b['employee_name']);
    });
    usort($last_employee_totals, function ($a, $b) {
        $count_compare = $b['days_last_count'] - $a['days_last_count'];
        return $count_compare !== 0 ? $count_compare : strcasecmp($a['employee_name'], $b['employee_name']);
    });
    $streak_top5 = calculateEarlyBirdStreaks($days);

    $month_name = date('F', strtotime($start_date));

    echo json_encode([
        'success'          => true,
        'month'            => $month,
        'year'             => $year,
        'month_name'       => $month_name,
        'start_date'       => $start_date,
        'end_date'         => $end_date,
        'days'             => $days,
        'employee_totals'  => $employee_totals,
        'last_employee_totals' => $last_employee_totals,
        'streak_top5'      => $streak_top5
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
