<?php
$page_title = "Dashboard";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/settings_helpers.php';

ensureErpSchema($pdo);
ensureSettingsSchema($pdo);
$school = getSchoolProfile($pdo);
$stats = getDashboardStats($pdo);
$notices = getActiveNotices($pdo, 5);
$session = getCurrentSession($pdo);
$pendingLeaves = $pdo->query(
    "SELECT lr.*, t.name AS teacher_name FROM leave_requests lr
     LEFT JOIN teachers t ON lr.person_type='Teacher' AND t.id=lr.person_id
     WHERE lr.status='Pending' ORDER BY lr.created_at DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
$topTeachers = $pdo->query(
    "SELECT name, subject, employee_id FROM teachers WHERE status='Active' ORDER BY name LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
$websiteEnquiries = getWebsiteEnquiries($pdo, 12);
$websiteEnquiriesNew = count(array_filter($websiteEnquiries, fn($e) => ($e['status'] ?? '') === 'New'));

require_once 'includes/header.php';

$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$chartData = array_values($stats['chartMonths']);
$yearFeeTotal = array_sum($stats['chartMonths']);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$adminDisplay = htmlspecialchars(ucfirst($_SESSION['admin_username']));
$todayLabel = date('l, d M Y');
?>

<div class="db-welcome">
    <div class="db-welcome-main">
        <div class="db-welcome-badge"><i class="fas fa-sun"></i> <?php echo $greeting; ?>, <?php echo $adminDisplay; ?></div>
        <h2><?php echo htmlspecialchars($school['name']); ?></h2>
        <p>
            <i class="fas fa-calendar-day"></i> <?php echo $todayLabel; ?>
            · Session <?php echo htmlspecialchars($session['name'] ?? '—'); ?>
            <?php if (!empty($school['tagline'])): ?> · <?php echo htmlspecialchars($school['tagline']); ?><?php endif; ?>
        </p>
    </div>
    <?php if (!empty($brandLogoUrl)): ?>
    <div class="db-welcome-logo"><img src="<?php echo htmlspecialchars($brandLogoUrl); ?>" alt="School logo"></div>
    <?php endif; ?>
    <div class="db-welcome-actions">
        <a href="student_add.php" class="db-action-btn"><i class="fas fa-user-plus"></i> Add Student</a>
        <a href="attendance.php" class="db-action-btn"><i class="far fa-calendar-check"></i> Attendance</a>
        <a href="fee_collect.php" class="db-action-btn is-primary"><i class="fas fa-rupee-sign"></i> Collect Fee</a>
    </div>
</div>

<div class="db-stat-grid">
    <div class="db-stat is-students">
        <div class="db-stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="db-stat-body">
            <span>Active Students</span>
            <strong><?php echo number_format($stats['totalStudents']); ?></strong>
            <em>+<?php echo $stats['newStudentsMonth']; ?> this month</em>
        </div>
    </div>
    <div class="db-stat is-teachers">
        <div class="db-stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="db-stat-body">
            <span>Active Teachers</span>
            <strong><?php echo number_format($stats['totalTeachers']); ?></strong>
        </div>
    </div>
    <div class="db-stat is-portal">
        <div class="db-stat-icon"><i class="fas fa-laptop"></i></div>
        <div class="db-stat-body">
            <span>Portal Enabled</span>
            <strong><?php echo number_format($stats['portalEnabled']); ?></strong>
        </div>
    </div>
    <div class="db-stat is-fee-today">
        <div class="db-stat-icon"><i class="fas fa-coins"></i></div>
        <div class="db-stat-body">
            <span>Fee Today</span>
            <strong>₹<?php echo number_format($stats['feeToday'], 0); ?></strong>
        </div>
    </div>
    <div class="db-stat is-fee-month">
        <div class="db-stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="db-stat-body">
            <span>Fee This Month</span>
            <strong>₹<?php echo number_format($stats['feeMonth'], 0); ?></strong>
        </div>
    </div>
    <div class="db-stat is-attendance">
        <div class="db-stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="db-stat-body">
            <span>Present Today</span>
            <strong><?php echo number_format($stats['presentToday']); ?></strong>
            <em><?php echo $stats['absentToday']; ?> absent</em>
        </div>
    </div>
</div>

<div class="db-layout">
    <div class="db-main">
        <div class="form-section-card db-chart-card">
            <div class="db-card-head">
                <div class="db-card-head-icon is-green"><i class="fas fa-chart-bar"></i></div>
                <div>
                    <h4>Fee Collection — <?php echo date('Y'); ?></h4>
                    <p>₹<?php echo number_format($yearFeeTotal, 0); ?> collected this year</p>
                </div>
                <a href="fee_reports.php" class="db-card-link">View reports <i class="fas fa-arrow-right"></i></a>
            </div>
            <div id="revenueChart" class="db-chart"></div>
        </div>

        <div class="db-split">
            <div class="form-section-card db-panel">
                <div class="db-card-head compact">
                    <div class="db-card-head-icon is-purple"><i class="fas fa-bullhorn"></i></div>
                    <div>
                        <h4>Notice Board</h4>
                        <p>Latest announcements</p>
                    </div>
                    <a href="notices.php" class="db-card-link">Manage</a>
                </div>
                <?php if ($notices): ?>
                <div class="db-notice-list">
                    <?php foreach ($notices as $n):
                        $prioClass = $n['priority'] === 'Urgent' ? 'is-urgent' : ($n['priority'] === 'Important' ? 'is-important' : '');
                    ?>
                    <a href="notices.php" class="db-notice-item">
                        <div class="db-notice-top">
                            <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                            <?php if ($n['priority'] !== 'Normal'): ?>
                            <span class="db-priority-badge <?php echo $prioClass; ?>"><?php echo htmlspecialchars($n['priority']); ?></span>
                            <?php endif; ?>
                        </div>
                        <p><?php echo htmlspecialchars(mb_substr($n['body'], 0, 90)); ?>…</p>
                        <small><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($n['publish_date'])); ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="db-empty">
                    <i class="fas fa-bullhorn"></i>
                    <p>No notices yet. <a href="notices.php">Publish one</a></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-section-card db-panel">
                <div class="db-card-head compact">
                    <div class="db-card-head-icon is-orange"><i class="fas fa-plane-departure"></i></div>
                    <div>
                        <h4>Pending Leaves</h4>
                        <p><?php echo $stats['pendingLeaves']; ?> awaiting approval</p>
                    </div>
                    <a href="leave_requests.php" class="db-card-link">Review</a>
                </div>
                <?php if ($pendingLeaves): ?>
                <div class="db-leave-list">
                    <?php foreach ($pendingLeaves as $l):
                        $name = $l['teacher_name'] ?? 'Staff';
                        $initial = strtoupper(substr($name, 0, 1));
                    ?>
                    <a href="leave_requests.php" class="db-leave-item">
                        <span class="db-leave-avatar"><?php echo htmlspecialchars($initial); ?></span>
                        <div class="db-leave-body">
                            <strong><?php echo htmlspecialchars($name); ?></strong>
                            <span><?php echo date('d M', strtotime($l['from_date'])); ?> → <?php echo date('d M Y', strtotime($l['to_date'])); ?></span>
                        </div>
                        <em><?php echo htmlspecialchars($l['reason'] ?: 'Leave'); ?></em>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="db-empty">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending leave requests</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-section-card db-panel">
            <div class="db-card-head compact">
                <div class="db-card-head-icon is-blue"><i class="fas fa-user-plus"></i></div>
                <div>
                    <h4>Recent Admissions</h4>
                    <p>Latest enrolled students</p>
                </div>
                <a href="students.php" class="db-card-link">All students</a>
            </div>
            <?php if ($stats['recentStudents']): ?>
            <div class="table-wrapper">
                <table class="db-table">
                    <thead><tr><th>Adm No</th><th>Student</th><th>Class</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['recentStudents'] as $s):
                        $initials = '';
                        foreach (preg_split('/\s+/', trim($s['name'])) as $part) {
                            if ($part !== '') {
                                $initials .= strtoupper($part[0]);
                            }
                        }
                        $initials = substr($initials, 0, 2) ?: 'S';
                    ?>
                    <tr>
                        <td><code class="db-adm-code"><?php echo htmlspecialchars($s['ad_no']); ?></code></td>
                        <td>
                            <div class="db-student-cell">
                                <span class="db-mini-avatar"><?php echo htmlspecialchars($initials); ?></span>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </div>
                        </td>
                        <td><span class="db-class-pill"><?php echo htmlspecialchars($s['class']); ?></span></td>
                        <td><a href="student_view.php?id=<?php echo (int) $s['id']; ?>" class="db-view-link">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="db-empty"><i class="fas fa-users"></i><p>No students yet</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="db-side">
        <div class="form-section-card db-panel">
            <div class="db-card-head compact">
                <div class="db-card-head-icon is-teal"><i class="fas fa-bolt"></i></div>
                <div><h4>Quick Actions</h4></div>
            </div>
            <div class="db-quick-grid">
                <a href="student_add.php" class="db-quick-tile"><i class="fas fa-user-plus"></i><span>Add Student</span></a>
                <a href="teacher_add.php" class="db-quick-tile"><i class="fas fa-chalkboard-teacher"></i><span>Add Teacher</span></a>
                <a href="admission_enquiries.php" class="db-quick-tile<?php echo $stats['newEnquiries'] ? ' has-badge' : ''; ?>">
                    <i class="fas fa-inbox"></i><span>Enquiries</span>
                    <?php if ($stats['newEnquiries']): ?><em><?php echo $stats['newEnquiries']; ?> new</em><?php endif; ?>
                </a>
                <a href="homework.php" class="db-quick-tile"><i class="fas fa-book"></i><span>Homework</span></a>
                <a href="certificates.php" class="db-quick-tile"><i class="fas fa-certificate"></i><span>Certificates</span></a>
                <a href="settings.php" class="db-quick-tile"><i class="fas fa-cog"></i><span>Settings</span></a>
            </div>
        </div>

        <div class="form-section-card db-panel">
            <div class="db-card-head compact">
                <div class="db-card-head-icon is-green"><i class="fas fa-receipt"></i></div>
                <div>
                    <h4>Recent Payments</h4>
                    <p>Latest fee collections</p>
                </div>
                <a href="fee_collect.php" class="db-card-link">Collect</a>
            </div>
            <?php if ($stats['recentPayments']): ?>
            <div class="db-payment-list">
                <?php foreach ($stats['recentPayments'] as $p): ?>
                <div class="db-payment-item">
                    <div class="db-payment-icon"><i class="fas fa-rupee-sign"></i></div>
                    <div class="db-payment-body">
                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                        <span><?php echo htmlspecialchars($p['receipt_no']); ?> · <?php echo date('d M Y', strtotime($p['payment_date'])); ?></span>
                    </div>
                    <div class="db-payment-amt">₹<?php echo number_format($p['amount'], 0); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="db-empty"><i class="fas fa-receipt"></i><p>No payments yet</p></div>
            <?php endif; ?>
        </div>

        <div class="form-section-card db-panel">
            <div class="db-card-head compact">
                <div class="db-card-head-icon is-indigo"><i class="fas fa-users"></i></div>
                <div><h4>Teachers</h4></div>
                <a href="teachers.php" class="db-card-link">View all</a>
            </div>
            <div class="db-teacher-list">
                <?php foreach ($topTeachers as $t):
                    $initial = strtoupper(substr($t['name'], 0, 1));
                ?>
                <div class="db-teacher-item">
                    <span class="db-teacher-avatar"><?php echo htmlspecialchars($initial); ?></span>
                    <div>
                        <strong><?php echo htmlspecialchars($t['name']); ?></strong>
                        <small><?php echo htmlspecialchars($t['employee_id']); ?></small>
                    </div>
                    <em><?php echo htmlspecialchars($t['subject']); ?></em>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="form-section-card db-panel db-website-enquiries">
    <div class="db-card-head compact">
        <div class="db-card-head-icon is-orange"><i class="fas fa-globe"></i></div>
        <div>
            <h4>Website Enquiries</h4>
            <p>Contact form submissions from the public homepage<?php if ($websiteEnquiriesNew): ?> · <strong><?php echo $websiteEnquiriesNew; ?> new</strong><?php endif; ?></p>
        </div>
        <a href="admission_enquiries.php" class="db-card-link">Manage all <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if ($websiteEnquiries): ?>
    <div class="table-wrapper">
        <table class="db-table db-enquiry-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($websiteEnquiries as $eq):
                $status = $eq['status'] ?? 'New';
                $statusClass = $status === 'New' ? 'is-new' : ($status === 'Converted' ? 'is-converted' : ($status === 'Contacted' ? 'is-contacted' : 'is-closed'));
            ?>
            <tr>
                <td class="db-enquiry-date">
                    <strong><?php echo date('d M Y', strtotime($eq['created_at'])); ?></strong>
                    <small><?php echo date('h:i A', strtotime($eq['created_at'])); ?></small>
                </td>
                <td><strong><?php echo htmlspecialchars($eq['student_name']); ?></strong></td>
                <td><a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $eq['mobile'])); ?>" class="db-enquiry-phone"><?php echo htmlspecialchars($eq['mobile']); ?></a></td>
                <td><?php if (!empty($eq['email'])): ?><a href="mailto:<?php echo htmlspecialchars($eq['email']); ?>"><?php echo htmlspecialchars($eq['email']); ?></a><?php else: ?>—<?php endif; ?></td>
                <td class="db-enquiry-msg"><?php
                    $enqMsg = trim($eq['message'] ?? '');
                    echo $enqMsg !== '' ? htmlspecialchars(mb_strimwidth($enqMsg, 0, 120, '…')) : '—';
                ?></td>
                <td><span class="db-enquiry-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="db-empty">
        <i class="fas fa-inbox"></i>
        <p>No website enquiries yet. Submissions from <a href="../index.php" target="_blank" rel="noopener">homepage contact form</a> will appear here.</p>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') return;
    var el = document.querySelector('#revenueChart');
    if (!el) return;
    new ApexCharts(el, {
        series: [{ name: 'Collected', data: <?php echo json_encode($chartData); ?> }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
        plotOptions: { bar: { borderRadius: 8, columnWidth: '52%' } },
        dataLabels: { enabled: false },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
        xaxis: {
            categories: <?php echo json_encode($monthNames); ?>,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: '#64748b', fontSize: '12px' } }
        },
        colors: ['#059669'],
        yaxis: {
            labels: {
                formatter: function (v) { return '₹' + Math.round(v).toLocaleString('en-IN'); },
                style: { colors: '#64748b', fontSize: '12px' }
            }
        },
        tooltip: {
            y: { formatter: function (v) { return '₹' + Math.round(v).toLocaleString('en-IN'); } }
        }
    }).render();
});
</script>

<?php require_once 'includes/footer.php'; ?>
