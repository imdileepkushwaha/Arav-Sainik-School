<?php
$page_title = "Admission Enquiries";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$class_options = getClassOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_enquiry') {
        $pdo->prepare("INSERT INTO admission_enquiries (student_name, parent_name, mobile, email, class_sought, message) VALUES (?,?,?,?,?,?)")
            ->execute([
                trim($_POST['student_name'] ?? ''),
                trim($_POST['parent_name'] ?? ''),
                trim($_POST['mobile'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['class_sought'] ?? ''),
                trim($_POST['message'] ?? ''),
            ]);
        $_SESSION['success_msg'] = 'Enquiry added.';
    } elseif ($action === 'update_status') {
        $pdo->prepare("UPDATE admission_enquiries SET status = ? WHERE id = ?")->execute([$_POST['status'], (int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Status updated.';
    } elseif ($action === 'delete_enquiry') {
        $pdo->prepare("DELETE FROM admission_enquiries WHERE id = ?")->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Enquiry deleted.';
    }
    header('Location: admission_enquiries.php');
    exit;
}

require_once 'includes/header.php';
$enquiries = $pdo->query("SELECT * FROM admission_enquiries ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$newCount = count(array_filter($enquiries, fn($e) => $e['status'] === 'New'));
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-user-plus"></i></div>
        <div class="content-top-title">
            <h2>Admission Enquiries</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Admissions</span></p>
        </div>
    </div>
    <div class="content-top-actions"><a href="student_add.php" class="btn-header-action btn-header-primary"><i class="fas fa-user-graduate"></i> Add Student</a></div>
</div>

<div class="cls-stat-strip">
    <div class="cls-stat-card"><div class="cls-stat-icon"><i class="fas fa-inbox"></i></div><div><span>New</span><strong><?php echo $newCount; ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-green"><i class="fas fa-check-double"></i></div><div><span>Converted</span><strong><?php echo count(array_filter($enquiries, fn($e) => $e['status'] === 'Converted')); ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-blue"><i class="fas fa-list"></i></div><div><span>Total</span><strong><?php echo count($enquiries); ?></strong></div></div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header"><div class="section-card-icon section-icon-school"><i class="fas fa-plus"></i></div><div><h4>New Enquiry</h4></div></div>
    <form method="POST">
        <input type="hidden" name="action" value="add_enquiry">
        <div class="form-grid form-grid-2 form-grid-spaced">
            <div class="form-field"><label>Student Name</label><input type="text" name="student_name" class="form-input" required></div>
            <div class="form-field"><label>Parent Name</label><input type="text" name="parent_name" class="form-input"></div>
            <div class="form-field"><label>Mobile</label><input type="text" name="mobile" class="form-input" required></div>
            <div class="form-field"><label>Email</label><input type="email" name="email" class="form-input"></div>
            <div class="form-field"><label>Class Sought</label><select name="class_sought" class="form-input form-select"><option value="">—</option><?php foreach ($class_options as $c): ?><option><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
            <div class="form-field form-field-full"><label>Message</label><textarea name="message" class="form-input form-textarea" rows="2"></textarea></div>
        </div>
        <div class="form-actions-end"><button type="submit" class="btn-header-action btn-header-primary">Save Enquiry</button></div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>All Enquiries</strong></div>
    <div class="table-wrapper">
        <table><thead><tr><th>Date</th><th>Student</th><th>Parent</th><th>Mobile</th><th>Class</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($enquiries as $e): ?>
        <tr>
            <td><?php echo date('d M Y', strtotime($e['created_at'])); ?></td>
            <td><strong><?php echo htmlspecialchars($e['student_name']); ?></strong></td>
            <td><?php echo displayVal($e['parent_name']); ?></td>
            <td><?php echo htmlspecialchars($e['mobile']); ?></td>
            <td><?php echo displayVal($e['class_sought']); ?></td>
            <td><span class="status-badge <?php echo $e['status'] === 'Converted' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $e['status']; ?></span></td>
            <td>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="update_status"><input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                <select name="status" class="form-input form-select" style="padding:4px 8px;font-size:0.8rem" onchange="this.form.submit()">
                    <?php foreach (['New','Contacted','Converted','Closed'] as $st): ?><option <?php echo $e['status']===$st?'selected':''; ?>><?php echo $st; ?></option><?php endforeach; ?>
                </select></form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
