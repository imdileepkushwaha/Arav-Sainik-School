<?php
$page_title = "Teacher Details";
require_once 'includes/header.php';
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';

ensureTeacherSchema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$teacher = $id ? getTeacherById($pdo, $id) : null;
$search = trim($_GET['q'] ?? '');

if (!$teacher) {
    $results = $search !== '' ? searchTeachers($pdo, $search) : [];
    ?>
    <div class="content-top-bar">
        <div class="content-top-main">
            <div class="content-top-icon icon-blue"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="content-top-title">
                <h2>Teacher Details</h2>
                <p class="content-top-breadcrumb"><a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i><span>Select Teacher</span></p>
            </div>
        </div>
    </div>
    <div class="form-section-card section-mb">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-search"></i></div>
            <div><h4>Find a teacher</h4><p>Search by name, employee ID, subject, or mobile</p></div>
        </div>
        <form method="GET" class="category-add-row">
            <div class="form-field form-field-grow"><input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search teacher..." autofocus></div>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </form>
        <?php if ($search !== ''): ?>
        <div class="erp-search-results teacher-search-results">
            <?php if ($results): foreach ($results as $r): ?>
            <a href="teacher_view.php?id=<?php echo $r['id']; ?>" class="erp-search-item teacher-search-card teacher-search-link">
                <div class="teacher-search-main">
                    <div class="teacher-search-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="teacher-search-info">
                        <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                        <span><?php echo htmlspecialchars($r['employee_id']); ?></span>
                        <div class="teacher-search-meta">
                            <span class="teacher-search-subject-pill"><i class="fas fa-book"></i> <?php echo htmlspecialchars($r['subject'] ?: 'No subject'); ?></span>
                        </div>
                    </div>
                </div>
                <span class="teacher-search-go"><i class="fas fa-arrow-right"></i></span>
            </a>
            <?php endforeach; else: ?>
            <div class="tab-empty-state tab-empty-pad-sm"><p>No teachers found for "<?php echo htmlspecialchars($search); ?>"</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php require_once 'includes/footer.php'; exit;
}

$photo_url = getTeacherPhotoUrl($teacher);
$timetable = getTeacherTimetable($pdo, $id);
$slotCount = 0;
$classesTaught = [];
foreach ($timetable as $day => $periods) {
    foreach ($periods as $slot) {
        $slotCount++;
        if (!empty($slot['class_name'])) {
            $key = $slot['class_name'] . ($slot['section_name'] ? '-' . $slot['section_name'] : '');
            $classesTaught[$key] = $slot['class_name'] . ($slot['section_name'] ? ' (' . $slot['section_name'] . ')' : '');
        }
    }
}
$teacher_name = htmlspecialchars($teacher['name']);
$is_active = $teacher['status'] === 'Active';
$class_assigned = !empty($teacher['class_assigned'])
    ? htmlspecialchars($teacher['class_assigned'] . ($teacher['section_assigned'] ? ' (' . $teacher['section_assigned'] . ')' : ''))
    : 'Not assigned';
$dob_display = $teacher['dob'] ? date('d M Y', strtotime($teacher['dob'])) : '-';
$join_display = $teacher['join_date'] ? date('d M Y', strtotime($teacher['join_date'])) : '-';
$days = getWeekDays();
$periods = range(1, 8);
?>

<div class="student-view-header teacher-view-header">
    <div class="student-view-header-card teacher-view-header-card">
        <div class="student-view-header-main">
            <a href="teachers.php" class="student-back-btn" aria-label="Back to teachers"><i class="fas fa-arrow-left"></i></a>
            <div class="student-header-avatar">
                <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo $teacher_name; ?>">
            </div>
            <div class="student-header-info">
                <div class="student-header-title-row">
                    <h1><?php echo $teacher_name; ?></h1>
                    <span class="status-badge <?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>"><?php echo htmlspecialchars($teacher['status']); ?></span>
                </div>
                <p class="student-view-breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Teacher Details</span>
                </p>
                <div class="student-header-meta">
                    <span class="header-meta-chip"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                    <span class="header-meta-chip"><i class="fas fa-book"></i> <?php echo htmlspecialchars($teacher['subject']); ?></span>
                    <span class="header-meta-chip"><i class="fas fa-school"></i> <?php echo $class_assigned; ?></span>
                </div>
            </div>
        </div>
        <div class="student-view-header-actions">
            <a href="teacher_timetable.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-outline"><i class="fas fa-calendar-alt"></i> Timetable</a>
            <a href="teacher_edit.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-primary"><i class="fas fa-pen"></i> Edit Teacher</a>
        </div>
    </div>
