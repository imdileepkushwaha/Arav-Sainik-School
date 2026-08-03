<?php
$page_title = 'Homework';
$page_subtitle = 'Post and manage homework for your classes';
require_once 'includes/init.php';

$session = getCurrentSession($pdo);
$classes = getTeacherClassesTaught($pdo, $teacherId);
$class = trim($_GET['class'] ?? $_POST['class_name'] ?? '');
$section = trim($_GET['section'] ?? $_POST['section_name'] ?? 'A');
$editId = (int) ($_GET['edit'] ?? $_POST['homework_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_homework'])) {
    $class = trim($_POST['class_name']);
    $section = trim($_POST['section_name'] ?? 'A');
    $title = trim($_POST['title'] ?? '');
    if (!teacherCanAccessClass($pdo, $teacherId, $class, $section)) {
        tp_flash('homework.php', 'You cannot post homework for this class.', 'error');
    } elseif ($title === '') {
        tp_flash('homework.php', 'Title is required.', 'error');
    } else {
        $pdo->prepare("INSERT INTO homework (class_name, section_name, title, description, due_date, session_id) VALUES (?,?,?,?,?,?)")
            ->execute([
                $class, $section, $title,
                trim($_POST['description'] ?? ''),
                $_POST['due_date'] ?: null,
                $session['id'] ?? null,
            ]);
        tp_flash('homework.php', 'Homework posted successfully.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_homework'])) {
    $hid = (int) $_POST['homework_id'];
    if (!tp_teacherOwnsHomework($pdo, $teacherId, $hid)) {
        tp_flash('homework.php', 'You cannot edit this homework.', 'error');
    } else {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            tp_flash('homework.php?edit=' . $hid, 'Title is required.', 'error');
        } else {
            $pdo->prepare("UPDATE homework SET title = ?, description = ?, due_date = ? WHERE id = ?")
                ->execute([
                    $title,
                    trim($_POST['description'] ?? ''),
                    $_POST['due_date'] ?: null,
                    $hid,
                ]);
            tp_flash('homework.php', 'Homework updated successfully.');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_homework'])) {
    $hid = (int) $_POST['homework_id'];
    if (!tp_teacherOwnsHomework($pdo, $teacherId, $hid)) {
        tp_flash('homework.php', 'You cannot delete this homework.', 'error');
    } else {
        $pdo->prepare("DELETE FROM homework WHERE id = ?")->execute([$hid]);
        tp_flash('homework.php', 'Homework deleted.');
    }
}

$myHomework = [];
if ($classes) {
    $conditions = [];
    $params = [];
    foreach ($classes as $c) {
        $conditions[] = '(class_name = ? AND section_name = ?)';
        $params[] = $c['class_name'];
        $params[] = $c['section_name'];
    }
    $sql = "SELECT * FROM homework WHERE " . implode(' OR ', $conditions) . " ORDER BY id DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $myHomework = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$editRow = null;
if ($editId && tp_teacherOwnsHomework($pdo, $teacherId, $editId)) {
    $stmt = $pdo->prepare("SELECT * FROM homework WHERE id = ?");
    $stmt->execute([$editId]);
    $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

$overdueCount = 0;
$dueSoonCount = 0;
foreach ($myHomework as $h) {
    if (empty($h['due_date'])) {
        continue;
    }
    $dueTs = strtotime($h['due_date']);
    if ($dueTs < strtotime('today')) {
        $overdueCount++;
    } elseif ($dueTs <= strtotime('+7 days')) {
        $dueSoonCount++;
    }
}

require_once 'includes/layout_header.php';
?>

<div class="tp-tt-hero is-purple">
    <div>
        <h2><i class="fas fa-book-open"></i> Homework</h2>
        <p>Post and manage assignments for <?php echo htmlspecialchars($teacher['subject']); ?> · <?php echo count($classes); ?> class<?php echo count($classes) === 1 ? '' : 'es'; ?></p>
        <div class="tp-tt-hero-chips">
            <span class="tp-tt-hero-chip"><i class="fas fa-list"></i> <?php echo count($myHomework); ?> posted</span>
            <?php if ($dueSoonCount): ?><span class="tp-tt-hero-chip"><i class="fas fa-hourglass-half"></i> <?php echo $dueSoonCount; ?> due soon</span><?php endif; ?>
            <?php if ($overdueCount): ?><span class="tp-tt-hero-chip"><i class="fas fa-exclamation-circle"></i> <?php echo $overdueCount; ?> overdue</span><?php endif; ?>
        </div>
    </div>
    <div class="tp-tt-hero-actions">
        <a href="my-classes.php" class="tp-tt-hero-btn"><i class="fas fa-users"></i> My Classes</a>
        <?php if ($classes && !$editRow): ?>
        <a href="#hwForm" class="tp-tt-hero-btn is-solid"><i class="fas fa-plus"></i> Post New</a>
        <?php endif; ?>
    </div>
</div>

<div class="tp-stat-grid cols-3">
    <div class="tp-stat-card">
        <div class="tp-stat-icon purple"><i class="fas fa-book-open"></i></div>
        <div><span>Total Posted</span><strong><?php echo count($myHomework); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-users"></i></div>
        <div><span>My Classes</span><strong><?php echo count($classes); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-book"></i></div>
        <div><span>Subject</span><strong style="font-size:1rem"><?php echo htmlspecialchars($teacher['subject']); ?></strong></div>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card" id="hwForm">
        <div class="tp-card-head">
            <h3><i class="fas fa-<?php echo $editRow ? 'pen' : 'plus-circle'; ?>"></i> <?php echo $editRow ? 'Edit Homework' : 'Post New Homework'; ?></h3>
            <?php if ($editRow): ?><a href="homework.php" class="tp-card-link">Cancel edit</a><?php endif; ?>
        </div>
        <?php if ($classes): ?>
        <form method="POST" class="tp-form-panel">
            <?php if ($editRow): ?>
            <input type="hidden" name="update_homework" value="1">
            <input type="hidden" name="homework_id" value="<?php echo (int) $editRow['id']; ?>">
            <?php else: ?>
            <input type="hidden" name="add_homework" value="1">
            <?php endif; ?>
            <div class="tp-form-grid" style="grid-template-columns:1fr">
                <?php if (!$editRow): ?>
                <div class="tp-field">
                    <label>Class</label>
                    <select name="class_name" id="hwClass" required>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['class_name']); ?>" data-section="<?php echo htmlspecialchars($c['section_name']); ?>" <?php echo $class === $c['class_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_name'] . ' (' . $c['section_name'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="section_name" id="hwSection" value="<?php echo htmlspecialchars($section ?: ($classes[0]['section_name'] ?? 'A')); ?>">
                <?php else: ?>
                <div class="tp-profile-note" style="margin-bottom:12px">
                    <i class="fas fa-school"></i>
                    <?php echo htmlspecialchars($editRow['class_name'] . ' (' . $editRow['section_name'] . ')'); ?>
                </div>
                <?php endif; ?>
                <div class="tp-field">
                    <label>Title</label>
                    <input type="text" name="title" value="<?php echo $editRow ? htmlspecialchars($editRow['title']) : ''; ?>" placeholder="e.g. Chapter 5 exercises" required>
                </div>
                <div class="tp-field">
                    <label>Due Date</label>
                    <input type="date" name="due_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo $editRow && $editRow['due_date'] ? htmlspecialchars($editRow['due_date']) : ''; ?>">
                </div>
                <div class="tp-field">
                    <label>Description / Instructions</label>
                    <textarea name="description" rows="4" placeholder="Write clear instructions for students..."><?php echo $editRow ? htmlspecialchars($editRow['description']) : ''; ?></textarea>
                </div>
                <div>
                    <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-<?php echo $editRow ? 'save' : 'paper-plane'; ?>"></i> <?php echo $editRow ? 'Save Changes' : 'Post Homework'; ?></button>
                </div>
            </div>
        </form>
        <?php else: ?>
        <div class="tp-empty"><i class="fas fa-school"></i><p>No classes assigned yet.<br>Contact admin to set up your timetable.</p></div>
        <?php endif; ?>
    </div>

    <div class="tp-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-list"></i> Recent Homework</h3>
            <?php if ($myHomework): ?><span class="tp-card-badge"><?php echo count($myHomework); ?> items</span><?php endif; ?>
        </div>
        <?php if ($myHomework): ?>
        <div class="tp-hw-list">
            <?php foreach ($myHomework as $h):
                $isOverdue = $h['due_date'] && strtotime($h['due_date']) < strtotime('today');
            ?>
            <div class="tp-hw-card">
                <div class="tp-hw-card-head">
                    <strong><?php echo htmlspecialchars($h['title']); ?></strong>
                    <span class="tp-hw-badge"><i class="fas fa-school"></i> <?php echo htmlspecialchars($h['class_name'] . ' (' . $h['section_name'] . ')'); ?></span>
                </div>
                <div class="tp-hw-meta">
                    <?php if ($h['due_date']): ?>
                    <i class="fas fa-calendar"></i>
                    Due <?php echo date('d M Y', strtotime($h['due_date'])); ?>
                    <?php if ($isOverdue): ?><span style="color:#dc2626;font-weight:700"> · Overdue</span><?php endif; ?>
                    <?php else: ?>
                    <i class="fas fa-infinity"></i> No due date
                    <?php endif; ?>
                    · Posted <?php echo date('d M Y', strtotime($h['created_at'])); ?>
                </div>
                <?php if ($h['description']): ?>
                <p class="tp-hw-desc"><?php echo nl2br(htmlspecialchars($h['description'])); ?></p>
                <?php endif; ?>
                <div class="tp-hw-actions">
                    <a href="homework.php?edit=<?php echo (int) $h['id']; ?>" class="tp-btn tp-btn-outline tp-btn-sm"><i class="fas fa-pen"></i> Edit</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this homework?');">
                        <input type="hidden" name="delete_homework" value="1">
                        <input type="hidden" name="homework_id" value="<?php echo (int) $h['id']; ?>">
                        <button type="submit" class="tp-btn tp-btn-outline tp-btn-sm" style="color:#dc2626;border-color:#fecaca"><i class="fas fa-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="tp-empty"><i class="fas fa-book"></i><p>No homework posted yet.<br>Use the form to assign your first homework.</p></div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var cls = document.getElementById('hwClass');
    var sec = document.getElementById('hwSection');
    if (cls && sec) {
        function sync() {
            var opt = cls.options[cls.selectedIndex];
            sec.value = opt ? (opt.getAttribute('data-section') || 'A') : 'A';
        }
        cls.addEventListener('change', sync);
        sync();
    }
})();
</script>

<?php require_once 'includes/layout_footer.php'; ?>
