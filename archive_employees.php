<?php
require_once 'config.php';
requireLogin();

$username = $_SESSION['username'];
$page_title = 'Archive Employees - ROMD Attendance';
$current_page = 'archive';
$show_back_btn = true;
include 'includes/header.php';

$conn = getDBConnection();

// Ensure resigned_at column exists
$check = $conn->query("SHOW COLUMNS FROM employees LIKE 'resigned_at'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN resigned_at DATE NULL DEFAULT NULL AFTER status");
}
if ($check) {
    $check->free();
}

$active = [];
$archived = [];

$ar = $conn->query("SELECT id, employee_name FROM employees WHERE status = 'active' ORDER BY employee_name");
if ($ar) {
    while ($row = $ar->fetch_assoc()) {
        $active[] = $row;
    }
    $ar->free();
}

$inr = $conn->query("SELECT id, employee_name, resigned_at FROM employees WHERE status = 'inactive' ORDER BY employee_name");
if ($inr) {
    while ($row = $inr->fetch_assoc()) {
        $archived[] = $row;
    }
    $inr->free();
}

$conn->close();

function formatResignedDate($dateStr) {
    if (empty($dateStr)) {
        return '';
    }
    $t = strtotime($dateStr);
    return $t ? date('M j, Y', $t) : $dateStr;
}
?>
<div class="container">
    <div class="welcome-card">
        <h2>Archive employees (resigned)</h2>
        <p>Archive employees who have resigned so they no longer appear in the attendance dashboard or monthly view. You can restore them later if needed.</p>
    </div>

    <div id="archiveMessage" class="message" style="display: none; margin-bottom: 16px;"></div>

    <div class="card">
        <h3 style="margin-bottom: 12px; color: #1e293b;">Active employees</h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">These employees appear in attendance. Click <strong>Archive</strong> to mark as resigned and hide from attendance.</p>
        <?php if (empty($active)): ?>
            <p style="color: #64748b; padding: 16px;">No active employees.</p>
        <?php else: ?>
            <ul class="archive-list">
                <?php foreach ($active as $emp): ?>
                    <li class="archive-item">
                        <span class="archive-name"><?php echo htmlspecialchars($emp['employee_name']); ?></span>
                        <button type="button" class="btn btn-archive" data-id="<?php echo (int)$emp['id']; ?>" data-name="<?php echo htmlspecialchars($emp['employee_name']); ?>">Archive</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px; color: #1e293b;">Archived / resigned employees</h3>
        <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">These employees are hidden from attendance. Click <strong>Restore</strong> to make them active again.</p>
        <?php if (empty($archived)): ?>
            <p style="color: #64748b; padding: 16px;">No archived employees.</p>
        <?php else: ?>
            <ul class="archive-list archive-list-muted">
                <li class="archive-item archive-item-header">
                    <span class="archive-name">Name</span>
                    <span class="archive-date">Date resigned</span>
                    <span style="min-width: 80px;"></span>
                </li>
                <?php foreach ($archived as $emp): ?>
                    <li class="archive-item">
                        <span class="archive-name"><?php echo htmlspecialchars($emp['employee_name']); ?></span>
                        <span class="archive-date"><?php echo htmlspecialchars(formatResignedDate($emp['resigned_at'] ?? '')); ?></span>
                        <button type="button" class="btn btn-restore" data-id="<?php echo (int)$emp['id']; ?>" data-name="<?php echo htmlspecialchars($emp['employee_name']); ?>">Restore</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Archive modal: optional date resigned -->
<div id="archiveModal" class="archive-modal" style="display: none;">
    <div class="archive-modal-content">
        <h3>Archive employee</h3>
        <p id="archiveModalEmployeeName" style="margin: 8px 0 16px; font-weight: 600; color: #334155;"></p>
        <div class="form-group">
            <label for="resignedAt">Date resigned (optional)</label>
            <input type="date" id="resignedAt" class="archive-date-input">
        </div>
        <div class="archive-modal-actions">
            <button type="button" class="btn btn-secondary" id="archiveModalCancel">Cancel</button>
            <button type="button" class="btn btn-archive" id="archiveModalConfirm">Archive</button>
        </div>
    </div>
</div>

