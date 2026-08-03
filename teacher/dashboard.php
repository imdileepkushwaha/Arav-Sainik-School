<?php
$page_title = 'Dashboard';
$page_subtitle = 'Overview of your teaching schedule';
require_once 'includes/init.php';

$classes = getTeacherClassesTaught($pdo, $teacherId);
$periodCount = countTeacherWeeklyPeriods($pdo, $teacherId);
$todaySlots = getTeacherTodaySchedule($pdo, $teacherId);
$dayName = date('l');
$periodDefaults = defaultPeriodTimes();

$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$currentPeriod = null;
$now = date('H:i');
foreach ($periodDefaults as $p => $times) {
    if ($now >= $times[0] && $now < $times[1]) {
        $currentPeriod = $p;
        break;
    }
}

$todayFilled = 0;
for ($p = 1; $p <= 8; $p++) {
    $slot = $todaySlots[$p] ?? null;
    if ($slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '')) {
        $todayFilled++;
    }
}

$notices = getActiveNotices($pdo, 5, 'Teachers');

require_once 'includes/layout_header.php';
$pwd_changed = isset($_GET['pwd_changed']);
$class_assigned = $teacher['class_assigned']
    ? htmlspecialchars($teacher['class_assigned'] . ' (' . ($teacher['section_assigned'] ?: 'A') . ')')
    : 'Not assigned';
?>

<?php if ($pwd_changed): ?>
<div class="tp-alert-success"><i class="fas fa-check-circle"></i> Password updated successfully. Welcome to your dashboard!</div>
<?php endif; ?>

<div class="tp-page-hero">
    <div class="tp-page-hero-main">
        <p class="tp-page-hero-greet"><?php echo $greeting; ?>,</p>
        <h2><?php echo $tp_name; ?></h2>
        <p class="tp-page-hero-desc"><?php echo $dayName; ?>, <?php echo date('d M Y'); ?> · <?php echo $todayFilled; ?> class<?php echo $todayFilled === 1 ? '' : 'es'; ?> scheduled today</p>
    </div>
    <div class="tp-page-hero-actions">
        <a href="attendance.php" class="tp-btn tp-btn-primary"><i class="far fa-calendar-check"></i> Mark Attendance</a>
        <a href="homework.php" class="tp-btn tp-btn-outline"><i class="fas fa-book-open"></i> Post Homework</a>
    </div>
</div>

