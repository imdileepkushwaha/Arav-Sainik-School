<?php
$page_title = "Suspend Student";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);

// Activate student
if (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("UPDATE students SET status='Active', suspend_reason=NULL, suspended_at=NULL WHERE id=?");
    $stmt->execute([$id]);
    $_SESSION['success_msg'] = 'Student activated successfully.';
    header('Location: student_view.php?id=' . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'], $_POST['suspend_reason'])) {
    $student_id = (int) $_POST['student_id'];
    $reason = trim($_POST['suspend_reason']);
    if ($reason === '') {
        $_SESSION['error_msg'] = 'Please provide a suspension reason.';
    } else {
        $stmt = $pdo->prepare("UPDATE students SET status='Inactive', suspend_reason=?, suspended_at=NOW() WHERE id=?");
        $stmt->execute([$reason, $student_id]);
        $_SESSION['success_msg'] = 'Student suspended successfully.';
        header('Location: student_suspend.php');
        exit;
    }
}

require_once 'includes/header.php';

$suspend_id = isset($_GET['suspend']) ? (int) $_GET['suspend'] : 0;

$suspended = $pdo->query("SELECT * FROM students WHERE suspended_at IS NOT NULL ORDER BY suspended_at DESC")->fetchAll();
$active_students = $pdo->query("SELECT id, ad_no, name, class, roll FROM students WHERE suspended_at IS NULL AND status='Active' ORDER BY name ASC")->fetchAll();
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-red"><i class="fas fa-ban"></i></div>
        <div class="content-top-title">
            <h2>Suspend Student</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Suspend</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-parent"><i class="fas fa-ban"></i></div>
        <div><h4>Suspend a Student</h4><p>Select student and provide reason for suspension</p></div>
    </div>
    <form method="POST" class="student-form form-compact">
        <div class="form-grid form-grid-2">
            <div class="form-field">
                <label for="student_id">Student <span class="required">*</span></label>
                <select name="student_id" id="student_id" class="form-input form-select" required>
                    <option value="">Select student</option>
                    <?php foreach ($active_students as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo $suspend_id === (int)$s['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['name'] . ' — ' . $s['ad_no'] . ' (Class ' . $s['class'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="suspend_reason">Reason <span class="required">*</span></label>
                <input type="text" name="suspend_reason" id="suspend_reason" class="form-input" placeholder="e.g. Fee default, disciplinary action" required>
            </div>
        </div>
        <div class="form-actions-end">
            <button type="submit" class="btn-header-action btn-danger-solid"><i class="fas fa-ban"></i> Suspend Student</button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="toolbar-left"><strong>Suspended Students (<?php echo count($suspended); ?>)</strong></div>
    </div>
    <div class="table-wrapper">
        <?php if (count($suspended) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Admission No</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Reason</th>
                    <th>Suspended On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suspended as $s): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['ad_no']); ?></td>
                    <td><a href="student_view.php?id=<?php echo $s['id']; ?>" class="teal-link"><?php echo htmlspecialchars($s['name']); ?></a></td>
                    <td><?php echo htmlspecialchars($s['class']); ?></td>
                    <td><?php echo displayVal($s['suspend_reason']); ?></td>
                    <td><?php echo $s['suspended_at'] ? date('d M Y', strtotime($s['suspended_at'])) : '-'; ?></td>
                    <td>
                        <a href="student_suspend.php?action=activate&id=<?php echo $s['id']; ?>" class="action-btn edit-btn" title="Activate"><i class="fas fa-check"></i></a>
                        <a href="student_view.php?id=<?php echo $s['id']; ?>" class="action-btn view-btn" title="View"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state empty-state-lg">
            <i class="fas fa-user-check empty-state-icon"></i>
            <h3>No Suspended Students</h3>
            <p>All students are currently active.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
