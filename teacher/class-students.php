<?php
$page_title = 'Class Students';
$page_subtitle = 'Student roster for your class';
require_once 'includes/init.php';

$class = trim($_GET['class'] ?? '');
$section = trim($_GET['section'] ?? 'A');

if (!$class || !teacherCanAccessClass($pdo, $teacherId, $class, $section)) {
    tp_flash('my-classes.php', 'You do not have access to this class.', 'error');
}

$students = getStudentsByClassSection($pdo, $class, $section);
$today = date('Y-m-d');
$attToday = [];
if ($students) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT student_id, status FROM attendance_records WHERE attendance_date = ? AND student_id IN ($placeholders)");
    $stmt->execute(array_merge([$today], $ids));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $attToday[$r['student_id']] = $r['status'];
    }
}

require_once 'includes/layout_header.php';
?>

<div class="tp-page-hero" style="margin-bottom:20px">
    <div class="tp-page-hero-main">
        <p class="tp-page-hero-greet">Class roster</p>
        <h2><?php echo htmlspecialchars($class); ?> (<?php echo htmlspecialchars($section); ?>)</h2>
        <p class="tp-page-hero-desc"><?php echo count($students); ?> active student<?php echo count($students) === 1 ? '' : 's'; ?> · Read-only view</p>
    </div>
    <div class="tp-page-hero-actions">
        <a href="my-classes.php" class="tp-btn tp-btn-outline"><i class="fas fa-arrow-left"></i> My Classes</a>
        <a href="attendance.php?class=<?php echo urlencode($class); ?>&section=<?php echo urlencode($section); ?>&date=<?php echo $today; ?>" class="tp-btn tp-btn-primary"><i class="far fa-calendar-check"></i> Mark Attendance</a>
    </div>
</div>

<div class="tp-card">
    <div class="tp-card-head">
        <h3><i class="fas fa-user-graduate"></i> Students</h3>
        <span class="tp-card-badge"><i class="fas fa-calendar-day"></i> Today: <?php echo date('d M Y'); ?></span>
    </div>
    <?php if ($students): ?>
    <div class="tp-table-wrap">
        <table class="tp-table">
            <thead>
                <tr>
                    <th>Roll</th>
                    <th>Student</th>
                    <th>Admission No</th>
                    <th>Mobile</th>
                    <th>Today's Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s):
                $initials = strtoupper(substr($s['name'], 0, 1));
                $st = $attToday[$s['id']] ?? null;
                $stClass = 'is-none';
                if ($st === 'Present') $stClass = 'is-present';
                elseif ($st === 'Absent') $stClass = 'is-absent';
                elseif ($st === 'Late') $stClass = 'is-late';
                elseif ($st === 'Half Day') $stClass = 'is-half';
            ?>
            <tr>
                <td><?php echo htmlspecialchars($s['roll']); ?></td>
                <td>
                    <div class="tp-student-cell">
                        <div class="tp-student-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <strong><?php echo htmlspecialchars($s['name']); ?></strong>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($s['ad_no']); ?></td>
                <td><?php echo displayVal($s['mobile'] ?? $s['phone'] ?? ''); ?></td>
                <td>
                    <?php if ($st): ?>
                    <span class="tp-att-pill <?php echo $stClass; ?>"><?php echo htmlspecialchars($st); ?></span>
                    <?php else: ?>
                    <span class="tp-att-pill is-none">Not marked</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="tp-empty"><i class="fas fa-user-slash"></i><p>No active students in this class.</p></div>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout_footer.php'; ?>
