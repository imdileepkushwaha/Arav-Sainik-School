<?php
$page_title = "Teacher List";
require_once 'includes/header.php';
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';

ensureTeacherSchema($pdo);
$teachers = getAllTeachers($pdo);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="content-top-title">
            <h2>Teacher List</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Teachers</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="teacher_timetable.php" class="btn-header-action btn-header-outline"><i class="fas fa-calendar-alt"></i> Timetables</a>
        <a href="teacher_add.php" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Add Teacher</a>
    </div>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="toolbar-left">
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="teacherSearch" placeholder="Search teachers...">
            </div>
        </div>
        <div class="toolbar-right">
            <span class="toolbar-meta"><?php echo count($teachers); ?> teachers</span>
        </div>
    </div>
    <div class="table-wrapper">
        <?php if ($teachers): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Subject</th>
                    <th>Class</th>
                    <th>Mobile</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="teacherTableBody">
                <?php foreach ($teachers as $i => $t): ?>
                <tr class="teacher-row">
                    <td><?php echo $i + 1; ?></td>
                    <td><a href="teacher_view.php?id=<?php echo $t['id']; ?>" class="teal-link"><?php echo htmlspecialchars($t['employee_id']); ?></a></td>
                    <td>
                        <div class="student-name-cell">
                            <img src="<?php echo htmlspecialchars(getTeacherPhotoUrl($t)); ?>" alt="">
                            <div class="name-info">
                                <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                                <span><?php echo displayVal($t['qualification']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo displayVal($t['subject']); ?></td>
                    <td><?php
                        if (!empty($t['class_assigned'])) {
                            echo htmlspecialchars($t['class_assigned'] . ($t['section_assigned'] ? ' (' . $t['section_assigned'] . ')' : ''));
                        } else {
                            echo '-';
                        }
                    ?></td>
                    <td><?php echo htmlspecialchars($t['phone']); ?></td>
                    <td><span class="status-badge <?php echo $t['status'] === 'Active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo htmlspecialchars($t['status']); ?></span></td>
                    <td>
                        <div class="table-actions-row">
                            <a href="teacher_view.php?id=<?php echo $t['id']; ?>" class="action-btn view-btn" title="Details"><i class="fas fa-eye"></i></a>
                            <a href="teacher_edit.php?id=<?php echo $t['id']; ?>" class="action-btn edit-btn" title="Edit"><i class="fas fa-pen"></i></a>
                            <a href="teacher_timetable.php?id=<?php echo $t['id']; ?>" class="action-btn view-btn" title="Timetable"><i class="fas fa-calendar-alt"></i></a>
                            <a href="teacher_delete.php?id=<?php echo $t['id']; ?>" class="action-btn delete-btn btn-delete-confirm" title="Delete"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr id="teacherNoResults" class="row-hidden"><td colspan="8" class="no-results-cell"><div class="empty-state empty-state-md"><h3>No matches</h3></div></td></tr>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher empty-state-icon"></i>
            <h3>No Teachers Yet</h3>
            <p>Add your first teacher to start managing staff records and timetables.</p>
            <a href="teacher_add.php" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Add First Teacher</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('teacherSearch');
    if (!input) return;
    input.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        var visible = 0;
        document.querySelectorAll('.teacher-row').forEach(function (row) {
            var show = row.textContent.toLowerCase().indexOf(q) >= 0;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var nr = document.getElementById('teacherNoResults');
        if (nr) nr.classList.toggle('row-hidden', visible > 0);
    });
})();
</script>
<?php require_once 'includes/footer.php'; ?>
