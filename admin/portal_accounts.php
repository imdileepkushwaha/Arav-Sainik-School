<?php
$page_title = "Student Portal Accounts";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_portal'])) {
    $id = (int) $_POST['student_id'];
    $pass = enableStudentPortal($pdo, $id, trim($_POST['password'] ?? '') ?: null);
    $stmt = $pdo->prepare("SELECT ad_no, name FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    $adNo = $s ? $s['ad_no'] : '';
    $_SESSION['success_msg'] = "Portal enabled! Login at /portal/ — Admission No: {$adNo} — Password: {$pass}";
    header('Location: portal_accounts.php');
    exit;
}

require_once 'includes/header.php';
$enabled = $pdo->query("SELECT id, ad_no, name, class, portal_enabled FROM students WHERE portal_enabled = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$totalActive = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$class_options = getClassOptions($pdo);
$filterName = trim($_GET['name'] ?? '');
$filterAdNo = trim($_GET['ad_no'] ?? '');
$filterClass = trim($_GET['class'] ?? '');
$filterSection = trim($_GET['section'] ?? '');
$sectionOptions = $filterClass !== '' ? getSectionOptions($pdo, $filterClass) : ['A', 'B', 'C', 'D'];
$searched = $filterName !== '' || $filterAdNo !== '' || $filterClass !== '' || $filterSection !== '';
$results = [];
if ($searched) {
    $sql = "SELECT id, ad_no, name, class, section, portal_enabled FROM students WHERE 1=1";
    $params = [];
    if ($filterName !== '') {
        $sql .= " AND name LIKE ?";
        $params[] = '%' . $filterName . '%';
    }
    if ($filterAdNo !== '') {
        $sql .= " AND ad_no LIKE ?";
        $params[] = '%' . $filterAdNo . '%';
    }
    if ($filterClass !== '') {
        $sql .= " AND class = ?";
        $params[] = $filterClass;
    }
    if ($filterSection !== '') {
        $sql .= " AND section = ?";
        $params[] = $filterSection;
    }
    $sql .= " ORDER BY class, section, name LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-laptop"></i></div>
        <div class="content-top-title">
            <h2>Student Portal</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Portal Access</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="notices.php" class="btn-header-action btn-header-outline"><i class="fas fa-bullhorn"></i> Notices</a>
        <a href="../portal/" target="_blank" class="btn-header-action btn-header-primary"><i class="fas fa-external-link-alt"></i> Open Portal</a>
    </div>
</div>

<div class="cls-stat-strip">
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-green"><i class="fas fa-user-check"></i></div><div><span>Portal Enabled</span><strong><?php echo count($enabled); ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon"><i class="fas fa-users"></i></div><div><span>Active Students</span><strong><?php echo $totalActive; ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-blue"><i class="fas fa-link"></i></div><div><span>Portal URL</span><strong style="font-size:0.95rem">/portal/</strong></div></div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-key"></i></div>
        <div><h4>Enable Portal Login</h4><p>Leave password empty to auto-generate an 8-character password</p></div>
    </div>
    <form method="GET" class="category-add-row erp-filter-row-4" id="portalSearchForm">
        <div class="form-field">
            <label>Name</label>
            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Student name">
        </div>
        <div class="form-field">
            <label>Admission No</label>
            <input type="text" name="ad_no" class="form-input" value="<?php echo htmlspecialchars($filterAdNo); ?>" placeholder="e.g. AD2026-C1-0001">
        </div>
        <div class="form-field">
            <label>Class</label>
            <select name="class" class="form-input form-select" onchange="this.form.section.value=''; this.form.submit()">
                <option value="">All classes</option>
                <?php foreach ($class_options as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterClass === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Section</label>
            <select name="section" class="form-input form-select">
                <option value="">All sections</option>
                <?php foreach ($sectionOptions as $sec): ?>
                <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <?php if ($searched): ?>
    <div class="erp-search-results student-search-results">
        <?php if ($results): foreach ($results as $r): ?>
        <form method="POST" class="erp-search-item student-search-card student-portal-card">
            <input type="hidden" name="enable_portal" value="1">
            <input type="hidden" name="student_id" value="<?php echo $r['id']; ?>">
            <div class="student-search-main">
                <div class="student-search-avatar"><i class="fas fa-user-graduate"></i></div>
                <div class="student-search-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <span><?php echo htmlspecialchars($r['ad_no']); ?></span>
                    <div class="student-search-meta">
                        <span class="student-search-class-pill"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($r['class']); ?><?php if (!empty($r['section'])): ?> - <?php echo htmlspecialchars($r['section']); ?><?php endif; ?></span>
                        <?php if ($r['portal_enabled']): ?><span class="status-badge badge-active">Portal Enabled</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="student-search-actions">
                <span class="student-search-actions-label">Portal password</span>
                <input type="text" name="password" class="form-input student-portal-pass" placeholder="Auto if empty">
                <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-key"></i> <?php echo $r['portal_enabled'] ? 'Reset' : 'Enable'; ?></button>
            </div>
        </form>
        <?php endforeach; else: ?>
        <div class="tab-empty-state tab-empty-pad-sm"><div class="tab-empty-icon"><i class="fas fa-search"></i></div><h3>No students found</h3></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>Portal Enabled (<?php echo count($enabled); ?>)</strong></div>
    <div class="table-wrapper">
        <table><thead><tr><th>Adm No</th><th>Name</th><th>Class</th><th>Status</th></tr></thead><tbody>
        <?php if ($enabled): foreach ($enabled as $e): ?>
        <tr><td><strong><?php echo htmlspecialchars($e['ad_no']); ?></strong></td><td><?php echo htmlspecialchars($e['name']); ?></td><td><?php echo htmlspecialchars($e['class']); ?></td><td><span class="status-badge badge-active">Active</span></td></tr>
        <?php endforeach; else: ?>
        <tr><td colspan="4" class="table-empty-cell">No students have portal access yet.</td></tr>
        <?php endif; ?>
        </tbody></table>
    </div>
</div>

<div class="notify-info-banner section-mb" style="margin-top:20px">
    <div class="notify-info-icon"><i class="fas fa-info-circle"></i></div>
    <div class="notify-info-text">
        <strong>Student portal URL:</strong> <code>/portal/</code> — Login with admission number + password.<br>
        Publish announcements from <a href="notices.php" class="teal-link">Notice Board</a>.
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
