<?php
$page_title = "Manage Toppers";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
ensureTopperSchema($pdo);
$class_options = getClassOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0) ?: null;
        $name = trim($_POST['name'] ?? '');
        $className = trim($_POST['class_name'] ?? '');
        $percentage = (float) ($_POST['percentage'] ?? 0);
        $title = trim($_POST['title'] ?? 'Topper') ?: 'Topper';
        $examLabel = trim($_POST['exam_label'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $showOnWebsite = isset($_POST['show_on_website']) ? 1 : 0;
        $status = ($_POST['status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active';
        $photo = trim($_POST['existing_photo'] ?? '');

        if ($studentId) {
            $st = $pdo->prepare("SELECT name, class, photo FROM students WHERE id = ?");
            $st->execute([$studentId]);
            $student = $st->fetch(PDO::FETCH_ASSOC);
            if ($student) {
                if ($name === '') {
                    $name = $student['name'];
                }
                if ($className === '') {
                    $className = $student['class'];
                }
                if ($photo === '' && !empty($student['photo'])) {
                    $photo = $student['photo'];
                }
            }
        }

        $upload = uploadTopperPhoto($_FILES['photo'] ?? []);
        if ($upload === false) {
            $_SESSION['error_msg'] = 'Invalid photo. Use JPG/PNG/WebP under 2 MB.';
            header('Location: toppers.php' . ($id ? '?edit=' . $id : ''));
            exit;
        }
        if (is_string($upload) && $upload !== '') {
            $photo = $upload;
        }

        if ($name === '' || $className === '') {
            $_SESSION['error_msg'] = 'Name and class are required.';
            header('Location: toppers.php' . ($id ? '?edit=' . $id : ''));
            exit;
        }
        if ($percentage < 0 || $percentage > 100) {
            $_SESSION['error_msg'] = 'Percentage must be between 0 and 100.';
            header('Location: toppers.php' . ($id ? '?edit=' . $id : ''));
            exit;
        }

        if ($action === 'update' && $id > 0) {
            $pdo->prepare(
                "UPDATE school_toppers
                 SET student_id=?, name=?, class_name=?, percentage=?, photo=?, title=?, exam_label=?, sort_order=?, status=?, show_on_website=?
                 WHERE id=?"
            )->execute([
                $studentId, $name, $className, $percentage, $photo ?: null, $title, $examLabel ?: null,
                $sortOrder, $status, $showOnWebsite, $id,
            ]);
            $_SESSION['success_msg'] = 'Topper updated.';
        } else {
            if ($sortOrder === 0) {
                $sortOrder = ((int) $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM school_toppers")->fetchColumn()) + 1;
            }
            $pdo->prepare(
                "INSERT INTO school_toppers
                 (student_id, name, class_name, percentage, photo, title, exam_label, sort_order, status, show_on_website)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $studentId, $name, $className, $percentage, $photo ?: null, $title, $examLabel ?: null,
                $sortOrder, $status, $showOnWebsite,
            ]);
            $_SESSION['success_msg'] = 'Topper added.';
        }
        header('Location: toppers.php');
        exit;
    }

    if ($action === 'toggle' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE school_toppers SET status = IF(status='Active','Inactive','Active') WHERE id = ?")
            ->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Topper status updated.';
        header('Location: toppers.php');
        exit;
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        $pdo->prepare("DELETE FROM school_toppers WHERE id = ?")->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Topper removed.';
        header('Location: toppers.php');
        exit;
    }

    if ($action === 'search_student') {
        header('Content-Type: application/json');
        echo json_encode(searchStudentsForTopper($pdo, trim($_POST['q'] ?? '')));
        exit;
    }
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? getTopperById($pdo, $editId) : null;
$toppers = getManagedToppers($pdo, false);
$searchQ = trim($_GET['q'] ?? '');
$searchResults = $searchQ !== '' ? searchStudentsForTopper($pdo, $searchQ) : [];

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-orange"><i class="fas fa-trophy"></i></div>
        <div class="content-top-title">
            <h2>Manage Toppers</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Toppers</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="../index.php#results" target="_blank" class="btn-header-action btn-header-outline"><i class="fas fa-external-link-alt"></i> View on Website</a>
    </div>
</div>

<div class="cls-stat-strip">
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-green"><i class="fas fa-trophy"></i></div><div><span>Total Toppers</span><strong><?php echo count($toppers); ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-blue"><i class="fas fa-globe"></i></div><div><span>On Website</span><strong><?php echo count(array_filter($toppers, static fn($t) => $t['status'] === 'Active' && (int) $t['show_on_website'] === 1)); ?></strong></div></div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-<?php echo $edit ? 'pen' : 'plus'; ?>"></i></div>
        <div>
            <h4><?php echo $edit ? 'Edit Topper' : 'Add Topper'; ?></h4>
            <p>These students appear in the website “Outstanding Results” section</p>
        </div>
        <?php if ($edit): ?>
        <a href="toppers.php" class="btn-header-action btn-header-outline btn-sm"><i class="fas fa-times"></i> Cancel</a>
        <?php endif; ?>
    </div>

    <?php if (!$edit): ?>
    <form method="GET" class="category-add-row" style="margin-bottom:18px">
        <div class="form-field form-field-grow">
            <label>Find student (optional)</label>
            <input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($searchQ); ?>" placeholder="Name, admission no or class...">
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <?php if ($searchQ !== ''): ?>
    <div class="erp-search-results student-search-results" style="margin-bottom:18px">
        <?php if ($searchResults): foreach ($searchResults as $r): ?>
        <form method="POST" class="erp-search-item student-search-card">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="student_id" value="<?php echo (int) $r['id']; ?>">
            <input type="hidden" name="name" value="<?php echo htmlspecialchars($r['name']); ?>">
            <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($r['class']); ?>">
            <input type="hidden" name="existing_photo" value="<?php echo htmlspecialchars($r['photo'] ?? ''); ?>">
            <input type="hidden" name="show_on_website" value="1">
            <div class="student-search-main">
                <div class="student-search-avatar"><i class="fas fa-user-graduate"></i></div>
                <div class="student-search-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <span><?php echo htmlspecialchars($r['ad_no']); ?></span>
                    <div class="student-search-meta">
                        <span class="student-search-class-pill"><i class="fas fa-school"></i> <?php echo htmlspecialchars($r['class']); ?><?php if (!empty($r['section'])): ?> - <?php echo htmlspecialchars($r['section']); ?><?php endif; ?></span>
                    </div>
                </div>
            </div>
            <div class="student-search-actions" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
                <div class="form-field" style="margin:0;min-width:110px">
                    <label>Percentage</label>
                    <input type="number" step="0.01" min="0" max="100" name="percentage" class="form-input" placeholder="e.g. 98.5" required>
                </div>
                <div class="form-field" style="margin:0;min-width:130px">
                    <label>Exam / Year</label>
                    <input type="text" name="exam_label" class="form-input" placeholder="Annual 2025-26">
                </div>
                <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-trophy"></i> Add as Topper</button>
            </div>
        </form>
        <?php endforeach; else: ?>
        <div class="tab-empty-state tab-empty-pad-sm"><h3>No students found</h3></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'add'; ?>">
        <?php if ($edit): ?>
        <input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>">
        <input type="hidden" name="existing_photo" value="<?php echo htmlspecialchars($edit['photo'] ?? ''); ?>">
        <input type="hidden" name="student_id" value="<?php echo (int) ($edit['student_id'] ?? 0); ?>">
        <?php endif; ?>
        <div class="form-grid form-grid-2 form-grid-spaced">
            <div class="form-field">
                <label>Student Name</label>
                <input type="text" name="name" class="form-input" required value="<?php echo htmlspecialchars($edit['name'] ?? ''); ?>" placeholder="Full name">
            </div>
            <div class="form-field">
                <label>Class</label>
                <select name="class_name" class="form-input form-select" required>
                    <option value="">Select class</option>
                    <?php foreach ($class_options as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo (($edit['class_name'] ?? '') === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Percentage (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="percentage" class="form-input" required value="<?php echo htmlspecialchars(isset($edit['percentage']) ? rtrim(rtrim(number_format((float) $edit['percentage'], 2, '.', ''), '0'), '.') : ''); ?>" placeholder="98.5">
            </div>
            <div class="form-field">
                <label>Title</label>
                <input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($edit['title'] ?? 'Topper'); ?>" placeholder="Topper">
            </div>
            <div class="form-field">
                <label>Exam / Year label</label>
                <input type="text" name="exam_label" class="form-input" value="<?php echo htmlspecialchars($edit['exam_label'] ?? ''); ?>" placeholder="Annual Exam 2025-26">
            </div>
            <div class="form-field">
                <label>Sort Order</label>
                <input type="number" name="sort_order" class="form-input" value="<?php echo (int) ($edit['sort_order'] ?? 0); ?>" placeholder="1">
            </div>
            <div class="form-field">
                <label>Photo</label>
                <input type="file" name="photo" class="form-input" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="form-field">
                <label>Status</label>
                <select name="status" class="form-input form-select">
                    <option value="Active" <?php echo (($edit['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo (($edit['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="form-field">
                <label>&nbsp;</label>
                <label style="display:flex;align-items:center;gap:8px;font-weight:600">
                    <input type="checkbox" name="show_on_website" value="1" <?php echo !isset($edit) || (int) ($edit['show_on_website'] ?? 1) === 1 ? 'checked' : ''; ?>>
                    Show on website
                </label>
            </div>
        </div>
        <div class="settings-form-actions">
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> <?php echo $edit ? 'Update Topper' : 'Add Topper'; ?></button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>All Toppers (<?php echo count($toppers); ?>)</strong></div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Class</th>
                    <th>%</th>
                    <th>Exam</th>
                    <th>Website</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($toppers): foreach ($toppers as $t): ?>
            <tr>
                <td><?php echo (int) $t['sort_order']; ?></td>
                <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($t['class_name']); ?></td>
                <td><strong><?php echo number_format((float) $t['percentage'], 2); ?>%</strong></td>
                <td><?php echo htmlspecialchars($t['exam_label'] ?: '—'); ?></td>
                <td><?php echo (int) $t['show_on_website'] === 1 ? 'Yes' : 'No'; ?></td>
                <td>
                    <span class="status-badge <?php echo $t['status'] === 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo htmlspecialchars($t['status']); ?>
                    </span>
                </td>
                <td class="table-actions">
                    <a href="toppers.php?edit=<?php echo (int) $t['id']; ?>" class="action-btn edit-btn" title="Edit"><i class="fas fa-pen"></i></a>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                        <button type="submit" class="action-btn" title="Toggle status"><i class="fas fa-exchange-alt"></i></button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Remove this topper?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                        <button type="submit" class="action-btn delete-btn" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" class="table-empty-cell">No toppers added yet. Search a student or add manually above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
