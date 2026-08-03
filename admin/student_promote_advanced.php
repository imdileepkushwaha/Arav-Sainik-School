<?php
$page_title = "Advanced Promotion";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$session = getCurrentSession($pdo);
$sessions = getAllSessions($pdo);
$class_options = getClassOptions($pdo);
$fromClass = trim($_GET['from_class'] ?? $_POST['from_class'] ?? '');
$fromSection = trim($_GET['from_section'] ?? $_POST['from_section'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_selected'])) {
    $ids = array_map('intval', $_POST['student_ids'] ?? []);
    $toClass = trim($_POST['to_class'] ?? '');
    $toSection = trim($_POST['to_section'] ?? 'A');
    $sessionId = (int) ($_POST['session_id'] ?? 0) ?: ($session['id'] ?? null);
    if ($ids && $toClass !== '') {
        $count = promoteStudentsAdvanced($pdo, $ids, $toClass, $toSection, $sessionId);
        $_SESSION['success_msg'] = "$count student(s) promoted to $toClass ($toSection).";
    } else {
        $_SESSION['error_msg'] = 'Select students and target class.';
    }
    header('Location: student_promote_advanced.php?from_class=' . urlencode($fromClass) . '&from_section=' . urlencode($fromSection));
    exit;
}

require_once 'includes/header.php';
$students = ($fromClass !== '') ? getStudentsByClassSection($pdo, $fromClass, $fromSection) : [];
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-user-graduate"></i></div>
        <div class="content-top-title"><h2>Advanced Promotion</h2><p class="content-top-breadcrumb"><a href="student_promote.php">Promote</a><i class="fas fa-chevron-right"></i><span>Advanced</span></p></div>
    </div>
    <div class="content-top-actions"><a href="student_promote.php" class="btn-header-action btn-header-outline">Bulk Promote</a></div>
</div>

<div class="form-section-card section-mb">
    <form method="GET" class="category-add-row erp-filter-row-4">
        <div class="form-field"><label>From Class</label><select name="from_class" class="form-input form-select" required><option value="">Select</option><?php foreach ($class_options as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $fromClass === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label>Section</label><select name="from_section" class="form-input form-select"><?php foreach (($fromClass ? getSectionOptions($pdo, $fromClass) : ['A']) as $sec): ?><option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $fromSection === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option><?php endforeach; ?></select></div>
        <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn">Load Students</button></div>
    </form>
</div>

<?php if ($students): ?>
<form method="POST">
    <input type="hidden" name="promote_selected" value="1">
    <input type="hidden" name="from_class" value="<?php echo htmlspecialchars($fromClass); ?>">
    <input type="hidden" name="from_section" value="<?php echo htmlspecialchars($fromSection); ?>">
    <div class="form-section-card section-mb">
        <div class="category-add-row erp-filter-row-4">
            <div class="form-field"><label>To Class</label><select name="to_class" class="form-input form-select" required><?php foreach ($class_options as $c): if ($c === $fromClass) continue; ?><option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label>To Section</label><input type="text" name="to_section" class="form-input" value="A" maxlength="10"></div>
            <div class="form-field"><label>Session</label><select name="session_id" class="form-input form-select"><?php foreach ($sessions as $s): ?><option value="<?php echo $s['id']; ?>" <?php echo !empty($s['is_current']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?></select></div>
        </div>
    </div>
    <div class="table-container">
        <div class="table-toolbar"><strong>Select students to promote</strong><button type="submit" class="btn-header-action btn-header-primary" onclick="return confirm('Promote selected students?');"><i class="fas fa-arrow-up"></i> Promote Selected</button></div>
        <div class="table-wrapper">
            <table><thead><tr><th><input type="checkbox" id="selectAllPromote"></th><th>Roll</th><th>Name</th><th>Adm No</th></tr></thead><tbody>
            <?php foreach ($students as $s): ?>
            <tr><td><input type="checkbox" class="promote-cb" name="student_ids[]" value="<?php echo $s['id']; ?>"></td><td><?php echo htmlspecialchars($s['roll']); ?></td><td><?php echo htmlspecialchars($s['name']); ?></td><td><?php echo htmlspecialchars($s['ad_no']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </div>
</form>
<script>document.getElementById('selectAllPromote')?.addEventListener('change',function(){document.querySelectorAll('.promote-cb').forEach(function(c){c.checked=this.checked}.bind(this));});</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
