<?php
$page_title = 'My Attendance';
$page_subtitle = 'Check in, check out, and view your attendance record';
require_once 'includes/init.php';

$today = date('Y-m-d');
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2020 || $year > 2100) {
    $year = (int) date('Y');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['self_check_in'])) {
    $status = $_POST['check_in_status'] ?? 'Present';
    $remarks = trim($_POST['check_in_note'] ?? '');
    $result = teacherSelfCheckIn($pdo, $teacherId, $status, $remarks ?: null);
    if ($result['ok']) {
        $time = formatTeacherAttTime($result['time'] ?? date('H:i:s'));
        tp_flash('my-attendance.php', "Checked in at {$time} ({$status}).");
    } else {
        tp_flash('my-attendance.php', $result['error'], 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['self_check_out'])) {
    $remarks = trim($_POST['check_out_note'] ?? '');
    $result = teacherSelfCheckOut($pdo, $teacherId, $remarks ?: null);
    if ($result['ok']) {
        $time = formatTeacherAttTime($result['time'] ?? date('H:i:s'));
        tp_flash('my-attendance.php', "Checked out at {$time}. Have a good evening!");
    } else {
        tp_flash('my-attendance.php', $result['error'], 'error');
    }
}

$todayRecord = getTeacherAttendanceRecord($pdo, $teacherId, $today);
$monthRecords = getTeacherAttendanceForMonth($pdo, $teacherId, $year, $month);

$counts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0, 'Leave' => 0];
foreach ($monthRecords as $r) {
    if (isset($counts[$r['status']])) {
        $counts[$r['status']]++;
    }
}

$monthLabel = date('F Y', strtotime("$year-$month-01"));
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$hasCheckIn = $todayRecord && !empty($todayRecord['check_in_time']);
$hasCheckOut = $todayRecord && !empty($todayRecord['check_out_time']);
$todayDuration = $hasCheckIn && $hasCheckOut
    ? teacherAttendanceWorkDuration($todayRecord['check_in_time'], $todayRecord['check_out_time'])
    : null;

$presentMonth = $counts['Present'] + $counts['Late'];

require_once 'includes/layout_header.php';
?>

<div class="tp-tt-hero is-teal">
    <div>
        <h2><i class="fas fa-user-check"></i> My Attendance</h2>
        <p>Check in, check out, and track your attendance record · <?php echo date('l, d M Y'); ?></p>
        <div class="tp-tt-hero-chips">
            <?php if ($hasCheckIn): ?>
            <span class="tp-tt-hero-chip"><i class="fas fa-sign-in-alt"></i> In <?php echo formatTeacherAttTime($todayRecord['check_in_time']); ?></span>
            <?php else: ?>
            <span class="tp-tt-hero-chip"><i class="fas fa-circle" style="font-size:6px"></i> Not checked in</span>
            <?php endif; ?>
            <?php if ($hasCheckOut): ?>
            <span class="tp-tt-hero-chip"><i class="fas fa-sign-out-alt"></i> Out <?php echo formatTeacherAttTime($todayRecord['check_out_time']); ?></span>
            <?php elseif ($hasCheckIn): ?>
            <span class="tp-tt-hero-chip"><i class="fas fa-hourglass-half"></i> In progress</span>
            <?php endif; ?>
            <span class="tp-tt-hero-chip"><i class="fas fa-calendar-check"></i> <?php echo $presentMonth; ?> present this month</span>
        </div>
    </div>
    <div class="tp-tt-hero-actions">
        <a href="leave.php" class="tp-tt-hero-btn"><i class="fas fa-plane-departure"></i> Apply Leave</a>
        <?php if (!$hasCheckIn): ?>
        <a href="#checkInForm" class="tp-tt-hero-btn is-solid"><i class="fas fa-sign-in-alt"></i> Check In</a>
        <?php elseif (!$hasCheckOut): ?>
        <a href="#checkOutForm" class="tp-tt-hero-btn is-solid"><i class="fas fa-sign-out-alt"></i> Check Out</a>
        <?php endif; ?>
    </div>
</div>

