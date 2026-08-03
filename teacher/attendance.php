<?php
$page_title = 'Mark Attendance';
$page_subtitle = 'Record daily attendance for your classes';
require_once 'includes/init.php';

$session = getCurrentSession($pdo);
$classes = getTeacherClassesTaught($pdo, $teacherId);
$class = trim($_GET['class'] ?? $_POST['class'] ?? '');
$section = trim($_GET['section'] ?? $_POST['section'] ?? 'A');
$date = trim($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $class = trim($_POST['class']);
    $section = trim($_POST['section']);
    if (!teacherCanAccessClass($pdo, $teacherId, $class, $section)) {
        $error = 'You are not assigned to this class.';
    } else {
        $statuses = $_POST['status'] ?? [];
        $clean = [];
        foreach ($statuses as $sid => $st) {
            if (in_array($st, ['Present', 'Absent', 'Late', 'Half Day'], true)) {
                $clean[(int) $sid] = $st;
            }
        }
        saveAttendance($pdo, $_POST['date'], $class, $section, $clean, $session['id'] ?? null);
        $message = count($clean) . ' attendance record(s) saved successfully.';
        $date = $_POST['date'];
    }
}

$students = [];
$existing = [];
if ($class && teacherCanAccessClass($pdo, $teacherId, $class, $section)) {
    $students = getStudentsByClassSection($pdo, $class, $section);
    if ($students) {
        $ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT student_id, status FROM attendance_records WHERE attendance_date = ? AND student_id IN ($placeholders)");
        $stmt->execute(array_merge([$date], $ids));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $existing[$r['student_id']] = $r['status'];
        }
    }
}

$attCounts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0];
if ($students) {
    foreach ($students as $s) {
        $st = $existing[$s['id']] ?? 'Present';
        if (isset($attCounts[$st])) {
            $attCounts[$st]++;
        }
    }
}

$isToday = ($date === date('Y-m-d'));
$dateLabel = date('l, d M Y', strtotime($date));

require_once 'includes/layout_header.php';
?>

<?php if ($message): ?><div class="tp-alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="tp-alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="tp-tt-hero is-green">
    <div>
        <h2><i class="far fa-calendar-check"></i> Mark Attendance</h2>
        <p>Record daily attendance for your assigned classes · <?php echo count($classes); ?> class<?php echo count($classes) === 1 ? '' : 'es'; ?> available</p>
        <div class="tp-tt-hero-chips">
            <span class="tp-tt-hero-chip"><i class="fas fa-calendar-day"></i> <?php echo $isToday ? 'Today' : $dateLabel; ?></span>
            <?php if ($class && $students): ?>
            <span class="tp-tt-hero-chip"><i class="fas fa-users"></i> <?php echo htmlspecialchars($class); ?> (<?php echo htmlspecialchars($section); ?>)</span>
            <span class="tp-tt-hero-chip"><i class="fas fa-user-graduate"></i> <?php echo count($students); ?> students</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="tp-tt-hero-actions">
        <a href="my-classes.php" class="tp-tt-hero-btn"><i class="fas fa-chalkboard"></i> My Classes</a>
        <?php if ($students): ?>
        <a href="#attForm" class="tp-tt-hero-btn is-solid"><i class="fas fa-save"></i> Save Below</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($students): ?>
<div class="tp-stat-grid cols-4">
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-check"></i></div>
        <div><span>Present</span><strong id="cntPresentTop"><?php echo $attCounts['Present']; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon red"><i class="fas fa-times"></i></div>
        <div><span>Absent</span><strong id="cntAbsentTop"><?php echo $attCounts['Absent']; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-clock"></i></div>
        <div><span>Late</span><strong id="cntLateTop"><?php echo $attCounts['Late']; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-adjust"></i></div>
        <div><span>Half Day</span><strong id="cntHalfTop"><?php echo $attCounts['Half Day']; ?></strong></div>
    </div>
</div>
<?php endif; ?>

<div class="tp-card tp-filter-card">
    <div class="tp-card-head">
        <h3><i class="far fa-calendar-check"></i> Select Class &amp; Date</h3>
    </div>
    <form method="GET" class="tp-filter-form">
        <div class="tp-field">
            <label>Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required>
        </div>
        <div class="tp-field">
            <label>Class</label>
            <select name="class" required>
                <option value="">Select class</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?php echo htmlspecialchars($c['class_name']); ?>" data-section="<?php echo htmlspecialchars($c['section_name']); ?>" <?php echo $class === $c['class_name'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['class_name'] . ' (' . $c['section_name'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="tp-field">
            <label>Section</label>
            <input type="text" name="section" value="<?php echo htmlspecialchars($section); ?>" readonly>
        </div>
        <div class="tp-field tp-field-btn">
            <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-users"></i> Load Students</button>
        </div>
    </form>
