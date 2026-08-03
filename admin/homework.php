<?php
$page_title = "Homework";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$class_options = getClassOptions($pdo);
$session = getCurrentSession($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_homework'])) {
        $pdo->prepare("INSERT INTO homework (class_name, section_name, title, description, due_date, session_id) VALUES (?,?,?,?,?,?)")
            ->execute([
                trim($_POST['class_name']), trim($_POST['section_name'] ?? 'A'),
                trim($_POST['title']), trim($_POST['description'] ?? ''),
                $_POST['due_date'] ?: null, $session['id'] ?? null,
            ]);
        $_SESSION['success_msg'] = 'Homework posted.';
    } elseif (isset($_POST['delete_homework'])) {
        $pdo->prepare("DELETE FROM homework WHERE id = ?")->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Homework deleted.';
    }
    header('Location: homework.php');
    exit;
}

require_once 'includes/header.php';
$homework = $pdo->query("SELECT * FROM homework ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-book-open"></i></div>
        <div class="content-top-title">
            <h2>Homework</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Homework</span></p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-paper-plane"></i></div>
        <div><h4>Post Homework</h4><p>Students see this in the student portal</p></div>
    </div>
    <form method="POST">
        <input type="hidden" name="add_homework" value="1">
        <div class="form-grid form-grid-2 form-grid-spaced">
            <div class="form-field"><label>Class</label><select name="class_name" class="form-input form-select" required><?php foreach ($class_options as $c): ?><option><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label>Section</label><input type="text" name="section_name" class="form-input" value="A"></div>
            <div class="form-field"><label>Title</label><input type="text" name="title" class="form-input" required></div>
            <div class="form-field"><label>Due Date</label><input type="date" name="due_date" class="form-input"></div>
            <div class="form-field form-field-full"><label>Description</label><textarea name="description" class="form-input form-textarea" rows="3"></textarea></div>
        </div>
        <div class="form-actions-end"><button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-paper-plane"></i> Post</button></div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>Recent Homework</strong></div>
    <div class="table-wrapper">
        <table><thead><tr><th>Class</th><th>Title</th><th>Due</th><th>Posted</th><th></th></tr></thead><tbody>
        <?php foreach ($homework as $h): ?>
        <tr>
            <td><span class="promo-next-pill"><?php echo htmlspecialchars($h['class_name'] . ' · ' . $h['section_name']); ?></span></td>
            <td><strong><?php echo htmlspecialchars($h['title']); ?></strong><?php if ($h['description']): ?><br><small style="color:#64748b"><?php echo htmlspecialchars(mb_substr($h['description'], 0, 60)); ?></small><?php endif; ?></td>
            <td><?php echo displayVal($h['due_date'], '—'); ?></td>
            <td><?php echo htmlspecialchars($h['created_at']); ?></td>
            <td><form method="POST" onsubmit="return confirm('Delete?');"><input type="hidden" name="delete_homework" value="1"><input type="hidden" name="id" value="<?php echo $h['id']; ?>"><button type="submit" class="action-btn delete-btn"><i class="fas fa-trash"></i></button></form></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