</div>

<div class="teacher-stat-strip">
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-clock"></i></div>
        <div><span>Periods / Week</span><strong><?php echo $slotCount; ?></strong></div>
    </div>
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-users"></i></div>
        <div><span>Classes Teaching</span><strong><?php echo count($classesTaught); ?></strong></div>
    </div>
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-briefcase"></i></div>
        <div><span>Experience</span><strong><?php echo displayVal($teacher['experience_years'], '—'); ?></strong></div>
    </div>
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div><span>Joined</span><strong><?php echo $join_display; ?></strong></div>
    </div>
</div>

<div class="student-view-container">
    <div class="profile-sidebar">
        <div class="profile-banner teacher-profile-banner"></div>
        <div class="profile-body">
            <div class="avatar-wrapper">
                <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo $teacher_name; ?>">
                <span class="avatar-status <?php echo $is_active ? 'active' : 'inactive'; ?>"></span>
            </div>
            <h3><?php echo $teacher_name; ?></h3>
            <p class="profile-subtitle"><?php echo htmlspecialchars($teacher['subject']); ?> Teacher</p>

            <div class="student-badge-info">
                <span class="badge badge-adno"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                <?php if (!empty($teacher['qualification'])): ?>
                <span class="badge badge-roll"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($teacher['qualification']); ?></span>
                <?php endif; ?>
            </div>

            <div class="profile-quick-stats">
                <div class="quick-stat">
                    <span class="quick-stat-label">Mobile</span>
                    <span class="quick-stat-value highlight-phone"><?php echo htmlspecialchars($teacher['phone']); ?></span>
                </div>
                <div class="quick-stat">
                    <span class="quick-stat-label">Gender</span>
                    <span class="quick-stat-value"><?php echo displayVal($teacher['gender']); ?></span>
                </div>
                <div class="quick-stat">
                    <span class="quick-stat-label">Status</span>
                    <span class="quick-stat-value status-text <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($teacher['status']); ?></span>
                </div>
            </div>

            <div class="action-buttons-group">
                <a href="teacher_timetable.php?id=<?php echo $id; ?>" class="btn-outline"><i class="fas fa-calendar-alt"></i> Timetable</a>
                <a href="teacher_edit.php?id=<?php echo $id; ?>" class="btn-admin"><i class="fas fa-pen"></i> Edit</a>
            </div>
        </div>
    </div>

    <div class="personal-info-card">
        <div class="info-header">
            <div class="info-header-title">
                <div class="info-header-icon teacher-info-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <h3>Professional Profile</h3>
                    <p>Qualification, assignment &amp; contact</p>
                </div>
            </div>
            <span class="status-badge <?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>"><?php echo htmlspecialchars($teacher['status']); ?></span>
        </div>

        <div class="info-tiles-grid">
            <div class="info-tile">
                <div class="info-tile-icon icon-subject"><i class="fas fa-book-open"></i></div>
                <div class="info-tile-content"><span class="label">Primary Subject</span><span class="value"><?php echo htmlspecialchars($teacher['subject']); ?></span></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-qualification"><i class="fas fa-graduation-cap"></i></div>
                <div class="info-tile-content"><span class="label">Qualification</span><span class="value"><?php echo displayVal($teacher['qualification']); ?></span></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-experience"><i class="fas fa-briefcase"></i></div>
                <div class="info-tile-content"><span class="label">Experience</span><span class="value"><?php echo displayVal($teacher['experience_years']); ?></span></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-class"><i class="fas fa-school"></i></div>
                <div class="info-tile-content"><span class="label">Class Assigned</span><span class="value"><?php echo $class_assigned; ?></span></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-dob"><i class="fas fa-cake-candles"></i></div>
                <div class="info-tile-content"><span class="label">Date of Birth</span><span class="value"><?php echo $dob_display; ?></span></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-year"><i class="fas fa-calendar-plus"></i></div>
                <div class="info-tile-content"><span class="label">Join Date</span><span class="value"><?php echo $join_display; ?></span></div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-phone"><i class="fas fa-mobile-alt"></i></div>
                <div class="info-tile-content"><span class="label">Mobile</span><span class="value highlight"><?php echo htmlspecialchars($teacher['phone']); ?></span></div>
            </div>
            <div class="info-tile info-tile-wide">
                <div class="info-tile-icon icon-email"><i class="fas fa-envelope"></i></div>
                <div class="info-tile-content"><span class="label">Email Address</span><span class="value highlight"><?php echo displayVal($teacher['email']); ?></span></div>
            </div>
        </div>
    </div>