</div>

<?php if ($class && !$students && !$error): ?>
<div class="tp-card"><div class="tp-empty"><i class="fas fa-user-slash"></i><p>No active students in this class.</p></div></div>
<?php elseif ($students): ?>
<form method="POST" id="attForm">
    <input type="hidden" name="save_attendance" value="1">
    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
    <input type="hidden" name="class" value="<?php echo htmlspecialchars($class); ?>">
    <input type="hidden" name="section" value="<?php echo htmlspecialchars($section); ?>">
    <div class="tp-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-user-graduate"></i> <?php echo count($students); ?> Students — <?php echo htmlspecialchars($class); ?> (<?php echo htmlspecialchars($section); ?>)</h3>
            <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-save"></i> Save Attendance</button>
        </div>

        <div class="tp-att-summary">
            <div class="tp-att-stat is-present"><span>Present</span><strong id="cntPresent"><?php echo $attCounts['Present']; ?></strong></div>
            <div class="tp-att-stat is-absent"><span>Absent</span><strong id="cntAbsent"><?php echo $attCounts['Absent']; ?></strong></div>
            <div class="tp-att-stat is-late"><span>Late</span><strong id="cntLate"><?php echo $attCounts['Late']; ?></strong></div>
            <div class="tp-att-stat is-half"><span>Half Day</span><strong id="cntHalf"><?php echo $attCounts['Half Day']; ?></strong></div>
        </div>

        <div class="tp-bulk-bar">
            <span>Quick mark:</span>
            <button type="button" class="tp-bulk-btn is-present" data-bulk="Present"><i class="fas fa-check"></i> All Present</button>
            <button type="button" class="tp-bulk-btn is-absent" data-bulk="Absent"><i class="fas fa-times"></i> All Absent</button>
        </div>

        <div class="tp-table-wrap">
            <table class="tp-table">
                <thead><tr><th>Roll</th><th>Student</th><th>Admission No</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s):
                    $st = $existing[$s['id']] ?? 'Present';
                    $initials = strtoupper(substr($s['name'], 0, 1));
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
                    <td>
                        <select name="status[<?php echo $s['id']; ?>]" class="tp-att-select" data-status="<?php echo htmlspecialchars($st); ?>">
                            <?php foreach (['Present', 'Absent', 'Late', 'Half Day'] as $opt): ?>
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
<?php elseif (!$classes): ?>
<div class="tp-card"><div class="tp-empty"><i class="fas fa-school"></i><p>No classes assigned. Contact admin to set up your timetable.</p></div></div>
<?php else: ?>
<div class="tp-card"><div class="tp-empty"><i class="far fa-calendar-check"></i><p>Select a class and date above to mark attendance.</p></div></div>
<?php endif; ?>

<script>
(function () {
    var cls = document.querySelector('select[name="class"]');
    var sec = document.querySelector('input[name="section"]');
    if (cls && sec) {
        cls.addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            sec.value = opt ? (opt.getAttribute('data-section') || 'A') : 'A';
        });
        if (cls.value) cls.dispatchEvent(new Event('change'));
    }

    function updateCounts() {
        var counts = { Present: 0, Absent: 0, Late: 0, 'Half Day': 0 };
        document.querySelectorAll('.tp-att-select').forEach(function (sel) {
            if (counts[sel.value] !== undefined) counts[sel.value]++;
            sel.setAttribute('data-status', sel.value);
        });
        var map = { Present: 'cntPresent', Absent: 'cntAbsent', Late: 'cntLate', 'Half Day': 'cntHalf' };
        Object.keys(map).forEach(function (k) {
            var el = document.getElementById(map[k]);
            if (el) el.textContent = counts[k];
            var top = document.getElementById(map[k] + 'Top');
            if (top) top.textContent = counts[k];
        });
    }

    document.querySelectorAll('.tp-att-select').forEach(function (sel) {
        sel.addEventListener('change', updateCounts);
    });

    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var val = this.getAttribute('data-bulk');
            document.querySelectorAll('.tp-att-select').forEach(function (sel) {
                sel.value = val;
            });
            updateCounts();
        });
    });
})();
</script>

<?php require_once 'includes/layout_footer.php'; ?>
