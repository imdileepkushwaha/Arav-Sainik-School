<?php
$page_title = 'Class Timetable';
require_once 'includes/init.php';

$section = trim($student['section'] ?? '') ?: 'A';
$slots = getClassTimetableFromTeachers($pdo, $student['class'], $section);
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$today = date('l');

$grid = [];
$maxPeriod = 0;
foreach ($slots as $s) {
    $grid[$s['day_of_week']][$s['period_no']] = $s;
    $maxPeriod = max($maxPeriod, (int) $s['period_no']);
}
if ($maxPeriod === 0) {
    $maxPeriod = 8;
}

$todaySlots = [];
if (isset($grid[$today])) {
    ksort($grid[$today]);
    $todaySlots = $grid[$today];
}
$hasData = !empty($slots);

require_once 'includes/layout_header.php';
?>
<?php if (!$hasData): ?>
<div class="sp-card">
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-table"></i></div><strong>Timetable not published</strong><p>The weekly timetable for Class <?php echo htmlspecialchars($student['class']); ?> · <?php echo htmlspecialchars($section); ?> hasn't been set up yet.</p></div>
</div>
<?php else: ?>

<div class="sp-card sp-tt-today">
    <div class="sp-card-head"><h3><i class="fas fa-calendar-day"></i> Today &middot; <?php echo $today; ?></h3><span class="sp-card-link" style="cursor:default"><?php echo count($todaySlots); ?> classes</span></div>
    <?php if (empty($todaySlots)): ?>
    <p class="sp-tt-today-empty"><i class="fas fa-mug-hot"></i> No classes scheduled for today. Enjoy your day off!</p>
    <?php else: ?>
    <div class="sp-tt-today-list">
        <?php foreach ($todaySlots as $p => $slot): ?>
        <div class="sp-tt-chip">
            <span class="sp-tt-chip-p">P<?php echo (int) $p; ?></span>
            <div class="sp-tt-chip-body">
                <strong><?php echo htmlspecialchars($slot['subject_name']); ?></strong>
                <small><i class="fas fa-user"></i> <?php echo htmlspecialchars($slot['teacher_name'] ?: 'TBA'); ?><?php if (!empty($slot['room_no'])): ?> &middot; Room <?php echo htmlspecialchars($slot['room_no']); ?><?php endif; ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="sp-card">
    <div class="sp-card-head"><h3><i class="fas fa-table"></i> Weekly Timetable</h3><span class="sp-card-link" style="cursor:default">Class <?php echo htmlspecialchars($student['class']); ?> &middot; <?php echo htmlspecialchars($section); ?></span></div>
    <div class="sp-table-wrap">
        <table class="sp-table sp-tt-grid">
            <thead>
                <tr>
                    <th>Period</th>
                    <?php foreach ($days as $d): ?><th class="ta-c<?php echo $d === $today ? ' is-today' : ''; ?>"><?php echo substr($d, 0, 3); ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($p = 1; $p <= $maxPeriod; $p++): ?>
                <tr>
                    <td class="sp-tt-pn">P<?php echo $p; ?></td>
                    <?php foreach ($days as $d): $slot = $grid[$d][$p] ?? null; ?>
                    <td class="ta-c<?php echo $d === $today ? ' is-today' : ''; ?>">
                        <?php if ($slot): ?>
                        <div class="sp-tt-cell">
                            <strong><?php echo htmlspecialchars($slot['subject_name']); ?></strong>
                            <small><?php echo htmlspecialchars($slot['teacher_name'] ?: 'TBA'); ?></small>
                            <?php if (!empty($slot['room_no'])): ?><span class="sp-tt-room"><?php echo htmlspecialchars($slot['room_no']); ?></span><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span class="sp-tt-free">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once 'includes/layout_footer.php'; ?>
