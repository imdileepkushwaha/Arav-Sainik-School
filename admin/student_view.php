<?php
$page_title = "Student Details";
require_once 'includes/header.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);

if (!isset($_GET['id'])) {
    header('Location: students.php');
    exit;
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    echo "<div class='table-container student-not-found'><h3>Student not found.</h3><a href='students.php' class='btn-admin btn-login'>Back to Student List</a></div>";
    require_once 'includes/footer.php';
    exit;
}

$guardians = getStudentGuardians($pdo, $id);
$photo_url = getStudentPhotoUrl($student);
$student_name = htmlspecialchars($student['name']);
$student_email = !empty($student['email']) ? htmlspecialchars($student['email']) : displayVal(null);
$section = displayVal($student['section'] ?? 'A', 'A');
$is_active = $student['status'] === 'Active';
$is_suspended = !empty($student['suspended_at']);
$dob_display = formatDobDisplay($student['dob']);
$academic_year = 'Jun ' . date('Y') . '/' . (date('Y') + 1);
$feeSummary = getStudentFeeSummary($pdo, $id);
$attendanceSummary = getStudentAttendanceSummary($pdo, $id, (int) date('Y'), (int) date('n'));
$studentDocs = getStudentDocuments($pdo, $id);
$studentExams = $pdo->prepare("SELECT * FROM exams WHERE class_name = ? AND status='Active' ORDER BY id DESC LIMIT 5");
$studentExams->execute([$student['class']]);
$examRows = $studentExams->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="student-view-header">
    <div class="student-view-header-card">
        <div class="student-view-header-main">
            <a href="students.php" class="student-back-btn" aria-label="Back to students">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="student-header-avatar">
                <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo $student_name; ?>">
            </div>
            <div class="student-header-info">
                <div class="student-header-title-row">
                    <h1><?php echo $student_name; ?></h1>
                    <span class="status-badge <?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo htmlspecialchars($student['status']); ?>
                    </span>
                </div>
                <p class="student-view-breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="students.php"><i class="fas fa-user-graduate"></i> Students</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Student Details</span>
                </p>
                <div class="student-header-meta">
                    <span class="header-meta-chip"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['ad_no']); ?></span>
                    <span class="header-meta-chip"><i class="fas fa-hashtag"></i> Roll <?php echo htmlspecialchars($student['roll']); ?></span>
                    <span class="header-meta-chip"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($student['class']); ?></span>
                </div>
            </div>
        </div>
        <div class="student-view-header-actions">
            <a href="certificates.php?id=<?php echo $student['id']; ?>" class="btn-header-action btn-header-outline"><i class="fas fa-certificate"></i> Certificate</a>
            <a href="student_id_card.php?id=<?php echo $student['id']; ?>" class="btn-header-action btn-header-outline" target="_blank"><i class="fas fa-id-card"></i> ID Card</a>
            <a href="student_edit.php?id=<?php echo $student['id']; ?>" class="btn-header-action btn-header-primary"><i class="fas fa-pen"></i> Edit Student</a>
        </div>
    </div>
</div>