<div class="tp-stat-grid cols-3">
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-sign-in-alt"></i></div>
        <div><span>Check-in Today</span><strong style="font-size:1rem"><?php echo $hasCheckIn ? formatTeacherAttTime($todayRecord['check_in_time']) : '—'; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-sign-out-alt"></i></div>
        <div><span>Check-out Today</span><strong style="font-size:1rem"><?php echo $hasCheckOut ? formatTeacherAttTime($todayRecord['check_out_time']) : '—'; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-hourglass-half"></i></div>
        <div><span>Duration Today</span><strong style="font-size:1rem"><?php echo $todayDuration ?: ($hasCheckIn && !$hasCheckOut ? 'In progress' : '—'); ?></strong></div>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card tp-self-att-today">
        <div class="tp-card-head">
            <h3><i class="fas fa-fingerprint"></i> Today — <?php echo date('l, d M Y'); ?></h3>
            <span class="tp-card-badge" id="tpLiveClock"><?php echo date('h:i:s A'); ?></span>
        </div>

        <?php if ($hasCheckIn || $hasCheckOut): ?>
        <div class="tp-self-att-times">
            <div class="tp-self-att-time-box is-in">
                <i class="fas fa-sign-in-alt"></i>
                <span>Check In</span>
                <strong><?php echo formatTeacherAttTime($todayRecord['check_in_time'] ?? null); ?></strong>
            </div>
            <div class="tp-self-att-time-arrow"><i class="fas fa-arrow-right"></i></div>
            <div class="tp-self-att-time-box is-out<?php echo $hasCheckOut ? '' : ' is-pending'; ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span>Check Out</span>
                <strong><?php echo $hasCheckOut ? formatTeacherAttTime($todayRecord['check_out_time']) : 'Pending'; ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($todayRecord): ?>
        <div class="tp-self-att-status is-<?php echo strtolower(str_replace(' ', '-', $todayRecord['status'])); ?>" style="margin-top:16px">
            <div class="tp-self-att-status-icon">
                <?php
                $icons = ['Present' => 'check', 'Absent' => 'times', 'Late' => 'clock', 'Half Day' => 'adjust', 'Leave' => 'plane'];
                $icon = $icons[$todayRecord['status']] ?? 'info';
                ?>
                <i class="fas fa-<?php echo $icon; ?>"></i>
            </div>
            <div>
                <strong><?php echo htmlspecialchars($todayRecord['status']); ?></strong>
                <span>Attendance status<?php if ($todayDuration): ?> · <?php echo $todayDuration; ?> worked<?php endif; ?></span>
                <?php if ($todayRecord['remarks']): ?>
                <p class="tp-self-att-remark"><?php echo htmlspecialchars($todayRecord['remarks']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$hasCheckIn): ?>
        <p style="margin:16px 0;color:var(--tp-muted);font-size:0.88rem">You haven't checked in yet today. Mark your arrival to record the time.</p>
        <form method="POST" class="tp-form-panel" id="checkInForm">
            <input type="hidden" name="self_check_in" value="1">
            <div class="tp-form-grid" style="grid-template-columns:1fr">
                <div class="tp-field">
                    <label>Check-in Status</label>
                    <select name="check_in_status" required>
                        <option value="Present">Present — On time</option>
                        <option value="Late">Late — Arrived late</option>
                    </select>
                </div>
                <div class="tp-field">
                    <label>Note (optional)</label>
                    <input type="text" name="check_in_note" placeholder="e.g. Traffic delay">
                </div>
                <div>
                    <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-sign-in-alt"></i> Check In Now</button>
                </div>
            </div>
        </form>
        <?php elseif (!$hasCheckOut): ?>
        <div class="tp-self-att-checkout" style="margin-top:16px">
            <p style="margin:0 0 12px;color:var(--tp-muted);font-size:0.88rem">You're checked in. Mark check-out when you leave for the day.</p>
            <form method="POST" class="tp-form-panel" id="checkOutForm">
                <input type="hidden" name="self_check_out" value="1">
                <div class="tp-form-grid" style="grid-template-columns:1fr">
                    <div class="tp-field">
                        <label>Note (optional)</label>
                        <input type="text" name="check_out_note" placeholder="e.g. Leaving early for meeting">
                    </div>
                    <div>
                        <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-sign-out-alt"></i> Check Out Now</button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="tp-profile-note" style="margin-top:16px;margin-bottom:0">
            <i class="fas fa-check-circle"></i>
            Day complete — checked in at <?php echo formatTeacherAttTime($todayRecord['check_in_time']); ?> and out at <?php echo formatTeacherAttTime($todayRecord['check_out_time']); ?>.
        </div>
        <?php endif; ?>
    </div>

    <div class="tp-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-chart-pie"></i> <?php echo $monthLabel; ?> Summary</h3>
        </div>
        <div class="tp-att-summary" style="margin-bottom:0">
            <div class="tp-att-stat is-present"><span>Present</span><strong><?php echo $counts['Present']; ?></strong></div>
            <div class="tp-att-stat is-absent"><span>Absent</span><strong><?php echo $counts['Absent']; ?></strong></div>
            <div class="tp-att-stat is-late"><span>Late</span><strong><?php echo $counts['Late']; ?></strong></div>
            <div class="tp-att-stat is-half"><span>Half / Leave</span><strong><?php echo $counts['Half Day'] + $counts['Leave']; ?></strong></div>
        </div>
        <div class="tp-profile-note" style="margin-top:16px;margin-bottom:0">
            <i class="fas fa-plane-departure"></i>
            For planned absence, use <a href="leave.php" style="color:#2563eb;font-weight:600">Apply Leave</a> — admin will mark Leave after approval.
        </div>
    </div>
