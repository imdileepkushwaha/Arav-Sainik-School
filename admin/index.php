<?php
// admin/index.php (Login Page)
session_start();
require_once '../includes/db_connection.php';

$dbConnection = connectDatabase(['soft_fail' => true]);
$pdo = $dbConnection['pdo'] ?? null;
$db_active_profile = $dbConnection['profile'] ?? '';
$db_connection_mode = $dbConnection['mode'] ?? 'auto';
$db_connection_error = $dbConnection['error'] ?? '';
$db_environment = $dbConnection['environment'] ?? (isLocalEnvironment() ? 'local' : 'server');

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$setupMessage = '';
$old_username = '';
$dbInstalled = false;

require_once 'includes/db_install_helpers.php';

if ($pdo) {
    $dbInstalled = isDatabaseInstalled($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'db_setup') {
    $setupResult = runDatabaseSetup();
    if (!empty($setupResult['ok'])) {
        $setupMessage = $setupResult['message'] ?? 'Database setup complete.';
        if (empty($setupResult['already_installed']) && !empty($setupResult['default_login'])) {
            $setupMessage .= ' Default login: admin / admin123';
        }
        $dbConnection = connectDatabase(['soft_fail' => true]);
        $pdo = $dbConnection['pdo'] ?? null;
        $db_active_profile = $dbConnection['profile'] ?? '';
        if ($pdo) {
            $dbInstalled = isDatabaseInstalled($pdo);
        }
    } else {
        $error = $setupResult['error'] ?? 'Database setup failed.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $old_username = $username;

    if (!$pdo) {
        $error = 'Database is not connected. Use Setup Database first.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (!$dbInstalled) {
        $error = 'Database tables are not installed yet. Click Setup Database first.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, username, password FROM admin_users WHERE username = :username');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                header('Location: dashboard.php');
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (PDOException $e) {
            $error = 'Database error. Please run Setup Database or check MySQL.';
        }
    }
}

$loginSchool = ['name' => 'EduDash', 'logo' => '', 'favicon' => ''];
$loginLogoUrl = '';
$loginFaviconUrl = '';
$loginSchoolName = 'EduDash';

if ($pdo) {
    require_once 'includes/settings_helpers.php';
    try {
        ensureSettingsSchema($pdo);
        $loginSchool = getSchoolProfile($pdo);
        $loginLogoUrl = schoolBrandingUrl($loginSchool['logo'] ?? '', 'admin');
        $loginFaviconUrl = schoolBrandingUrl($loginSchool['favicon'] ?? '', 'admin');
        $loginSchoolName = $loginSchool['name'] ?: 'EduDash';
    } catch (Throwable $e) {
    }
}

$dbStatusLabel = $pdo
    ? (($db_active_profile === 'online' ? 'Online' : 'Offline') . ' DB')
    : 'Not Connected';
$dbSetupTarget = getSetupProfileKey() === 'online' ? 'Online (Server)' : 'Local (XAMPP)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?php echo htmlspecialchars($loginSchoolName); ?></title>
    <?php if ($loginFaviconUrl): ?><link rel="icon" href="<?php echo htmlspecialchars($loginFaviconUrl); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">

    <div class="login-bg">
        <span class="login-orb login-orb-1"></span>
        <span class="login-orb login-orb-2"></span>
        <span class="login-orb login-orb-3"></span>
    </div>

    <div class="login-shell">
        <div class="login-container">
            <div class="login-brand-panel">
                <div class="login-brand-content">
                    <div class="login-brand-logo">
                        <div class="login-logo-icon<?php echo $loginLogoUrl ? ' has-logo' : ''; ?>">
                            <?php if ($loginLogoUrl): ?>
                            <img src="<?php echo htmlspecialchars($loginLogoUrl); ?>" alt="<?php echo htmlspecialchars($loginSchoolName); ?>">
                            <?php else: ?>
                            <i class="fas fa-graduation-cap"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="login-logo-tag"><?php echo htmlspecialchars($loginSchoolName); ?></span>
                            <h1>Admin Panel</h1>
                        </div>
                    </div>
                    <p class="login-brand-tagline">Complete school management — students, teachers, fees, attendance, exams &amp; reports in one secure dashboard.</p>

                    <div class="login-feature-grid">
                        <div class="login-feature-card">
                            <div class="login-feature-icon"><i class="fas fa-chart-line"></i></div>
                            <div><strong>Live Dashboard</strong><span>Analytics &amp; insights</span></div>
                        </div>
                        <div class="login-feature-card">
                            <div class="login-feature-icon"><i class="fas fa-user-graduate"></i></div>
                            <div><strong>Student ERP</strong><span>Admissions to alumni</span></div>
                        </div>
                        <div class="login-feature-card">
                            <div class="login-feature-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                            <div><strong>Staff &amp; Teachers</strong><span>Profiles &amp; timetable</span></div>
                        </div>
                        <div class="login-feature-card">
                            <div class="login-feature-icon"><i class="fas fa-database"></i></div>
                            <div><strong>Smart DB</strong><span>Local &amp; server auto-switch</span></div>
                        </div>
                    </div>
                </div>
                <div class="login-brand-footer">
                    <i class="fas fa-lock"></i> Authorized administrators only
                </div>
            </div>

            <div class="login-form-panel">
                <div class="login-box">
                    <div class="login-header">
                        <div class="login-header-badge"><i class="fas fa-user-shield"></i></div>
                        <h2>Welcome back</h2>
                        <p>Sign in to manage your school</p>
                    </div>

                    <p class="login-meta-line login-meta-line--<?php echo $pdo ? ($db_active_profile === 'online' ? 'online' : 'offline') : 'error'; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo htmlspecialchars($dbStatusLabel); ?>
                        <span class="login-meta-sep">·</span>
                        <?php echo $db_environment === 'local' ? 'Local (XAMPP)' : 'Live Server'; ?>
                        <span class="login-meta-sep">·</span>
                        Mode <?php echo htmlspecialchars(strtoupper($db_connection_mode)); ?>
                    </p>

                    <?php if (!empty($setupMessage)): ?>
                    <div class="alert alert-success login-alert" id="setupSuccessAlert">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($setupMessage); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger login-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!$pdo): ?>
                    <div class="alert alert-warning login-alert login-db-hint">
                        <i class="fas fa-plug-circle-exclamation"></i>
                        <span>MySQL not connected. Start XAMPP MySQL or verify online credentials, then run database setup below.</span>
                    </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="login-form">
                        <div class="admin-login-field">
                            <label for="username">Username</label>
                            <div class="admin-login-input">
                                <span class="admin-login-input-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                                <input type="text" id="username" name="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($old_username); ?>" autocomplete="username" required autofocus>
                            </div>
                        </div>
                        <div class="admin-login-field">
                            <label for="password">Password</label>
                            <div class="admin-login-input admin-login-input-password">
                                <span class="admin-login-input-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                                <button type="button" class="admin-login-eye" id="passwordToggle" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn-admin btn-login">
                            <span>Sign in to admin</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <?php if (!$dbInstalled): ?>
                    <div class="login-divider" aria-hidden="true"><span>First time setup</span></div>

                    <section class="login-install-card" id="loginInstallCard">
                        <div class="login-install-head">
                            <span class="login-install-icon" aria-hidden="true"><i class="fas fa-database"></i></span>
                            <div class="login-install-copy">
                                <h3>Database Setup</h3>
                                <p>Install database and all ERP tables on <strong><?php echo htmlspecialchars($dbSetupTarget); ?></strong>.</p>
                            </div>
                        </div>
                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="login-install-form" id="dbSetupForm">
                            <input type="hidden" name="action" value="db_setup">
                            <button type="submit" class="login-install-btn" id="dbSetupBtn">
                                <i class="fas fa-wrench"></i>
                                <span>Run Database Setup</span>
                            </button>
                        </form>
                        <p class="login-install-note">Default login after setup: <code>admin</code> / <code>admin123</code></p>
                    </section>
                    <?php endif; ?>

                    <div class="login-portal-links">
                        <a href="../teacher/" class="login-portal-link"><i class="fas fa-chalkboard-teacher"></i> Teacher Portal</a>
                        <a href="../portal/" class="login-portal-link"><i class="fas fa-user-graduate"></i> Student Portal</a>
                    </div>

                    <div class="login-footer">
                        <a href="../index.php" class="back-link">
                            <i class="fas fa-arrow-left"></i> Back to Website
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <p class="login-copyright">&copy; <?php echo date('Y'); ?> EduDash School ERP</p>
    </div>

    <script>
        document.getElementById('passwordToggle').addEventListener('click', function () {
            var input = document.getElementById('password');
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

        var dbSetupForm = document.getElementById('dbSetupForm');
        if (dbSetupForm) {
            dbSetupForm.addEventListener('submit', function () {
                var btn = document.getElementById('dbSetupBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Installing tables…</span>';
            });
        }
    </script>
</body>
</html>
