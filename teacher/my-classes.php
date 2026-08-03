<?php
$page_title = 'My Classes';
$page_subtitle = 'Classes assigned to you';
require_once 'includes/init.php';

$classes = getTeacherClassesTaught($pdo, $teacherId);
$totalStudents = 0;
$classStats = [];

foreach ($classes as $c) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class = ? AND section = ? AND status = 'Active'");
    $stmt->execute([$c['class_name'], $c['section_name']]);
    $count = (int) $stmt->fetchColumn();
    $totalStudents += $count;

    $attStmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt FROM attendance_records
         WHERE class_name = ? AND section_name = ? AND attendance_date = ?
         GROUP BY status"
    );
    $attStmt->execute([$c['class_name'], $c['section_name'], date('Y-m-d')]);
    $todayAtt = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0];
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $todayAtt[$row['status']] = (int) $row['cnt'];
    }
    $classStats[$c['class_name'] . '|' . $c['section_name']] = [
        'students' => $count,
        'att' => $todayAtt,
    ];
}

require_once 'includes/layout_header.php';
?>

<div class="tp-page-hero">
    <div class="tp-page-hero-main">
        <p class="tp-page-hero-greet">Your classes</p>
        <h2><?php echo count($classes); ?> Class<?php echo count($classes) === 1 ? '' : 'es'; ?> Assigned</h2>
        <p class="tp-page-hero-desc"><?php echo $totalStudents; ?> total students · <?php echo htmlspecialchars($teacher['subject']); ?></p>
    </div>
    <div class="tp-page-hero-actions">
        <a href="attendance.php" class="tp-btn tp-btn-primary"><i class="far fa-calendar-check"></i> Mark Attendance</a>
        <a href="homework.php" class="tp-btn tp-btn-outline"><i class="fas fa-book-open"></i> Post Homework</a>
    </div>
</div>

<?php if ($classes): ?>
<div class="tp-stat-grid cols-3">
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-school"></i></div>
        <div><span>Classes</span><strong><?php echo count($classes); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-user-graduate"></i></div>
        <div><span>Total Students</span><strong><?php echo $totalStudents; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-book"></i></div>
        <div><span>Subject</span><strong style="font-size:1rem"><?php echo htmlspecialchars($teacher['subject']); ?></strong></div>
    </div>
</div>

<div class="tp-mc-grid">
    <?php foreach ($classes as $c):
        $key = $c['class_name'] . '|' . $c['section_name'];
        $stat = $classStats[$key] ?? ['students' => 0, 'att' => []];
        $att = $stat['att'];
        $marked = ($att['Present'] ?? 0) + ($att['Absent'] ?? 0) + ($att['Late'] ?? 0) + ($att['Half Day'] ?? 0);
    ?>
    <div class="tp-mc-card">
        <div class="tp-mc-card-top">
            <div class="tp-mc-icon"><i class="fas fa-chalkboard"></i></div>
            <div>
                <h3><?php echo htmlspecialchars($c['class_name']); ?> <span>(<?php echo htmlspecialchars($c['section_name']); ?>)</span></h3>
                <p><?php echo htmlspecialchars($c['subject_name'] ?: $teacher['subject']); ?></p>
            </div>
        </div>
        <div class="tp-mc-stats">
            <div class="tp-mc-stat"><span>Students</span><strong><?php echo $stat['students']; ?></strong></div>
            <div class="tp-mc-stat is-green"><span>Present Today</span><strong><?php echo $att['Present'] ?? 0; ?></strong></div>
            <div class="tp-mc-stat is-red"><span>Absent Today</span><strong><?php echo $att['Absent'] ?? 0; ?></strong></div>
            <div class="tp-mc-stat"><span>Marked</span><strong><?php echo $marked; ?>/<?php echo $stat['students']; ?></strong></div>
        </div>
        <div class="tp-mc-actions">
            <a href="class-students.php?class=<?php echo urlencode($c['class_name']); ?>&section=<?php echo urlencode($c['section_name']); ?>" class="tp-btn tp-btn-outline tp-btn-sm"><i class="fas fa-users"></i> Students</a>
            <a href="attendance.php?class=<?php echo urlencode($c['class_name']); ?>&section=<?php echo urlencode($c['section_name']); ?>" class="tp-btn tp-btn-primary tp-btn-sm"><i class="far fa-calendar-check"></i> Attendance</a>
            <a href="homework.php?class=<?php echo urlencode($c['class_name']); ?>&section=<?php echo urlencode($c['section_name']); ?>" class="tp-btn tp-btn-outline tp-btn-sm"><i class="fas fa-book"></i> Homework</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="tp-card">
    <div class="tp-empty">
        <i class="fas fa-users"></i>
        <h3 style="margin:12px 0 8px;color:var(--tp-text)">No Classes Yet</h3>
        <p>Your timetable or class assignment has not been configured.<br>Contact the school admin to set up your schedule.</p>
        <a href="timetable.php" class="tp-btn tp-btn-outline" style="margin-top:16px"><i class="fas fa-calendar-alt"></i> View Timetable</a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/layout_footer.php'; ?>
