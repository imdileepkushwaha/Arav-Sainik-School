<?php
$page_title = 'Apply Leave';
$page_subtitle = 'Submit and track your leave requests';
require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_leave'])) {
    $leaveId = (int) ($_POST['leave_id'] ?? 0);
    $result = cancelTeacherLeaveRequest($pdo, $teacherId, $leaveId);
    if ($result['ok']) {
        tp_flash('leave.php', 'Leave request cancelled successfully.');
    } else {
        tp_flash('leave.php', $result['error'], 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $from = trim($_POST['from_date'] ?? '');
    $to = trim($_POST['to_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if ($from === '' || $to === '') {
        tp_flash('leave.php', 'From and to dates are required.', 'error');
    } elseif (strtotime($from) > strtotime($to)) {
        tp_flash('leave.php', 'From date cannot be after to date.', 'error');
    } else {
        $conflict = findOverlappingLeaveRequest($pdo, 'Teacher', $teacherId, $from, $to);
        if ($conflict) {
            tp_flash('leave.php', leaveOverlapMessage($conflict), 'error');
        } else {
            $pdo->prepare("INSERT INTO leave_requests (person_type, person_id, from_date, to_date, reason) VALUES ('Teacher',?,?,?,?)")
                ->execute([$teacherId, $from, $to, $reason ?: null]);
            tp_flash('leave.php', 'Leave request submitted successfully. Admin will review it soon.');
        }
    }
}

$leaves = $pdo->prepare("SELECT * FROM leave_requests WHERE person_type = 'Teacher' AND person_id = ? ORDER BY created_at DESC LIMIT 50");
$leaves->execute([$teacherId]);
$myLeaves = $leaves->fetchAll(PDO::FETCH_ASSOC);

$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$cancelledCount = 0;
$approvedDays = 0;
$activeLeave = null;
$upcomingLeave = null;
$today = date('Y-m-d');

foreach ($myLeaves as $l) {
    $st = $l['status'] ?? 'Pending';
    $days = max(1, (int) ((strtotime($l['to_date']) - strtotime($l['from_date'])) / 86400) + 1);
    if ($st === 'Pending') {
        $pendingCount++;
    } elseif ($st === 'Approved') {
        $approvedCount++;
        $approvedDays += $days;
        if ($l['from_date'] <= $today && $l['to_date'] >= $today) {
            $activeLeave = $l;
        } elseif ($l['from_date'] > $today && ($upcomingLeave === null || $l['from_date'] < $upcomingLeave['from_date'])) {
            $upcomingLeave = $l;
        }
    } elseif ($st === 'Rejected') {
        $rejectedCount++;
    } elseif ($st === 'Cancelled') {
        $cancelledCount++;
    }
}

require_once 'includes/layout_header.php';
?>

<div class="tp-leave-hero">
    <div class="tp-leave-hero-main">
        <p class="tp-leave-hero-greet"><i class="fas fa-plane-departure"></i> Leave Management</p>
        <h2>Apply &amp; Track Leave</h2>
        <p class="tp-leave-hero-desc">
            Submit requests for admin approval. Overlapping dates with pending or approved leave are not allowed.
        </p>
        <div class="tp-leave-hero-chips">
            <?php if ($activeLeave): ?>
            <span class="tp-leave-hero-chip is-active"><i class="fas fa-circle"></i> On leave today</span>
            <?php endif; ?>
            <?php if ($pendingCount): ?>
            <span class="tp-leave-hero-chip"><i class="fas fa-hourglass-half"></i> <?php echo $pendingCount; ?> pending</span>
            <?php endif; ?>
            <?php if ($approvedCount): ?>
            <span class="tp-leave-hero-chip"><i class="fas fa-check"></i> <?php echo $approvedCount; ?> approved</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="tp-leave-hero-aside">
        <div class="tp-leave-hero-stat">
            <span>Approved Days</span>
            <strong><?php echo $approvedDays; ?></strong>
        </div>
        <div class="tp-leave-hero-stat">
            <span>Total Requests</span>
            <strong><?php echo count($myLeaves); ?></strong>
        </div>
    </div>
</div>

<?php if ($activeLeave): ?>
<div class="tp-leave-active-banner">
    <div class="tp-leave-active-icon"><i class="fas fa-umbrella-beach"></i></div>
    <div>
        <strong>You are currently on approved leave</strong>
        <p><?php echo date('d M Y', strtotime($activeLeave['from_date'])); ?> – <?php echo date('d M Y', strtotime($activeLeave['to_date'])); ?>
        <?php if ($activeLeave['reason']): ?> · <?php echo htmlspecialchars($activeLeave['reason']); ?><?php endif; ?></p>
    </div>
</div>
<?php elseif ($upcomingLeave): ?>
<div class="tp-leave-upcoming-banner">
    <div class="tp-leave-active-icon"><i class="fas fa-calendar-check"></i></div>
    <div>
        <strong>Upcoming approved leave</strong>
        <p><?php echo date('d M Y', strtotime($upcomingLeave['from_date'])); ?> – <?php echo date('d M Y', strtotime($upcomingLeave['to_date'])); ?></p>
    </div>
</div>
<?php endif; ?>

<div class="tp-stat-grid cols-4 tp-leave-stats">
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
        <div><span>Pending</span><strong><?php echo $pendingCount; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div><span>Approved</span><strong><?php echo $approvedCount; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div><span>Rejected</span><strong><?php echo $rejectedCount; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon purple"><i class="fas fa-ban"></i></div>
        <div><span>Cancelled</span><strong><?php echo $cancelledCount; ?></strong></div>
    </div>
</div>

<div class="tp-leave-layout">
    <div class="tp-leave-form-col">
        <div class="tp-card tp-leave-form-card">
            <div class="tp-card-head">
                <h3><i class="fas fa-plus-circle"></i> New Leave Request</h3>
                <span class="tp-card-badge"><i class="fas fa-paper-plane"></i> Submit to Admin</span>
            </div>
            <form method="POST" class="tp-form-panel" id="tpLeaveForm">
                <input type="hidden" name="apply_leave" value="1">
                <div class="tp-leave-date-row">
                    <div class="tp-field tp-leave-date-field">
                        <label><i class="fas fa-calendar-alt"></i> From Date</label>
                        <input type="date" name="from_date" id="tpLeaveFrom" required>
                    </div>
                    <div class="tp-leave-date-arrow"><i class="fas fa-long-arrow-alt-right"></i></div>
                    <div class="tp-field tp-leave-date-field">
                        <label><i class="fas fa-calendar-alt"></i> To Date</label>
                        <input type="date" name="to_date" id="tpLeaveTo" required>
                    </div>
                </div>
                <div class="tp-leave-duration-preview" id="tpLeaveDuration" hidden>
                    <i class="fas fa-clock"></i>
                    <span>Duration: <strong id="tpLeaveDays">—</strong></span>
                </div>
                <div class="tp-field">
                    <label><i class="fas fa-comment-alt"></i> Reason <small>(optional)</small></label>
                    <textarea name="reason" rows="4" placeholder="Brief reason for leave — e.g. family function, medical, personal"></textarea>
                </div>
                <button type="submit" class="tp-btn tp-btn-primary tp-leave-submit"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </form>
        </div>

        <div class="tp-leave-tips">
            <h4><i class="fas fa-lightbulb"></i> Before you apply</h4>
            <ul>
                <li>Apply at least 1–2 days in advance when possible.</li>
                <li>Dates cannot overlap with pending or approved leave.</li>
                <li>Admin will review and update status — check history below.</li>
                <li>Pending or upcoming approved leave can be cancelled from history.</li>
                <li>Rejected or cancelled requests can be re-submitted with new dates.</li>
            </ul>
        </div>
    </div>

    <div class="tp-card tp-leave-history-card">
        <div class="tp-card-head">
            <h3><i class="fas fa-history"></i> Leave History</h3>
            <?php if ($myLeaves): ?><span class="tp-card-badge"><?php echo count($myLeaves); ?> requests</span><?php endif; ?>
        </div>
        <?php if ($myLeaves): ?>
        <div class="tp-leave-timeline">
            <?php foreach ($myLeaves as $l):
                $st = $l['status'] ?? 'Pending';
                $stClass = $st === 'Approved' ? 'is-approved' : ($st === 'Rejected' ? 'is-rejected' : ($st === 'Cancelled' ? 'is-cancelled' : 'is-pending'));
                $stIcon = $st === 'Approved' ? 'check-circle' : ($st === 'Rejected' ? 'times-circle' : ($st === 'Cancelled' ? 'ban' : 'clock'));
                $days = max(1, (int) ((strtotime($l['to_date']) - strtotime($l['from_date'])) / 86400) + 1);
                $isActive = $st === 'Approved' && $l['from_date'] <= $today && $l['to_date'] >= $today;
                $isUpcoming = $st === 'Approved' && $l['from_date'] > $today;
                $canCancel = teacherCanCancelLeave($l, $today)['ok'];
                $statusBadgeClass = $st === 'Approved' ? 'badge-active' : ($st === 'Rejected' ? 'badge-inactive' : ($st === 'Cancelled' ? 'badge-cancelled' : 'is-pending'));
            ?>
            <article class="tp-leave-card <?php echo $stClass; ?><?php echo $isActive ? ' is-current' : ''; ?>">
                <div class="tp-leave-card-status">
                    <span class="tp-leave-card-icon"><i class="fas fa-<?php echo $stIcon; ?>"></i></span>
                </div>
                <div class="tp-leave-card-body">
                    <div class="tp-leave-card-top">
                        <div class="tp-leave-card-dates">
                            <strong><?php echo date('d M Y', strtotime($l['from_date'])); ?></strong>
                            <span class="tp-leave-card-sep">→</span>
                            <strong><?php echo date('d M Y', strtotime($l['to_date'])); ?></strong>
                        </div>
                        <div class="tp-leave-card-badges">
                            <span class="tp-leave-days-badge"><?php echo $days; ?> day<?php echo $days === 1 ? '' : 's'; ?></span>
                            <span class="tp-leave-status <?php echo $statusBadgeClass; ?>"><?php echo htmlspecialchars($st); ?></span>
                        </div>
                    </div>
                    <?php if ($isActive): ?>
                    <span class="tp-leave-card-tag is-active"><i class="fas fa-circle"></i> Active now</span>
                    <?php elseif ($isUpcoming): ?>
                    <span class="tp-leave-card-tag is-upcoming"><i class="fas fa-calendar"></i> Upcoming</span>
                    <?php endif; ?>
                    <?php if ($l['reason']): ?>
                    <p class="tp-leave-reason"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($l['reason']); ?></p>
                    <?php endif; ?>
                    <footer class="tp-leave-card-foot">
                        <span><i class="fas fa-paper-plane"></i> Submitted <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?></span>
                        <?php if ($canCancel): ?>
                        <form method="POST" class="tp-leave-cancel-form" onsubmit="return confirm('Cancel this leave request?');">
                            <input type="hidden" name="cancel_leave" value="1">
                            <input type="hidden" name="leave_id" value="<?php echo (int) $l['id']; ?>">
                            <button type="submit" class="tp-btn tp-btn-outline tp-btn-sm tp-leave-cancel-btn"><i class="fas fa-times"></i> Cancel Leave</button>
                        </form>
                        <?php endif; ?>
                    </footer>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="tp-empty tp-leave-empty">
            <div class="tp-leave-empty-icon"><i class="fas fa-plane-departure"></i></div>
            <h4>No leave requests yet</h4>
            <p>Submit your first request using the form.<br>Your history will appear here.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var fromEl = document.getElementById('tpLeaveFrom');
    var toEl = document.getElementById('tpLeaveTo');
    var preview = document.getElementById('tpLeaveDuration');
    var daysEl = document.getElementById('tpLeaveDays');
    if (!fromEl || !toEl) return;

    function updateDuration() {
        var from = fromEl.value;
        var to = toEl.value;
        if (toEl.min !== from) toEl.min = from || '';
        if (!from || !to) {
            preview.hidden = true;
            return;
        }
        var start = new Date(from + 'T00:00:00');
        var end = new Date(to + 'T00:00:00');
        if (end < start) {
            preview.hidden = true;
            return;
        }
        var days = Math.round((end - start) / 86400000) + 1;
        daysEl.textContent = days + (days === 1 ? ' day' : ' days');
        preview.hidden = false;
    }

    fromEl.addEventListener('change', updateDuration);
    toEl.addEventListener('change', updateDuration);
})();
</script>

<?php require_once 'includes/layout_footer.php'; ?>