<div class="student-view-container">
    <div class="profile-sidebar">
        <div class="profile-banner"></div>
        <div class="profile-body">
            <div class="avatar-wrapper">
                <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo $student_name; ?>">
                <span class="avatar-status <?php echo $is_active ? 'active' : 'inactive'; ?>"></span>
            </div>
            <h3><?php echo $student_name; ?></h3>
            <p class="profile-subtitle">Class <?php echo htmlspecialchars($student['class']); ?> · Section <?php echo $section; ?></p>

            <div class="student-badge-info">
                <span class="badge badge-adno"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['ad_no']); ?></span>
                <span class="badge badge-roll"><i class="fas fa-hashtag"></i> Roll <?php echo htmlspecialchars($student['roll']); ?></span>
            </div>

            <div class="profile-quick-stats">
                <div class="quick-stat">
                    <span class="quick-stat-label">Gender</span>
                    <span class="quick-stat-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
                <div class="quick-stat">
                    <span class="quick-stat-label">Category</span>
                    <span class="quick-stat-value"><?php echo htmlspecialchars($student['category']); ?></span>
                </div>
                <div class="quick-stat">
                    <span class="quick-stat-label">Status</span>
                    <span class="quick-stat-value status-text <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($student['status']); ?></span>
                </div>
            </div>

            <div class="action-buttons-group">
                <?php if ($is_suspended): ?>
                <a href="student_suspend.php?action=activate&id=<?php echo $student['id']; ?>" class="btn-admin"><i class="fas fa-check"></i> Activate</a>
                <?php else: ?>
                <a href="student_suspend.php?suspend=<?php echo $student['id']; ?>" class="btn-outline suspend"><i class="far fa-window-close"></i> Suspend</a>
                <?php endif; ?>
                <a href="student_edit.php?id=<?php echo $student['id']; ?>" class="btn-admin"><i class="fas fa-pen"></i> Edit</a>
            </div>
        </div>
    </div>

    <div class="personal-info-card">
        <div class="info-header">
            <div class="info-header-title">
                <div class="info-header-icon"><i class="fas fa-id-card"></i></div>
                <div>
                    <h3>Personal Information</h3>
                    <p>Basic details and contact information</p>
                </div>
            </div>
            <span class="status-badge <?php echo $is_active ? 'badge-active' : 'badge-inactive'; ?>">
                <?php echo htmlspecialchars($student['status']); ?>
            </span>
        </div>

        <div class="info-tiles-grid">
            <div class="info-tile">
                <div class="info-tile-icon icon-class"><i class="fas fa-school"></i></div>
                <div class="info-tile-content">
                    <span class="label">Class</span>
                    <span class="value"><?php echo htmlspecialchars($student['class']); ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-section"><i class="fas fa-table-columns"></i></div>
                <div class="info-tile-content">
                    <span class="label">Section</span>
                    <span class="value"><?php echo $section; ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-roll"><i class="fas fa-hashtag"></i></div>
                <div class="info-tile-content">
                    <span class="label">Roll No</span>
                    <span class="value"><?php echo htmlspecialchars($student['roll']); ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-gender"><i class="fas fa-venus-mars"></i></div>
                <div class="info-tile-content">
                    <span class="label">Gender</span>
                    <span class="value"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-dob"><i class="fas fa-cake-candles"></i></div>
                <div class="info-tile-content">
                    <span class="label">Date of Birth</span>
                    <span class="value"><?php echo $dob_display; ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-category"><i class="fas fa-tag"></i></div>
                <div class="info-tile-content">
                    <span class="label">Category</span>
                    <span class="value"><?php echo htmlspecialchars($student['category']); ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-year"><i class="fas fa-calendar-days"></i></div>
                <div class="info-tile-content">
                    <span class="label">Academic Year</span>
                    <span class="value"><?php echo $academic_year; ?></span>
                </div>
            </div>
            <div class="info-tile">
                <div class="info-tile-icon icon-phone"><i class="fas fa-phone"></i></div>
                <div class="info-tile-content">
                    <span class="label">Phone Number</span>
                    <span class="value highlight"><?php echo htmlspecialchars($student['mobile']); ?></span>
                </div>
            </div>
            <div class="info-tile info-tile-wide">
                <div class="info-tile-icon icon-email"><i class="fas fa-envelope"></i></div>
                <div class="info-tile-content">
                    <span class="label">Email Address</span>
                    <span class="value highlight"><?php echo $student_email; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="custom-tabs-wrapper">
    <div class="custom-tabs">
        <a href="#" class="tab-link active" data-target="tab-details">
            <span class="tab-link-icon"><i class="far fa-user"></i></span>
            <span class="tab-link-text">Student Details</span>
        </a>
        <a href="#" class="tab-link" data-target="tab-attendance">
            <span class="tab-link-icon"><i class="far fa-calendar-check"></i></span>
            <span class="tab-link-text">Attendance</span>
        </a>
        <a href="#" class="tab-link" data-target="tab-leave">
            <span class="tab-link-icon"><i class="far fa-clock"></i></span>
            <span class="tab-link-text">Leave</span>
        </a>
        <a href="#" class="tab-link" data-target="tab-fees">
            <span class="tab-link-icon"><i class="fas fa-file-invoice-dollar"></i></span>
            <span class="tab-link-text">Fees</span>
        </a>
        <a href="#" class="tab-link" data-target="tab-exam">
            <span class="tab-link-icon"><i class="far fa-edit"></i></span>
            <span class="tab-link-text">Exam</span>
        </a>
        <a href="#" class="tab-link" data-target="tab-library">
            <span class="tab-link-icon"><i class="fas fa-book"></i></span>
            <span class="tab-link-text">Library</span>
        </a>
    </div>
