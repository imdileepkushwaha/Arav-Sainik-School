<?php
// admin/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = htmlspecialchars(ucfirst($_SESSION['admin_username']));
$avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['admin_username']) . '&background=059669&color=fff&bold=true';

$student_pages = ['students.php', 'student_add.php', 'student_edit.php', 'student_view.php', 'student_suspend.php', 'student_categories.php', 'student_import.php', 'student_promote.php', 'student_promote_advanced.php', 'student_id_card.php', 'student_documents.php', 'classes.php', 'portal_accounts.php', 'admission_enquiries.php', 'toppers.php'];
$academic_pages = ['academic_sessions.php', 'subjects.php', 'class_timetable.php', 'notices.php'];
$attendance_pages = ['attendance.php', 'attendance_report.php'];
$fee_pages = ['fees.php', 'fee_collect.php', 'fee_receipt.php', 'fee_reports.php'];
$hostel_pages = ['hostel.php', 'hostel_fees.php', 'hostel_fee_collect.php', 'hostel_fee_receipt.php'];
$transport_pages = ['transport.php', 'transport_fees.php', 'transport_fee_collect.php', 'transport_fee_receipt.php'];
$exam_pages = ['exams.php', 'marks.php', 'report_card.php', 'exam_analytics.php'];
$teacher_pages = ['teachers.php', 'teacher_add.php', 'teacher_edit.php', 'teacher_view.php', 'teacher_timetable.php', 'teacher_portal_accounts.php', 'teacher_attendance.php', 'leave_requests.php'];
?>
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo-icon<?php echo $brandLogoUrl ? ' has-logo' : ''; ?>">
            <?php if ($brandLogoUrl): ?>
            <img src="<?php echo htmlspecialchars($brandLogoUrl); ?>" alt="<?php echo htmlspecialchars($brandTitle); ?>" class="sidebar-logo-img">
            <?php else: ?>
            <i class="fas fa-graduation-cap"></i>
            <?php endif; ?>
        </div>
        <div class="sidebar-brand-text">
            <h2><?php echo htmlspecialchars($brandTitle); ?></h2>
            <span>Admin Panel</span>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <img src="<?php echo $avatar_url; ?>" alt="<?php echo $admin_name; ?>">
            <span class="online-badge"></span>
        </div>
        <div class="sidebar-user-info">
            <h4><?php echo $admin_name; ?></h4>
            <p>Administrator</p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <p class="sidebar-nav-label">Main Menu</p>
        <ul class="sidebar-menu">
            <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <span class="menu-icon-wrap"><i class="fas fa-home menu-icon"></i></span>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $student_pages)) ? 'active open' : ''; ?>">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="fas fa-user-graduate menu-icon"></i></span>
                    <span class="menu-text">Students</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'student_add.php') ? 'active' : ''; ?>"><a href="student_add.php">Add New Student</a></li>
                    <li class="<?php echo ($current_page == 'students.php') ? 'active' : ''; ?>"><a href="students.php">Student List</a></li>
                    <li class="<?php echo ($current_page == 'classes.php') ? 'active' : ''; ?>"><a href="classes.php">Classes & Sections</a></li>
                    <li class="<?php echo ($current_page == 'student_documents.php') ? 'active' : ''; ?>"><a href="student_documents.php">Documents</a></li>
                    <li class="<?php echo ($current_page == 'student_promote.php') ? 'active' : ''; ?>"><a href="student_promote.php">Promote (Bulk)</a></li>
                    <li class="<?php echo ($current_page == 'student_promote_advanced.php') ? 'active' : ''; ?>"><a href="student_promote_advanced.php">Promote (Selected)</a></li>
                    <li class="<?php echo ($current_page == 'student_import.php') ? 'active' : ''; ?>"><a href="student_import.php">Import</a></li>
                    <li class="<?php echo ($current_page == 'student_categories.php') ? 'active' : ''; ?>"><a href="student_categories.php">Categories</a></li>
                    <li class="<?php echo ($current_page == 'toppers.php') ? 'active' : ''; ?>"><a href="toppers.php">Topper Manage</a></li>
                    <li class="<?php echo ($current_page == 'student_suspend.php') ? 'active' : ''; ?>"><a href="student_suspend.php">Suspend</a></li>
                    <li class="<?php echo ($current_page == 'portal_accounts.php') ? 'active' : ''; ?>" style="display: none;"><a href="portal_accounts.php">Student Portal</a></li>
                    <li class="<?php echo ($current_page == 'admission_enquiries.php') ? 'active' : ''; ?>"><a href="admission_enquiries.php">Admission Enquiries</a></li>
                </ul>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $academic_pages)) ? 'active open' : ''; ?>">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="fas fa-book-open menu-icon"></i></span>
                    <span class="menu-text">Academic</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'academic_sessions.php') ? 'active' : ''; ?>"><a href="academic_sessions.php">Sessions</a></li>
                    <li class="<?php echo ($current_page == 'subjects.php') ? 'active' : ''; ?>"><a href="subjects.php">Subjects</a></li>
                    <li class="<?php echo ($current_page == 'class_timetable.php') ? 'active' : ''; ?>"><a href="class_timetable.php">Class Timetable</a></li>
                    <li class="<?php echo ($current_page == 'notices.php') ? 'active' : ''; ?>"><a href="notices.php">Notice Board</a></li>
                </ul>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $attendance_pages)) ? 'active open' : ''; ?>" style="display: none;">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="far fa-calendar-check menu-icon"></i></span>
                    <span class="menu-text">Attendance</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>"><a href="attendance.php">Mark Attendance</a></li>
                    <li class="<?php echo ($current_page == 'attendance_report.php') ? 'active' : ''; ?>"><a href="attendance_report.php">Monthly Report</a></li>
                </ul>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $fee_pages)) ? 'active open' : ''; ?>">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="fas fa-file-invoice-dollar menu-icon"></i></span>
                    <span class="menu-text">Fees</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'fees.php') ? 'active' : ''; ?>"><a href="fees.php">Fee Structure</a></li>
                    <li class="<?php echo ($current_page == 'fee_collect.php') ? 'active' : ''; ?>"><a href="fee_collect.php">Collect Fee</a></li>
                    <li class="<?php echo ($current_page == 'fee_reports.php') ? 'active' : ''; ?>"><a href="fee_reports.php">Fee Reports</a></li>
                </ul>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $exam_pages)) ? 'active open' : ''; ?>" style="display: none;">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="far fa-edit menu-icon"></i></span>
                    <span class="menu-text">Examinations</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'exams.php') ? 'active' : ''; ?>"><a href="exams.php">Manage Exams</a></li>
                    <li class="<?php echo ($current_page == 'marks.php') ? 'active' : ''; ?>"><a href="marks.php">Enter Marks</a></li>
                    <li class="<?php echo ($current_page == 'exam_analytics.php') ? 'active' : ''; ?>"><a href="exam_analytics.php">Result Analytics</a></li>
                </ul>
            </li>
            <li class="<?php echo ($current_page == 'certificates.php' || $current_page == 'certificate_print.php') ? 'active' : ''; ?>" style="display: none;">
                <a href="certificates.php">
                    <span class="menu-icon-wrap"><i class="fas fa-certificate menu-icon"></i></span>
                    <span class="menu-text">Certificates</span>
                </a>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $hostel_pages)) ? 'active open' : ''; ?>">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="fas fa-bed menu-icon"></i></span>
                    <span class="menu-text">Hostel</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'hostel.php') ? 'active' : ''; ?>"><a href="hostel.php">Rooms &amp; Allotment</a></li>
                    <li class="<?php echo ($current_page == 'hostel_fees.php') ? 'active' : ''; ?>"><a href="hostel_fees.php">Hostel Fee Structure</a></li>
                    <li class="<?php echo ($current_page == 'hostel_fee_collect.php' || $current_page == 'hostel_fee_receipt.php') ? 'active' : ''; ?>"><a href="hostel_fee_collect.php">Collect Hostel Fee</a></li>
                </ul>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $transport_pages)) ? 'active open' : ''; ?>" style="display: none;">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="fas fa-bus menu-icon"></i></span>
                    <span class="menu-text">Transport</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu">
                    <li class="<?php echo ($current_page == 'transport.php') ? 'active' : ''; ?>"><a href="transport.php">Routes &amp; Assign</a></li>
                    <li class="<?php echo ($current_page == 'transport_fees.php') ? 'active' : ''; ?>"><a href="transport_fees.php">Transport Fee Structure</a></li>
                    <li class="<?php echo ($current_page == 'transport_fee_collect.php' || $current_page == 'transport_fee_receipt.php') ? 'active' : ''; ?>"><a href="transport_fee_collect.php">Collect Transport Fee</a></li>
                </ul>
            </li>
            <li class="<?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>" style="display: none;">
                <a href="notifications.php">
                    <span class="menu-icon-wrap"><i class="fas fa-bell menu-icon"></i></span>
                    <span class="menu-text">SMS / WhatsApp</span>
                </a>
            </li>
            <li class="has-submenu <?php echo (in_array($current_page, $teacher_pages)) ? 'active open' : ''; ?>">
                <a href="#" class="submenu-toggle" onclick="return toggleSubmenu(this, event)">
                    <span class="menu-icon-wrap"><i class="fas fa-chalkboard-teacher menu-icon"></i></span>
                    <span class="menu-text">Teachers</span>
                    <i class="fas fa-chevron-down chevron"></i>
                </a>
                <ul class="submenu submenu-tree">
                    <li class="<?php echo ($current_page == 'teacher_add.php') ? 'active' : ''; ?>"><a href="teacher_add.php">Add New Teacher</a></li>
                    <li class="<?php echo ($current_page == 'teachers.php') ? 'active' : ''; ?>"><a href="teachers.php">Teacher List</a></li>
                    <li class="<?php echo ($current_page == 'teacher_edit.php') ? 'active' : ''; ?>" style="display: none;"><a href="teacher_edit.php">Edit Teacher</a></li>
                    <li class="<?php echo ($current_page == 'teacher_view.php') ? 'active' : ''; ?>"><a href="teacher_view.php">Teacher Details</a></li>
                    <li class="<?php echo ($current_page == 'teacher_timetable.php') ? 'active' : ''; ?>"><a href="teacher_timetable.php">Teacher Timetable</a></li>
                    <li class="<?php echo ($current_page == 'teacher_portal_accounts.php') ? 'active' : ''; ?>" style="display: none;"><a href="teacher_portal_accounts.php">Teacher Portal</a></li>
                    <li class="<?php echo ($current_page == 'teacher_attendance.php') ? 'active' : ''; ?>" style="display: none;"><a href="teacher_attendance.php">Teacher Attendance</a></li>
                    <li class="<?php echo ($current_page == 'leave_requests.php') ? 'active' : ''; ?>" style="display: none;"><a href="leave_requests.php">Leave Requests</a></li>
                </ul>
            </li>
            <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <a href="settings.php">
                    <span class="menu-icon-wrap"><i class="fas fa-cog menu-icon"></i></span>
                    <span class="menu-text">Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-logout">
            <span class="menu-icon-wrap"><i class="fas fa-sign-out-alt menu-icon"></i></span>
            <span class="menu-text">Logout</span>
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
function toggleSubmenu(link, e) {
    if (e) e.preventDefault();

    var item = link.parentElement;
    if (!item) return false;

    var isOpen = item.classList.contains('open');
    var items = document.querySelectorAll('.sidebar-menu > li.has-submenu');
    var i;

    for (i = 0; i < items.length; i++) {
        items[i].classList.remove('open');
    }

    if (!isOpen) {
        item.classList.add('open');
    }

    return false;
}
</script>
