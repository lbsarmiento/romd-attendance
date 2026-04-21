<?php
require_once 'config.php';
requireLogin();

$page_title = 'Test Employee Computation - ROMD Attendance';
$current_page = 'dashboard';
$show_back_btn = true;
include 'includes/header.php';
?>
<style>
    .test-layout {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 20px;
        align-items: start;
    }
    .test-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 16px;
    }
    .test-muted {
        color: #666;
        font-size: 14px;
    }
    .test-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 14px;
        align-items: end;
    }
    .test-toolbar label {
        display: block;
        font-size: 13px;
        color: #374151;
    }
    .test-toolbar input,
    .test-toolbar select,
    .test-toolbar button {
        margin-top: 6px;
    }
    .test-btn {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 13px;
        background: #f9fafb;
        cursor: pointer;
    }
    .test-btn:hover {
        background: #f3f4f6;
    }
    .test-btn-primary {
        border-color: #2563eb;
        background: #2563eb;
        color: #fff;
    }
    .test-btn-primary:hover {
        background: #1d4ed8;
    }
    .test-table-wrap {
        overflow-x: auto;
    }
    .test-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
    }
    .test-table th,
    .test-table td {
        padding: 9px;
        border: 1px solid #e5e7eb;
    }
    .test-table th {
        background: #f3f4f6;
        font-weight: 600;
        font-size: 13px;
    }
    .rule-chip {
        display: inline-block;
        font-size: 11px;
        padding: 2px 7px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1e40af;
        border: 1px solid #bfdbfe;
    }
    .points-cell {
        font-weight: 700;
        text-align: right;
        min-width: 45px;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 8px;
        margin-bottom: 14px;
    }
    .summary-box {
        border: 1px solid #dbeafe;
        background: #f8fbff;
        border-radius: 8px;
        padding: 10px;
    }
    .summary-label {
        color: #6b7280;
        font-size: 12px;
        margin-bottom: 4px;
    }
    .summary-value {
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
    }
    .computation-box {
        font-family: Consolas, "Courier New", monospace;
        font-size: 12.5px;
        white-space: pre-wrap;
        line-height: 1.55;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
    }
    .legend {
        margin-top: 12px;
        font-size: 12px;
        color: #4b5563;
        line-height: 1.5;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 10px;
    }
    .toolbar-message {
        margin-bottom: 12px;
        font-size: 13px;
        color: #475569;
    }
    .toolbar-message.error {
        color: #991b1b;
    }
    @media (max-width: 980px) {
        .test-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container">
    <div class="welcome-card" style="margin-bottom: 20px;">
        <h2>TEST EMPLOYEE - Weekly Computation Draft</h2>
        <p>Enter attendance for one selected week (Mon-Fri only) and review the exact formula used for points and the final rating out of 5.</p>
    </div>

    <div class="test-layout">
        <section class="test-card">
            <h3 style="margin-top: 0;">Input Area (1 Week, Mon-Fri)</h3>
            <p class="test-muted" style="margin-top: 0;">This follows the same scoring behavior used in the monthly pages, but limited to a single week.</p>

            <div class="test-toolbar">
                <label>
                    Week Starting (Monday)
                    <input id="weekStartInput" type="date" style="padding:8px;">
                </label>
                <label>
                    Existing Employee
                    <select id="existingEmployeeSelect" style="padding:8px; min-width: 230px;">
                        <option value="">Select employee...</option>
                    </select>
                </label>
                <label>
                    Employee Name
                    <input id="employeeNameInput" type="text" value="TEST EMPLOYEE" style="padding:8px; min-width: 210px;">
                </label>
                <button type="button" class="test-btn" id="loadEmployeeBtn">Load Existing Data</button>
                <button type="button" class="test-btn test-btn-primary" id="sampleBtn">Load Sample Data</button>
                <button type="button" class="test-btn" id="resetBtn">Reset Inputs</button>
            </div>
            <div id="toolbarMessage" class="toolbar-message"></div>

            <div class="test-table-wrap">
                <table class="test-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Day</th>
                            <th style="width: 150px;">Date</th>
                            <th style="width: 130px;">Status</th>
                            <th style="width: 120px;">Time In</th>
                            <th>Rule Applied</th>
                            <th style="width: 90px; text-align: right;">Day Points</th>
                        </tr>
                    </thead>
                    <tbody id="rowsBody"></tbody>
                </table>
            </div>
        </section>

        <section class="test-card" style="background: #f8fafc; border-color: #dbeafe;">
            <h3 style="margin-top: 0;">Computation Box</h3>

            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Final Rating (out of 5)</div>
                    <div class="summary-value" id="finalRatingValue">0.00</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Net Points</div>
                    <div class="summary-value" id="finalPointsValue">0</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Average</div>
                    <div class="summary-value" id="averageValue">0.00</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Late Count</div>
                    <div class="summary-value" id="lateValue">0</div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Absent (from lates)</div>
                    <div class="summary-value" id="lateAbsentValue">0</div>
                </div>
            </div>

            <div id="computationBox" class="computation-box"></div>

            <div class="legend">
                <strong>Reference:</strong> Late conversion in this test uses fixed cutoff 8:00 AM (same as monthly stats query).<br>
                <strong>2026+ bands:</strong> 6:00-6:44=5, 6:45-7:45=4, 7:46-8:00=3, 8:01-8:15=2, 8:16+=1.<br>
                <strong>Before 2026:</strong> up to 7:44=5, 7:45-7:59=4, 8:00-8:29=3, 8:30=2, 8:31+=1.<br>
                <strong>Final rating:</strong> computed as `Net Points / Attendance Days`, producing a rating on the original `0-5` scale (same scale as the per-day points).
            </div>
        </section>
    </div>
</div>

<script>
    const STATUSES = ["clear", "present", "late", "absent", "offset", "leave", "holiday", "suspended"];
    const EMPTY_TIME_STATUSES = ["clear", "absent", "offset", "leave", "holiday", "suspended"];
    const EXCLUDED_AVG_STATUSES = ["clear", "leave", "holiday", "suspended"];
    const WEEKDAY_LABELS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    let employeeOptions = [];

    function toMinutes(hhmm) {
        if (!hhmm) return null;
        const parts = hhmm.split(":");
        if (parts.length < 2) return null;
        const hour = parseInt(parts[0], 10);
        const minute = parseInt(parts[1], 10);
        if (Number.isNaN(hour) || Number.isNaN(minute)) return null;
        return (hour * 60) + minute;
    }

    function getBandInfo(dateStr, status, timeIn) {
        if (status === "clear" || status === "") {
            return { basePoints: 0, rule: "no saved record" };
        }
        if (status === "leave" || status === "holiday" || status === "suspended") {
            return { basePoints: 0, rule: `${status} = 0` };
        }
        if (status === "offset") {
            return { basePoints: 5, rule: "offset = 5" };
        }
        if (status === "absent") {
            return { basePoints: 0, rule: "absent base = 0 (then -1 deduction)" };
        }
        if (!timeIn) {
            return { basePoints: 0, rule: "no time-in = 0" };
        }

        const mins = toMinutes(timeIn);
        if (mins === null) {
            return { basePoints: 0, rule: "invalid time = 0" };
        }

        const year = parseInt((dateStr || "").slice(0, 4), 10) || (new Date()).getFullYear();
        if (year >= 2026) {
            if (mins <= (6 * 60 + 44)) return { basePoints: 5, rule: "2026+: 6:00-6:44 => 5" };
            if (mins <= (7 * 60 + 45)) return { basePoints: 4, rule: "2026+: 6:45-7:45 => 4" };
            if (mins <= (8 * 60)) return { basePoints: 3, rule: "2026+: 7:46-8:00 => 3" };
            if (mins <= (8 * 60 + 15)) return { basePoints: 2, rule: "2026+: 8:01-8:15 => 2" };
            return { basePoints: 1, rule: "2026+: 8:16+ => 1" };
        }

        if (mins <= (7 * 60 + 44)) return { basePoints: 5, rule: "pre-2026: up to 7:44 => 5" };
        if (mins <= (7 * 60 + 59)) return { basePoints: 4, rule: "pre-2026: 7:45-7:59 => 4" };
        if (mins <= (8 * 60 + 29)) return { basePoints: 3, rule: "pre-2026: 8:00-8:29 => 3" };
        if (mins === (8 * 60 + 30)) return { basePoints: 2, rule: "pre-2026: 8:30 => 2" };
        return { basePoints: 1, rule: "pre-2026: 8:31+ => 1" };
    }

    function pad2(n) {
        return String(n).padStart(2, "0");
    }

    function formatDate(dateObj) {
        return `${dateObj.getFullYear()}-${pad2(dateObj.getMonth() + 1)}-${pad2(dateObj.getDate())}`;
    }

    function getMondayOf(dateObj) {
        const d = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
        const dow = d.getDay(); // 0=Sun..6=Sat
        const diff = (dow === 0) ? -6 : (1 - dow); // move to Monday
        d.setDate(d.getDate() + diff);
        return d;
    }

    function createRows() {
        const body = document.getElementById("rowsBody");
        const weekInput = document.getElementById("weekStartInput");
        const rawVal = weekInput.value;
        if (!rawVal) {
            body.innerHTML = "";
            return;
        }

        const parts = rawVal.split("-").map((p) => parseInt(p, 10));
        const picked = new Date(parts[0], parts[1] - 1, parts[2]);
        const monday = getMondayOf(picked);
        if (formatDate(picked) !== formatDate(monday)) {
            weekInput.value = formatDate(monday);
        }

        body.innerHTML = "";

        for (let i = 0; i < 5; i++) {
            const dateObj = new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i);
            const dateStr = formatDate(dateObj);
            const weekday = WEEKDAY_LABELS[dateObj.getDay()];

            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${weekday}</td>
                <td><input class="row-date" type="date" value="${dateStr}" style="padding:6px;"></td>
                <td>
                    <select class="row-status" style="padding:6px;">
                        ${STATUSES.map(s => `<option value="${s}" ${s === "clear" ? "selected" : ""}>${s}</option>`).join("")}
                    </select>
                </td>
                <td><input class="row-time" type="time" value="" style="padding:6px;"></td>
                <td class="row-rule"><span class="rule-chip">${weekday}</span></td>
                <td class="row-points points-cell">0</td>
            `;
            body.appendChild(tr);
        }

        wireRows();
        recompute();
    }

    function wireRows() {
        document.querySelectorAll(".row-date, .row-status, .row-time").forEach((el) => {
            el.addEventListener("input", recompute);
            el.addEventListener("change", recompute);
        });
    }

    function setToolbarMessage(message, isError = false) {
        const box = document.getElementById("toolbarMessage");
        if (!box) return;
        box.textContent = message || "";
        box.className = isError ? "toolbar-message error" : "toolbar-message";
    }

    function populateEmployeeOptions(employees) {
        const select = document.getElementById("existingEmployeeSelect");
        if (!select) return;
        select.innerHTML = '<option value="">Select employee...</option>';
        employees.forEach((emp) => {
            const option = document.createElement("option");
            option.value = String(emp.id);
            option.textContent = emp.name;
            select.appendChild(option);
        });
    }

    function loadEmployeeOptions() {
        fetch("get_employees.php")
            .then((response) => response.json())
            .then((data) => {
                if (!data.success || !Array.isArray(data.employees)) {
                    throw new Error(data.message || "Failed to load employees");
                }
                employeeOptions = data.employees;
                populateEmployeeOptions(employeeOptions);
                setToolbarMessage("You can load real attendance data or generate random sample data.");
            })
            .catch((error) => {
                setToolbarMessage(`Unable to load employees: ${error.message}`, true);
            });
    }

    function getRandomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function buildRandomTime(status) {
        if (EMPTY_TIME_STATUSES.includes(status)) return "";

        // Generate random realistic times for demo:
        // present => mostly early/on-time; late => after 8:00
        let hour;
        let minute;
        if (status === "late") {
            hour = 8;
            minute = getRandomInt(1, 45);
        } else {
            // present
            hour = getRandomInt(6, 8);
            if (hour === 8) {
                minute = getRandomInt(0, 0); // exactly 08:00 for on-time edge case
            } else {
                minute = getRandomInt(0, 59);
            }
        }

        return `${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}`;
    }

    function pickRandomStatus() {
        // Weighted random for useful testing variety
        const weighted = [
            "present", "present", "present",
            "late", "late",
            "absent",
            "offset",
            "leave",
            "holiday",
            "suspended"
        ];
        return weighted[getRandomInt(0, weighted.length - 1)];
    }

    function applySampleData() {
        const rows = Array.from(document.querySelectorAll("#rowsBody tr"));
        rows.forEach((row) => {
            const status = pickRandomStatus();
            const time = buildRandomTime(status);
            row.querySelector(".row-status").value = status;
            row.querySelector(".row-time").value = time;
        });
        recompute();
    }

    function applyLoadedEmployeeData(data) {
        const recordsByDate = new Map();
        (data.attendance || []).forEach((record) => {
            recordsByDate.set(record.attendance_date, record);
        });

        const rows = Array.from(document.querySelectorAll("#rowsBody tr"));
        rows.forEach((row) => {
            const dateVal = row.querySelector(".row-date").value;
            const record = recordsByDate.get(dateVal);
            const status = record ? (record.status || "clear") : "clear";
            const time = record && record.time_in ? String(record.time_in).substring(0, 5) : "";
            row.querySelector(".row-status").value = status;
            row.querySelector(".row-time").value = time;
        });

        if (data.employee_name) {
            document.getElementById("employeeNameInput").value = data.employee_name;
        }
        recompute();
    }

    function loadExistingEmployeeData() {
        const select = document.getElementById("existingEmployeeSelect");
        const employeeId = select.value;
        const rows = Array.from(document.querySelectorAll("#rowsBody tr"));
        if (!employeeId) {
            setToolbarMessage("Please select an employee to load.", true);
            return;
        }
        if (rows.length === 0) {
            setToolbarMessage("Please pick a week first.", true);
            return;
        }

        const startDate = rows[0].querySelector(".row-date").value;
        const endDate = rows[rows.length - 1].querySelector(".row-date").value;
        const selectedEmployee = employeeOptions.find((emp) => String(emp.id) === String(employeeId));
        setToolbarMessage(`Loading saved attendance for ${selectedEmployee ? selectedEmployee.name : "employee"}...`);

        fetch(`get_test_employee_data.php?employee_id=${encodeURIComponent(employeeId)}&start_date=${startDate}&end_date=${endDate}`)
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(data.message || "Failed to load employee data");
                }
                applyLoadedEmployeeData(data);
                setToolbarMessage(`Loaded existing data for ${data.employee_name}.`);
            })
            .catch((error) => {
                setToolbarMessage(`Unable to load attendance: ${error.message}`, true);
            });
    }

    function resetInputs() {
        createRows();
        document.getElementById("existingEmployeeSelect").value = "";
        document.getElementById("employeeNameInput").value = "TEST EMPLOYEE";
        setToolbarMessage("Inputs reset. You can load another employee or use random sample data.");
    }

    function recompute() {
        const rows = Array.from(document.querySelectorAll("#rowsBody tr"));
        const detailLines = [];
        let totalDayPoints = 0;
        let attendanceDays = 0;
        let lateCount = 0;
        let actualAbsentCount = 0;

        rows.forEach((row, index) => {
            const dateVal = row.querySelector(".row-date").value;
            const statusVal = row.querySelector(".row-status").value;
            const timeInput = row.querySelector(".row-time");
            let timeVal = timeInput.value;

            if (EMPTY_TIME_STATUSES.includes(statusVal)) {
                timeVal = "";
                timeInput.value = "";
                timeInput.disabled = true;
                timeInput.style.opacity = "0.6";
            } else {
                timeInput.disabled = false;
                timeInput.style.opacity = "1";
            }

            const info = getBandInfo(dateVal, statusVal, timeVal);
            let dayPoints = info.basePoints;

            if (statusVal === "absent") {
                dayPoints -= 1;
                actualAbsentCount += 1;
            }
            if (!EXCLUDED_AVG_STATUSES.includes(statusVal)) {
                attendanceDays += 1;
            }

            const mins = toMinutes(timeVal);
            const isLate = mins !== null && mins > (8 * 60);
            if (statusVal === "late" || isLate) {
                lateCount += 1;
            }

            totalDayPoints += dayPoints;
            row.querySelector(".row-points").textContent = String(dayPoints);
            const weekday = dateVal ? WEEKDAY_LABELS[new Date(dateVal + "T00:00:00").getDay()] : "-";
            row.querySelector(".row-rule").innerHTML = `<span class="rule-chip">${weekday} | ${info.rule}</span>`;

            detailLines.push(
                `${index + 1}) ${dateVal} | status=${statusVal} | time=${timeVal || "-"} | base=${info.basePoints} | day=${dayPoints}`
            );
        });

        const absentFromLates = Math.floor(lateCount / 4);
        const remainingLates = lateCount % 4;
        const finalPoints = totalDayPoints - absentFromLates;
        const average = attendanceDays > 0 ? (finalPoints / attendanceDays) : 0;
        const employeeName = (document.getElementById("employeeNameInput").value || "TEST EMPLOYEE").trim();
        const averageLine = attendanceDays > 0
            ? `${finalPoints} / ${attendanceDays} = ${average.toFixed(2)}`
            : `0.00 (no counted attendance days)`;

        document.getElementById("finalRatingValue").textContent = average.toFixed(2);
        document.getElementById("finalPointsValue").textContent = String(finalPoints);
        document.getElementById("averageValue").textContent = average.toFixed(2);
        document.getElementById("lateValue").textContent = String(lateCount);
        document.getElementById("lateAbsentValue").textContent = String(absentFromLates);

        const summary = [
            `Employee: ${employeeName}`,
            "",
            "DETAIL PER DAY",
            ...detailLines,
            "",
            "FORMULA BREAKDOWN",
            `1) Total day points = ${totalDayPoints}`,
            `2) Actual absent count = ${actualAbsentCount} (already reflected as -1/day)`,
            `3) Late count = ${lateCount}`,
            `4) Equivalent absent from lates (if 4 lates, 1 absent) = floor(${lateCount} / 4) = ${absentFromLates}`,
            `5) Net points = ${totalDayPoints} - ${absentFromLates} = ${finalPoints}`,
            `6) Average divisor (attendance days) = ${attendanceDays}`,
            `7) Final rating = ${averageLine} (out of 5)`,
            `8) Equivalent 100-point view = (${average.toFixed(2)} / 5) x 100 = ${((average / 5) * 100).toFixed(2)}%`,
            `9) Remaining tardiness = ${remainingLates}`
        ];

        document.getElementById("computationBox").textContent = summary.join("\n");
    }

    (function init() {
        const weekInput = document.getElementById("weekStartInput");
        const now = new Date();
        const monday = getMondayOf(now);
        weekInput.value = formatDate(monday);
        weekInput.addEventListener("change", createRows);
        document.getElementById("employeeNameInput").addEventListener("input", recompute);
        document.getElementById("loadEmployeeBtn").addEventListener("click", loadExistingEmployeeData);
        document.getElementById("sampleBtn").addEventListener("click", applySampleData);
        document.getElementById("resetBtn").addEventListener("click", resetInputs);
        createRows();
        loadEmployeeOptions();
    })();
</script>
<?php include 'includes/footer.php'; ?>
