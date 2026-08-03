<?php
$page_title = "Classes & Sections";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureClassSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_class') {
        $name = trim($_POST['name'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        if ($name !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO school_classes (name, sort_order) VALUES (?, ?)");
                $stmt->execute([$name, $sort]);
                $classId = (int) $pdo->lastInsertId();
                $secStmt = $pdo->prepare("INSERT INTO class_sections (class_id, name) VALUES (?, ?)");
                foreach (['A', 'B', 'C', 'D'] as $sec) {
                    try {
                        $secStmt->execute([$classId, $sec]);
                    } catch (PDOException $e) {
                        // ignore duplicate section
                    }
                }
                $_SESSION['success_msg'] = 'Class added with default sections A–D.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Class name already exists or could not be saved.';
            }
        }
    } elseif ($action === 'update_class' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $old = getClassById($pdo, $id);
        $name = trim($_POST['name'] ?? '');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $status = $_POST['status'] ?? 'Active';
        if ($name !== '' && $old) {
            try {
                $pdo->prepare("UPDATE school_classes SET name=?, sort_order=?, status=? WHERE id=?")
                    ->execute([$name, $sort, $status, $id]);
                if ($old['name'] !== $name) {
                    $pdo->prepare("UPDATE students SET class=? WHERE class=?")->execute([$name, $old['name']]);
                }
                $_SESSION['success_msg'] = 'Class updated.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Could not update class. Name may already exist.';
            }
        }
    } elseif ($action === 'delete_class' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $class = getClassById($pdo, $id);
        if ($class) {
            $count = countStudentsInClass($pdo, $class['name']);
            if ($count > 0) {
                $_SESSION['error_msg'] = "Cannot delete — $count active student(s) in this class.";
            } else {
                $pdo->prepare("DELETE FROM class_sections WHERE class_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM school_classes WHERE id=?")->execute([$id]);
                $_SESSION['success_msg'] = 'Class deleted.';
            }
        }
    } elseif ($action === 'add_section' && isset($_POST['class_id'])) {
        $classId = (int) $_POST['class_id'];
        $name = strtoupper(trim($_POST['section_name'] ?? ''));
        if ($name !== '' && $classId) {
            try {
                $pdo->prepare("INSERT INTO class_sections (class_id, name) VALUES (?, ?)")
                    ->execute([$classId, $name]);
                $_SESSION['success_msg'] = 'Section added.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Section already exists for this class.';
            }
        }
    } elseif ($action === 'update_section' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $name = strtoupper(trim($_POST['name'] ?? ''));
        $status = $_POST['status'] ?? 'Active';
        if ($name !== '') {
            try {
                $pdo->prepare("UPDATE class_sections SET name=?, status=? WHERE id=?")
                    ->execute([$name, $status, $id]);
                $_SESSION['success_msg'] = 'Section updated.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Could not update section.';
            }
        }
    } elseif ($action === 'delete_section' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare(
            "SELECT cs.*, sc.name AS class_name FROM class_sections cs
             INNER JOIN school_classes sc ON sc.id = cs.class_id WHERE cs.id = ?"
        );
        $stmt->execute([$id]);
        $sec = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sec) {
            $cntStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM students WHERE class = ? AND section = ? AND status = 'Active'"
            );
            $cntStmt->execute([$sec['class_name'], $sec['name']]);
            $count = (int) $cntStmt->fetchColumn();
            if ($count > 0) {
                $_SESSION['error_msg'] = "Cannot delete — $count active student(s) in this section.";
            } else {
                $pdo->prepare("DELETE FROM class_sections WHERE id=?")->execute([$id]);
                $_SESSION['success_msg'] = 'Section deleted.';
            }
        }
    }

    header('Location: classes.php');
    exit;
}

require_once 'includes/header.php';

