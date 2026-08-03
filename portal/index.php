<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../admin/includes/erp_helpers.php';
require_once __DIR__ . '/../admin/includes/settings_helpers.php';

if (isset($_SESSION['student_portal_id'])) {
    header('Location: dashboard.php');
    exit;
}

ensureSettingsSchema($pdo);
$school = getSchoolProfile($pdo);
$sp_login_logo = schoolBrandingUrl($school['logo'] ?? '', 'portal');
$sp_login_favicon = schoolBrandingUrl($school['favicon'] ?? '', 'portal');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureErpSchema($pdo);
    $student = authenticateStudentPortal($pdo, $_POST['ad_no'] ?? '', $_POST['password'] ?? '');
    if ($student) {
        $_SESSION['student_portal_id'] = $student['id'];
        $_SESSION['student_portal_name'] = $student['name'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid admission number or password.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login — <?php echo htmlspecialchars($school['name']); ?></title>
    <?php if ($sp_login_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($sp_login_favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/student-portal.css">
</head>
<body class="sp-login-page">
    <div class="sp-login-bg">
        <span class="sp-login-orb sp-login-orb-1"></span>
        <span class="sp-login-orb sp-login-orb-2"></span>
        <span class="sp-login-orb sp-login-orb-3"></span>
    </div>

    <div class="sp-login-shell">
        <div class="sp-login-wrap">
            <div class="sp-login-brand">
                <div class="sp-login-brand-inner">
                    <div class="sp-login-logo">
                        <div class="sp-login-logo-icon<?php echo $sp_login_logo ? ' has-logo' : ''; ?>">
                            <?php if ($sp_login_logo): ?><img src="<?php echo htmlspecialchars($sp_login_logo); ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
                        </div>
                        <div>
                            <span class="sp-login-logo-tag"><?php echo htmlspecialchars($school['name']); ?></span>
                            <h1>Student Portal</h1>
                        </div>
                    </div>
                    <p class="sp-login-tagline">Your learning hub — attendance, homework, fees, results and notices in one secure place.</p>

                    <div class="sp-login-features">
                        <div class="sp-login-feature">
                            <div class="sp-login-feature-icon"><i class="fas fa-user-check"></i></div>
                            <div>
                                <strong>My Attendance</strong>
                                <span>Track your daily record</span>
                            </div>
                        </div>
                        <div class="sp-login-feature">
                            <div class="sp-login-feature-icon"><i class="fas fa-book-open"></i></div>
                            <div>
                                <strong>Homework</strong>
                                <span>Tasks from your teachers</span>
                            </div>
                        </div>
                        <div class="sp-login-feature">
                            <div class="sp-login-feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div>
                                <strong>Fees &amp; Payments</strong>
                                <span>Check dues &amp; history</span>
                            </div>
                        </div>
                        <div class="sp-login-feature">
                            <div class="sp-login-feature-icon"><i class="fas fa-award"></i></div>
                            <div>
                                <strong>Exam Results</strong>
                                <span>See your report cards</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="sp-login-brand-footer">
                    <i class="fas fa-shield-halved"></i> Secure login · Admission number required
                </div>
            </div>

            <div class="sp-login-form-panel">
                <div class="sp-login-form">
                    <div class="sp-login-form-head">
                        <div class="sp-login-form-badge"><i class="fas fa-user-graduate"></i></div>
                        <h2>Welcome back</h2>
                        <p>Sign in to continue to your dashboard</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="sp-alert-error sp-login-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="sp-login-form-fields">
                        <div class="sp-login-field">
                            <label for="ad_no">Admission Number</label>
                            <div class="sp-login-input">
                                <span class="sp-login-input-icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                                <input type="text" id="ad_no" name="ad_no" placeholder="e.g. ADM1024" required autofocus autocomplete="username">
                            </div>
                        </div>
                        <div class="sp-login-field">
                            <label for="password">Password</label>
                            <div class="sp-login-input sp-login-input-password">
                                <span class="sp-login-input-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="sp-login-eye" id="spPasswordToggle" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="sp-btn sp-login-submit">
                            <span>Login to Portal</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="sp-login-help">
                        <div class="sp-login-help-icon"><i class="fas fa-circle-info"></i></div>
                        <div>
                            <strong>First time logging in?</strong>
                            <p>Your admission number and password are provided by the school office. Forgot your password? Admin can reset access from <em>Admin → Portal Accounts</em>.</p>
                        </div>
                    </div>

                    <div class="sp-login-switch">
                        <a href="../teacher/index.php"><i class="fas fa-chalkboard-teacher"></i> Teacher</a>
                        <a href="../admin/index.php"><i class="fas fa-user-shield"></i> Admin</a>
                        <a href="../index.php"><i class="fas fa-house"></i> Home</a>
                    </div>
                </div>
            </div>
        </div>

        <p class="sp-login-copyright">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school['name']); ?> · Student Portal</p>
    </div>

    <script>
    (function () {
        var btn = document.getElementById('spPasswordToggle');
        var input = document.getElementById('password');
        if (btn && input) {
            btn.addEventListener('click', function () {
                var icon = btn.querySelector('i');
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                if (icon) {
                    icon.classList.toggle('fa-eye', !show);
                    icon.classList.toggle('fa-eye-slash', show);
                }
            });
        }
    })();
    </script>
</body></html>
