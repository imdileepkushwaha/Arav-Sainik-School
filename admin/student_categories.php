<?php
$page_title = "Student Categories";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO student_categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $desc]);
                $_SESSION['success_msg'] = 'Category added.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Category already exists or could not be saved.';
            }
        }
    } elseif ($action === 'update' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        if ($name !== '') {
            $stmt = $pdo->prepare("UPDATE student_categories SET name=?, description=?, status=? WHERE id=?");
            $stmt->execute([$name, $desc, $status, $id]);
            $_SESSION['success_msg'] = 'Category updated.';
        }
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        $pdo->prepare("DELETE FROM student_categories WHERE id=?")->execute([$id]);
        $_SESSION['success_msg'] = 'Category deleted.';
    }
    header('Location: student_categories.php');
    exit;
}

require_once 'includes/header.php';

$categories = $pdo->query("SELECT * FROM student_categories ORDER BY name ASC")->fetchAll();
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-tags"></i></div>
        <div class="content-top-title">
            <h2>Student Categories</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Categories</span>
            </p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-plus"></i></div>
        <div><h4>Add Category</h4></div>
    </div>
    <form method="POST" class="category-add-form">
        <input type="hidden" name="action" value="add">
        <div class="category-add-row">
            <div class="form-field">
                <label>Category Name</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. EWS" required>
            </div>
            <div class="form-field">
                <label>Description</label>
                <input type="text" name="description" class="form-input" placeholder="Optional">
            </div>
            <div class="form-field category-add-btn-wrap">
                <label aria-hidden="true">&nbsp;</label>
                <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-plus"></i> Add Category</button>
            </div>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $i => $cat): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td>
                        <input type="text" name="name" form="cat-update-<?php echo $cat['id']; ?>" class="form-input table-inline-input" value="<?php echo htmlspecialchars($cat['name']); ?>">
                    </td>
                    <td>
                        <input type="text" name="description" form="cat-update-<?php echo $cat['id']; ?>" class="form-input table-inline-input" value="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>">
                    </td>
                    <td>
                        <select name="status" form="cat-update-<?php echo $cat['id']; ?>" class="form-input form-select table-inline-input">
                            <option value="Active" <?php echo $cat['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $cat['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </td>
                    <td>
                        <div class="table-action-btns">
                            <button type="submit" form="cat-update-<?php echo $cat['id']; ?>" class="action-btn edit-btn" title="Save"><i class="fas fa-save"></i></button>
                            <button type="submit" form="cat-delete-<?php echo $cat['id']; ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Delete this category?');"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($categories as $cat): ?>
<form id="cat-update-<?php echo $cat['id']; ?>" method="POST" class="hidden-form">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
</form>
<form id="cat-delete-<?php echo $cat['id']; ?>" method="POST" class="hidden-form">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
</form>
<?php endforeach; ?>
<?php require_once 'includes/footer.php'; ?>
