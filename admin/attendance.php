<?php
$page_title = "Mark Attendance";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$session = getCurrentSession($pdo);
$class_options = getClassOptions($pdo);
$class = trim($_GET['class'] ?? $_POST['class'] ?? '');
$section = trim($_GET['section'] ?? $_POST['section'] ?? 'A');
$date = trim($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $statuses = $_POST['status'] ?? [];
    $clean = [];
    foreach ($statuses as $sid => $st) {
        if (in_array($st, ['Present', 'Absent', 'Late', 'Half Day'], true)) {
            $clean[(int) $sid] = $st;
        }
    }
    saveAttendance($pdo, $_POST['date'], $_POST['class'], $_POST['section'], $clean, $session['id'] ?? null);
    $_SESSION['success_msg'] = count($clean) . ' attendance record(s) saved.';
    header('Location: attendance.php?class=' . urlencode($_POST['class']) . '&section=' . urlencode($_POST['section']) . '&date=' . urlencode($_POST['date']));
    exit;
}

require_once 'includes/header.php';
$students = ($class !== '') ? getStudentsByClassSection($pdo, $class, $section) : [];
$existing = [];
if ($students) {
    $ids = array_column($students, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT student_id, status FROM attendance_records WHERE attendance_date = ? AND student_id IN ($placeholders)");
    $stmt->execute(array_merge([$date], $ids));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $existing[$r['student_id']] = $r['status'];
    }
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="far fa-calendar-check"></i></div>
        <div class="content-top-title">
            <h2>Daily Attendance</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Attendance</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="attendance_report.php" class="btn-header-action btn-header-outline"><i class="fas fa-chart-bar"></i> Monthly Report</a>
    </div>
</div>

<div class="form-section-card section-mb">
    <form method="GET" class="category-add-form">
        <div class="category-add-row erp-filter-row">
            <div class="form-field"><label>Date</label><input type="date" name="date" class="form-input" value="<?php echo htmlspecialchars($date); ?>" required></div>
            <div class="form-field"><label>Class</label>
                <select name="class" class="form-input form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($class_options as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Section</label>
                <select name="section" class="form-input form-select">
                    <?php foreach (($class ? getSectionOptions($pdo, $class) : ['A']) as $sec): ?>
                    <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $section === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Load</button></div>
        </div>
    </form>
</div>

<?php if ($class && $students): ?>
<form method="POST">
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
    <input type="hidden" name="class" value="<?php echo htmlspecialchars($class); ?>">
    <input type="hidden" name="section" value="<?php echo htmlspecialchars($section); ?>">
    <div class="table-container">
        <div class="table-toolbar"><strong><?php echo count($students); ?> students — <?php echo htmlspecialchars($class); ?> (<?php echo htmlspecialchars($section); ?>)</strong>
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Attendance</button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Roll</th><th>Name</th><th>Admission No</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): $st = $existing[$s['id']] ?? 'Present'; ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['roll']); ?></td>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['ad_no']); ?></td>
                    <td>
                        <select name="status[<?php echo $s['id']; ?>]" class="form-input form-select table-inline-input">
                            <?php foreach (['Present','Absent','Late','Half Day'] as $opt): ?>
                            <option value="<?php echo $opt; ?>" <?php echo $st === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
<?php elseif ($class): ?>
<div class="tab-empty-state"><p>No active students in this class/section.</p></div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
