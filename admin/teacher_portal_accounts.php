<?php
$page_title = "Teacher Portal Accounts";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';

ensureTeacherSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_portal'])) {
    $id = (int) $_POST['teacher_id'];
    $pass = enableTeacherPortal($pdo, $id, trim($_POST['password'] ?? '') ?: null);
    $t = getTeacherById($pdo, $id);
    $empId = $t ? $t['employee_id'] : '';
    $_SESSION['success_msg'] = "Portal enabled! Login at /teacher/ — Employee ID: {$empId} — Password: {$pass}" . ($pass === getTeacherPortalDefaultPassword() ? ' (default — teacher must change on first login)' : '');
    header('Location: teacher_portal_accounts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_all_portal'])) {
    $count = enableAllTeachersPortal($pdo);
    $_SESSION['success_msg'] = "Portal enabled for {$count} active teacher(s). Default password: " . getTeacherPortalDefaultPassword();
    header('Location: teacher_portal_accounts.php');
    exit;
}

require_once 'includes/header.php';

$defaultPass = getTeacherPortalDefaultPassword();
$enabled = $pdo->query("SELECT id, employee_id, name, subject, portal_enabled FROM teachers WHERE portal_enabled = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allTeachers = getAllTeachers($pdo, false);
$totalActive = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'Active'")->fetchColumn();
$enabledCount = count($enabled);
$pendingTeachers = array_values(array_filter($allTeachers, function ($t) {
    return isTeacherActive($t) && !isTeacherPortalEnabled($t);
}));
$pendingCount = count($pendingTeachers);
$search = trim($_GET['q'] ?? '');
$results = [];
if ($search !== '') {
    $results = searchTeachers($pdo, $search, 20);
}
$pendingPreview = array_slice($pendingTeachers, 0, 8);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-laptop-code"></i></div>
        <div class="content-top-title">
            <h2>Teacher Portal</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i>
                <a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i>
                <span>Portal Access</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="homework.php" class="btn-header-action btn-header-outline"><i class="fas fa-book-open"></i> Homework</a>
        <a href="teachers.php" class="btn-header-action btn-header-outline"><i class="fas fa-chalkboard-teacher"></i> All Teachers</a>
        <a href="../teacher/" target="_blank" class="btn-header-action btn-header-primary"><i class="fas fa-external-link-alt"></i> Open Portal</a>
    </div>
</div>

<div class="cls-stat-strip cols-4">
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-user-check"></i></div>
        <div><span>Portal Enabled</span><strong><?php echo $enabledCount; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-users"></i></div>
        <div><span>Active Teachers</span><strong><?php echo $totalActive; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-amber"><i class="fas fa-hourglass-half"></i></div>
        <div><span>Pending Access</span><strong><?php echo $pendingCount; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-link"></i></div>
        <div><span>Portal URL</span><strong style="font-size:0.95rem">/teacher/</strong></div>
    </div>
</div>

<div class="tpa-hero">
    <div class="tpa-hero-main">
        <h3><i class="fas fa-sign-in-alt"></i> Teacher Login Details</h3>
        <p>Teachers login with their Employee ID and password. Leave password empty when enabling — the default password is used automatically.</p>
        <div class="tpa-login-chips">
            <div class="tpa-login-chip"><strong>URL</strong> <code>/teacher/</code></div>
            <div class="tpa-login-chip"><strong>Username</strong> <code>Employee ID</code></div>
            <div class="tpa-login-chip"><strong>Default Password</strong> <code><?php echo htmlspecialchars($defaultPass); ?></code></div>
        </div>
    </div>
    <div class="tpa-hero-actions">
        <form method="POST" onsubmit="return confirm('Enable portal for ALL active teachers with default password?');">
            <input type="hidden" name="enable_all_portal" value="1">
            <button type="submit" class="btn-header-action btn-header-outline"><i class="fas fa-users-cog"></i> Enable All Active</button>
        </form>
        <a href="../teacher/" target="_blank" class="btn-header-action btn-header-primary"><i class="fas fa-external-link-alt"></i> Preview Portal</a>
    </div>
</div>

<div class="tpa-steps">
    <div class="tpa-step">
        <div class="tpa-step-num">1</div>
        <div><strong>Search teacher</strong><span>Find by name, employee ID, subject, or mobile number</span></div>
    </div>
    <div class="tpa-step">
        <div class="tpa-step-num">2</div>
        <div><strong>Enable access</strong><span>Set a custom password or leave empty for default <?php echo htmlspecialchars($defaultPass); ?></span></div>
    </div>
    <div class="tpa-step">
        <div class="tpa-step-num">3</div>
        <div><strong>Share credentials</strong><span>Teacher logs in at /teacher/ and can change password on first visit</span></div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-search"></i></div>
        <div>
            <h4>Find &amp; Enable Teacher</h4>
            <p>Search any teacher to grant or reset portal access</p>
        </div>
    </div>
    <form method="GET" class="category-add-row">
        <div class="form-field form-field-grow">
            <label>Search teacher</label>
            <input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, employee ID, subject, mobile..." autofocus>
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <?php if ($search !== ''): ?>
    <div class="erp-search-results teacher-search-results">
        <?php if ($results): foreach ($results as $r): ?>
        <form method="POST" class="erp-search-item teacher-search-card teacher-portal-card">
            <input type="hidden" name="enable_portal" value="1">
            <input type="hidden" name="teacher_id" value="<?php echo $r['id']; ?>">
            <div class="teacher-search-main">
                <div class="teacher-search-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="teacher-search-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <span><?php echo htmlspecialchars($r['employee_id']); ?><?php if (!empty($r['phone'])): ?> · <?php echo htmlspecialchars($r['phone']); ?><?php endif; ?></span>
                    <div class="teacher-search-meta">
                        <span class="teacher-search-subject-pill"><i class="fas fa-book"></i> <?php echo htmlspecialchars($r['subject'] ?: 'No subject'); ?></span>
                        <span class="status-badge <?php echo ($r['status'] ?? '') === 'Active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo htmlspecialchars($r['status'] ?? 'Unknown'); ?></span>
                        <?php if (!empty($r['portal_enabled'])): ?><span class="status-badge badge-active">Portal On</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="teacher-search-actions">
                <span class="teacher-search-actions-label">Portal password</span>
                <input type="text" name="password" class="form-input teacher-portal-pass" placeholder="Default if empty">
                <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-key"></i> <?php echo !empty($r['portal_enabled']) ? 'Reset' : 'Enable'; ?></button>
            </div>
        </form>
        <?php endforeach; else: ?>
        <div class="tab-empty-state tab-empty-pad-sm">
            <div class="tab-empty-icon"><i class="fas fa-search"></i></div>
            <h3>No teachers found</h3>
            <p>Try a different name, employee ID, or subject</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($search === '' && $pendingPreview): ?>
<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-user-clock"></i></div>
        <div>
            <h4>Quick Enable — Pending Access (<?php echo $pendingCount; ?>)</h4>
            <p class="tpa-section-hint">Active teachers without portal access — enable individually below</p>
        </div>
    </div>
    <div class="tpa-pending-stack">
        <?php foreach ($pendingPreview as $r): ?>
        <form method="POST" class="erp-search-item teacher-search-card teacher-portal-card">
            <input type="hidden" name="enable_portal" value="1">
            <input type="hidden" name="teacher_id" value="<?php echo $r['id']; ?>">
            <div class="teacher-search-main">
                <div class="teacher-search-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="teacher-search-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <span><?php echo htmlspecialchars($r['employee_id']); ?></span>
                    <div class="teacher-search-meta">
                        <span class="teacher-search-subject-pill"><i class="fas fa-book"></i> <?php echo htmlspecialchars($r['subject'] ?: 'No subject'); ?></span>
                        <span class="status-badge badge-inactive">No Portal</span>
                    </div>
                </div>
            </div>
            <div class="teacher-search-actions">
                <span class="teacher-search-actions-label">Portal password</span>
                <input type="text" name="password" class="form-input teacher-portal-pass" placeholder="Default if empty">
                <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-unlock"></i> Enable</button>
            </div>
        </form>
        <?php endforeach; ?>
        <?php if ($pendingCount > count($pendingPreview)): ?>
        <div class="tpa-more-hint">
            <i class="fas fa-info-circle"></i>
            Showing <?php echo count($pendingPreview); ?> of <?php echo $pendingCount; ?> pending teachers — use search above to find others, or click <strong>Enable All Active</strong>.
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-user-shield"></i></div>
        <div>
            <h4>Portal Enabled Teachers (<?php echo $enabledCount; ?>)</h4>
            <p class="tpa-section-hint">Teachers who can currently access the portal</p>
        </div>
    </div>
    <?php if ($enabled): ?>
    <div class="tpa-enabled-grid">
        <?php foreach ($enabled as $e): ?>
        <div class="tpa-enabled-card">
            <div class="tpa-enabled-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="tpa-enabled-info">
                <strong><?php echo htmlspecialchars($e['name']); ?></strong>
                <span><?php echo htmlspecialchars($e['employee_id']); ?> · <?php echo htmlspecialchars($e['subject'] ?: '—'); ?></span>
            </div>
            <div class="tpa-enabled-meta">
                <span class="status-badge badge-active">Active</span>
                <a href="teacher_view.php?id=<?php echo (int) $e['id']; ?>" class="tpa-view-link">View profile <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="tab-empty-state tab-empty-pad-sm">
        <div class="tab-empty-icon"><i class="fas fa-user-lock"></i></div>
        <h3>No portal access yet</h3>
        <p>Search a teacher above or use <strong>Enable All Active</strong> to get started</p>
    </div>
    <?php endif; ?>
</div>

<div class="notify-info-banner section-mb">
    <div class="notify-info-icon"><i class="fas fa-lightbulb"></i></div>
    <div class="notify-info-text">
        <strong>Tip:</strong> Active teachers can also self-login at <code>/teacher/</code> with Employee ID + default password — portal auto-enables on first login.<br>
        After login, teachers can view timetable, mark attendance, post homework from <a href="homework.php" class="teal-link">Homework module</a>, and update their profile.
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
