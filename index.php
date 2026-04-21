<?php

require_once 'config.php';
if (!isLoggedIn()) {
    header('Location: landingpage.php');
    exit();
}
requireLogin();

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';
$app_settings = getAppSettings();
$system_name = $app_settings['system_name'] ?? 'ROMD Attendance';
$late_time_threshold = $app_settings['late_time_threshold'] ?? '08:00';
$page_title = 'Dashboard - ' . $system_name;
$current_page = 'dashboard';
include 'includes/header.php';
?>
    <div class="container">
        <!-- Monitoring Dashboard -->
        <div class="welcome-card">
            <h2>Welcome to <?php echo htmlspecialchars($system_name); ?> Dashboard</h2>
            <p>You have successfully logged in. Use this dashboard to manage attendance records and update live system settings.</p>
        </div>
        <div class="monitoring-dashboard">
            <div class="dashboard-header">
                <div>
                    <h2>📊 Today's Monitoring Dashboard</h2>
                    <div class="dashboard-date" id="dashboardDate"></div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="refresh-btn" onclick="openReportPreview()" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);">📋 Preview / Copy Time-in Report</button>
                    <button class="refresh-btn" onclick="loadDashboard()">🔄 Refresh</button>
                </div>
            </div>
            
            <div class="dashboard-split">
                <div class="dashboard-left">
                    <div class="dashboard-section-header">
                        <h3 class="dashboard-section-title">👑 Employees Today</h3>
                        <button type="button" id="employeeViewToggle" class="employee-view-toggle" onclick="toggleEmployeeView()">
                            <span id="employeeViewArrow">▶</span>
                            <span id="employeeViewText">Show All Employees</span>
                        </button>
                    </div>
                    <div class="dashboard-view-note" id="employeeViewNote">Showing: Early Bird only</div>
                    <div id="employeesList" class="employees-list">
                        <div class="loading">Loading employee data...</div>
                    </div>
                </div>
                <div class="dashboard-right">
                    <h3 class="dashboard-section-title">Quick Stats</h3>
                    <div id="dashboardStats" class="stats-grid">
                        <div class="loading">Loading statistics...</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Time-in Report Preview Modal -->
        <div id="reportPreviewModal" class="modal" style="display: none;">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3>📋 Time-in Report (Preview)</h3>
                    <span class="close" onclick="closeReportPreview()">&times;</span>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 10px; color: #666; font-size: 14px;">Copy this text to share today's time-in and absent list.</p>
                    <textarea id="reportPreviewText" readonly style="width: 100%; min-height: 320px; font-family: Consolas, monospace; font-size: 13px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; resize: vertical;"></textarea>
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <button type="button" id="copyReportBtn" class="btn btn-primary" onclick="copyReportToClipboard()">Copy to clipboard</button>
                        <button type="button" class="btn btn-secondary" onclick="closeReportPreview()">Close</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Task Assignment Modal -->
        <div id="taskModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalEmployeeName">Assign Task</h3>
                    <span class="close" onclick="closeTaskModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="taskMessage" class="message"></div>
                    
                    <div class="form-group">
                        <label>Task Description:</label>
                        <textarea id="taskDescription" placeholder="Enter task description..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <select id="taskStatus">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <button class="btn btn-primary" onclick="saveTask()" style="width: 100%;">Add Task</button>
                    
                    <div class="tasks-list" id="existingTasksList">
                        <h4 style="margin-top: 25px; margin-bottom: 15px; color: #333;">Existing Tasks:</h4>
                        <div id="existingTasks" style="max-height: 300px; overflow-y: auto;">
                            <div class="loading">Loading tasks...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="month-year-selector">
            <div class="selector-header">
                <h2>Select Month & Year</h2>
                <p>Click on a month to view attendance records for that period</p>
                <div class="selector-actions selector-actions-primary">
                    <a href="early_bird.php" class="btn btn-primary">🐦 Early Bird / Early Time-In Report</a>
                    <a href="first_arrival.php" class="btn btn-primary">🥇 First to Arrive (Monthly)</a>
                    <a href="employee_summary.php" class="btn btn-primary">👤 Employee Summary Report (Per Month)</a>
                    <a href="semester_report.php?period=janjun" class="btn btn-primary">📄 Print 6-Month Report (January – June)</a>
                    <a href="semester_report.php?period=juldec" class="btn btn-print">📄 Print 6-Month Report (July – December)</a>
                </div>
                <div class="selector-actions selector-actions-secondary">
                    <a href="call_times.php" class="btn btn-secondary">
                        ⏰ Manage Call Times / Events
                    </a>
                    <?php if ($role === 'admin'): ?>
                        <a href="ttis_import.php" class="btn btn-secondary">
                            📤 TTIS Spreadsheet Import
                        </a>
                        <a href="admin_config.php" class="btn btn-secondary">
                            ⚙ Admin Configuration
                        </a>
                    <?php endif; ?>
                    <a href="archive_employees.php" class="btn btn-secondary">
                        📁 Archive Employees (Resigned)
                    </a>
                    <a href="test_employee.php" class="btn btn-secondary">
                        🧪 Test Employee Computation
                    </a>
                </div>
            </div>
            
            <?php
            // Get current year and month
            $currentYear = (int) date('Y');
            $currentMonth = date('n');
            
            // Generate years (always include 2025 for record review)
            $startYear = min(2025, $currentYear);
            $endYear = $currentYear + 1;
            $years = range($startYear, $endYear);
            $months = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];
            
            // Display years in reverse order (most recent first)
            foreach (array_reverse($years) as $year):
            ?>
                <div class="year-section">
                    <div class="year-title"><?php echo $year; ?></div>
                    <div class="months-grid">
                        <?php foreach ($months as $monthNum => $monthName): 
                            $isCurrent = ($year == $currentYear && $monthNum == $currentMonth);
                        ?>
                            <a href="month.php?month=<?php echo $monthNum; ?>&year=<?php echo $year; ?>" 
                               class="month-item <?php echo $isCurrent ? 'current-month' : ''; ?>">
                                <div class="month-name"><?php echo $monthName; ?></div>
                                <div class="month-number"><?php echo str_pad($monthNum, 2, '0', STR_PAD_LEFT); ?>/<?php echo $year; ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        const lateThresholdMinutes = toMinutes('<?php echo htmlspecialchars($late_time_threshold, ENT_QUOTES); ?>');

        // Set today's date
        document.getElementById('dashboardDate').textContent = new Date().toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        let lastDashboardData = null;
        let showEarlyBirdOnly = true;

        function toMinutes(timeValue) {
            if (!timeValue) return 0;
            const parts = String(timeValue).split(':');
            const hour = parseInt(parts[0], 10) || 0;
            const minute = parseInt(parts[1], 10) || 0;
            return (hour * 60) + minute;
        }

        function isLateByStatusOrTime(emp) {
            if (!emp) return false;
            if (emp.status === 'late') return true;
            if (!emp.time_in) return false;
            return toMinutes(emp.time_in) > lateThresholdMinutes;
        }

        function updateEmployeeToggleUI() {
            const arrow = document.getElementById('employeeViewArrow');
            const text = document.getElementById('employeeViewText');
            const note = document.getElementById('employeeViewNote');
            if (!arrow || !text || !note) return;

            if (showEarlyBirdOnly) {
                arrow.textContent = '▶';
                text.textContent = 'Show All Employees';
                note.textContent = 'Showing: First time-in only';
            } else {
                arrow.textContent = '▼';
                text.textContent = 'Show First Time-In Only';
                note.textContent = 'Showing: All employees (Absent moved to bottom)';
            }
        }

        function renderEmployeesList(data) {
            const employeesDiv = document.getElementById('employeesList');
            if (!employeesDiv) return;

            if (!data.employees || data.employees.length === 0) {
                employeesDiv.innerHTML = '<div class="loading">No employees found.</div>';
                return;
            }

            const absentMap = new Map();
            if (Array.isArray(data.absent_employees)) {
                data.absent_employees.forEach(emp => {
                    absentMap.set(emp.id, emp);
                });
            }

            const allEmployees = data.employees.slice();
            const nonAbsent = allEmployees.filter(emp => emp.status !== 'absent');
            const absentEmployees = allEmployees.filter(emp => emp.status === 'absent');
            const checkedInNonAbsent = nonAbsent.filter(emp => emp.time_in !== null && emp.time_in !== '');
            let earlyBirdOnly = [];
            if (checkedInNonAbsent.length > 0) {
                const earliestMinutes = Math.min(...checkedInNonAbsent.map(emp => toMinutes(emp.time_in)));
                // If multiple employees share the same earliest time, show all of them.
                earlyBirdOnly = checkedInNonAbsent.filter(emp => toMinutes(emp.time_in) === earliestMinutes);
            }

            const visibleEmployees = showEarlyBirdOnly
                ? earlyBirdOnly
                : nonAbsent.concat(absentEmployees); // keep absent at bottom

            if (visibleEmployees.length === 0) {
                employeesDiv.innerHTML = showEarlyBirdOnly
                    ? '<div class="loading">No first time-in yet today.</div>'
                    : '<div class="loading">No employees found.</div>';
                return;
            }

            let employeesHTML = '';
            visibleEmployees.forEach(emp => {
                const hasCheckedIn = emp.time_in !== null && emp.time_in !== '';
                const isLate = isLateByStatusOrTime(emp);
                const isAbsent = emp.status === 'absent';
                const isEarlyBird = earlyBirdOnly.some(earlyEmp => earlyEmp.id === emp.id);

                let cardClass = isAbsent
                    ? 'not-checked-in'
                    : (isLate ? 'late' : (hasCheckedIn ? '' : 'not-checked-in'));
                if (isEarlyBird) {
                    cardClass += ' early-bird';
                }

                let statusBadge = '';
                if (isAbsent) {
                    statusBadge = '<span class="employee-status status-not-checked-in">Absent</span>';
                } else if (hasCheckedIn) {
                    statusBadge = `<span class="employee-status ${isLate ? 'status-late' : 'status-present'}">${isLate ? 'Late' : 'Present'}</span>`;
                } else {
                    statusBadge = '<span class="employee-status status-not-checked-in">Not Checked In</span>';
                }
                if (isEarlyBird) {
                    statusBadge += '<span class="employee-status status-early-bird">Early Bird</span>';
                }

                const timeDisplay = isAbsent
                    ? 'Marked as Absent'
                    : (emp.time_in ? emp.time_in.substring(0, 5) : 'Not checked in');
                const timeClass = isAbsent ? '' : (isLate ? 'late' : (hasCheckedIn ? 'present' : ''));

                const taskSource = (isAbsent && absentMap.has(emp.id)) ? absentMap.get(emp.id) : emp;
                const tasks = (taskSource && Array.isArray(taskSource.tasks)) ? taskSource.tasks : [];
                let tasksHTML = '';
                if (tasks.length > 0) {
                    tasks.forEach(task => {
                        tasksHTML += `
                            <div class="task-item">
                                <span class="task-description">${task.description}</span>
                                <span class="task-status ${task.status}">${task.status.replace('_', ' ').toUpperCase()}</span>
                            </div>
                        `;
                    });
                } else {
                    tasksHTML = '<div class="no-tasks">No tasks assigned for today</div>';
                }

                const employeeNameSafe = (emp.name || '').replace(/'/g, "\\'");
                employeesHTML += `
                    <div class="employee-card ${cardClass}">
                        ${isEarlyBird ? '<div class="employee-spotlight">👑 First time-in today</div>' : ''}
                        <div class="employee-header">
                            <div>
                                <span class="employee-name" onclick="openTaskModal(${emp.id}, '${employeeNameSafe}')" title="Click to assign task">${emp.name}</span>
                                ${statusBadge}
                            </div>
                            <div class="employee-time ${timeClass}" ${isAbsent ? 'style="color: #721c24; font-weight: 600;"' : ''}>${timeDisplay}</div>
                        </div>
                        <div class="tasks-section">
                            <div class="tasks-title">📋 Today's Tasks:</div>
                            ${tasksHTML}
                        </div>
                    </div>
                `;
            });

            employeesDiv.innerHTML = employeesHTML;
        }

        function toggleEmployeeView() {
            showEarlyBirdOnly = !showEarlyBirdOnly;
            updateEmployeeToggleUI();
            if (lastDashboardData) {
                renderEmployeesList(lastDashboardData);
            }
        }

        // Load dashboard data
        function loadDashboard() {
            const statsDiv = document.getElementById('dashboardStats');
            const employeesDiv = document.getElementById('employeesList');
            
            statsDiv.innerHTML = '<div class="loading">Loading statistics...</div>';
            employeesDiv.innerHTML = '<div class="loading">Loading employee data...</div>';
            
            fetch('get_today_dashboard.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        lastDashboardData = data;
                        // Update statistics
                        const absentCount = data.absent_count || 0;
                        const notCheckedInCount = data.total_employees - data.checked_in_count - absentCount;
                        let statsHTML = `
                            <div class="stat-card">
                                <div class="stat-number">${data.total_employees}</div>
                                <div class="stat-label">Total Employees</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-number">${data.checked_in_count}</div>
                                <div class="stat-label">Present Today</div>
                            </div>
                            <div class="stat-card danger">
                                <div class="stat-number">${absentCount}</div>
                                <div class="stat-label">Absent Today</div>
                            </div>
                            <div class="stat-card ${notCheckedInCount > 0 ? 'warning' : 'success'}">
                                <div class="stat-number">${notCheckedInCount}</div>
                                <div class="stat-label">Not Checked In</div>
                            </div>
                        `;
                        statsDiv.innerHTML = statsHTML;
                        
                        renderEmployeesList(data);
                    } else {
                        statsDiv.innerHTML = '<div class="loading">Error loading data: ' + (data.message || 'Unknown error') + '</div>';
                        employeesDiv.innerHTML = '<div class="loading">Error loading employee data.</div>';
                    }
                })
                .catch(error => {
                    statsDiv.innerHTML = '<div class="loading">Error: ' + error.message + '</div>';
                    employeesDiv.innerHTML = '<div class="loading">Error loading employee data.</div>';
                    console.error('Error:', error);
                });
        }
        
        updateEmployeeToggleUI();

        // Load dashboard on page load
        loadDashboard();
        
        // Auto-refresh every 30 seconds
        setInterval(loadDashboard, 30000);
        
        // Time-in Report Preview / Copy
        function openReportPreview() {
            if (!lastDashboardData || !lastDashboardData.employees) {
                alert('Please wait for today\'s data to load, then click Refresh if needed.');
                return;
            }
            const reportText = buildTimeInReportText(lastDashboardData);
            document.getElementById('reportPreviewText').value = reportText;
            document.getElementById('reportPreviewModal').style.display = 'block';
        }
        
        function closeReportPreview() {
            document.getElementById('reportPreviewModal').style.display = 'none';
        }
        
        function buildTimeInReportText(data) {
            const dateStr = data.date || new Date().toISOString().split('T')[0];
            const d = new Date(dateStr + 'T12:00:00');
            const dateFormatted = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const dayName = d.toLocaleDateString('en-US', { weekday: 'long' });
            const now = new Date();
            const hour = now.getHours();
            const min = now.getMinutes();
            const ampm = hour >= 12 ? 'pm' : 'am';
            const hour12 = hour % 12 || 12;
            const timeAsOf = hour12 + ':' + (min < 10 ? '0' : '') + min + ' ' + ampm;
            
            const formatTime = (t) => {
                if (!t) return '';
                const parts = (t + '').split(':');
                const h = parseInt(parts[0], 10);
                const m = (parts[1] || '00').replace(/\D/g, '').slice(0, 2) || '00';
                return h + ':' + (m.length === 1 ? '0' + m : m);
            };
            const toMinutes = (t) => {
                const parts = (t || '').split(':');
                return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
            };
            const isLate = (t) => toMinutes(t) > lateThresholdMinutes;
            
            const all = data.employees || [];
            const withTime = all.filter(emp => emp.time_in).slice().sort((a, b) => toMinutes(a.time_in) - toMinutes(b.time_in));
            const earlyBird = withTime.filter(emp => !isLate(emp.time_in));
            const late = withTime.filter(emp => isLate(emp.time_in));
            
            const earlyBirdNames = earlyBird.map(emp => emp.name + ' - ' + formatTime(emp.time_in));
            const earlyBirdLines = earlyBirdNames.length > 0
                ? earlyBirdNames[0] + (earlyBirdNames.length > 1 ? '\n\n' + earlyBirdNames.slice(1).join('\n') : '')
                : '';
            const lateLines = late.map(emp => emp.name + ' - ' + formatTime(emp.time_in)).join('\n');
            const absentLines = all.filter(emp => !emp.time_in).map(emp => emp.name).join('\n');
            
            let out = dateFormatted + ' (' + dayName + ') Time in as of ' + timeAsOf + '\n\nEarly Bird\n' + (earlyBirdLines || '(None)');
            out += '\n\nLate\n' + (lateLines || '(None)');
            out += '\n\nAbsent\n' + (absentLines || '(None)');
            return out;
        }
        
        function copyReportToClipboard() {
            const ta = document.getElementById('reportPreviewText');
            ta.select();
            ta.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                const btn = document.getElementById('copyReportBtn');
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(() => { btn.textContent = orig; }, 2000);
            } catch (err) {
                navigator.clipboard.writeText(ta.value).then(() => {
                    const btn = document.getElementById('copyReportBtn');
                    btn.textContent = 'Copied!';
                    setTimeout(() => { btn.textContent = 'Copy to clipboard'; }, 2000);
                }).catch(() => alert('Could not copy. Please select and copy manually.'));
            }
        }
        
        // Task Assignment Modal Functions
        let currentEmployeeId = 0;
        let currentEmployeeName = '';
        const today = new Date().toISOString().split('T')[0];
        
        function openTaskModal(employeeId, employeeName) {
            currentEmployeeId = employeeId;
            currentEmployeeName = employeeName;
            
            document.getElementById('modalEmployeeName').textContent = `Assign Task - ${employeeName}`;
            document.getElementById('taskDescription').value = '';
            document.getElementById('taskStatus').value = 'pending';
            document.getElementById('taskMessage').style.display = 'none';
            document.getElementById('taskModal').style.display = 'block';
            
            loadExistingTasks();
        }
        
        function closeTaskModal() {
            document.getElementById('taskModal').style.display = 'none';
            currentEmployeeId = 0;
            currentEmployeeName = '';
        }
        
        function loadExistingTasks() {
            const tasksDiv = document.getElementById('existingTasks');
            tasksDiv.innerHTML = '<div class="loading">Loading tasks...</div>';
            
            fetch(`get_employee_tasks.php?employee_id=${currentEmployeeId}&task_date=${today}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.tasks && data.tasks.length > 0) {
                            let tasksHTML = '';
                            data.tasks.forEach(task => {
                                tasksHTML += `
                                    <div class="task-item-edit">
                                        <div class="task-content">
                                            <div style="font-weight: 600; margin-bottom: 5px;">${task.description}</div>
                                            <div style="font-size: 12px; color: #666;">
                                                Status: <span class="task-status ${task.status}">${task.status.replace('_', ' ').toUpperCase()}</span>
                                            </div>
                                        </div>
                                        <div class="task-actions">
                                            <button class="btn btn-success btn-sm" onclick="editTask(${task.id}, '${task.description.replace(/'/g, "\\'")}', '${task.status}')">Edit</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteTask(${task.id})">Delete</button>
                                        </div>
                                    </div>
                                `;
                            });
                            tasksDiv.innerHTML = tasksHTML;
                        } else {
                            tasksDiv.innerHTML = '<div class="no-tasks">No tasks assigned for today</div>';
                        }
                    } else {
                        tasksDiv.innerHTML = '<div class="loading">Error loading tasks</div>';
                    }
                })
                .catch(error => {
                    tasksDiv.innerHTML = '<div class="loading">Error loading tasks</div>';
                    console.error('Error:', error);
                });
        }
        
        function saveTask() {
            const description = document.getElementById('taskDescription').value.trim();
            const status = document.getElementById('taskStatus').value;
            const messageDiv = document.getElementById('taskMessage');
            
            if (!description) {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'Please enter a task description';
                messageDiv.style.display = 'block';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('employee_id', currentEmployeeId);
            formData.append('task_date', today);
            formData.append('task_description', description);
            formData.append('status', status);
            
            fetch('save_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                    document.getElementById('taskDescription').value = '';
                    document.getElementById('taskStatus').value = 'pending';
                    loadExistingTasks();
                    // Refresh dashboard after a moment
                    setTimeout(() => {
                        loadDashboard();
                    }, 500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || 'Failed to save task';
                    messageDiv.style.display = 'block';
                }
            })
            .catch(error => {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'Error: ' + error.message;
                messageDiv.style.display = 'block';
                console.error('Error:', error);
            });
        }
        
        function editTask(taskId, description, status) {
            document.getElementById('taskDescription').value = description;
            document.getElementById('taskStatus').value = status;
            
            // Change save button to update
            const saveBtn = document.querySelector('#taskModal .btn-primary');
            saveBtn.textContent = 'Update Task';
            saveBtn.onclick = function() {
                updateTask(taskId);
            };
        }
        
        function updateTask(taskId) {
            const description = document.getElementById('taskDescription').value.trim();
            const status = document.getElementById('taskStatus').value;
            const messageDiv = document.getElementById('taskMessage');
            
            if (!description) {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'Please enter a task description';
                messageDiv.style.display = 'block';
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('task_id', taskId);
            formData.append('employee_id', currentEmployeeId);
            formData.append('task_description', description);
            formData.append('status', status);
            
            fetch('save_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.className = 'message success';
                    messageDiv.textContent = data.message;
                    messageDiv.style.display = 'block';
                    document.getElementById('taskDescription').value = '';
                    document.getElementById('taskStatus').value = 'pending';
                    
                    // Reset save button
                    const saveBtn = document.querySelector('#taskModal .btn-primary');
                    saveBtn.textContent = 'Add Task';
                    saveBtn.onclick = saveTask;
                    
                    loadExistingTasks();
                    setTimeout(() => {
                        loadDashboard();
                    }, 500);
                } else {
                    messageDiv.className = 'message error';
                    messageDiv.textContent = data.message || 'Failed to update task';
                    messageDiv.style.display = 'block';
                }
            })
            .catch(error => {
                messageDiv.className = 'message error';
                messageDiv.textContent = 'Error: ' + error.message;
                messageDiv.style.display = 'block';
                console.error('Error:', error);
            });
        }
        
        function deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('task_id', taskId);
            formData.append('employee_id', currentEmployeeId);
            
            fetch('save_task.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadExistingTasks();
                    setTimeout(() => {
                        loadDashboard();
                    }, 500);
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete task'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                console.error('Error:', error);
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('taskModal')) closeTaskModal();
            if (event.target === document.getElementById('reportPreviewModal')) closeReportPreview();
        }
    </script>
<?php include 'includes/footer.php'; ?>

