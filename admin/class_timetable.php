<?php
$page_title = "Class Timetable";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/class_helpers.php';

ensureErpSchema($pdo);
$class_options = getClassOptions($pdo);
$className = trim($_GET['class'] ?? ($class_options[0] ?? ''));
$section = trim($_GET['section'] ?? 'A');
$sections = [];
foreach (getAllClasses($pdo, false) as $c) {
    if ($c['name'] === $className) {
        $sections = getSectionsForClassId($pdo, $c['id']);
        break;
    }
}
if (empty($sections)) {
    $sections = [['name' => 'A']];
}
$slots = $className ? getClassTimetableFromTeachers($pdo, $className, $section) : [];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$grid = [];
foreach ($slots as $s) {
    $grid[$s['day_of_week']][$s['period_no']] = $s;
}
$maxPeriod = 0;
foreach ($slots as $s) {
    $maxPeriod = max($maxPeriod, (int) $s['period_no']);
}
if ($maxPeriod === 0) {
    $maxPeriod = 8;
}

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-table"></i></div>
        <div class="content-top-title">
            <h2>Class Timetable</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Timetable</span></p>
        </div>
    </div>
    <div class="content-top-actions"><a href="teacher_timetable.php" class="btn-header-action btn-header-outline"><i class="fas fa-edit"></i> Edit (Teacher View)</a></div>
</div>

<div class="form-section-card section-mb">
    <form method="GET" class="category-add-row">
        <div class="form-field"><label>Class</label><select name="class" class="form-input form-select" onchange="this.form.submit()"><?php foreach ($class_options as $c): ?><option <?php echo $className === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label>Section</label><select name="section" class="form-input form-select"><?php foreach ($sections as $sec): $sn = is_array($sec) ? $sec['name'] : $sec; ?><option <?php echo $section === $sn ? 'selected' : ''; ?>><?php echo htmlspecialchars($sn); ?></option><?php endforeach; ?></select></div>
        <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn">View</button></div>
    </form>
</div>

<?php if ($className): ?>
<div class="table-container">
    <div class="table-toolbar"><strong><?php echo htmlspecialchars($className); ?> — Section <?php echo htmlspecialchars($section); ?></strong><span class="toolbar-meta">Built from teacher timetables</span></div>
    <div class="table-wrapper table-scroll-x">
        <table class="timetable-grid-table">
            <thead><tr><th>Period</th><?php foreach ($days as $d): ?><th><?php echo $d; ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php for ($p = 1; $p <= $maxPeriod; $p++): ?>
            <tr>
                <td><strong>P<?php echo $p; ?></strong></td>
                <?php foreach ($days as $d):
                    $slot = $grid[$d][$p] ?? null;
                ?>
                <td>
                    <?php if ($slot): ?>
                    <div class="teacher-tt-slot" style="min-height:60px;padding:8px">
                        <strong><?php echo htmlspecialchars($slot['subject_name']); ?></strong>
                        <small><?php echo htmlspecialchars($slot['teacher_name']); ?></small>
                        <?php if ($slot['room_no']): ?><small>Room <?php echo htmlspecialchars($slot['room_no']); ?></small><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="teacher-tt-slot is-free"><span class="free-label">Free</span></div>
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
<?php require_once 'includes/footer.php'; ?>
