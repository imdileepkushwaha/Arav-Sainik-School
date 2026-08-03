<?php
$page_title = 'Attendance';
$page_subtitle = date('F Y');
require_once 'includes/init.php';
$id = (int) $_SESSION['student_portal_id'];
$attendance = getStudentAttendanceSummary($pdo, $id, (int) date('Y'), (int) date('n'));
require_once 'includes/layout_header.php';
$s = $attendance['summary'];
$total = array_sum($s);
$pct = $total ? round($s['Present'] / $total * 100) : 0;

function sp_att_badge($status) {
    $map = ['Present' => 'present', 'Absent' => 'absent', 'Late' => 'late', 'Half Day' => 'half'];
    return $map[$status] ?? 'normal';
}
?>
<div class="sp-stat-grid">
    <div class="sp-stat tone-green"><div class="sp-stat-icon"><i class="fas fa-check"></i></div><div class="sp-stat-body"><span>Present</span><strong><?php echo $s['Present']; ?></strong><small>days this month</small></div></div>
    <div class="sp-stat tone-red"><div class="sp-stat-icon"><i class="fas fa-times"></i></div><div class="sp-stat-body"><span>Absent</span><strong><?php echo $s['Absent']; ?></strong><small>days this month</small></div></div>
    <div class="sp-stat tone-amber"><div class="sp-stat-icon"><i class="fas fa-clock"></i></div><div class="sp-stat-body"><span>Late / Half</span><strong><?php echo $s['Late'] + $s['Half Day']; ?></strong><small>days this month</small></div></div>
    <div class="sp-stat tone-purple"><div class="sp-stat-icon"><i class="fas fa-percent"></i></div><div class="sp-stat-body"><span>Attendance</span><strong><?php echo $pct; ?>%</strong><small>overall rate</small></div></div>
</div>

<div class="sp-grid-2 wide-first">
    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="far fa-calendar-check"></i> Daily Records — <?php echo date('F Y'); ?></h3></div>
        <?php if ($attendance['records']): ?>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead><tr><th>Date</th><th>Day</th><th class="ta-c">Status</th></tr></thead>
                <tbody>
                <?php foreach ($attendance['records'] as $r): ?>
                <tr>
                    <td><strong><?php echo date('d M Y', strtotime($r['attendance_date'])); ?></strong></td>
                    <td><?php echo date('l', strtotime($r['attendance_date'])); ?></td>
                    <td class="ta-c"><span class="sp-badge <?php echo sp_att_badge($r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="sp-empty"><div class="sp-empty-icon"><i class="far fa-calendar-check"></i></div><strong>No records this month</strong><p>Attendance will appear here once marked.</p></div>
        <?php endif; ?>
    </div>

    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="fas fa-chart-pie"></i> Monthly Summary</h3></div>
        <div class="sp-ring-card">
            <div class="sp-ring" style="--pct: <?php echo $pct; ?>">
                <div class="sp-ring-inner"><strong><?php echo $pct; ?>%</strong><span>Present</span></div>
            </div>
            <div class="sp-ring-legend">
                <div class="sp-ring-leg"><span class="sp-ring-dot" style="background:#059669"></span><span class="lbl">Present</span><span class="val"><?php echo $s['Present']; ?></span></div>
                <div class="sp-ring-leg"><span class="sp-ring-dot" style="background:#dc2626"></span><span class="lbl">Absent</span><span class="val"><?php echo $s['Absent']; ?></span></div>
                <div class="sp-ring-leg"><span class="sp-ring-dot" style="background:#d97706"></span><span class="lbl">Late</span><span class="val"><?php echo $s['Late']; ?></span></div>
                <div class="sp-ring-leg"><span class="sp-ring-dot" style="background:#2563eb"></span><span class="lbl">Half Day</span><span class="val"><?php echo $s['Half Day']; ?></span></div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/layout_footer.php'; ?>
