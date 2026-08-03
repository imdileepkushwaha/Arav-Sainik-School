<?php
$page_title = "Leave Requests";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/teacher_helpers.php';

ensureErpSchema($pdo);

$redirectUrl = 'leave_requests.php';
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $redirectUrl .= '?status=' . urlencode($_GET['status']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_leave') {
        $from = trim($_POST['from_date'] ?? '');
        $to = trim($_POST['to_date'] ?? '');
        $personType = $_POST['person_type'] ?? 'Teacher';
        $personId = (int) $_POST['person_id'];
        if ($from === '' || $to === '') {
            $_SESSION['error_msg'] = 'From and to dates are required.';
        } elseif (strtotime($from) > strtotime($to)) {
            $_SESSION['error_msg'] = 'From date cannot be after to date.';
        } else {
            $conflict = findOverlappingLeaveRequest($pdo, $personType, $personId, $from, $to);
            if ($conflict) {
                $_SESSION['error_msg'] = leaveOverlapMessage($conflict);
            } else {
                $pdo->prepare(
                    "INSERT INTO leave_requests (person_type, person_id, from_date, to_date, reason, added_by) VALUES (?,?,?,?,?,'Admin')"
                )->execute([
                    $personType,
                    $personId,
                    $from,
                    $to,
                    trim($_POST['reason'] ?? '') ?: null,
                ]);
                $_SESSION['success_msg'] = 'Leave added successfully.';
            }
        }
    } elseif ($action === 'edit_leave') {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        $from = trim($_POST['from_date'] ?? '');
        $to = trim($_POST['to_date'] ?? '');
        $personType = $_POST['person_type'] ?? 'Teacher';
        $personId = (int) $_POST['person_id'];
        $result = updateAdminLeaveRequest($pdo, $leaveId, $personType, $personId, $from, $to, $_POST['reason'] ?? '');
        if ($result['ok']) {
            $_SESSION['success_msg'] = 'Leave updated successfully.';
        } else {
            $_SESSION['error_msg'] = $result['error'];
        }
    } elseif ($action === 'update_status') {
        $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?")->execute([$_POST['status'], (int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Leave status updated.';
    } elseif ($action === 'cancel_leave') {
        $result = cancelAdminLeaveRequest($pdo, (int) ($_POST['leave_id'] ?? 0));
        if ($result['ok']) {
            $_SESSION['success_msg'] = 'Leave cancelled successfully.';
        } else {
            $_SESSION['error_msg'] = $result['error'];
        }
    }

    header('Location: ' . $redirectUrl);
    exit;
}

require_once 'includes/header.php';

$teachers = getAllTeachers($pdo, true);
$teacherMap = [];
foreach ($teachers as $t) {
    $teacherMap[(int) $t['id']] = $t;
}

$filterStatus = $_GET['status'] ?? 'all';
$editId = (int) ($_GET['edit'] ?? 0);
$editLeave = $editId ? getLeaveRequestById($pdo, $editId) : null;
if ($editLeave && !adminCanEditLeave($editLeave)) {
    $editLeave = null;
    $editId = 0;
}