</div>

<div class="tab-content-container">

<div id="tab-details" class="tab-pane active">
    <div class="data-card data-card-full">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-parent"><i class="fas fa-users"></i></div>
            <div>
                <h4>Parent & Guardian Details</h4>
                <p>Emergency contacts and family information</p>
            </div>
        </div>
        <div class="parent-list">
            <?php if (empty($guardians)): ?>
            <div class="tab-empty-state tab-empty-pad">
                <p>No parent or guardian details added yet.</p>
            </div>
            <?php else: foreach ($guardians as $g):
                $gname = htmlspecialchars($g['name']);
                $gavatar = 'https://ui-avatars.com/api/?name=' . urlencode($g['name']) . '&background=f8fafc&color=059669&bold=true';
            ?>
            <div class="parent-card">
                <div class="parent-card-main">
                    <div class="parent-avatar"><img src="<?php echo $gavatar; ?>" alt="<?php echo $gname; ?>"></div>
                    <div class="parent-identity">
                        <strong class="parent-name"><?php echo $gname; ?></strong>
                        <span class="parent-role-badge <?php echo guardianRoleClass($g['relation']); ?>"><i class="fas <?php echo guardianRoleIcon($g['relation']); ?>"></i> <?php echo htmlspecialchars($g['relation']); ?></span>
                    </div>
                </div>
                <div class="parent-contact-grid">
                    <div class="parent-contact-item">
                        <div class="parent-contact-icon"><i class="fas fa-phone"></i></div>
                        <div><span>Phone</span><strong><?php echo displayVal($g['phone']); ?></strong></div>
                    </div>
                    <div class="parent-contact-item">
                        <div class="parent-contact-icon"><i class="fas fa-envelope"></i></div>
                        <div><span>Email</span><strong><?php echo displayVal($g['email']); ?></strong></div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="details-grid">
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-school"><i class="fas fa-school"></i></div>
                <div>
                    <h4>Previous School Details</h4>
                    <p>Academic history and transfers</p>
                </div>
            </div>
            <div class="detail-items-list">
                <?php
                $hasPrev = trim(($student['previous_school'] ?? '') . ($student['previous_class'] ?? '') . ($student['previous_year'] ?? '') . ($student['previous_tc_no'] ?? '')) !== '';
                if (!$hasPrev):
                ?>
                <div class="detail-item">
                    <div class="detail-item-icon di-blue"><i class="fas fa-info-circle"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Previous Schooling</span>
                        <span class="detail-value">None / Not provided</span>
                    </div>
                </div>
                <?php else: ?>
                <div class="detail-item">
                    <div class="detail-item-icon di-blue"><i class="fas fa-building-columns"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Previous School</span>
                        <span class="detail-value"><?php echo displayVal($student['previous_school']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-green"><i class="fas fa-graduation-cap"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Last Class</span>
                        <span class="detail-value"><?php echo displayVal($student['previous_class'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-orange"><i class="fas fa-calendar"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Year / Session</span>
                        <span class="detail-value"><?php echo displayVal($student['previous_year'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-purple"><i class="fas fa-file-alt"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">TC / SLC No.</span>
                        <span class="detail-value"><?php echo displayVal($student['previous_tc_no'] ?? ''); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-address"><i class="fas fa-map-marker-alt"></i></div>
                <div>
                    <h4>Address</h4>
                    <p>Residential and permanent location</p>
                </div>
            </div>
            <div class="detail-items-list">
                <div class="detail-item detail-item-block">
                    <div class="detail-item-icon di-orange"><i class="fas fa-house"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Current Address</span>
                        <span class="detail-value"><?php echo displayVal($student['current_address']); ?></span>
                    </div>
                </div>
                <div class="detail-item detail-item-block">
                    <div class="detail-item-icon di-purple"><i class="fas fa-location-dot"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Permanent Address</span>
                        <span class="detail-value"><?php echo displayVal($student['permanent_address']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="details-grid">
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-bank"><i class="fas fa-university"></i></div>
                <div>
                    <h4>Bank Details</h4>
                    <p>Account and branch information</p>
                </div>
            </div>
            <div class="detail-items-grid">
                <div class="detail-item">
                    <div class="detail-item-icon di-indigo"><i class="fas fa-landmark"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Bank Name</span>
                        <span class="detail-value"><?php echo displayVal($student['bank_name']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-cyan"><i class="fas fa-code-branch"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Branch</span>
                        <span class="detail-value"><?php echo displayVal($student['bank_branch']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-teal"><i class="fas fa-barcode"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">IFSC Code</span>
                        <span class="detail-value"><?php echo displayVal($student['ifsc_code']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-medical"><i class="fas fa-heartbeat"></i></div>
                <div>
                    <h4>Medical Details</h4>
                    <p>Health records and vitals</p>
                </div>
            </div>
            <div class="detail-items-grid">
                <div class="detail-item">
                    <div class="detail-item-icon di-red"><i class="fas fa-droplet"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Blood Group</span>
                        <span class="detail-value"><?php echo displayVal($student['blood_group']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-green"><i class="fas fa-ruler-vertical"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Height</span>
                        <span class="detail-value"><?php echo displayVal($student['height']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-blue"><i class="fas fa-weight-scale"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Weight</span>
                        <span class="detail-value"><?php echo displayVal($student['weight']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="details-grid">
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-docs"><i class="fas fa-file-alt"></i></div>
                <div>
                    <h4>Documents</h4>
                    <p>Uploaded certificates and files</p>
                </div>
            </div>
            <div class="document-list">
                <?php if ($studentDocs): ?>
                <ul class="erp-doc-list">
                    <?php foreach ($studentDocs as $doc): ?>
                    <li><a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="teal-link"><i class="fas fa-file"></i> <?php echo htmlspecialchars($doc['doc_type']); ?></a> <small><?php echo htmlspecialchars($doc['uploaded_at']); ?></small></li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <div class="tab-empty-state tab-empty-pad-sm"><p>No documents uploaded.</p></div>
                <?php endif; ?>
                <a href="student_documents.php?student_id=<?php echo $id; ?>" class="btn-header-action btn-header-outline btn-sm" style="margin-top:12px"><i class="fas fa-upload"></i> Manage Documents</a>
            </div>
        </div>
        <div class="data-card">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-hostel"><i class="fas fa-bed"></i></div>
                <div>
                    <h4>Hostel</h4>
                    <p>Boarding and room allocation</p>
                </div>
            </div>
            <div class="detail-items-grid">
                <div class="detail-item">
                    <div class="detail-item-icon di-purple"><i class="fas fa-hotel"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Hostel</span>
                        <span class="detail-value"><?php echo displayVal($student['hostel_name']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-orange"><i class="fas fa-door-open"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Room No.</span>
                        <span class="detail-value"><?php echo displayVal($student['room_no']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-icon di-teal"><i class="fas fa-bed"></i></div>
                    <div class="detail-item-body">
                        <span class="detail-label">Room Type</span>
                        <span class="detail-value"><?php echo displayVal($student['room_type']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="data-card data-card-full">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-desc"><i class="fas fa-align-left"></i></div>
            <div>
                <h4>Description</h4>
                <p>Teacher remarks and student overview</p>
            </div>
        </div>
        <div class="description-box">
            <div class="description-quote-icon"><i class="fas fa-quote-left"></i></div>
            <p class="description-text"><?php echo !empty($student['description']) ? nl2br(htmlspecialchars($student['description'])) : 'No remarks added for this student.'; ?></p>
        </div>
    </div>
</div>

<div id="tab-attendance" class="tab-pane">
    <?php if (array_sum($attendanceSummary['summary']) > 0): ?>
    <div class="data-card">
        <div class="section-card-header"><div class="section-card-icon section-icon-school"><i class="far fa-calendar-check"></i></div><div><h4>Attendance — <?php echo date('F Y'); ?></h4></div></div>
        <div class="erp-fee-summary">
            <div><span>Present</span><strong><?php echo $attendanceSummary['summary']['Present']; ?></strong></div>
            <div><span>Absent</span><strong><?php echo $attendanceSummary['summary']['Absent']; ?></strong></div>
            <div><span>Late</span><strong><?php echo $attendanceSummary['summary']['Late']; ?></strong></div>
            <div><span>Half Day</span><strong><?php echo $attendanceSummary['summary']['Half Day']; ?></strong></div>
        </div>
        <div class="table-wrapper" style="margin-top:16px">
            <table><thead><tr><th>Date</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($attendanceSummary['records'] as $ar): ?>
            <tr><td><?php echo htmlspecialchars($ar['attendance_date']); ?></td><td><?php echo htmlspecialchars($ar['status']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <a href="attendance_report.php?class=<?php echo urlencode($student['class']); ?>&section=<?php echo urlencode($student['section'] ?? 'A'); ?>" class="teal-link">View class report</a>
    </div>
    <?php else: ?>
    <div class="tab-empty-state"><div class="tab-empty-icon"><i class="far fa-calendar-check"></i></div><h3>Attendance Records</h3><p>No attendance marked this month.</p><a href="attendance.php" class="btn-header-action btn-header-outline">Mark Attendance</a></div>
    <?php endif; ?>
</div>

<div id="tab-leave" class="tab-pane">
    <div class="tab-empty-state">
        <div class="tab-empty-icon"><i class="far fa-clock"></i></div>
        <h3>Leave History</h3>
        <p>This student has not applied for any leave.</p>
    </div>
</div>

<div id="tab-fees" class="tab-pane">
    <?php if ($feeSummary): ?>
    <div class="data-card">
        <div class="erp-fee-summary">
            <div><span>Total Due</span><strong>Rs. <?php echo number_format($feeSummary['total_due'], 2); ?></strong></div>
            <div><span>Paid</span><strong>Rs. <?php echo number_format($feeSummary['total_paid'], 2); ?></strong></div>
            <div><span>Balance</span><strong>Rs. <?php echo number_format($feeSummary['balance'], 2); ?></strong></div>
        </div>
        <?php if ($feeSummary['payments']): ?>
        <table style="width:100%;margin-top:16px"><thead><tr><th>Date</th><th>Receipt</th><th>Amount</th></tr></thead><tbody>
        <?php foreach ($feeSummary['payments'] as $p): ?>
        <tr><td><?php echo htmlspecialchars($p['payment_date']); ?></td><td><?php echo htmlspecialchars($p['receipt_no']); ?></td><td>Rs. <?php echo number_format($p['amount'], 2); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>
        <a href="fee_collect.php?student_id=<?php echo $id; ?>" class="btn-header-action btn-header-primary" style="margin-top:12px"><i class="fas fa-money-bill"></i> Collect Fee</a>
    </div>
    <?php else: ?>
    <div class="tab-empty-state"><div class="tab-empty-icon"><i class="fas fa-file-invoice-dollar"></i></div><h3>Fee Collection</h3><p>No fee records.</p></div>
    <?php endif; ?>
</div>

<div id="tab-exam" class="tab-pane">
    <?php
    $hasMarks = false;
    foreach ($examRows as $ex) {
        $marks = getStudentMarksForExam($pdo, $id, $ex['id']);
        if (array_filter($marks, function ($m) { return $m['marks_obtained'] !== null; })) {
            $hasMarks = true;
            break;
        }
    }
    ?>
    <?php if ($hasMarks): ?>
    <?php foreach ($examRows as $ex):
        $marks = getStudentMarksForExam($pdo, $id, $ex['id']);
        if (!array_filter($marks, function ($m) { return $m['marks_obtained'] !== null; })) continue;
    ?>
    <div class="data-card section-mb">
        <h4><?php echo htmlspecialchars($ex['name']); ?></h4>
        <table><thead><tr><th>Subject</th><th>Marks</th><th>Grade</th><th></th></tr></thead><tbody>
        <?php foreach ($marks as $m): if ($m['marks_obtained'] === null) continue; ?>
        <tr><td><?php echo htmlspecialchars($m['subject_name']); ?></td><td><?php echo $m['marks_obtained']; ?>/<?php echo $m['max_marks']; ?></td><td><?php echo displayVal($m['grade']); ?></td><td></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <a href="report_card.php?student_id=<?php echo $id; ?>&exam_id=<?php echo $ex['id']; ?>" target="_blank" class="teal-link">Print Report Card</a>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="tab-empty-state"><div class="tab-empty-icon"><i class="far fa-edit"></i></div><h3>Exam Results</h3><p>No marks entered yet.</p><a href="marks.php" class="btn-header-action btn-header-outline">Enter Marks</a></div>
    <?php endif; ?>
</div>

<div id="tab-library" class="tab-pane">
    <div class="tab-empty-state">
        <div class="tab-empty-icon"><i class="fas fa-book"></i></div>
        <h3>Library Books</h3>
        <p>No books are currently issued to this student.</p>
    </div>
</div>

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
