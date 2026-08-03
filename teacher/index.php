<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../admin/includes/teacher_helpers.php';
require_once __DIR__ . '/../admin/includes/settings_helpers.php';

ensureTeacherSchema($pdo);
ensureTeacherPortalRepair($pdo);
ensureSettingsSchema($pdo);
$tp_login_school = getSchoolProfile($pdo);
$tp_login_logo = schoolBrandingUrl($tp_login_school['logo'] ?? '', 'teacher');
$tp_login_favicon = schoolBrandingUrl($tp_login_school['favicon'] ?? '', 'teacher');

if (isset($_SESSION['teacher_portal_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = getTeacherLoginStatus($pdo, $_POST['employee_id'] ?? '', $_POST['password'] ?? '');
    if ($login['ok']) {
        $_SESSION['teacher_portal_id'] = $login['teacher']['id'];
        $_SESSION['teacher_portal_name'] = $login['teacher']['name'];
        $_SESSION['teacher_portal_emp'] = $login['teacher']['employee_id'];
        if (teacherMustChangePassword($login['teacher'])) {
            header('Location: change-password.php?required=1');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    }
    $error = teacherLoginErrorMessage($login['reason']);
    $old_employee_id = trim($_POST['employee_id'] ?? '');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login — <?php echo htmlspecialchars($tp_login_school['name']); ?></title>
    <?php if ($tp_login_favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($tp_login_favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/teacher-portal.css">
</head>
<body class="tp-login-page">
    <div class="tp-login-bg">
        <span class="tp-login-orb tp-login-orb-1"></span>
        <span class="tp-login-orb tp-login-orb-2"></span>
        <span class="tp-login-orb tp-login-orb-3"></span>
    </div>

    <div class="tp-login-shell">
        <div class="tp-login-wrap">
            <div class="tp-login-brand">
                <div class="tp-login-brand-inner">
                    <div class="tp-login-logo">
                        <div class="tp-login-logo-icon<?php echo $tp_login_logo ? ' has-logo' : ''; ?>">
                            <?php if ($tp_login_logo): ?><img src="<?php echo htmlspecialchars($tp_login_logo); ?>" alt="Logo"><?php else: ?><i class="fas fa-chalkboard-teacher"></i><?php endif; ?>
                        </div>
                        <div>
                            <span class="tp-login-logo-tag"><?php echo htmlspecialchars($tp_login_school['name']); ?></span>
                            <h1>Teacher Portal</h1>
                        </div>
                    </div>
                    <p class="tp-login-tagline">Your classroom hub — timetable, attendance, homework, and profile in one secure place.</p>

                    <div class="tp-login-features">
                        <div class="tp-login-feature">
                            <div class="tp-login-feature-icon"><i class="fas fa-calendar-week"></i></div>
                            <div>
                                <strong>Weekly Timetable</strong>
                                <span>View periods &amp; rooms</span>
                            </div>
                        </div>
                        <div class="tp-login-feature">
                            <div class="tp-login-feature-icon"><i class="far fa-calendar-check"></i></div>
                            <div>
                                <strong>Mark Attendance</strong>
                                <span>For your assigned classes</span>
                            </div>
                        </div>
                        <div class="tp-login-feature">
                            <div class="tp-login-feature-icon"><i class="fas fa-book-open"></i></div>
                            <div>
                                <strong>Post Homework</strong>
                                <span>Share tasks with students</span>
                            </div>
                        </div>
                        <div class="tp-login-feature">
                            <div class="tp-login-feature-icon"><i class="fas fa-user-shield"></i></div>
                            <div>
                                <strong>Secure Profile</strong>
                                <span>Update password anytime</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tp-login-brand-footer">
                    <i class="fas fa-shield-halved"></i> Secure login · Employee ID required
                </div>
            </div>

            <div class="tp-login-form-panel">
                <div class="tp-login-form">
                    <div class="tp-login-form-head">
                        <div class="tp-login-form-badge"><i class="fas fa-sign-in-alt"></i></div>
                        <h2>Welcome back</h2>
                        <p>Sign in to continue to your dashboard</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="tp-alert-error tp-login-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="tp-login-form-fields">
                        <div class="tp-login-field">
                            <label for="employee_id">Employee ID</label>
                            <div class="tp-login-input">
                                <span class="tp-login-input-icon" aria-hidden="true"><i class="fas fa-id-badge"></i></span>
                                <input type="text" id="employee_id" name="employee_id" placeholder="e.g. EMP20250001" value="<?php echo htmlspecialchars($old_employee_id ?? ''); ?>" required autofocus autocomplete="username">
                            </div>
                        </div>
                        <div class="tp-login-field">
                            <label for="password">Password</label>
                            <div class="tp-login-input tp-login-input-password">
                                <span class="tp-login-input-icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                                <button type="button" class="tp-login-eye" id="tpPasswordToggle" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="tp-btn tp-btn-primary tp-login-submit">
                            <span>Login to Portal</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="tp-login-help">
                        <div class="tp-login-help-icon"><i class="fas fa-circle-info"></i></div>
                        <div>
                            <strong>First time logging in?</strong>
                            <p>Default password: <strong><?php echo htmlspecialchars(getTeacherPortalDefaultPassword()); ?></strong> — you will be asked to change it after login. Admin can reset access from <em>Admin → Teachers → Teacher Portal</em>.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="tp-login-copyright">&copy; <?php echo date('Y'); ?> EduDash School ERP</p>
    </div>

    <script>
    (function () {
        var btn = document.getElementById('tpPasswordToggle');
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
</body>
</html>
