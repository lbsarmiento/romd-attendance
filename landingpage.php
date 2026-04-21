<?php
require_once 'config.php';

$is_logged_in = isLoggedIn();
$app_settings = getAppSettings();
$system_name = $app_settings['system_name'] ?? 'ROMD Attendance';
$organization_name = $app_settings['organization_name'] ?? 'Regional Operations Management Division';
$public_subtitle = $app_settings['public_subtitle'] ?? 'Attendance Monitoring System';
$public_welcome_message = $app_settings['public_welcome_message'] ?? "Welcome to Regional Operations Management Division's Attendance Monitoring System. Easily review and track daily attendance records. Use the date selector to navigate through each working day and view real-time, color-coded status updates in a clear and simple interface.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($system_name); ?> - Public View</title>
    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --blue-950: #0f172a;
            --blue-900: #172554;
            --blue-700: #1d4ed8;
            --blue-600: #2563eb;
            --blue-500: #3b82f6;
            --blue-300: #93c5fd;
            --blue-200: #bfdbfe;
            --blue-100: #dbeafe;
            --blue-50: #eff6ff;
            --text-main: #0f172a;
            --text-soft: #475569;
            --white-soft: rgba(255, 255, 255, 0.9);
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text-main);
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.22), transparent 28%),
                linear-gradient(180deg, #f8fbff 0%, #dbeafe 50%, #c7d2fe 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .test-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .test-header,
        .test-main,
        .test-footer {
            width: min(1240px, calc(100% - 32px));
            margin: 0 auto;
        }

        .test-header {
            padding: 22px 0 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .test-brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .test-brand img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            display: block;
        }

        .test-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.05;
        }

        .test-brand-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-main);
        }

        .test-brand-subtitle {
            font-size: 12px;
            font-weight: 700;
            color: var(--blue-600);
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }

        .test-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .test-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 999px;
            border: 1px solid rgba(37, 99, 235, 0.15);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .test-btn:hover {
            transform: translateY(-1px);
        }

        .test-btn-secondary {
            color: var(--blue-700);
            background: rgba(255, 255, 255, 0.8);
        }

        .test-btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
            box-shadow: 0 14px 26px rgba(37, 99, 235, 0.22);
        }

        .test-main {
            padding: 14px 0 32px;
            display: grid;
            gap: 24px;
            min-width: 0;
        }

        .test-main > * {
            min-width: 0;
        }

        .hero-panel {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 30px;
            color: #fff;
            background: linear-gradient(135deg, var(--blue-950) 0%, var(--blue-700) 52%, #38bdf8 100%);
            box-shadow: 0 28px 70px rgba(29, 78, 216, 0.22);
        }

        .hero-panel::before,
        .hero-panel::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-panel::before {
            width: 260px;
            height: 260px;
            top: -90px;
            right: -90px;
        }

        .hero-panel::after {
            width: 180px;
            height: 180px;
            left: -40px;
            bottom: -40px;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(300px, 0.85fr);
            gap: 22px;
            align-items: stretch;
        }

        .hero-grid > * {
            min-width: 0;
        }

        .hero-title {
            margin: 0 0 10px;
            font-size: clamp(34px, 4vw, 54px);
            line-height: 0.98;
            letter-spacing: -0.04em;
        }

        .hero-copy {
            margin: 0;
            max-width: 660px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 16px;
            line-height: 1.7;
        }

        .hero-meta {
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-chip {
            padding: 11px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.16);
            min-width: 170px;
        }

        .hero-chip-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #bfdbfe;
            margin-bottom: 5px;
        }

        .hero-chip-value {
            font-size: 19px;
            font-weight: 800;
        }

        .admin-month-chip {
            min-width: 240px;
        }

        .admin-month-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .admin-month-select {
            min-width: 104px;
            padding: 9px 12px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            outline: none;
        }

        .admin-month-select option {
            color: #0f172a;
        }

        .admin-month-select:focus {
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(191, 219, 254, 0.2);
        }

        .highlight-card {
            padding: 24px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 240px;
        }

        .highlight-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(254, 240, 138, 0.18);
            border: 1px solid rgba(254, 240, 138, 0.4);
            color: #fef08a;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .highlight-name {
            margin: 18px 0 6px;
            font-size: clamp(28px, 3vw, 36px);
            line-height: 1.05;
        }

        .highlight-name.is-multiple {
            font-size: clamp(22px, 2.4vw, 30px);
            line-height: 1.18;
        }

        .highlight-time {
            font-size: 38px;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .highlight-caption {
            color: rgba(255, 255, 255, 0.76);
            font-size: 14px;
            line-height: 1.6;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 28px;
            box-shadow: 0 20px 46px rgba(148, 163, 184, 0.16);
            backdrop-filter: blur(16px);
        }

        .deck-panel {
            padding: 20px 22px 22px;
        }

        .deck-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            min-width: 0;
        }

        .deck-title {
            margin: 0;
            font-size: 24px;
        }

        .deck-copy {
            margin: 6px 0 0;
            font-size: 14px;
            color: var(--text-soft);
        }

        .deck-header > div:first-child {
            min-width: 0;
        }

        .deck-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .deck-nav-btn {
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--blue-600), var(--blue-700));
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 12px 20px rgba(37, 99, 235, 0.18);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .deck-nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 26px rgba(37, 99, 235, 0.26);
        }

        .deck-viewport {
            overflow-x: auto;
            overflow-y: hidden;
            max-width: 100%;
            padding-bottom: 4px;
            scrollbar-width: thin;
            scrollbar-color: rgba(37, 99, 235, 0.45) transparent;
        }

        .deck-viewport::-webkit-scrollbar {
            height: 10px;
        }

        .deck-viewport::-webkit-scrollbar-thumb {
            background: rgba(37, 99, 235, 0.4);
            border-radius: 999px;
        }

        .working-day-deck {
            display: flex;
            gap: 14px;
            min-width: max-content;
        }

        .deck-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--blue-100);
            border-radius: 24px;
            padding: 16px 20px;
            min-width: 122px;
            text-align: left;
            background: linear-gradient(180deg, #ffffff, #eff6ff);
            box-shadow: 0 12px 24px rgba(148, 163, 184, 0.12);
            cursor: pointer;
            transition: transform 0.24s ease, box-shadow 0.24s ease, border-color 0.24s ease, background 0.24s ease;
        }

        .deck-card-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            z-index: 2;
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.14);
        }

        .deck-card::before,
        .timeline-row::before {
            content: "";
            position: absolute;
            inset: -130% auto -130% -35%;
            width: 54%;
            background: linear-gradient(120deg, transparent 0%, rgba(255, 255, 255, 0.84) 50%, transparent 100%);
            transform: translateX(-220%) rotate(18deg);
            transition: transform 0.9s ease;
            pointer-events: none;
        }

        .deck-card:hover::before,
        .timeline-row:hover::before {
            transform: translateX(420%) rotate(18deg);
        }

        .deck-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 34px rgba(37, 99, 235, 0.16);
            border-color: var(--blue-300);
        }

        .deck-card.is-blocked {
            cursor: not-allowed;
            box-shadow: none;
        }

        .deck-card.is-blocked:hover {
            transform: none;
            box-shadow: 0 12px 24px rgba(148, 163, 184, 0.12);
            border-color: var(--blue-100);
        }

        .deck-card.is-blocked::before {
            display: none;
        }

        .deck-card.is-selected {
            color: #fff;
            border-color: rgba(255, 255, 255, 0.2);
            background: linear-gradient(135deg, var(--blue-700), #38bdf8);
            box-shadow: 0 18px 34px rgba(37, 99, 235, 0.28);
        }

        .deck-card.is-blocked.deck-card-status-holiday {
            color: #7c3aed;
            background: linear-gradient(180deg, #faf5ff, #f3e8ff);
            border-color: #d8b4fe;
        }

        .deck-card.is-blocked.deck-card-status-suspended {
            color: #991b1b;
            background: linear-gradient(180deg, #fff1f2, #ffe4e6);
            border-color: #fda4af;
        }

        .deck-card.is-blocked.deck-card-status-holiday .deck-card-badge {
            color: #ffffff;
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .deck-card.is-blocked.deck-card-status-suspended .deck-card-badge {
            color: #ffffff;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
        }

        .deck-card-month {
            display: block;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.88;
        }

        .deck-card-day {
            display: block;
            margin-top: 6px;
            font-size: 34px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .deck-card-weekday {
            display: block;
            margin-top: 8px;
            font-size: 13px;
            font-weight: 700;
            opacity: 0.86;
        }

        .board-panel {
            padding: 22px;
        }

        .board-stage {
            transition: opacity 0.28s ease, transform 0.28s ease, filter 0.28s ease;
        }

        .board-stage.is-switching {
            opacity: 0.22;
            transform: translateX(26px) scale(0.985);
            filter: blur(4px);
        }

        .board-top {
            display: grid;
            gap: 18px;
            margin-bottom: 18px;
            min-width: 0;
        }

        .board-top > * {
            min-width: 0;
        }

        .board-date-card,
        .board-highlight-card {
            border-radius: 24px;
            padding: 22px;
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border: 1px solid var(--blue-100);
        }

        .board-date-title {
            margin: 0;
            font-size: clamp(24px, 2.2vw, 30px);
            line-height: 1.08;
            letter-spacing: -0.03em;
            overflow-wrap: anywhere;
        }

        .board-report-label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--blue-700);
            opacity: 0.76;
        }

        .board-report-title {
            margin: 8px 0 0;
            font-size: clamp(22px, 2vw, 28px);
            line-height: 1.15;
            letter-spacing: -0.03em;
            color: var(--slate-900);
        }

        .board-report-copy {
            margin: 10px 0 0;
            font-size: 14px;
            line-height: 1.6;
            color: var(--slate-600);
        }

        .summary-pills {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            width: 100%;
        }

        .summary-pill,
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 800;
        }

        .summary-pill {
            min-height: 88px;
            border-radius: 20px;
            padding: 14px 16px;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            border: 1px solid var(--blue-100);
            box-shadow: 0 10px 20px rgba(148, 163, 184, 0.10);
        }

        .summary-pill-label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.8;
        }

        .summary-pill-value {
            font-size: 30px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.04em;
        }

        .status-pill {
            border-radius: 999px;
        }

        .timeline-list {
            display: grid;
            gap: 12px;
        }

        .timeline-row {
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 76px minmax(0, 1fr) minmax(92px, auto);
            gap: 14px;
            align-items: center;
            padding: 16px 18px;
            border-radius: 22px;
            border: 1px solid var(--blue-100);
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            animation: rowIn 0.45s ease both;
        }

        .timeline-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(148, 163, 184, 0.14);
        }

        .timeline-rank {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--blue-50);
            border: 1px solid var(--blue-200);
            color: var(--blue-700);
            font-size: 18px;
            font-weight: 900;
        }

        .timeline-row.is-early .timeline-rank {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-color: #fcd34d;
            color: #92400e;
        }

        .timeline-name {
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 6px;
            color: var(--text-main);
            overflow-wrap: anywhere;
        }

        .timeline-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .timeline-value {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.03em;
            white-space: nowrap;
            text-align: right;
        }

        .empty-state {
            padding: 48px 22px 54px;
            border-radius: 24px;
            text-align: center;
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            border: 1px dashed var(--blue-200);
        }

        .empty-state h3 {
            margin: 0 0 10px;
            font-size: 26px;
        }

        .empty-state p {
            margin: 0 auto;
            max-width: 540px;
            color: var(--text-soft);
            line-height: 1.7;
        }

        .status-present {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .status-late {
            color: #9a3412;
            background: #fed7aa;
        }

        .status-offset {
            color: #6d28d9;
            background: #ede9fe;
        }

        .status-leave {
            color: #0f766e;
            background: #ccfbf1;
        }

        .status-absent {
            color: #b91c1c;
            background: #fee2e2;
        }

        .status-holiday {
            color: #6d28d9;
            background: #f3e8ff;
        }

        .status-suspended {
            color: #991b1b;
            background: #ffe4e6;
        }

        .status-not_checked_in {
            color: #4338ca;
            background: #e0e7ff;
        }

        .timeline-row.status-present {
            background: linear-gradient(180deg, #ffffff, #eff6ff);
        }

        .timeline-row.status-late {
            background: linear-gradient(180deg, #ffffff, #fff7ed);
            border-color: #fdba74;
        }

        .timeline-row.status-offset {
            background: linear-gradient(180deg, #ffffff, #f5f3ff);
            border-color: #c4b5fd;
        }

        .timeline-row.status-leave {
            background: linear-gradient(180deg, #ffffff, #f0fdfa);
            border-color: #99f6e4;
        }

        .timeline-row.status-absent {
            background: linear-gradient(180deg, #ffffff, #fef2f2);
            border-color: #fecaca;
        }

        .timeline-row.status-holiday {
            background: linear-gradient(180deg, #ffffff, #faf5ff);
            border-color: #d8b4fe;
        }

        .timeline-row.status-suspended {
            background: linear-gradient(180deg, #ffffff, #fff1f2);
            border-color: #fda4af;
        }

        .timeline-row.status-not_checked_in {
            background: linear-gradient(180deg, #ffffff, #eef2ff);
            border-color: #c7d2fe;
        }

        .value-present {
            color: #1d4ed8;
        }

        .value-late {
            color: #c2410c;
        }

        .value-offset {
            color: #6d28d9;
        }

        .value-leave {
            color: #0f766e;
        }

        .value-absent {
            color: #b91c1c;
        }

        .value-holiday {
            color: #7c3aed;
        }

        .value-suspended {
            color: #b91c1c;
        }

        .value-not_checked_in {
            color: #4338ca;
        }

        .early-pill {
            color: #92400e;
            background: #fef3c7;
        }

        .test-footer {
            margin-top: auto;
            padding: 0 0 24px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            color: var(--text-soft);
            font-size: 13px;
        }

        @keyframes rowIn {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1100px) {
            .hero-grid,
            .board-top {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 720px) {
            .test-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .deck-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-panel,
            .deck-panel,
            .board-panel {
                padding-left: 20px;
                padding-right: 20px;
            }

            .timeline-row {
                grid-template-columns: 58px minmax(0, 1fr);
            }

            .timeline-value {
                grid-column: 1 / -1;
                padding-left: 72px;
                font-size: 24px;
            }
        }

        @media (max-width: 520px) {
            .test-header,
            .test-main,
            .test-footer {
                width: min(100% - 20px, 1240px);
            }

            .hero-title {
                font-size: 32px;
            }

            .deck-card {
                min-width: 104px;
                padding: 14px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="test-shell">
        <header class="test-header">
            <a href="public_view_test.php" class="test-brand">
                <img src="assets/img/tesda-logo.png" alt="TESDA logo">
                <span class="test-brand-text">
                    <span class="test-brand-title"><?php echo htmlspecialchars($organization_name); ?></span>
                    <span class="test-brand-subtitle"><?php echo htmlspecialchars($public_subtitle); ?></span>
                </span>
            </a>
            <div class="test-actions">
                <a href="javascript:void(0)" class="test-btn test-btn-secondary" id="refreshSelectedBtn">Refresh Day</a>
                <?php if ($is_logged_in): ?>
                    <a href="index.php" class="test-btn test-btn-primary">Open Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="test-btn test-btn-primary">Admin Login</a>
                <?php endif; ?>
            </div>
        </header>

        <main class="test-main">
            <section class="hero-panel">
                <div class="hero-grid">
                    <div>
                        <h1 class="hero-title">Today is <?php echo date('F j, Y'); ?></h1>
                        <p class="hero-copy">
                            <?php echo htmlspecialchars($public_welcome_message); ?>
                        </p>
                        <div class="hero-meta">
                            <div class="hero-chip">
                                <div class="hero-chip-label"><?php echo $is_logged_in ? 'Viewing Month' : 'Current Month'; ?></div>
                                <div class="hero-chip-value" id="deckMonthLabel">Loading...</div>
                            </div>
                            <?php if ($is_logged_in): ?>
                                <div class="hero-chip admin-month-chip">
                                    <div class="hero-chip-label">Admin Month View</div>
                                    <div class="admin-month-controls">
                                        <select id="adminMonthSelect" class="admin-month-select" aria-label="Select month">
                                            <option value="0">January</option>
                                            <option value="1">February</option>
                                            <option value="2">March</option>
                                            <option value="3">April</option>
                                            <option value="4">May</option>
                                            <option value="5">June</option>
                                            <option value="6">July</option>
                                            <option value="7">August</option>
                                            <option value="8">September</option>
                                            <option value="9">October</option>
                                            <option value="10">November</option>
                                            <option value="11">December</option>
                                        </select>
                                        <select id="adminYearSelect" class="admin-month-select" aria-label="Select year">
                                            <?php
                                            $currentYear = (int) date('Y');
                                            for ($year = $currentYear - 5; $year <= $currentYear + 5; $year++):
                                            ?>
                                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="highlight-card">
                        <div>
                            <div class="highlight-badge" id="highlightBadge">Early Bird</div>
                            <div class="highlight-name" id="highlightName">Waiting for first arrival</div>
                            <div class="highlight-time" id="highlightTime">--:--</div>
                        </div>
                        <div class="highlight-caption" id="highlightCaption">
                            Select a working day below to view the first recorded arrival for that date.
                        </div>
                    </div>
                </div>
            </section>

            <section class="deck-panel glass-card">
                <div class="deck-header">
                    <div class="deck-controls">
                        <button type="button" class="deck-nav-btn" id="prevDayBtn" aria-label="Previous working day">&#8249;</button>
                        <button type="button" class="deck-nav-btn" id="nextDayBtn" aria-label="Next working day">&#8250;</button>
                    </div>
                </div>
                <div class="deck-viewport" id="deckViewport">
                    <div class="working-day-deck" id="workingDayDeck"></div>
                </div>
            </section>

            <section class="board-panel glass-card">
                <div class="board-stage" id="boardStage">
                    <div class="board-top">
                        <div class="board-date-card">
                            <div class="board-report-label">Public Report</div>
                            <h2 class="board-report-title" id="boardReportTitle">Employee Time-in updated as of loading...</h2>
                            <p class="board-report-copy" id="boardReportCopy">Live public attendance summary for the selected working day.</p>
                            <div class="summary-pills" id="summaryPills"></div>
                        </div>
                    </div>
                    <div class="timeline-list" id="timelineList">
                        <div class="empty-state">
                            <h3>Loading public attendance</h3>
                            <p>Please wait while the live page retrieves attendance data for the selected working day.</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="test-footer">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($organization_name); ?></span>
        </footer>
    </div>

    <script>
        const state = {
            selectedDate: '',
            workingDays: [],
            requestId: 0,
            metaRequestId: 0,
            dayMeta: {},
            viewYear: 0,
            viewMonth: 0
        };

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDisplayDate(dateStr) {
            const dateObj = new Date(dateStr + 'T12:00:00');
            return dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function formatMonthShort(dateStr) {
            const dateObj = new Date(dateStr + 'T12:00:00');
            return dateObj.toLocaleDateString('en-US', { month: 'short' });
        }

        function formatWeekdayShort(dateStr) {
            const dateObj = new Date(dateStr + 'T12:00:00');
            return dateObj.toLocaleDateString('en-US', { weekday: 'short' });
        }

        function formatTime(timeValue) {
            if (!timeValue) return '--:--';
            const [hours = '0', minutes = '0'] = String(timeValue).split(':');
            const dateObj = new Date();
            dateObj.setHours(parseInt(hours, 10) || 0, parseInt(minutes, 10) || 0, 0, 0);
            return dateObj.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function toTimeMinutes(timeValue) {
            if (!timeValue) return Number.POSITIVE_INFINITY;
            const [hours = '0', minutes = '0'] = String(timeValue).split(':');
            return (parseInt(hours, 10) || 0) * 60 + (parseInt(minutes, 10) || 0);
        }

        function formatUpdatedTimestamp(value) {
            const dateObj = value ? new Date(value) : new Date();
            if (Number.isNaN(dateObj.getTime())) {
                return new Date().toLocaleString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit'
                });
            }

            return dateObj.toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function isWorkingDay(dateObj) {
            const day = dateObj.getDay();
            return day >= 1 && day <= 5;
        }

        function toDateString(dateObj) {
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function getInitialDate() {
            const now = new Date();
            const candidate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            while (!isWorkingDay(candidate)) {
                candidate.setDate(candidate.getDate() - 1);
            }
            return toDateString(candidate);
        }

        function getFirstWorkingDayOfMonth(year, month) {
            const cursor = new Date(year, month, 1);
            while (cursor.getMonth() === month && !isWorkingDay(cursor)) {
                cursor.setDate(cursor.getDate() + 1);
            }
            return cursor.getMonth() === month ? toDateString(cursor) : '';
        }

        function syncAdminMonthControls() {
            const monthSelect = document.getElementById('adminMonthSelect');
            const yearSelect = document.getElementById('adminYearSelect');
            if (!monthSelect || !yearSelect) {
                return;
            }

            monthSelect.value = String(state.viewMonth);
            yearSelect.value = String(state.viewYear);
        }

        function buildWorkingDays() {
            const selected = new Date((state.selectedDate || getInitialDate()) + 'T12:00:00');
            const year = state.viewYear || selected.getFullYear();
            const month = Number.isInteger(state.viewMonth) ? state.viewMonth : selected.getMonth();
            const cursor = new Date(year, month, 1);
            const days = [];

            while (cursor.getMonth() === month) {
                if (isWorkingDay(cursor)) {
                    days.push(toDateString(cursor));
                }
                cursor.setDate(cursor.getDate() + 1);
            }

            state.workingDays = days;
            document.getElementById('deckMonthLabel').textContent = new Date(year, month, 1).toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
            syncAdminMonthControls();
        }

        function refreshVisibleMonth(preferredDate = '') {
            buildWorkingDays();

            if (!state.workingDays.length) {
                state.selectedDate = '';
                renderDeck();
                renderEmptyState('No working days are available for the selected month.');
                return;
            }

            if (preferredDate && state.workingDays.includes(preferredDate)) {
                state.selectedDate = preferredDate;
            } else if (!state.workingDays.includes(state.selectedDate)) {
                state.selectedDate = getFirstWorkingDayOfMonth(state.viewYear, state.viewMonth) || state.workingDays[0];
            }

            renderDeck();
            preloadWorkingDayMeta().finally(() => {
                const fallbackDate = state.workingDays.find((dateStr) => !getDeckMeta(dateStr).blocked) || state.selectedDate;
                if (!state.selectedDate || !state.workingDays.includes(state.selectedDate) || getDeckMeta(state.selectedDate).blocked) {
                    state.selectedDate = fallbackDate;
                    renderDeck();
                }

                if (state.selectedDate) {
                    animateAndLoad(state.selectedDate);
                } else {
                    renderEmptyState('No working days are available for the selected month.');
                }
            });
        }

        function getStatusClass(status) {
            const allowed = ['present', 'late', 'offset', 'leave', 'absent', 'holiday', 'suspended', 'not_checked_in'];
            return allowed.includes(status) ? status : 'not_checked_in';
        }

        function getStatusLabel(status) {
            const labels = {
                present: 'Present',
                late: 'Late',
                offset: 'Offset',
                leave: 'Leave',
                absent: 'Absent',
                holiday: 'Holiday',
                suspended: 'Suspended',
                not_checked_in: 'Not Checked In'
            };
            return labels[status] || 'Not Checked In';
        }

        function getDisplayValue(employee) {
            if (employee.time_in) {
                return formatTime(employee.time_in);
            }

            const labels = {
                offset: 'Offset',
                leave: 'On Leave',
                absent: 'Absent',
                holiday: 'Holiday',
                suspended: 'Suspended',
                not_checked_in: 'No Time In'
            };
            return labels[employee.status] || 'No Time In';
        }

        function buildSummary(employees) {
            const order = ['present', 'late', 'offset', 'leave', 'absent', 'holiday', 'suspended', 'not_checked_in'];
            const counts = {};
            order.forEach((status) => {
                counts[status] = 0;
            });

            employees.forEach((employee) => {
                counts[getStatusClass(employee.status)] += 1;
            });

            return order
                .filter((status) => counts[status] > 0)
                .map((status) => `
                    <div class="summary-pill status-${status}">
                        <span class="summary-pill-label">${escapeHtml(getStatusLabel(status))}</span>
                        <span class="summary-pill-value">${counts[status]}</span>
                    </div>
                `)
                .join('');
        }

        function updateBoardReport(dateStr, generatedAt, message) {
            const safeDate = dateStr || state.selectedDate;
            document.getElementById('boardReportTitle').textContent = `Employee Time-in updated as of ${formatUpdatedTimestamp(generatedAt)}`;
            document.getElementById('boardReportCopy').textContent = message || `Live public attendance summary for ${formatDisplayDate(safeDate)}.`;
        }

        function getEarlyBirds(employees) {
            const arrivals = employees.filter((employee) => employee.time_in);
            if (arrivals.length === 0) {
                return [];
            }

            const earliestMinutes = toTimeMinutes(arrivals[0].time_in);
            return arrivals.filter((employee) => toTimeMinutes(employee.time_in) === earliestMinutes);
        }

        function getBlockedDeckStatus(employees) {
            if (!Array.isArray(employees) || employees.length === 0) {
                return null;
            }

            const statuses = employees.map((employee) => getStatusClass(employee.status));
            if (statuses.every((status) => status === 'holiday')) {
                return 'holiday';
            }
            if (statuses.every((status) => status === 'suspended')) {
                return 'suspended';
            }
            return null;
        }

        function getDeckMeta(dateStr) {
            return state.dayMeta[dateStr] || { blocked: false, status: null, label: '' };
        }

        function preloadWorkingDayMeta() {
            const requestId = ++state.metaRequestId;
            const uncachedDates = state.workingDays.filter((dateStr) => !(dateStr in state.dayMeta));

            if (uncachedDates.length === 0) {
                renderDeck();
                return Promise.resolve();
            }

            return Promise.all(
                uncachedDates.map((dateStr) =>
                    fetch(`get_public_today.php?date=${encodeURIComponent(dateStr)}`, { cache: 'no-store' })
                        .then((response) => response.json())
                        .then((data) => ({ dateStr, data }))
                        .catch(() => ({ dateStr, data: null }))
                )
            ).then((results) => {
                if (requestId !== state.metaRequestId) {
                    return;
                }

                results.forEach(({ dateStr, data }) => {
                    const blockedStatus = data && data.success ? getBlockedDeckStatus(data.employees || []) : null;
                    state.dayMeta[dateStr] = {
                        blocked: Boolean(blockedStatus),
                        status: blockedStatus,
                        label: blockedStatus ? getStatusLabel(blockedStatus) : ''
                    };
                });

                renderDeck();
            });
        }

        function renderDeck() {
            const deck = document.getElementById('workingDayDeck');
            deck.innerHTML = state.workingDays.map((dateStr) => `
                ${(() => {
                    const meta = getDeckMeta(dateStr);
                    const blockedClass = meta.blocked ? `is-blocked deck-card-status-${meta.status}` : '';
                    const selectedClass = dateStr === state.selectedDate ? 'is-selected' : '';
                    const badgeHtml = meta.blocked ? `<span class="deck-card-badge">${escapeHtml(meta.label)}</span>` : '';
                    const disabledAttr = meta.blocked ? 'disabled aria-disabled="true"' : '';
                    return `
                <button type="button" class="deck-card ${selectedClass} ${blockedClass}" data-date="${dateStr}" ${disabledAttr}>
                    ${badgeHtml}
                    <span class="deck-card-month">${formatMonthShort(dateStr)}</span>
                    <span class="deck-card-day">${dateStr.slice(-2)}</span>
                    <span class="deck-card-weekday">${formatWeekdayShort(dateStr)}</span>
                </button>
                    `;
                })()}
            `).join('');

            const selectedCard = deck.querySelector('.deck-card.is-selected');
            if (selectedCard) {
                selectedCard.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        }

        function renderEmptyState(message) {
            updateBoardReport(
                state.selectedDate,
                new Date().toISOString(),
                `Live public attendance summary for ${formatDisplayDate(state.selectedDate)}.`
            );
            document.getElementById('timelineList').innerHTML = `
                <div class="empty-state">
                    <h3>No records for this working day</h3>
                    <p>${escapeHtml(message)}</p>
                </div>
            `;
            document.getElementById('summaryPills').innerHTML = `
                <div class="summary-pill status-not_checked_in">
                    <span class="summary-pill-label">Arrivals</span>
                    <span class="summary-pill-value">0</span>
                </div>
            `;
            document.getElementById('highlightBadge').textContent = 'Early Bird';
            document.getElementById('highlightName').classList.remove('is-multiple');
            document.getElementById('highlightName').textContent = 'Waiting for first arrival';
            document.getElementById('highlightTime').textContent = '--:--';
            document.getElementById('highlightCaption').textContent = 'Select a working day below to view the first recorded arrival for that date.';
        }

        function renderBoard(data) {
            const employees = Array.isArray(data.employees) ? data.employees : [];
            const earlyBirds = getEarlyBirds(employees);
            const firstEarlyBird = earlyBirds[0] || null;
            const earlyBirdIds = new Set(earlyBirds.map((employee) => employee.id));

            updateBoardReport(
                data.date,
                data.generated_at,
                `Live public attendance summary for ${formatDisplayDate(data.date)}.`
            );
            document.getElementById('summaryPills').innerHTML = buildSummary(employees) || `
                <div class="summary-pill status-not_checked_in">
                    <span class="summary-pill-label">Records</span>
                    <span class="summary-pill-value">0</span>
                </div>
            `;

            if (firstEarlyBird) {
                const statusClass = getStatusClass(firstEarlyBird.status);
                const highlightBadge = document.getElementById('highlightBadge');
                const highlightName = document.getElementById('highlightName');
                highlightBadge.textContent = earlyBirds.length > 1 ? 'Early Birds' : 'Early Bird';
                highlightName.classList.toggle('is-multiple', earlyBirds.length > 1);
                highlightName.innerHTML = earlyBirds
                    .map((employee) => escapeHtml(employee.name || 'Employee'))
                    .join('<br>');
                document.getElementById('highlightTime').textContent = formatTime(firstEarlyBird.time_in);
                document.getElementById('highlightCaption').textContent = earlyBirds.length > 1
                    ? `${earlyBirds.length} employees share the first recorded time-in for the selected day.`
                    : `${getStatusLabel(statusClass)} and first recorded time-in for the selected day.`;
            } else {
                document.getElementById('highlightBadge').textContent = 'Early Bird';
                document.getElementById('highlightName').classList.remove('is-multiple');
                document.getElementById('highlightName').textContent = 'No first arrival yet';
                document.getElementById('highlightTime').textContent = '--:--';
                document.getElementById('highlightCaption').textContent = 'There is no recorded time-in for the selected working day yet.';
            }

            if (employees.length === 0) {
                renderEmptyState('No employee data is available for this date.');
                return;
            }

            document.getElementById('timelineList').innerHTML = employees.map((employee, index) => {
                const statusClass = getStatusClass(employee.status);
                const isEarly = earlyBirdIds.has(employee.id);
                const rank = isEarly ? 1 : index - earlyBirds.length + 2;
                return `
                    <div class="timeline-row status-${statusClass} ${isEarly ? 'is-early' : ''}" style="animation-delay:${index * 0.04}s;">
                        <div class="timeline-rank">${rank}</div>
                        <div>
                            <div class="timeline-name">${escapeHtml(employee.name || 'Employee')}</div>
                            <div class="timeline-badges">
                                <span class="status-pill status-${statusClass}">${escapeHtml(getStatusLabel(statusClass))}</span>
                                ${isEarly ? '<span class="status-pill early-pill">Early Bird</span>' : ''}
                            </div>
                        </div>
                        <div class="timeline-value value-${statusClass}">${escapeHtml(getDisplayValue(employee))}</div>
                    </div>
                `;
            }).join('');
        }

        function animateAndLoad(dateStr) {
            const boardStage = document.getElementById('boardStage');
            const requestId = ++state.requestId;

            boardStage.classList.add('is-switching');

            window.setTimeout(() => {
                fetch(`get_public_today.php?date=${encodeURIComponent(dateStr)}`, { cache: 'no-store' })
                    .then((response) => response.json())
                    .then((data) => {
                        if (requestId !== state.requestId) {
                            return;
                        }
                        if (!data.success) {
                            throw new Error(data.message || 'Unable to load date data.');
                        }
                        renderBoard(data);
                    })
                    .catch(() => {
                        if (requestId !== state.requestId) {
                            return;
                        }
                        renderEmptyState('The public board could not load that day right now. Please try another date or refresh again.');
                    })
                    .finally(() => {
                        if (requestId === state.requestId) {
                            boardStage.classList.remove('is-switching');
                        }
                    });
            }, 150);
        }

        function selectDate(dateStr) {
            const meta = getDeckMeta(dateStr);
            if (meta.blocked) {
                return;
            }
            state.selectedDate = dateStr;
            renderDeck();
            animateAndLoad(dateStr);
        }

        function moveSelectedDay(step) {
            const currentIndex = state.workingDays.indexOf(state.selectedDate);
            if (currentIndex === -1) {
                return;
            }

            let nextIndex = currentIndex + step;
            while (nextIndex >= 0 && nextIndex < state.workingDays.length) {
                const nextDate = state.workingDays[nextIndex];
                if (!getDeckMeta(nextDate).blocked) {
                    selectDate(nextDate);
                    return;
                }
                nextIndex += step;
            }
        }

        document.getElementById('workingDayDeck').addEventListener('click', (event) => {
            const card = event.target.closest('.deck-card');
            if (!card) {
                return;
            }
            if (card.disabled || card.getAttribute('aria-disabled') === 'true') {
                return;
            }
            const dateStr = card.getAttribute('data-date');
            if (dateStr && dateStr !== state.selectedDate) {
                selectDate(dateStr);
            }
        });

        document.getElementById('prevDayBtn').addEventListener('click', () => moveSelectedDay(-1));
        document.getElementById('nextDayBtn').addEventListener('click', () => moveSelectedDay(1));
        document.getElementById('refreshSelectedBtn').addEventListener('click', () => animateAndLoad(state.selectedDate));

        const adminMonthSelect = document.getElementById('adminMonthSelect');
        const adminYearSelect = document.getElementById('adminYearSelect');
        if (adminMonthSelect && adminYearSelect) {
            const updateAdminMonthView = () => {
                state.viewMonth = parseInt(adminMonthSelect.value, 10);
                state.viewYear = parseInt(adminYearSelect.value, 10);
                refreshVisibleMonth();
            };

            adminMonthSelect.addEventListener('change', updateAdminMonthView);
            adminYearSelect.addEventListener('change', updateAdminMonthView);
        }

        (function init() {
            state.selectedDate = getInitialDate();
            const initialDate = new Date(state.selectedDate + 'T12:00:00');
            state.viewYear = initialDate.getFullYear();
            state.viewMonth = initialDate.getMonth();
            refreshVisibleMonth(state.selectedDate);
        })();
    </script>
</body>
</html>