$leaves = $pdo->query("SELECT * FROM leave_requests ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Cancelled' => 0];
foreach ($leaves as $l) {
    $st = $l['status'] ?? 'Pending';
    if (isset($counts[$st])) {
        $counts[$st]++;
    }
}

$filteredLeaves = $leaves;
if ($filterStatus !== 'all' && isset($counts[$filterStatus])) {
    $filteredLeaves = array_values(array_filter($leaves, fn($l) => ($l['status'] ?? '') === $filterStatus));
}

$today = date('Y-m-d');
?>

<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-orange"><i class="fas fa-plane-departure"></i></div>
        <div class="content-top-title">
            <h2>Leave Requests</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i>
                <a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i>
                <span>Leave Management</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="teacher_attendance.php" class="btn-header-action btn-header-outline"><i class="fas fa-user-check"></i> Attendance</a>
        <a href="teachers.php" class="btn-header-action btn-header-outline"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
    </div>
</div>

<div class="alr-hero">
    <div class="alr-hero-main">
        <p class="alr-hero-label"><i class="fas fa-calendar-minus"></i> HR Leave Desk</p>
        <h3>Manage teacher leave requests</h3>
        <p>Review portal submissions, add leave on behalf of teachers, edit admin entries, and cancel when needed.</p>
    </div>
    <div class="alr-hero-stats">
        <div class="alr-hero-stat"><span>Pending Review</span><strong><?php echo $counts['Pending']; ?></strong></div>
        <div class="alr-hero-stat"><span>Total Records</span><strong><?php echo count($leaves); ?></strong></div>
    </div>
</div>

<div class="cls-stat-strip cols-4">
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-amber"><i class="fas fa-hourglass-half"></i></div>
        <div><span>Pending</span><strong><?php echo $counts['Pending']; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-check-circle"></i></div>
        <div><span>Approved</span><strong><?php echo $counts['Approved']; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon" style="background:#fef2f2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
        <div><span>Rejected</span><strong><?php echo $counts['Rejected']; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-ban"></i></div>
        <div><span>Cancelled</span><strong><?php echo $counts['Cancelled']; ?></strong></div>
    </div>
</div>

<div class="alr-layout">
    <div class="alr-form-col">
        <div class="form-section-card alr-form-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-school"><i class="fas fa-<?php echo $editLeave ? 'edit' : 'plus'; ?>"></i></div>
                <div>
                    <h4><?php echo $editLeave ? 'Edit Admin Leave' : 'Add Leave (Admin)'; ?></h4>
                    <p class="alr-form-sub"><?php echo $editLeave ? 'Update dates, teacher, or reason for this admin entry.' : 'Record leave directly — marked as added by admin.'; ?></p>
                </div>
            </div>
            <form method="POST" class="alr-form">
                <input type="hidden" name="action" value="<?php echo $editLeave ? 'edit_leave' : 'add_leave'; ?>">
                <?php if ($editLeave): ?>
                <input type="hidden" name="leave_id" value="<?php echo (int) $editLeave['id']; ?>">
                <?php endif; ?>
                <input type="hidden" name="person_type" value="Teacher">
                <div class="form-grid form-grid-2 form-grid-spaced">
                    <div class="form-field form-field-full">
                        <label><i class="fas fa-user"></i> Teacher</label>
                        <select name="person_id" class="form-input form-select" required>
                            <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($editLeave && (int) $editLeave['person_id'] === (int) $t['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name'] . ' (' . $t['employee_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-calendar-alt"></i> From Date</label>
                        <input type="date" name="from_date" class="form-input" required value="<?php echo $editLeave ? htmlspecialchars($editLeave['from_date']) : ''; ?>">
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-calendar-alt"></i> To Date</label>
                        <input type="date" name="to_date" class="form-input" required value="<?php echo $editLeave ? htmlspecialchars($editLeave['to_date']) : ''; ?>">
                    </div>
                    <div class="form-field form-field-full">
                        <label><i class="fas fa-comment"></i> Reason</label>
                        <input type="text" name="reason" class="form-input" placeholder="Optional reason" value="<?php echo $editLeave ? htmlspecialchars($editLeave['reason'] ?? '') : ''; ?>">
                    </div>
                </div>
                <div class="alr-form-actions">
                    <?php if ($editLeave): ?>
                    <a href="leave_requests.php<?php echo $filterStatus !== 'all' ? '?status=' . urlencode($filterStatus) : ''; ?>" class="btn-header-action btn-header-outline">Cancel Edit</a>
                    <?php endif; ?>
                    <button type="submit" class="btn-header-action btn-header-primary">
                        <i class="fas fa-<?php echo $editLeave ? 'save' : 'plus'; ?>"></i>
                        <?php echo $editLeave ? 'Save Changes' : 'Add Leave'; ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="alr-note">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Admin-added leave</strong> can be edited later. Teacher portal submissions can only be approved, rejected, or cancelled.
            </div>
        </div>
    </div>

    <div class="alr-list-col">
        <div class="form-section-card alr-list-card">
            <div class="alr-list-head">
                <div>
                    <h4><i class="fas fa-list"></i> All Leave Requests</h4>
                    <p><?php echo count($filteredLeaves); ?> record<?php echo count($filteredLeaves) === 1 ? '' : 's'; ?><?php echo $filterStatus !== 'all' ? ' · ' . htmlspecialchars($filterStatus) : ''; ?></p>
                </div>
                <div class="alr-filter-tabs">
                    <?php
                    $tabs = ['all' => 'All', 'Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected', 'Cancelled' => 'Cancelled'];
                    foreach ($tabs as $key => $label):
                        $href = $key === 'all' ? 'leave_requests.php' : 'leave_requests.php?status=' . urlencode($key);
                        $active = ($filterStatus === $key) || ($key === 'all' && $filterStatus === 'all');
                    ?>
                    <a href="<?php echo $href; ?>" class="alr-filter-tab<?php echo $active ? ' is-active' : ''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($filteredLeaves): ?>
            <div class="alr-leave-list">
                <?php foreach ($filteredLeaves as $l):
                    $teacher = ($l['person_type'] === 'Teacher') ? ($teacherMap[(int) $l['person_id']] ?? null) : null;
                    $name = $teacher ? $teacher['name'] : '—';
                    $empId = $teacher ? $teacher['employee_id'] : '';
                    $st = $l['status'] ?? 'Pending';
                    $days = leaveRequestDays($l['from_date'], $l['to_date']);
                    $isAdminAdded = ($l['added_by'] ?? 'Teacher') === 'Admin';
                    $canEdit = adminCanEditLeave($l);
                    $canCancel = adminCanCancelLeave($l);
                    $isActive = $st === 'Approved' && $l['from_date'] <= $today && $l['to_date'] >= $today;
                    $stClass = $st === 'Approved' ? 'is-approved' : ($st === 'Rejected' ? 'is-rejected' : ($st === 'Cancelled' ? 'is-cancelled' : 'is-pending'));
                    $initials = '';
                    foreach (preg_split('/\s+/', trim($name)) as $part) {
                        if ($part !== '') {
                            $initials .= strtoupper($part[0]);
                        }
                    }
                    $initials = substr($initials, 0, 2) ?: 'T';
                ?>
                <article class="alr-leave-card <?php echo $stClass; ?><?php echo $isActive ? ' is-active' : ''; ?>">
                    <div class="alr-leave-card-top">
                        <div class="alr-teacher-cell">
                            <span class="alr-teacher-avatar"><?php echo htmlspecialchars($initials); ?></span>
                            <div>
                                <strong><?php echo htmlspecialchars($name); ?></strong>
                                <small><?php echo htmlspecialchars($empId ?: $l['person_type']); ?></small>
                            </div>
                        </div>
                        <div class="alr-leave-badges">
                            <span class="status-badge <?php echo leaveStatusBadgeClass($st); ?>"><?php echo htmlspecialchars($st); ?></span>
                            <span class="alr-source-badge <?php echo $isAdminAdded ? 'is-admin' : 'is-portal'; ?>">
                                <i class="fas fa-<?php echo $isAdminAdded ? 'user-shield' : 'mobile-alt'; ?>"></i>
                                <?php echo $isAdminAdded ? 'Admin' : 'Portal'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="alr-leave-dates">
                        <div class="alr-date-node">
                            <span>From</span>
                            <strong><?php echo date('d M Y', strtotime($l['from_date'])); ?></strong>
                        </div>
                        <div class="alr-date-arrow"><i class="fas fa-arrow-right"></i></div>
                        <div class="alr-date-node">
                            <span>To</span>
                            <strong><?php echo date('d M Y', strtotime($l['to_date'])); ?></strong>
                        </div>
                        <span class="alr-days-pill"><?php echo $days; ?> day<?php echo $days === 1 ? '' : 's'; ?></span>
                        <?php if ($isActive): ?>
                        <span class="alr-active-tag"><i class="fas fa-circle"></i> Active</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($l['reason'])): ?>
                    <p class="alr-leave-reason"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($l['reason']); ?></p>
                    <?php endif; ?>

                    <div class="alr-leave-foot">
                        <span class="alr-submitted"><i class="fas fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?></span>
                        <div class="alr-leave-actions">
                            <?php if ($st === 'Pending'): ?>
                            <form method="POST" class="alr-inline-form">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo (int) $l['id']; ?>">
                                <button type="submit" name="status" value="Approved" class="alr-action-btn is-approve"><i class="fas fa-check"></i> Approve</button>
                                <button type="submit" name="status" value="Rejected" class="alr-action-btn is-reject"><i class="fas fa-times"></i> Reject</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                            <a href="leave_requests.php?edit=<?php echo (int) $l['id']; ?><?php echo $filterStatus !== 'all' ? '&status=' . urlencode($filterStatus) : ''; ?>" class="alr-action-btn is-edit"><i class="fas fa-edit"></i> Edit</a>
                            <?php endif; ?>
                            <?php if ($canCancel): ?>
                            <form method="POST" class="alr-inline-form" onsubmit="return confirm('Cancel this leave request?');">
                                <input type="hidden" name="action" value="cancel_leave">
                                <input type="hidden" name="leave_id" value="<?php echo (int) $l['id']; ?>">
                                <button type="submit" class="alr-action-btn is-cancel"><i class="fas fa-ban"></i> Cancel</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alr-empty">
                <div class="alr-empty-icon"><i class="fas fa-plane-departure"></i></div>
                <h4>No leave requests</h4>
                <p><?php echo $filterStatus !== 'all' ? 'No ' . htmlspecialchars($filterStatus) . ' leave found.' : 'Add leave using the form or wait for teacher submissions.'; ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
