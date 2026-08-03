<?php
$page_title = "Promote Students";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);

$class_options = getClassOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['from_class'])) {
    $from_class = $_POST['from_class'];
    $to_class = getNextClass($pdo, $from_class);
    if (!$to_class) {
        $_SESSION['error_msg'] = 'No next class available for promotion.';
    } else {
        $stmt = $pdo->prepare("UPDATE students SET class=? WHERE class=? AND status='Active'");
        $stmt->execute([$to_class, $from_class]);
        $promoted = $stmt->rowCount();
        $_SESSION['success_msg'] = "$promoted student(s) promoted from $from_class to $to_class.";
        header('Location: student_promote.php');
        exit;
    }
}

require_once 'includes/header.php';

$class_counts = [];
$totalActive = 0;
$promotableClasses = 0;
foreach ($class_options as $cls) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class=? AND status='Active'");
    $stmt->execute([$cls]);
    $class_counts[$cls] = (int) $stmt->fetchColumn();
    $totalActive += $class_counts[$cls];
    if (getNextClass($pdo, $cls)) {
        $promotableClasses++;
    }
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-arrow-up"></i></div>
        <div class="content-top-title">
            <h2>Promote Students</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Promote</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-left"></i> Back to Students</a>
    </div>
</div>

<div class="cls-stat-strip">
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div><span>Active Students</span><strong><?php echo $totalActive; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-layer-group"></i></div>
        <div><span>Total Classes</span><strong><?php echo count($class_options); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-arrow-up"></i></div>
        <div><span>Promotable Classes</span><strong><?php echo $promotableClasses; ?></strong></div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-arrow-up"></i></div>
        <div>
            <h4>Bulk Class Promotion</h4>
            <p>Move all active students from one class to the next class in sequence</p>
        </div>
    </div>
    <form method="POST" id="promote-form">
        <div class="promo-flow">
            <div class="promo-flow-step">
                <label class="promo-flow-label">From Class</label>
                <select name="from_class" id="from_class" class="form-input form-select promo-flow-select" required>
                    <option value="">Select source class</option>
                    <?php foreach ($class_options as $cls):
                        $next = getNextClass($pdo, $cls);
                    ?>
                    <option value="<?php echo htmlspecialchars($cls); ?>" data-next="<?php echo htmlspecialchars($next ?? ''); ?>" data-count="<?php echo $class_counts[$cls]; ?>" <?php echo !$next ? 'disabled' : ''; ?>>
                        <?php echo htmlspecialchars($cls); ?> — <?php echo $class_counts[$cls]; ?> student(s)<?php echo !$next ? ' (final class)' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="promo-flow-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></div>
            <div class="promo-flow-step">
                <label class="promo-flow-label">To Class</label>
                <input type="text" id="to_class" class="form-input promo-flow-readonly" readonly placeholder="Auto-selected" value="">
            </div>
        </div>
        <div class="promo-preview" id="promo-preview" hidden>
            <div class="promo-preview-icon"><i class="fas fa-info-circle"></i></div>
            <div class="promo-preview-text">
                <strong id="promo-preview-title">Ready to promote</strong>
                <span id="promo-preview-desc"></span>
            </div>
        </div>
        <div class="form-actions-end">
            <button type="submit" id="promote-btn" class="btn-header-action btn-header-primary" disabled onclick="return confirm('Promote all active students in this class to the next class?');">
                <i class="fas fa-arrow-up"></i> Promote Students
            </button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <strong>Class-wise Overview</strong>
        <span class="toolbar-meta">Next class is auto-calculated from class order</span>
    </div>
    <div class="table-wrapper">
        <table class="promo-overview-table">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Active Students</th>
                    <th>Next Class</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($class_options as $cls):
                    $next = getNextClass($pdo, $cls);
                    $count = $class_counts[$cls];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($cls); ?></strong></td>
                    <td>
                        <span class="badge-count"><?php echo $count; ?></span>
                    </td>
                    <td>
                        <?php if ($next): ?>
                        <span class="promo-next-pill"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($next); ?></span>
                        <?php else: ?>
                        <span class="promo-final-pill"><i class="fas fa-flag-checkered"></i> Final class</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($next && $count > 0): ?>
                        <span class="status-badge badge-active">Ready</span>
                        <?php elseif ($next): ?>
                        <span class="status-badge badge-inactive">No students</span>
                        <?php else: ?>
                        <span class="status-badge badge-inactive">Cannot promote</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="notify-info-banner section-mb" style="margin-top:20px">
    <div class="notify-info-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="notify-info-text">
        <strong>Important:</strong> Promotion moves <em>all active students</em> in the selected class to the next class. Students in the final class cannot be promoted further. This action cannot be undone in bulk — use individual student edit if needed.
    </div>
</div>

<script>
(function () {
    var fromSel = document.getElementById('from_class');
    var toInput = document.getElementById('to_class');
    var preview = document.getElementById('promo-preview');
    var previewTitle = document.getElementById('promo-preview-title');
    var previewDesc = document.getElementById('promo-preview-desc');
    var promoteBtn = document.getElementById('promote-btn');

    function updatePromoPreview() {
        var opt = fromSel.options[fromSel.selectedIndex];
        var next = opt.getAttribute('data-next') || '';
        var count = parseInt(opt.getAttribute('data-count') || '0', 10);
        var from = opt.value;

        if (!from || !next) {
            toInput.value = '';
            preview.hidden = true;
            promoteBtn.disabled = true;
            return;
        }

        toInput.value = next;
        preview.hidden = false;
        previewTitle.textContent = count + ' student(s) will be promoted';
        previewDesc.textContent = 'From ' + from + ' → ' + next + '. Only active students are included.';
        promoteBtn.disabled = count === 0;
    }

    fromSel.addEventListener('change', updatePromoPreview);
    updatePromoPreview();
})();
</script>
<?php require_once 'includes/footer.php'; ?>