</div>

<div class="tp-card">
    <div class="tp-card-head">
        <h3><i class="fas fa-calendar-alt"></i> Attendance Log</h3>
        <div style="display:flex;gap:8px;align-items:center">
            <a href="my-attendance.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="tp-btn tp-btn-outline tp-btn-sm"><i class="fas fa-chevron-left"></i></a>
            <span class="tp-card-badge"><?php echo $monthLabel; ?></span>
            <a href="my-attendance.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="tp-btn tp-btn-outline tp-btn-sm"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <?php if ($monthRecords): ?>
    <div class="tp-self-att-log">
        <?php foreach ($monthRecords as $r):
            $stClass = strtolower(str_replace(' ', '-', $r['status']));
            $duration = teacherAttendanceWorkDuration($r['check_in_time'] ?? null, $r['check_out_time'] ?? null);
        ?>
        <div class="tp-self-att-row">
            <div class="tp-self-att-date">
                <strong><?php echo date('d', strtotime($r['attendance_date'])); ?></strong>
                <span><?php echo date('D', strtotime($r['attendance_date'])); ?></span>
            </div>
            <div class="tp-self-att-info">
                <span class="tp-att-pill is-<?php echo $stClass === 'half-day' ? 'half' : ($stClass === 'leave' ? 'none' : $stClass); ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                <?php if (!empty($r['check_in_time']) || !empty($r['check_out_time'])): ?>
                <span class="tp-self-att-row-time">
                    <i class="fas fa-sign-in-alt"></i> <?php echo formatTeacherAttTime($r['check_in_time'] ?? null); ?>
                    <i class="fas fa-sign-out-alt" style="margin-left:8px"></i> <?php echo formatTeacherAttTime($r['check_out_time'] ?? null); ?>
                    <?php if ($duration): ?> · <?php echo $duration; ?><?php endif; ?>
                </span>
                <?php endif; ?>
                <?php if ($r['remarks']): ?><span class="tp-self-att-row-note"><?php echo htmlspecialchars($r['remarks']); ?></span><?php endif; ?>
            </div>
            <?php if ($r['attendance_date'] === $today): ?>
            <span class="tp-schedule-now-badge">Today</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="tp-empty"><i class="fas fa-calendar-times"></i><p>No attendance records for <?php echo $monthLabel; ?>.<br>Check in daily or wait for admin to mark attendance.</p></div>
    <?php endif; ?>
</div>

<script>
(function () {
    var clock = document.getElementById('tpLiveClock');
    if (!clock) return;
    function tick() {
        var d = new Date();
        clock.textContent = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

<?php require_once 'includes/layout_footer.php'; ?>
