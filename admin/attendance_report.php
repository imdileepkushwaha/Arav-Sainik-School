<?php
$page_title = "Attendance Report";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$class_options = getClassOptions($pdo);
$class = trim($_GET['class'] ?? '');
$section = trim($_GET['section'] ?? 'A');
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));

require_once 'includes/header.php';
$report = ($class !== '') ? getAttendanceMonthlyReport($pdo, $class, $section, $year, $month) : null;
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-chart-bar"></i></div>
        <div class="content-top-title">
            <h2>Monthly Attendance Report</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><a href="attendance.php">Attendance</a><i class="fas fa-chevron-right"></i><span>Report</span></p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb">
    <form method="GET" class="category-add-form">
        <div class="category-add-row erp-filter-row-4">
            <div class="form-field"><label>Class</label><select name="class" class="form-input form-select" required><option value="">Select</option><?php foreach ($class_options as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label>Section</label><select name="section" class="form-input form-select"><?php foreach (($class ? getSectionOptions($pdo, $class) : ['A']) as $sec): ?><option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $section === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label>Month</label><select name="month" class="form-input form-select"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option><?php endfor; ?></select></div>
            <div class="form-field"><label>Year</label><input type="number" name="year" class="form-input" value="<?php echo $year; ?>"></div>
            <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn">View Report</button></div>
        </div>
    </form>
</div>

<?php if ($report): ?>
<div class="table-container">
    <div class="table-toolbar"><strong><?php echo htmlspecialchars($class); ?> (<?php echo htmlspecialchars($section); ?>) — <?php echo date('F Y', strtotime($report['start'])); ?></strong></div>
    <div class="table-wrapper table-scroll-x">
        <table>
            <thead><tr><th>Student</th><th>Roll</th><th>Present</th><th>Absent</th><th>Late</th><th>Half Day</th><th>%</th></tr></thead>
            <tbody>
            <?php foreach ($report['students'] as $s):
                $rec = $report['records'][$s['id']] ?? [];
                $counts = ['Present'=>0,'Absent'=>0,'Late'=>0,'Half Day'=>0];
                foreach ($rec as $st) { if (isset($counts[$st])) $counts[$st]++; }
                $total = array_sum($counts);
                $pct = $total ? round(($counts['Present'] + $counts['Late'] * 0.5) / $total * 100) : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($s['name']); ?></td>
                <td><?php echo htmlspecialchars($s['roll']); ?></td>
                <td><?php echo $counts['Present']; ?></td>
                <td><?php echo $counts['Absent']; ?></td>
                <td><?php echo $counts['Late']; ?></td>
                <td><?php echo $counts['Half Day']; ?></td>
                <td><?php echo $pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