<style>
.archive-date { font-size: 13px; color: #64748b; min-width: 100px; text-align: right; }
.archive-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.archive-modal-content {
    background: white; padding: 24px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    min-width: 320px; max-width: 90vw;
}
.archive-modal-content h3 { margin: 0 0 8px; color: #1e293b; font-size: 18px; }
.archive-modal-content .form-group { margin-bottom: 16px; }
.archive-modal-content label { display: block; margin-bottom: 6px; font-size: 14px; color: #475569; }
.archive-date-input {
    width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;
}
.archive-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
.archive-list { list-style: none; padding: 0; margin: 0; }
.archive-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 14px; border-bottom: 1px solid #e2e8f0;
    gap: 12px;
}
.archive-item:last-child { border-bottom: none; }   
.archive-item:nth-child(odd) { background: #f8fafc; }
.archive-item-header { background: #e2e8f0 !important; font-weight: 600; color: #475569; }
.archive-item-header .archive-date { color: #475569; }
.archive-name { flex: 1; font-weight: 500; color: #334155; }
.archive-list-muted .archive-name { color: #64748b; }
.btn-archive {
    background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
    color: white; border: none; padding: 8px 16px;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    cursor: pointer; white-space: nowrap;
}
.btn-archive:hover { filter: brightness(1.1); }
.btn-restore {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: white; border: none; padding: 8px 16px;
    border-radius: 8px; font-size: 13px; font-weight: 500;
    cursor: pointer; white-space: nowrap;
}
.btn-restore:hover { filter: brightness(1.1); }
.message.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
</style>

<script>
(function() {
    const msgEl = document.getElementById('archiveMessage');

    function showMsg(text, isError) {
        msgEl.textContent = text;
        msgEl.className = 'message ' + (isError ? 'error' : 'success');
        msgEl.style.display = 'block';
        setTimeout(function() { msgEl.style.display = 'none'; }, 4000);
    }

    function setStatus(employeeId, status, btn, resignedAtOptional) {
        btn.disabled = true;
        btn.textContent = status === 'inactive' ? 'Archiving…' : 'Restoring…';

        var form = new FormData();
        form.append('employee_id', employeeId);
        form.append('status', status);
        if (status === 'inactive' && resignedAtOptional) {
            form.append('resigned_at', resignedAtOptional);
        }

        fetch('set_employee_status.php', { method: 'POST', body: form })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showMsg(data.message, false);
                    window.location.reload();
                } else {
                    showMsg(data.message || 'Action failed', true);
                    btn.disabled = false;
                    btn.textContent = status === 'inactive' ? 'Archive' : 'Restore';
                }
            })
            .catch(function() {
                showMsg('Network error. Please try again.', true);
                btn.disabled = false;
                btn.textContent = status === 'inactive' ? 'Archive' : 'Restore';
            });
    }

    var archiveModal = document.getElementById('archiveModal');
    var archiveModalEmployeeName = document.getElementById('archiveModalEmployeeName');
    var resignedAtInput = document.getElementById('resignedAt');
    var archiveModalCancel = document.getElementById('archiveModalCancel');
    var archiveModalConfirm = document.getElementById('archiveModalConfirm');
    var pendingArchive = { id: null, btn: null };

    function openArchiveModal(btn) {
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var name = btn.getAttribute('data-name') || '';
        pendingArchive.id = id;
        pendingArchive.btn = btn;
        archiveModalEmployeeName.textContent = name;
        resignedAtInput.value = '';
        archiveModal.style.display = 'flex';
        resignedAtInput.focus();
    }

    function closeArchiveModal() {
        archiveModal.style.display = 'none';
        pendingArchive.id = null;
        pendingArchive.btn = null;
    }

    archiveModalCancel.addEventListener('click', closeArchiveModal);
    archiveModalConfirm.addEventListener('click', function() {
        if (pendingArchive.id === null || !pendingArchive.btn) return;
        var employeeId = pendingArchive.id;
        var btn = pendingArchive.btn;
        var dateVal = resignedAtInput.value.trim();
        closeArchiveModal();
        setStatus(employeeId, 'inactive', btn, dateVal || undefined);
    });
    archiveModal.addEventListener('click', function(e) {
        if (e.target === archiveModal) closeArchiveModal();
    });

    // Only list-item Archive buttons open the modal (exclude modal's confirm button)
    document.querySelectorAll('.archive-list .btn-archive').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openArchiveModal(btn);
        });
    });

    document.querySelectorAll('.btn-restore').forEach(function(btn) {
        btn.addEventListener('click', function() {
            setStatus(parseInt(btn.getAttribute('data-id'), 10), 'active', btn);
        });
    });
})();
</script>
<?php include 'includes/footer.php'; ?>