<div class="tp-stat-grid">
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-clock"></i></div>
        <div><span>Weekly Periods</span><strong><?php echo $periodCount; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-users"></i></div>
        <div><span>Classes</span><strong><?php echo count($classes); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-book"></i></div>
        <div><span>Subject</span><strong style="font-size:1rem"><?php echo htmlspecialchars($teacher['subject']); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon purple"><i class="fas fa-calendar-day"></i></div>
        <div><span>Today</span><strong><?php echo $todayFilled; ?> / 8</strong></div>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-sun"></i> Today's Schedule</h3>
            <a href="timetable.php" class="tp-card-link">Full timetable →</a>
        </div>
        <div class="tp-schedule-list">
            <?php for ($p = 1; $p <= 8; $p++):
                $slot = $todaySlots[$p] ?? null;
                $def = $periodDefaults[$p];
                $filled = $slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '');
                $isNow = ($currentPeriod === $p);
            ?>
            <div class="tp-schedule-item <?php echo $isNow ? 'is-now' : ($filled ? '' : 'is-free'); ?>">
                <div class="tp-period-num">P<?php echo $p; ?></div>
                <div class="tp-schedule-body">
                    <?php if ($filled): ?>
                    <strong><?php echo htmlspecialchars($slot['subject_name'] ?: $teacher['subject']); ?><?php if ($isNow): ?><span class="tp-schedule-now-badge"><i class="fas fa-circle"></i> Now</span><?php endif; ?></strong>
                    <span><?php echo htmlspecialchars($slot['class_name']); ?><?php echo $slot['section_name'] ? ' · Sec ' . htmlspecialchars($slot['section_name']) : ''; ?><?php echo $slot['room_no'] ? ' · Room ' . htmlspecialchars($slot['room_no']) : ''; ?></span>
                    <?php else: ?>
                    <strong style="color:#94a3b8">Free Period</strong>
                    <span>No class scheduled</span>
                    <?php endif; ?>
                </div>
                <div class="tp-schedule-time"><?php echo $def[0]; ?> – <?php echo $def[1]; ?></div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="tp-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-users-class"></i> My Classes</h3>
            <a href="my-classes.php" class="tp-card-link">View all →</a>
        </div>
        <?php if ($classes): ?>
        <div class="tp-class-grid">
            <?php foreach (array_slice($classes, 0, 6) as $c):
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class = ? AND section = ? AND status = 'Active'");
                $stmt->execute([$c['class_name'], $c['section_name']]);
                $studentCount = (int) $stmt->fetchColumn();
            ?>
            <div class="tp-class-chip">
                <strong><?php echo htmlspecialchars($c['class_name']); ?> (<?php echo htmlspecialchars($c['section_name']); ?>)</strong>
                <span><?php echo htmlspecialchars($c['subject_name'] ?: $teacher['subject']); ?></span>
                <div class="tp-class-chip-count"><i class="fas fa-user-graduate"></i> <?php echo $studentCount; ?> students</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="tp-empty"><i class="fas fa-school"></i><p>No classes assigned yet.<br>Contact admin to set up your timetable.</p></div>
        <?php endif; ?>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card">
        <div class="tp-card-head"><h3><i class="fas fa-bolt"></i> Quick Actions</h3></div>
        <div class="tp-action-grid">
            <a href="attendance.php" class="tp-action-card">
                <div class="tp-action-card-icon green"><i class="far fa-calendar-check"></i></div>
                <div><strong>Mark Attendance</strong><span>Record daily student attendance</span></div>
            </a>
            <a href="homework.php" class="tp-action-card">
                <div class="tp-action-card-icon purple"><i class="fas fa-book-open"></i></div>
                <div><strong>Post Homework</strong><span>Assign work to your classes</span></div>
            </a>
            <a href="timetable.php" class="tp-action-card">
                <div class="tp-action-card-icon blue"><i class="fas fa-calendar-alt"></i></div>
                <div><strong>View Timetable</strong><span>Weekly teaching schedule</span></div>
            </a>
            <a href="profile.php" class="tp-action-card">
                <div class="tp-action-card-icon orange"><i class="fas fa-user"></i></div>
                <div><strong>My Profile</strong><span>Personal &amp; professional info</span></div>
            </a>
            <a href="leave.php" class="tp-action-card">
                <div class="tp-action-card-icon green"><i class="fas fa-plane-departure"></i></div>
                <div><strong>Apply Leave</strong><span>Submit leave requests</span></div>
            </a>
            <a href="my-attendance.php" class="tp-action-card">
                <div class="tp-action-card-icon blue"><i class="fas fa-user-check"></i></div>
                <div><strong>My Attendance</strong><span>Check in &amp; view record</span></div>
            </a>
        </div>
    </div>
    <div class="tp-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-bullhorn"></i> Notices</h3>
            <a href="notices.php" class="tp-card-link">View all →</a>
        </div>
        <?php if ($notices): ?>
        <div class="tp-dash-notices">
            <?php foreach ($notices as $n):
                $priority = $n['priority'] ?? 'Normal';
            ?>
            <a href="notices.php" class="tp-dash-notice-item<?php echo $priority === 'Urgent' ? ' is-urgent' : ''; ?>">
                <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                <span><?php echo date('d M Y', strtotime($n['publish_date'])); ?><?php if ($priority !== 'Normal'): ?> · <?php echo htmlspecialchars($priority); ?><?php endif; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="tp-dash-notice-empty">No new notices at this time.</p>
        <?php endif; ?>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card">
        <div class="tp-card-head"><h3><i class="fas fa-id-badge"></i> Profile Summary</h3></div>
        <div class="tp-profile-fields">
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-id-badge"></i></div>
                <div><label>Employee ID</label><span><?php echo htmlspecialchars($teacher['employee_id']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-phone"></i></div>
                <div><label>Mobile</label><span><?php echo htmlspecialchars($teacher['phone']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-envelope"></i></div>
                <div><label>Email</label><span><?php echo displayVal($teacher['email']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-chalkboard"></i></div>
                <div><label>Class Teacher</label><span><?php echo $class_assigned; ?></span></div>
            </div>
        </div>
    </div>
    <div class="tp-card">
        <div class="tp-card-head"><h3><i class="fas fa-school"></i> <?php echo htmlspecialchars($tp_school['name']); ?></h3></div>
        <div class="tp-profile-fields">
            <?php if ($tp_school['phone']): ?>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-phone"></i></div>
                <div><label>Office Phone</label><span><?php echo htmlspecialchars($tp_school['phone']); ?></span></div>
            </div>
            <?php endif; ?>
            <?php if ($tp_school['email']): ?>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-envelope"></i></div>
                <div><label>Office Email</label><span><?php echo htmlspecialchars($tp_school['email']); ?></span></div>
            </div>
            <?php endif; ?>
            <?php if ($tp_school['principal']): ?>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-user-tie"></i></div>
                <div><label>Principal</label><span><?php echo htmlspecialchars($tp_school['principal']); ?></span></div>
            </div>
            <?php endif; ?>
            <div class="tp-profile-field tp-profile-field-full">
                <div class="tp-profile-field-icon"><i class="fas fa-bullhorn"></i></div>
                <div><label>Notices &amp; Leave</label><span><a href="notices.php" style="color:#2563eb;font-weight:600">View notices</a> · <a href="leave.php" style="color:#2563eb;font-weight:600">Apply leave</a></span></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_footer.php'; ?>
