<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$today = date('Y-m-d');
$year_filter = isset($_GET['year']) ? trim($_GET['year']) : '';

// Optional year filter: empty or "all" = all time up to today; otherwise that year (up to today if current year)
$start_date = null;
$end_date = $today;
$filter_label = 'all time';

if ($year_filter !== '' && $year_filter !== 'all' && preg_match('/^\d{4}$/', $year_filter)) {
    $y = (int) $year_filter;
    $start_date = sprintf('%04d-01-01', $y);
    $end_date = min(sprintf('%04d-12-31', $y), $today);
    $filter_label = (string) $y;
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

try {
    $conn = getDBConnection();

    if ($start_date !== null) {
        $query = "SELECT a.employee_id, e.employee_name, a.attendance_date, a.time_in
            FROM attendance a
            INNER JOIN employees e ON e.id = a.employee_id AND e.status = 'active'
            WHERE a.attendance_date >= ? AND a.attendance_date <= ?
            AND a.time_in IS NOT NULL AND a.time_in != '' AND a.time_in != '00:00:00'
            ORDER BY a.attendance_date, a.time_in";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ss', $start_date, $end_date);
    } else {
        $query = "SELECT a.employee_id, e.employee_name, a.attendance_date, a.time_in
            FROM attendance a
            INNER JOIN employees e ON e.id = a.employee_id AND e.status = 'active'
            WHERE a.attendance_date <= ?
            AND a.time_in IS NOT NULL AND a.time_in != '' AND a.time_in != '00:00:00'
            ORDER BY a.attendance_date, a.time_in";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $by_date = [];
    while ($row = $result->fetch_assoc()) {
        $d = $row['attendance_date'];
        if (!isset($by_date[$d])) {
            $by_date[$d] = [];
        }
        $by_date[$d][] = [
            'employee_id'   => (int) $row['employee_id'],
            'employee_name' => $row['employee_name'],
            'time_in'       => $row['time_in']
        ];
    }
    $stmt->close();
    $conn->close();

    $employee_days = [];
    $last_employee_days = [];
    $names_by_id = [];
    $streak_days = [];
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
        $streak_days[] = [
            'date' => $date,
            'first_arrivers' => $first_arrivers
        ];
        foreach ($first_arrivers as $fa) {
            $eid = $fa['employee_id'];
            $employee_days[$eid] = ($employee_days[$eid] ?? 0) + 1;
            if (!isset($names_by_id[$eid])) {
                $names_by_id[$eid] = $fa['employee_name'];
            }
        }
        foreach ($last_arrivers as $la) {
            $eid = $la['employee_id'];
            $last_employee_days[$eid] = ($last_employee_days[$eid] ?? 0) + 1;
            if (!isset($names_by_id[$eid])) {
                $names_by_id[$eid] = $la['employee_name'];
            }
        }
    }

    $ranking = [];
    $last_ranking = [];
    foreach ($employee_days as $eid => $count) {
        $ranking[] = [
            'employee_id'      => $eid,
            'employee_name'    => $names_by_id[$eid] ?? '',
            'days_first_count' => $count
        ];
    }
    foreach ($last_employee_days as $eid => $count) {
        $last_ranking[] = [
            'employee_id'     => $eid,
            'employee_name'   => $names_by_id[$eid] ?? '',
            'days_last_count' => $count
        ];
    }
    usort($ranking, function ($a, $b) {
        $count_compare = $b['days_first_count'] - $a['days_first_count'];
        return $count_compare !== 0 ? $count_compare : strcasecmp($a['employee_name'], $b['employee_name']);
    });
    usort($last_ranking, function ($a, $b) {
        $count_compare = $b['days_last_count'] - $a['days_last_count'];
        return $count_compare !== 0 ? $count_compare : strcasecmp($a['employee_name'], $b['employee_name']);
    });
    $streak_top5 = calculateEarlyBirdStreaks($streak_days);

    echo json_encode([
        'success'      => true,
        'as_of_date'   => $today,
        'year_filter'  => $year_filter,
        'filter_label' => $filter_label,
        'ranking'      => $ranking,
        'last_ranking' => $last_ranking,
        'streak_top5'  => $streak_top5
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
