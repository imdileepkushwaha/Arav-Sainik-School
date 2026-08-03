<?php
$page_title = 'Dashboard';
$page_subtitle = 'Class ' . ($student['class'] ?? '') . ' · Section ' . ($student['section'] ?? 'A');
require_once 'includes/init.php';

$id = (int) $_SESSION['student_portal_id'];
$fee = getStudentFeeSummary($pdo, $id);
$attendance = getStudentAttendanceSummary($pdo, $id, (int) date('Y'), (int) date('n'));
$notices = getActiveNotices($pdo, 3, 'Students');
$hwStmt = $pdo->prepare("SELECT * FROM homework WHERE class_name = ? AND section_name = ? ORDER BY due_date DESC LIMIT 4");
$hwStmt->execute([$student['class'], $student['section'] ?? 'A']);
$homework = $hwStmt->fetchAll(PDO::FETCH_ASSOC);

$attSummary = $attendance['summary'];
$attTotal = array_sum($attSummary);
$attPct = $attTotal ? round($attSummary['Present'] / $attTotal * 100) : 0;
$firstName = trim(explode(' ', $student['name'])[0]);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

require_once 'includes/layout_header.php';
?>
<div class="sp-welcome">
    <img class="sp-welcome-avatar" src="<?php echo htmlspecialchars($sp_photo); ?>" alt="">
    <div class="sp-welcome-body">
        <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($firstName); ?>!</h2>
        <p>Here's a quick overview of your school activity.</p>
        <div class="sp-welcome-chips">
            <span class="sp-welcome-chip"><i class="fas fa-id-card"></i> <?php echo $sp_ad_no; ?></span>
            <span class="sp-welcome-chip"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($student['class']); ?> · <?php echo htmlspecialchars($student['section'] ?? 'A'); ?></span>
            <span class="sp-welcome-chip"><i class="fas fa-hashtag"></i> Roll <?php echo htmlspecialchars($student['roll']); ?></span>
        </div>
    </div>
    <div class="sp-welcome-date">
        <strong><?php echo date('d'); ?></strong>
        <span><?php echo date('M Y'); ?></span>
    </div>
</div>

<div class="sp-stat-grid">
    <div class="sp-stat tone-green">
        <div class="sp-stat-icon"><i class="fas fa-percent"></i></div>
        <div class="sp-stat-body"><span>Attendance</span><strong><?php echo $attPct; ?>%</strong><small><?php echo $attSummary['Present']; ?> present this month</small></div>
    </div>
    <div class="sp-stat tone-purple">
        <div class="sp-stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="sp-stat-body"><span>Fee Balance</span><strong>₹<?php echo number_format($fee['balance'] ?? 0, 0); ?></strong><small>₹<?php echo number_format($fee['total_paid'] ?? 0, 0); ?> paid so far</small></div>
    </div>
    <div class="sp-stat tone-blue">
        <div class="sp-stat-icon"><i class="fas fa-book"></i></div>
        <div class="sp-stat-body"><span>Homework</span><strong><?php echo count($homework); ?></strong><small>Recent assignments</small></div>
    </div>
    <div class="sp-stat tone-amber">
        <div class="sp-stat-icon"><i class="fas fa-bullhorn"></i></div>
        <div class="sp-stat-body"><span>Notices</span><strong><?php echo count($notices); ?></strong><small>Active for students</small></div>
    </div>
</div>

<div class="sp-card sp-quick-card">
    <div class="sp-card-head"><h3><i class="fas fa-bolt"></i> Quick Access</h3></div>
    <div class="sp-quick-grid">
        <a href="results.php" class="sp-quick tone-purple"><span class="sp-quick-ic"><i class="fas fa-chart-line"></i></span><strong>Exam Results</strong><small>Marks &amp; report card</small></a>
        <a href="timetable.php" class="sp-quick tone-blue"><span class="sp-quick-ic"><i class="fas fa-table"></i></span><strong>Timetable</strong><small>Weekly schedule</small></a>
        <a href="attendance.php" class="sp-quick tone-green"><span class="sp-quick-ic"><i class="far fa-calendar-check"></i></span><strong>Attendance</strong><small>Your record</small></a>
        <a href="fees.php" class="sp-quick tone-amber"><span class="sp-quick-ic"><i class="fas fa-file-invoice-dollar"></i></span><strong>Fees</strong><small>Pay &amp; receipts</small></a>
        <a href="certificates.php" class="sp-quick tone-purple"><span class="sp-quick-ic"><i class="fas fa-certificate"></i></span><strong>Certificates</strong><small>View &amp; download</small></a>
        <a href="documents.php" class="sp-quick tone-blue"><span class="sp-quick-ic"><i class="fas fa-folder-open"></i></span><strong>Documents</strong><small>Your files</small></a>
    </div>
</div>

<div class="sp-grid-2">
    <div class="sp-card">
        <div class="sp-card-head">
            <h3><i class="fas fa-bullhorn"></i> Latest Notices</h3>
            <a href="notices.php" class="sp-card-link">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if ($notices): ?>
        <div class="sp-list">
            <?php foreach ($notices as $n):
                $prio = $n['priority'] ?? 'Normal';
                $ptone = $prio === 'Urgent' ? 'tone-amber' : 'tone-blue';
            ?>
            <div class="sp-list-row">
                <div class="sp-list-ico <?php echo $ptone; ?>"><i class="fas fa-bullhorn"></i></div>
                <div class="sp-list-main">
                    <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                    <p><?php echo htmlspecialchars(mb_substr($n['body'], 0, 110)); ?><?php echo mb_strlen($n['body']) > 110 ? '…' : ''; ?></p>
                    <small><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($n['publish_date'])); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-bullhorn"></i></div><strong>No notices</strong><p>You're all caught up.</p></div>
        <?php endif; ?>
    </div>

    <div class="sp-card">
        <div class="sp-card-head">
            <h3><i class="fas fa-book-open"></i> Recent Homework</h3>
            <a href="homework.php" class="sp-card-link">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php if ($homework): ?>
        <div class="sp-list">
            <?php foreach ($homework as $h): ?>
            <div class="sp-list-row">
                <div class="sp-list-ico"><i class="fas fa-book"></i></div>
                <div class="sp-list-main">
                    <strong><?php echo htmlspecialchars($h['title']); ?></strong>
                    <?php if ($h['due_date']): ?><small><i class="far fa-clock"></i> Due <?php echo date('d M Y', strtotime($h['due_date'])); ?></small><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-book-open"></i></div><strong>No homework</strong><p>Nothing assigned right now.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/layout_footer.php'; ?>
