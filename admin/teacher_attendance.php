<?php
$page_title = "Teacher Attendance";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/teacher_helpers.php';

ensureErpSchema($pdo);
ensureTeacherSchema($pdo);

$date = $_GET['date'] ?? date('Y-m-d');
$teachers = getAllTeachers($pdo, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attendance'])) {
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    if ($teacherId > 0) {
        $pdo->prepare("DELETE FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?")
            ->execute([$teacherId, $date]);
        $_SESSION['success_msg'] = 'Attendance entry removed for ' . date('d M Y', strtotime($date)) . '.';
    }
    header('Location: teacher_attendance.php?date=' . urlencode($date));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    $statuses = $_POST['status'] ?? [];
    $stmt = $pdo->prepare(
        "INSERT INTO teacher_attendance (teacher_id, attendance_date, status) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)"
    );
    foreach ($statuses as $tid => $status) {
        $stmt->execute([(int) $tid, $date, $status]);
    }
    $_SESSION['success_msg'] = 'Teacher attendance saved for ' . date('d M Y', strtotime($date)) . '.';
    header('Location: teacher_attendance.php?date=' . urlencode($date));
    exit;
}

$existing = [];
$stmt = $pdo->prepare("SELECT * FROM teacher_attendance WHERE attendance_date = ?");
$stmt->execute([$date]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row['teacher_id']] = $row;
}

$stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0, 'Leave' => 0, 'Unmarked' => 0];
$portalCheckIn = 0;
$portalCheckOut = 0;

foreach ($teachers as $t) {
    $rec = $existing[$t['id']] ?? null;
    if (!$rec) {
        $stats['Unmarked']++;
        continue;
    }
    if (isset($stats[$rec['status']])) {
        $stats[$rec['status']]++;
    }
    if (!empty($rec['check_in_time'])) {
        $portalCheckIn++;
    }
    if (!empty($rec['check_out_time'])) {
        $portalCheckOut++;
    }
}

$isToday = ($date === date('Y-m-d'));
$dateLabel = date('l, d M Y', strtotime($date));

require_once 'includes/header.php';
?>

<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-user-check"></i></div>
        <div class="content-top-title">
            <h2>Teacher Attendance</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i>
                <a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i>
                <span>HR Attendance</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="leave_requests.php" class="btn-header-action btn-header-outline"><i class="fas fa-plane-departure"></i> Leave Requests</a>
        <a href="teachers.php" class="btn-header-action btn-header-outline"><i class="fas fa-chalkboard-teacher"></i> All Teachers</a>
    </div>
</div>

<div class="cls-stat-strip cols-4">
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-users"></i></div>
        <div><span>Active Teachers</span><strong><?php echo count($teachers); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-sign-in-alt"></i></div>
        <div><span>Portal Check-ins</span><strong><?php echo $portalCheckIn; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-sign-out-alt"></i></div>
        <div><span>Portal Check-outs</span><strong><?php echo $portalCheckOut; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-amber"><i class="fas fa-calendar-day"></i></div>
        <div><span>Selected Date</span><strong style="font-size:0.95rem"><?php echo $isToday ? 'Today' : date('d M', strtotime($date)); ?></strong></div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-calendar-alt"></i></div>
        <div>
            <h4>Select Date</h4>
            <p>Load attendance for a specific day — teachers can self check-in via portal</p>
        </div>
    </div>
    <form method="GET" class="category-add-row">
        <div class="form-field"><label>Attendance Date</label><input type="date" name="date" class="form-input" value="<?php echo htmlspecialchars($date); ?>"></div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Load</button>
        </div>
        <?php if (!$isToday): ?>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <a href="teacher_attendance.php" class="btn-header-action btn-header-outline category-add-btn"><i class="fas fa-calendar-day"></i> Today</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<div class="tta-date-bar">
    <div>
        <strong><i class="fas fa-calendar-check"></i> <?php echo $dateLabel; ?></strong>
        <?php if ($isToday): ?><span class="tta-self-badge"><i class="fas fa-circle" style="font-size:6px"></i> Today</span><?php endif; ?>
    </div>
    <span><?php echo count($teachers); ?> active teachers · <?php echo count($teachers) - $stats['Unmarked']; ?> marked</span>
</div>

<div class="tta-summary-strip">
    <div class="tta-summary-chip is-present"><span>Present</span><strong><?php echo $stats['Present']; ?></strong></div>
    <div class="tta-summary-chip is-absent"><span>Absent</span><strong><?php echo $stats['Absent']; ?></strong></div>
    <div class="tta-summary-chip is-late"><span>Late</span><strong><?php echo $stats['Late']; ?></strong></div>
    <div class="tta-summary-chip"><span>Half / Leave</span><strong><?php echo $stats['Half Day'] + $stats['Leave']; ?></strong></div>
    <div class="tta-summary-chip is-portal"><span>Self Check-in</span><strong><?php echo $portalCheckIn; ?></strong></div>
</div>

