<?php
$page_title = "Exam Analytics";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$examId = (int) ($_GET['exam_id'] ?? 0);
$exams = $pdo->query("SELECT * FROM exams ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$analytics = $examId ? getExamClassAnalytics($pdo, $examId) : null;

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-chart-pie"></i></div>
        <div class="content-top-title">
            <h2>Exam Analytics</h2>
            <p class="content-top-breadcrumb"><a href="exams.php">Exams</a><i class="fas fa-chevron-right"></i><span>Analytics</span></p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb">
    <form method="GET" class="category-add-row">
        <div class="form-field form-field-grow"><label>Select Exam</label>
            <select name="exam_id" class="form-input form-select" required>
                <option value="">Choose exam...</option>
                <?php foreach ($exams as $e): ?>
                <option value="<?php echo $e['id']; ?>" <?php echo $examId === (int)$e['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($e['name'] . ' — ' . $e['class_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn">View Report</button></div>
    </form>
</div>

<?php if ($analytics): ?>
<div class="cls-stat-strip">
    <div class="cls-stat-card"><div class="cls-stat-icon"><i class="fas fa-users"></i></div><div><span>Students</span><strong><?php echo count($analytics['results']); ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-green"><i class="fas fa-check"></i></div><div><span>Passed</span><strong><?php echo $analytics['pass_count']; ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-blue"><i class="fas fa-percent"></i></div><div><span>Class Average</span><strong><?php echo $analytics['avg_pct']; ?>%</strong></div></div>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>Rank List — <?php echo htmlspecialchars($analytics['exam']['name']); ?></strong></div>
    <div class="table-wrapper">
        <table><thead><tr><th>Rank</th><th>Student</th><th>Roll</th><th>Marks</th><th>%</th><th>Grade</th><th></th></tr></thead><tbody>
        <?php foreach ($analytics['results'] as $i => $r): ?>
        <tr>
            <td><strong>#<?php echo $i + 1; ?></strong></td>
            <td><?php echo htmlspecialchars($r['student']['name']); ?></td>
            <td><?php echo htmlspecialchars($r['student']['roll']); ?></td>
            <td><?php echo $r['total_obt']; ?> / <?php echo $r['total_max']; ?></td>
            <td><strong><?php echo $r['percentage']; ?>%</strong></td>
            <td><span class="status-badge <?php echo $r['percentage'] >= 33 ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $r['grade']; ?></span></td>
            <td><a href="report_card.php?student_id=<?php echo $r['student']['id']; ?>&exam_id=<?php echo $examId; ?>" target="_blank" class="btn-header-action btn-header-outline btn-sm">Report Card</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<?php elseif ($examId): ?>
<div class="tab-empty-state tab-empty-pad-sm"><p>No marks data for this exam yet.</p></div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
