<?php
$page_title = "Subjects";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$class_options = getClassOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_subject') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $class = trim($_POST['class_name'] ?? '') ?: null;
        if ($name !== '') {
            try {
                $pdo->prepare("INSERT INTO subjects (name, code, class_name) VALUES (?,?,?)")->execute([$name, $code ?: null, $class]);
                $_SESSION['success_msg'] = 'Subject added.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Subject already exists for this class.';
            }
        }
    } elseif ($action === 'delete_subject') {
        $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Subject deleted.';
    }
    header('Location: subjects.php');
    exit;
}

require_once 'includes/header.php';
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY class_name IS NULL DESC, class_name, name")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-book"></i></div>
        <div class="content-top-title">
            <h2>Subjects Master</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Subjects</span></p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-plus"></i></div>
        <div><h4>Add Subject</h4><p>Leave class empty for all classes</p></div>
    </div>
    <form method="POST" class="category-add-row">
        <input type="hidden" name="action" value="add_subject">
        <div class="form-field"><label>Name</label><input type="text" name="name" class="form-input" required placeholder="Mathematics"></div>
        <div class="form-field"><label>Code</label><input type="text" name="code" class="form-input" placeholder="MATH"></div>
        <div class="form-field"><label>Class (optional)</label><select name="class_name" class="form-input form-select"><option value="">All Classes</option><?php foreach ($class_options as $c): ?><option><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
        <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-plus"></i> Add</button></div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>Subjects (<?php echo count($subjects); ?>)</strong></div>
    <div class="table-wrapper">
        <table><thead><tr><th>Name</th><th>Code</th><th>Class</th><th></th></tr></thead><tbody>
        <?php foreach ($subjects as $s): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
            <td><?php echo displayVal($s['code']); ?></td>
            <td><?php echo $s['class_name'] ? htmlspecialchars($s['class_name']) : '<span class="promo-next-pill">All Classes</span>'; ?></td>
            <td><form method="POST" onsubmit="return confirm('Delete?');"><input type="hidden" name="action" value="delete_subject"><input type="hidden" name="id" value="<?php echo $s['id']; ?>"><button type="submit" class="action-btn delete-btn"><i class="fas fa-trash"></i></button></form></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