<?php if ($teachers): ?>
<form method="POST" id="ttaForm">
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($date); ?>">

    <div class="form-section-card section-mb">
        <div class="tta-bulk-bar">
            <span>Quick mark all:</span>
            <button type="button" class="tta-bulk-btn is-present" data-bulk="Present"><i class="fas fa-check"></i> All Present</button>
            <button type="button" class="tta-bulk-btn is-absent" data-bulk="Absent"><i class="fas fa-times"></i> All Absent</button>
            <button type="button" class="tta-bulk-btn is-late" data-bulk="Late"><i class="fas fa-clock"></i> All Late</button>
        </div>

        <div class="table-container">
            <div class="table-toolbar tta-table-toolbar">
                <strong><i class="fas fa-chalkboard-teacher"></i> Mark Attendance — <?php echo date('d M Y', strtotime($date)); ?></strong>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Attendance</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Check In / Out</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teachers as $t):
                        $rec = $existing[$t['id']] ?? null;
                        $status = $rec['status'] ?? 'Present';
                        $initials = strtoupper(substr($t['name'], 0, 1));
                        $hasPortalIn = $rec && !empty($rec['check_in_time']);
                        $duration = ($rec && $hasPortalIn && !empty($rec['check_out_time']))
                            ? teacherAttendanceWorkDuration($rec['check_in_time'], $rec['check_out_time'])
                            : null;
                    ?>
                    <tr>
                        <td>
                            <div class="tta-teacher-cell">
                                <div class="tta-teacher-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                    <small><?php echo htmlspecialchars($t['employee_id']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($t['subject']); ?></td>
                        <td>
                            <?php
                            $hasOut = $rec && !empty($rec['check_out_time']);
                            $inClass = $hasPortalIn ? 'is-in' : 'is-none';
                            $outClass = $hasOut ? 'is-out' : ($hasPortalIn ? 'is-pending' : 'is-none');
                            ?>
                            <div class="tta-time-cell">
                                <div class="tta-time-track">
                                    <div class="tta-time-node <?php echo $inClass; ?>">
                                        <span class="tta-time-node-icon"><i class="fas fa-sign-in-alt"></i></span>
                                        <span class="tta-time-node-label">Check In</span>
                                        <strong><?php echo $hasPortalIn ? formatTeacherAttTime($rec['check_in_time']) : '—'; ?></strong>
                                    </div>
                                    <div class="tta-time-connector<?php echo ($hasPortalIn && $hasOut) ? ' is-complete' : ''; ?>"></div>
                                    <div class="tta-time-node <?php echo $outClass; ?>">
                                        <span class="tta-time-node-icon"><i class="fas fa-sign-out-alt"></i></span>
                                        <span class="tta-time-node-label">Check Out</span>
                                        <strong><?php echo $hasOut ? formatTeacherAttTime($rec['check_out_time']) : ($hasPortalIn ? 'Pending' : '—'); ?></strong>
                                    </div>
                                </div>
                                <?php if ($duration || $hasPortalIn): ?>
                                <div class="tta-time-foot">
                                    <?php if ($duration): ?>
                                    <span class="tta-duration"><i class="fas fa-hourglass-half"></i> <?php echo $duration; ?></span>
                                    <?php endif; ?>
                                    <?php if ($hasPortalIn): ?>
                                    <span class="tta-self-badge"><i class="fas fa-mobile-alt"></i> Portal</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <select name="status[<?php echo $t['id']; ?>]" class="tta-att-select" data-status="<?php echo htmlspecialchars($status); ?>">
                                <?php foreach (['Present', 'Absent', 'Late', 'Half Day', 'Leave'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <?php if (!empty($rec['remarks'])): ?>
                            <span class="tta-remarks-readonly" title="<?php echo htmlspecialchars($rec['remarks']); ?>"><?php echo htmlspecialchars($rec['remarks']); ?></span>
                            <?php else: ?>
                            <span class="tta-no-entry">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="tta-actions-cell">
                            <?php if ($rec): ?>
                            <button type="submit" form="tta-delete-<?php echo (int) $t['id']; ?>" class="action-btn delete-btn tta-delete-btn" title="Delete entry" onclick="return confirm(<?php echo json_encode('Remove attendance for ' . $t['name'] . ' on ' . date('d M Y', strtotime($date)) . '?'); ?>);"><i class="fas fa-trash"></i></button>
                            <?php else: ?>
                            <span class="tta-no-entry">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-actions-end" style="margin-top:16px;margin-bottom:0">
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Attendance</button>
        </div>
    </div>
</form>
<?php foreach ($teachers as $t): if (!empty($existing[$t['id']])): ?>
<form method="POST" id="tta-delete-<?php echo (int) $t['id']; ?>" class="tta-delete-form">
    <input type="hidden" name="delete_attendance" value="1">
    <input type="hidden" name="teacher_id" value="<?php echo (int) $t['id']; ?>">
    <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($date); ?>">
</form>
<?php endif; endforeach; ?>
<?php else: ?>
<div class="form-section-card section-mb">
    <div class="tab-empty-state tab-empty-pad-sm">
        <div class="tab-empty-icon"><i class="fas fa-user-slash"></i></div>
        <h3>No active teachers</h3>
        <p>Add teachers first to mark attendance.</p>
        <a href="teacher_add.php" class="btn-header-action btn-header-primary" style="margin-top:12px"><i class="fas fa-plus"></i> Add Teacher</a>
    </div>
</div>
<?php endif; ?>

<div class="notify-info-banner section-mb">
    <div class="notify-info-icon"><i class="fas fa-info-circle"></i></div>
    <div class="notify-info-text">
        <strong>Portal self check-in:</strong> Teachers can check in/out at <code>/teacher/my-attendance.php</code> — times appear automatically here.<br>
        Admin can update status only. Remarks are set by teachers via portal check-in/out.
    </div>
</div>

<script>
(function () {
    function syncSelect(sel) {
        sel.setAttribute('data-status', sel.value);
    }
    document.querySelectorAll('.tta-att-select').forEach(function (sel) {
        syncSelect(sel);
        sel.addEventListener('change', function () { syncSelect(this); });
    });
    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var val = this.getAttribute('data-bulk');
            document.querySelectorAll('.tta-att-select').forEach(function (sel) {
                sel.value = val;
                syncSelect(sel);
            });
        });
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