</div>

<div class="custom-tabs-wrapper">
    <div class="custom-tabs">
        <a href="#" class="tab-link active" data-target="tab-teacher-address"><span class="tab-link-icon"><i class="fas fa-map-marker-alt"></i></span><span class="tab-link-text">Address &amp; Bank</span></a>
        <a href="#" class="tab-link" data-target="tab-teacher-schedule"><span class="tab-link-icon"><i class="fas fa-calendar-alt"></i></span><span class="tab-link-text">Timetable</span></a>
        <?php if ($teacher['description']): ?>
        <a href="#" class="tab-link" data-target="tab-teacher-remarks"><span class="tab-link-icon"><i class="fas fa-align-left"></i></span><span class="tab-link-text">Remarks</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="tab-content-container">

<div id="tab-teacher-address" class="tab-pane active">
    <div class="details-grid details-grid-balanced">
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-address"><i class="fas fa-map-marker-alt"></i></div>
                <div><h4>Residential Address</h4><p>Current contact location</p></div>
            </div>
            <div class="detail-items-list">
                <div class="detail-item detail-item-block">
                    <div class="detail-item-icon di-orange"><i class="fas fa-house"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Street Address</span>
                        <span class="detail-value"><?php echo displayVal($teacher['address']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-blue"><i class="fas fa-city"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">City</span>
                        <span class="detail-value"><?php echo displayVal($teacher['city']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-purple"><i class="fas fa-map"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">State</span>
                        <span class="detail-value"><?php echo displayVal($teacher['state']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-teal"><i class="fas fa-mailbox"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Pincode</span>
                        <span class="detail-value"><?php echo displayVal($teacher['pincode']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-bank"><i class="fas fa-university"></i></div>
                <div><h4>Salary &amp; Bank Details</h4><p>Payroll information</p></div>
            </div>
            <div class="detail-items-list">
                <div class="detail-item">
                    <div class="detail-item-icon di-green"><i class="fas fa-indian-rupee-sign"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Monthly Salary</span>
                        <span class="detail-value"><?php echo $teacher['salary'] ? 'Rs. ' . number_format($teacher['salary'], 2) : '-'; ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-indigo"><i class="fas fa-building-columns"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Bank Name</span>
                        <span class="detail-value"><?php echo displayVal($teacher['bank_name']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-cyan"><i class="fas fa-credit-card"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Account Number</span>
                        <span class="detail-value"><?php echo displayVal($teacher['bank_account']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-orange"><i class="fas fa-barcode"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">IFSC Code</span>
                        <span class="detail-value"><?php echo displayVal($teacher['ifsc_code']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="tab-teacher-schedule" class="tab-pane">
    <div class="data-card data-card-full">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-calendar-week"></i></div>
            <div>
                <h4>Weekly Timetable</h4>
                <p><?php echo $slotCount; ?> period(s) · <?php echo count($classesTaught); ?> class(es)</p>
            </div>
            <a href="teacher_timetable.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-outline btn-sm"><i class="fas fa-pen"></i> Manage</a>
        </div>

        <?php if ($slotCount): ?>
        <div class="teacher-timetable-visual">
            <div class="teacher-tt-head">
                <div class="teacher-tt-corner">Day</div>
                <?php foreach ($periods as $p): ?><div class="teacher-tt-period">P<?php echo $p; ?></div><?php endforeach; ?>
            </div>
            <?php foreach ($days as $day): ?>
            <div class="teacher-tt-row">
                <div class="teacher-tt-day"><?php echo substr($day, 0, 3); ?></div>
                <?php foreach ($periods as $p):
                    $slot = $timetable[$day][$p] ?? null;
                ?>
                <div class="teacher-tt-slot <?php echo $slot ? 'has-class' : 'is-free'; ?>">
                    <?php if ($slot): ?>
                    <strong><?php echo htmlspecialchars($slot['subject_name'] ?: $teacher['subject']); ?></strong>
                    <span><?php echo htmlspecialchars($slot['class_name'] ?? ''); ?><?php echo $slot['section_name'] ? ' · ' . htmlspecialchars($slot['section_name']) : ''; ?></span>
                    <?php if ($slot['room_no']): ?><em>R<?php echo htmlspecialchars($slot['room_no']); ?></em><?php endif; ?>
                    <?php else: ?>
                    <span class="free-label">Free</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="table-wrapper table-scroll-x section-mt">
            <table class="timetable-preview-table">
                <thead><tr><th>Day</th><th>Period</th><th>Time</th><th>Class</th><th>Subject</th><th>Room</th></tr></thead>
                <tbody>
                <?php foreach ($days as $day):
                    if (empty($timetable[$day])) continue;
                    foreach ($timetable[$day] as $slot):
                ?>
                <tr>
                    <td><span class="notify-channel-badge notify-badge-sms"><?php echo $day; ?></span></td>
                    <td><strong>P<?php echo (int) $slot['period_no']; ?></strong></td>
                    <td><?php echo $slot['start_time'] ? substr($slot['start_time'], 0, 5) . ' – ' . substr($slot['end_time'], 0, 5) : '—'; ?></td>
                    <td><?php echo displayVal($slot['class_name']); ?><?php echo $slot['section_name'] ? ' (' . htmlspecialchars($slot['section_name']) . ')' : ''; ?></td>
                    <td><?php echo displayVal($slot['subject_name']); ?></td>
                    <td><?php echo displayVal($slot['room_no']); ?></td>
                </tr>
                <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="tab-empty-state">
            <div class="tab-empty-icon"><i class="fas fa-calendar-alt"></i></div>
            <h3>No timetable scheduled</h3>
            <p>Set up this teacher's weekly periods and class assignments.</p>
            <a href="teacher_timetable.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Create Timetable</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($teacher['description']): ?>
<div id="tab-teacher-remarks" class="tab-pane">
    <div class="data-card data-card-full">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-desc"><i class="fas fa-quote-left"></i></div>
            <div><h4>Remarks &amp; Notes</h4><p>Internal notes about this teacher</p></div>
        </div>
        <div class="description-box">
            <div class="description-quote-icon"><i class="fas fa-quote-left"></i></div>
            <p class="description-text"><?php echo nl2br(htmlspecialchars($teacher['description'])); ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tabLinks = document.querySelectorAll('.tab-link');
    var tabPanes = document.querySelectorAll('.tab-pane');
    tabLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            tabLinks.forEach(function (t) { t.classList.remove('active'); });
            tabPanes.forEach(function (p) { p.classList.remove('active'); });
            this.classList.add('active');
            var target = document.getElementById(this.getAttribute('data-target'));
            if (target) target.classList.add('active');
        });
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