$classes = getAllClasses($pdo);
$sectionsByClass = [];
$totalSections = 0;
$activeClasses = 0;
$nextSortOrder = 0;
foreach ($classes as $cls) {
    $sectionsByClass[$cls['id']] = getSectionsForClassId($pdo, $cls['id']);
    $totalSections += count($sectionsByClass[$cls['id']]);
    if ($cls['status'] === 'Active') {
        $activeClasses++;
    }
    $nextSortOrder = max($nextSortOrder, (int) $cls['sort_order'] + 1);
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-school"></i></div>
        <div class="content-top-title">
            <h2>Classes & Sections</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Classes</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-user-graduate"></i> Students</a>
    </div>
</div>

<div class="cls-stat-strip">
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-layer-group"></i></div>
        <div><span>Total Classes</span><strong><?php echo count($classes); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-check-circle"></i></div>
        <div><span>Active</span><strong><?php echo $activeClasses; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-table-columns"></i></div>
        <div><span>Total Sections</span><strong><?php echo $totalSections; ?></strong></div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-plus"></i></div>
        <div><h4>Add Class</h4><p>New class with default sections A, B, C, D</p></div>
    </div>
    <form method="POST" class="category-add-form">
        <input type="hidden" name="action" value="add_class">
        <div class="category-add-row">
            <div class="form-field">
                <label>Class Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Class 13" required>
            </div>
            <div class="form-field">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-input" value="<?php echo (int) $nextSortOrder; ?>" min="0">
            </div>
            <div class="form-field category-add-btn-wrap">
                <label aria-hidden="true">&nbsp;</label>
                <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-plus"></i> Add Class</button>
            </div>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>All Classes</strong></div>
    <?php if (empty($classes)): ?>
    <div class="empty-state">
        <div class="empty-state-icon"><i class="fas fa-school"></i></div>
        <h3>No class added</h3>
        <p>Add a class above to get started. Default sections A–D will be created automatically.</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="classes-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class Name</th>
                    <th>Sort</th>
                    <th>Sections</th>
                    <th>Students</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classes as $i => $cls):
                    $sections = $sectionsByClass[$cls['id']] ?? [];
                    $studentCount = countStudentsInClass($pdo, $cls['name']);
                ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td>
                        <input type="text" name="name" form="cls-update-<?php echo $cls['id']; ?>" class="form-input table-inline-input" value="<?php echo htmlspecialchars($cls['name']); ?>">
                    </td>
                    <td>
                        <input type="number" name="sort_order" form="cls-update-<?php echo $cls['id']; ?>" class="form-input table-inline-input table-inline-input-sm" value="<?php echo (int) $cls['sort_order']; ?>" min="0">
                    </td>
                    <td><span class="badge-count"><?php echo count($sections); ?></span></td>
                    <td><?php echo $studentCount; ?></td>
                    <td>
                        <select name="status" form="cls-update-<?php echo $cls['id']; ?>" class="form-input form-select table-inline-input">
                            <option value="Active" <?php echo $cls['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $cls['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </td>
                    <td>
                        <div class="table-action-btns">
                            <button type="submit" form="cls-update-<?php echo $cls['id']; ?>" class="action-btn edit-btn" title="Save"><i class="fas fa-save"></i></button>
                            <button type="submit" form="cls-delete-<?php echo $cls['id']; ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Delete this class and all its sections?');"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <tr class="class-sections-row">
                    <td colspan="7">
                        <div class="class-sections-panel">
                            <div class="class-sections-header">
                                <div class="class-sections-title">
                                    <span class="class-sections-icon"><i class="fas fa-table-columns"></i></span>
                                    <div>
                                        <strong>Sections</strong>
                                        <span><?php echo htmlspecialchars($cls['name']); ?> · <?php echo count($sections); ?> section(s)</span>
                                    </div>
                                </div>
                                <form method="POST" class="section-add-inline">
                                    <input type="hidden" name="action" value="add_section">
                                    <input type="hidden" name="class_id" value="<?php echo $cls['id']; ?>">
                                    <label class="section-add-label">Add section</label>
                                    <div class="section-add-field">
                                        <input type="text" name="section_name" class="form-input section-add-input" placeholder="E" maxlength="10" required title="Section letter e.g. E">
                                        <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-plus"></i> Add</button>
                                    </div>
                                </form>
                            </div>
                            <?php if (empty($sections)): ?>
                            <div class="class-sections-empty">
                                <i class="fas fa-inbox"></i>
                                <p>No sections yet. Add a section letter above (e.g. A, B, E).</p>
                            </div>
                            <?php else: ?>
                            <div class="section-chips">
                                <?php foreach ($sections as $sec):
                                    $isActive = $sec['status'] === 'Active';
                                ?>
                                <div class="section-chip <?php echo $isActive ? 'is-active' : 'is-inactive'; ?>">
                                    <div class="section-chip-badge" aria-hidden="true"><?php echo htmlspecialchars($sec['name']); ?></div>
                                    <div class="section-chip-main">
                                        <label class="section-chip-label">Name</label>
                                        <input type="text" name="name" form="sec-update-<?php echo $sec['id']; ?>" class="form-input section-chip-input" value="<?php echo htmlspecialchars($sec['name']); ?>" maxlength="10" aria-label="Section name">
                                        <label class="section-chip-label">Status</label>
                                        <select name="status" form="sec-update-<?php echo $sec['id']; ?>" class="form-input form-select section-chip-select section-chip-status-<?php echo $isActive ? 'active' : 'inactive'; ?>" aria-label="Section status">
                                            <option value="Active" <?php echo $isActive ? 'selected' : ''; ?>>Active</option>
                                            <option value="Inactive" <?php echo !$isActive ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="section-chip-actions">
                                        <button type="submit" form="sec-update-<?php echo $sec['id']; ?>" class="section-chip-btn section-chip-btn-save" title="Save section"><i class="fas fa-check"></i></button>
                                        <button type="submit" form="sec-delete-<?php echo $sec['id']; ?>" class="section-chip-btn section-chip-btn-delete" title="Delete section" onclick="return confirm('Delete section <?php echo htmlspecialchars($sec['name']); ?>?');"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($classes as $cls): ?>
<form id="cls-update-<?php echo $cls['id']; ?>" method="POST" class="hidden-form">
    <input type="hidden" name="action" value="update_class">
    <input type="hidden" name="id" value="<?php echo $cls['id']; ?>">
</form>
<form id="cls-delete-<?php echo $cls['id']; ?>" method="POST" class="hidden-form">
    <input type="hidden" name="action" value="delete_class">
    <input type="hidden" name="id" value="<?php echo $cls['id']; ?>">
</form>
<?php endforeach; ?>

<?php foreach ($classes as $cls):
    foreach ($sectionsByClass[$cls['id']] ?? [] as $sec): ?>
<form id="sec-update-<?php echo $sec['id']; ?>" method="POST" class="hidden-form">
    <input type="hidden" name="action" value="update_section">
    <input type="hidden" name="id" value="<?php echo $sec['id']; ?>">
</form>
<form id="sec-delete-<?php echo $sec['id']; ?>" method="POST" class="hidden-form">
    <input type="hidden" name="action" value="delete_section">
    <input type="hidden" name="id" value="<?php echo $sec['id']; ?>">
</form>
<?php endforeach; endforeach; ?>

<script>
document.querySelectorAll('.section-chip-select').forEach(function (sel) {
    sel.addEventListener('change', function () {
        var chip = this.closest('.section-chip');
        if (!chip) return;
        var active = this.value === 'Active';
        chip.classList.toggle('is-active', active);
        chip.classList.toggle('is-inactive', !active);
        this.classList.toggle('section-chip-status-active', active);
        this.classList.toggle('section-chip-status-inactive', !active);
    });
});

document.querySelectorAll('.section-chip-input').forEach(function (input) {
    input.addEventListener('input', function () {
        var badge = this.closest('.section-chip') && this.closest('.section-chip').querySelector('.section-chip-badge');
        if (badge) {
            badge.textContent = this.value.trim().toUpperCase() || '?';
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
