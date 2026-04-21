<?php
require_once 'config.php';

$is_logged_in = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROMD Attendance - Public View</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(96, 165, 250, 0.25), transparent 30%),
                linear-gradient(180deg, #eff6ff 0%, #dbeafe 45%, #bfdbfe 100%);
            color: #0f172a;
            min-height: 100vh;
        }

        .public-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .public-header {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 22px 0 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .public-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #0f172a;
            font-weight: 700;
            font-size: 20px;
        }

        .public-brand-mark {
            width: 58px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .public-brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .public-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.05;
        }

        .public-brand-title {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.03em;
        }

        .public-brand-subtitle {
            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .public-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .public-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(37, 99, 235, 0.15);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .public-btn:hover {
            transform: translateY(-1px);
        }

        .public-btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.28);
        }

        .public-btn-secondary {
            color: #1d4ed8;
            background: rgba(255, 255, 255, 0.75);
        }

        .public-main {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 16px 0 32px;
            display: grid;
            gap: 24px;
        }

        .hero-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%);
            color: #fff;
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 28px 60px rgba(30, 64, 175, 0.28);
        }

        .hero-card::before,
        .hero-card::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-card::before {
            width: 260px;
            height: 260px;
            top: -80px;
            right: -90px;
        }

        .hero-card::after {
            width: 180px;
            height: 180px;
            bottom: -50px;
            left: -40px;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(290px, 0.8fr);
            gap: 24px;
            align-items: stretch;
        }

        .hero-title {
            margin: 0 0 12px;
            font-size: clamp(34px, 4vw, 52px);
            line-height: 1.02;
            letter-spacing: -0.04em;
        }

        .hero-copy {
            margin: 0;
            max-width: 620px;
            font-size: 16px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.82);
        }

        .hero-meta {
            margin-top: 22px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .hero-meta-chip {
            padding: 10px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.14);
            min-width: 150px;
        }

        .hero-meta-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(191, 219, 254, 0.95);
            margin-bottom: 5px;
        }

        .hero-meta-value {
            font-size: 18px;
            font-weight: 700;
        }

        .hero-status-bar {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .hero-status-chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.12);
            color: #eff6ff;
        }

        .spotlight-card {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 24px;
            padding: 24px;
            backdrop-filter: blur(10px);
            min-height: 260px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .spotlight-rank {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(254, 240, 138, 0.18);
            border: 1px solid rgba(254, 240, 138, 0.36);
            color: #fef08a;
            font-weight: 700;
            margin-top: 14px;
        }

        .spotlight-name {
            margin: 18px 0 8px;
            font-size: clamp(28px, 3vw, 36px);
            line-height: 1.08;
        }

        .spotlight-time {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .board-card {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 28px;
            box-shadow: 0 20px 45px rgba(148, 163, 184, 0.18);
            overflow: hidden;
            backdrop-filter: blur(14px);
        }

        .board-header {
            padding: 24px 26px 20px;
            border-bottom: 1px solid #dbeafe;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 12px;
        }

        .board-title {
            margin: 0;
            font-size: 24px;
            color: #0f172a;
        }

        .board-subtitle {
            margin: 6px 0 0;
            color: #475569;
            font-size: 14px;
        }

        .board-refresh {
            font-size: 13px;
            color: #1d4ed8;
            font-weight: 600;
            white-space: nowrap;
        }

        .timeline-list {
            padding: 10px 18px 22px;
            display: grid;
            gap: 12px;
        }

        .timeline-row {
            display: grid;
            grid-template-columns: 74px minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 16px 18px;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.92), rgba(255, 255, 255, 0.95));
            border: 1px solid #dbeafe;
        }

        .timeline-row.is-early {
            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 65%, #ffffff 100%);
            border-color: #93c5fd;
            box-shadow: 0 16px 30px rgba(59, 130, 246, 0.16);
        }

        .timeline-rank {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .timeline-row.is-early .timeline-rank {
            color: #1e3a8a;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-color: #fcd34d;
        }

        .timeline-name {
            font-size: 19px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .timeline-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .timeline-chip {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .timeline-chip.rank {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .timeline-chip.early {
            color: #92400e;
            background: #fef3c7;
        }

        .timeline-chip.status-present,
        .hero-status-chip.status-present {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .timeline-chip.status-late,
        .hero-status-chip.status-late {
            color: #9a3412;
            background: #fed7aa;
        }

        .timeline-chip.status-offset,
        .hero-status-chip.status-offset {
            color: #6d28d9;
            background: #ede9fe;
        }

        .timeline-chip.status-leave,
        .hero-status-chip.status-leave {
            color: #0f766e;
            background: #ccfbf1;
        }

        .timeline-chip.status-absent,
        .hero-status-chip.status-absent {
            color: #b91c1c;
            background: #fee2e2;
        }

        .timeline-chip.status-holiday,
        .hero-status-chip.status-holiday {
            color: #065f46;
            background: #dcfce7;
        }

        .timeline-chip.status-suspended,
        .hero-status-chip.status-suspended {
            color: #475569;
            background: #e2e8f0;
        }

        .timeline-chip.status-not_checked_in,
        .hero-status-chip.status-not_checked_in {
            color: #4338ca;
            background: #e0e7ff;
        }

        .timeline-row.status-present {
            border-color: #bfdbfe;
            background: linear-gradient(135deg, #eff6ff, #ffffff);
        }

        .timeline-row.status-late {
            border-color: #fdba74;
            background: linear-gradient(135deg, #fff7ed, #ffffff);
        }

        .timeline-row.status-offset {
            border-color: #c4b5fd;
            background: linear-gradient(135deg, #f5f3ff, #ffffff);
        }

        .timeline-row.status-leave {
            border-color: #99f6e4;
            background: linear-gradient(135deg, #f0fdfa, #ffffff);
        }

        .timeline-row.status-absent {
            border-color: #fecaca;
            background: linear-gradient(135deg, #fef2f2, #ffffff);
        }

        .timeline-row.status-holiday {
            border-color: #86efac;
            background: linear-gradient(135deg, #f0fdf4, #ffffff);
        }

        .timeline-row.status-suspended {
            border-color: #cbd5e1;
            background: linear-gradient(135deg, #f8fafc, #ffffff);
        }

        .timeline-row.status-not_checked_in {
            border-color: #c7d2fe;
            background: linear-gradient(135deg, #eef2ff, #ffffff);
        }

        .timeline-time {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.03em;
            white-space: nowrap;
        }

        .timeline-time.status-late {
            color: #c2410c;
        }

        .timeline-time.status-offset {
            color: #6d28d9;
        }

        .timeline-time.status-leave {
            color: #0f766e;
        }

        .timeline-time.status-absent {
            color: #b91c1c;
        }

        .timeline-time.status-holiday {
            color: #15803d;
        }

        .timeline-time.status-suspended {
            color: #475569;
        }

        .timeline-time.status-not_checked_in {
            color: #4338ca;
        }

        .empty-state {
            padding: 48px 24px 56px;
            text-align: center;
            color: #475569;
        }

        .empty-state-title {
            margin: 0 0 10px;
            font-size: 24px;
            color: #0f172a;
        }

        .empty-state-copy {
            margin: 0 auto;
            max-width: 520px;
            line-height: 1.7;
        }

        .public-footer {
            width: min(1180px, calc(100% - 32px));
            margin: auto auto 0;
            padding: 0 0 24px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            color: #475569;
            font-size: 13px;
        }

        @media (max-width: 920px) {
            .hero-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 720px) {
            .public-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .hero-card {
                padding: 24px;
            }

            .board-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .timeline-row {
                grid-template-columns: 58px minmax(0, 1fr);
            }

            .timeline-time {
                grid-column: 1 / -1;
                padding-left: 72px;
                font-size: 24px;
            }
        }

        @media (max-width: 520px) {
            .public-main,
            .public-header,
            .public-footer {
                width: min(100% - 20px, 1180px);
            }

            .hero-title {
                font-size: 32px;
            }

            .spotlight-time {
                font-size: 32px;
            }

            .timeline-name {
                font-size: 17px;
            }
        }
    </style>
</head>
<body>
    <div class="public-shell">
        <header class="public-header">
            <a href="landingpage.php" class="public-brand">
                <span class="public-brand-mark">
                    <img src="assets/img/tesda-logo.png" alt="TESDA logo">
                </span>
                <span class="public-brand-text">
                    <span class="public-brand-title">Regional Operations Management Division</span>
                    <span class="public-brand-subtitle">Attendance Public Monitoring Board</span>
                </span>
            </a>
            <div class="public-actions">
                <a href="javascript:void(0)" class="public-btn public-btn-secondary" id="manualRefreshBtn">Refresh</a>
                <?php if ($is_logged_in): ?>
                    <a href="index.php" class="public-btn public-btn-primary">Open Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="public-btn public-btn-primary">Admin Login</a>
                <?php endif; ?>
            </div>
        </header>

        <main class="public-main">
            <section class="hero-card">
                <div class="hero-grid">
                    <div>
                        <h1 class="hero-title">Today's attendance timeline.</h1>
                        <p class="hero-copy">
                            A live public board for today only. The first employee with a recorded time-in is highlighted as the Early Bird, while late, absent, offset, leave, and other statuses appear with their own colors and badges.
                        </p>
                        <div class="hero-meta">
                            <div class="hero-meta-chip">
                                <div class="hero-meta-label">Today</div>
                                <div class="hero-meta-value" id="todayDateLabel">Loading...</div>
                            </div>
                        </div>
                        <div class="hero-status-bar" id="heroStatusBar"></div>
                    </div>
                    <div class="spotlight-card" id="earlyBirdCard">
                        <div>
                            <div class="spotlight-rank">Early Bird</div>
                            <h2 class="spotlight-name" id="earlyBirdName">Waiting for today's first time-in</h2>
                            <div class="spotlight-time" id="earlyBirdTime">--:--</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="board-card">
                <div class="board-header">
                    <div>
                        <h2 class="board-title">Arrival Timeline</h2>
                        <p class="board-subtitle">Only today's recorded time-in entries are shown here.</p>
                    </div>
                    <div class="board-refresh" id="totalArrivalLabel">Loading today's arrivals...</div>
                </div>
                <div class="timeline-list" id="timelineList">
                    <div class="empty-state">
                        <h3 class="empty-state-title">Loading today's time-in records</h3>
                        <p class="empty-state-copy">Please wait while the public board retrieves the latest arrival list.</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="public-footer">
            <span>&copy; <?php echo date('Y'); ?> ROMD Attendance</span>
        </footer>
    </div>

    <script>
        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDisplayDate(dateStr) {
            const dateObj = dateStr ? new Date(dateStr + 'T12:00:00') : new Date();
            return dateObj.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function formatDisplayTime(timeValue) {
            if (!timeValue) return '--:--';
            const parts = String(timeValue).split(':');
            const hours = parseInt(parts[0], 10) || 0;
            const minutes = parseInt(parts[1], 10) || 0;
            const dateObj = new Date();
            dateObj.setHours(hours, minutes, 0, 0);
            return dateObj.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function getOrdinal(rank) {
            if (rank % 100 >= 11 && rank % 100 <= 13) return rank + 'th';
            if (rank % 10 === 1) return rank + 'st';
            if (rank % 10 === 2) return rank + 'nd';
            if (rank % 10 === 3) return rank + 'rd';
            return rank + 'th';
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
                return formatDisplayTime(employee.time_in);
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

        function buildStatusSummary(employees) {
            const order = ['present', 'late', 'offset', 'leave', 'absent', 'holiday', 'suspended', 'not_checked_in'];
            const counts = {};
            order.forEach((status) => {
                counts[status] = 0;
            });

            employees.forEach((employee) => {
                const key = getStatusClass(employee.status);
                counts[key] = (counts[key] || 0) + 1;
            });

            return order
                .filter((status) => counts[status] > 0)
                .map((status) => `<span class="hero-status-chip status-${status}">${counts[status]} ${escapeHtml(getStatusLabel(status))}</span>`)
                .join('');
        }

        function renderEmptyState(message) {
            document.getElementById('timelineList').innerHTML = `
                <div class="empty-state">
                    <h3 class="empty-state-title">No time-in records yet today</h3>
                    <p class="empty-state-copy">${message}</p>
                </div>
            `;
            document.getElementById('earlyBirdName').textContent = 'Waiting for today\'s first time-in';
            document.getElementById('earlyBirdTime').textContent = '--:--';
            document.getElementById('heroStatusBar').innerHTML = '';
            document.getElementById('totalArrivalLabel').textContent = '0 arrivals today';
        }

        function renderBoard(data) {
            const employees = Array.isArray(data.employees) ? data.employees : [];
            document.getElementById('todayDateLabel').textContent = formatDisplayDate(data.date);

            if (employees.length === 0) {
                renderEmptyState('Once an employee checks in today, the public timeline will appear here.');
                return;
            }

            const arrivals = employees.filter((employee) => employee.time_in);
            const earlyBird = arrivals.length > 0 ? arrivals[0] : null;

            document.getElementById('heroStatusBar').innerHTML = buildStatusSummary(employees);

            if (earlyBird) {
                document.getElementById('earlyBirdName').textContent = earlyBird.name || 'Early Bird';
                document.getElementById('earlyBirdTime').textContent = formatDisplayTime(earlyBird.time_in);
            } else {
                document.getElementById('earlyBirdName').textContent = 'No recorded time-in yet';
                document.getElementById('earlyBirdTime').textContent = '--:--';
            }

            document.getElementById('totalArrivalLabel').textContent = arrivals.length + (arrivals.length === 1 ? ' arrival today' : ' arrivals today');

            const timelineHtml = employees.map((employee, index) => {
                const rank = index + 1;
                const statusClass = getStatusClass(employee.status);
                const isEarly = earlyBird && employee.id === earlyBird.id;
                const displayValue = getDisplayValue(employee);
                return `
                    <div class="timeline-row status-${statusClass} ${isEarly ? 'is-early' : ''}">
                        <div class="timeline-rank">${rank}</div>
                        <div>
                            <div class="timeline-name">${escapeHtml(employee.name || 'Employee')}</div>
                            <div class="timeline-badges">
                                <span class="timeline-chip status-${statusClass}">${escapeHtml(getStatusLabel(statusClass))}</span>
                                ${isEarly ? '<span class="timeline-chip early">Early Bird</span>' : ''}
                            </div>
                        </div>
                        <div class="timeline-time status-${statusClass}">${escapeHtml(displayValue)}</div>
                    </div>
                `;
            }).join('');

            document.getElementById('timelineList').innerHTML = timelineHtml;
        }

        function loadPublicBoard() {
            fetch('get_public_today.php', { cache: 'no-store' })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success) {
                        throw new Error(data.message || 'Unable to load public board.');
                    }
                    renderBoard(data);
                })
                .catch(() => {
                    renderEmptyState('The public board could not load right now. Please refresh again in a moment.');
                });
        }

        document.getElementById('manualRefreshBtn').addEventListener('click', loadPublicBoard);
        loadPublicBoard();
        setInterval(loadPublicBoard, 30000);
    </script>
</body>
</html>
