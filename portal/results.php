<?php
$page_title = 'Exam Results';
require_once 'includes/init.php';

$exams = getExamsForClass($pdo, $student['class']);
$selectedExamId = (int) ($_GET['exam'] ?? 0);
$validIds = array_column($exams, 'id');
if (!$selectedExamId || !in_array($selectedExamId, array_map('intval', $validIds), true)) {
    $selectedExamId = (int) ($exams[0]['id'] ?? 0);
}
$selectedExam = null;
foreach ($exams as $e) {
    if ((int) $e['id'] === $selectedExamId) {
        $selectedExam = $e;
        break;
    }
}
$result = $selectedExamId ? getStudentExamResult($pdo, (int) $student['id'], $selectedExamId) : null;

require_once 'includes/layout_header.php';
?>
<?php if (empty($exams)): ?>
<div class="sp-card">
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-chart-line"></i></div><strong>No exams scheduled</strong><p>Exam results for Class <?php echo htmlspecialchars($student['class']); ?> will appear here once published.</p></div>
</div>
<?php else: ?>

<div class="sp-exam-tabs">
    <?php foreach ($exams as $e): ?>
    <a href="results.php?exam=<?php echo (int) $e['id']; ?>" class="sp-exam-tab<?php echo (int) $e['id'] === $selectedExamId ? ' active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <span><strong><?php echo htmlspecialchars($e['name']); ?></strong><small><?php echo htmlspecialchars($e['exam_type'] ?: 'Exam'); ?></small></span>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($result && !$result['published']): ?>
<div class="sp-card">
    <div class="sp-card-head"><h3><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($selectedExam['name'] ?? 'Exam'); ?></h3></div>
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-hourglass-half"></i></div><strong>Result not published yet</strong><p>Marks for this exam have not been entered. Please check back later.</p></div>
</div>
<?php elseif ($result): ?>
<?php
$pct = $result['percentage'];
$isPass = $result['result'] === 'Pass';
$ringColor = $pct >= 75 ? '#059669' : ($pct >= 40 ? '#d97706' : '#dc2626');
?>
<div class="sp-stat-grid">
    <div class="sp-stat tone-purple">
        <div class="sp-stat-icon"><i class="fas fa-star"></i></div>
        <div class="sp-stat-body"><span>Marks Obtained</span><strong><?php echo rtrim(rtrim(number_format($result['total_obtained'], 2), '0'), '.'); ?> / <?php echo (int) $result['total_max']; ?></strong></div>
    </div>
    <div class="sp-stat tone-green">
        <div class="sp-stat-icon"><i class="fas fa-percent"></i></div>
        <div class="sp-stat-body"><span>Percentage</span><strong><?php echo $pct; ?>%</strong></div>
    </div>
    <div class="sp-stat tone-amber">
        <div class="sp-stat-icon"><i class="fas fa-award"></i></div>
        <div class="sp-stat-body"><span>Overall Grade</span><strong><?php echo htmlspecialchars($result['grade']); ?></strong></div>
    </div>
    <div class="sp-stat <?php echo $isPass ? 'tone-green' : 'tone-red'; ?>">
        <div class="sp-stat-icon"><i class="fas fa-<?php echo $isPass ? 'circle-check' : 'circle-xmark'; ?>"></i></div>
        <div class="sp-stat-body"><span>Result</span><strong><?php echo htmlspecialchars($result['result']); ?></strong></div>
    </div>
</div>

<div class="sp-grid-2 wide-first">
    <div class="sp-card">
        <div class="sp-card-head">
            <h3><i class="fas fa-list-ol"></i> Subject-wise Marks</h3>
            <a href="report_card.php?exam=<?php echo $selectedExamId; ?>" target="_blank" class="sp-card-link"><i class="fas fa-download"></i> Report Card</a>
        </div>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead><tr><th>Subject</th><th class="ta-c">Max</th><th class="ta-c">Obtained</th><th class="ta-c">Grade</th></tr></thead>
                <tbody>
                    <?php foreach ($result['marks'] as $m):
                        $entered = $m['marks_obtained'] !== null && $m['marks_obtained'] !== '';
                        $sPct = ($entered && (int) $m['max_marks'] > 0) ? ((float) $m['marks_obtained'] / (int) $m['max_marks'] * 100) : 0;
                        $sGrade = $m['grade'] ?: ($entered ? examGradeFromPercent($sPct) : '—');
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($m['subject_name']); ?></strong></td>
                        <td class="ta-c"><?php echo (int) $m['max_marks']; ?></td>
                        <td class="ta-c"><?php echo $entered ? rtrim(rtrim(number_format((float) $m['marks_obtained'], 2), '0'), '.') : '—'; ?></td>
                        <td class="ta-c"><span class="sp-grade-chip<?php echo (!$entered ? '' : ($sPct < 33 ? ' is-fail' : '')); ?>"><?php echo htmlspecialchars($sGrade); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td>Total</td><td class="ta-c"><?php echo (int) $result['total_max']; ?></td><td class="ta-c"><?php echo rtrim(rtrim(number_format($result['total_obtained'], 2), '0'), '.'); ?></td><td class="ta-c"><?php echo $pct; ?>%</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="fas fa-gauge-high"></i> Performance</h3></div>
        <div class="sp-ring-card" style="flex-direction:column;text-align:center">
            <div class="sp-ring" style="--pct: <?php echo $pct; ?>; background: conic-gradient(<?php echo $ringColor; ?> calc(var(--pct) * 1%), #ede9fe 0)">
                <div class="sp-ring-inner"><strong><?php echo $pct; ?>%</strong><span>Score</span></div>
            </div>
            <div style="margin-top:16px">
                <div class="sp-badge <?php echo $isPass ? 'present' : 'absent'; ?>" style="font-size:0.85rem"><i class="fas fa-<?php echo $isPass ? 'trophy' : 'triangle-exclamation'; ?>"></i> <?php echo htmlspecialchars($result['result']); ?> · Grade <?php echo htmlspecialchars($result['grade']); ?></div>
                <p style="margin:12px 0 0;color:var(--sp-muted);font-size:0.85rem"><?php echo (int) $result['entered']; ?> of <?php echo (int) $result['subject_count']; ?> subjects evaluated<?php if ($selectedExam && $selectedExam['start_date']): ?> · <?php echo date('M Y', strtotime($selectedExam['start_date'])); ?><?php endif; ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<?php require_once 'includes/layout_footer.php'; ?>
