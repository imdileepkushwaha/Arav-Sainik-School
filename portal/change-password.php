<?php
$page_title = 'Change Password';
require_once 'includes/init.php';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = updateStudentPortalPassword($pdo, (int) $_SESSION['student_portal_id'], $_POST['current'] ?? '', $_POST['new_password'] ?? '');
    if ($result === true) {
        $_SESSION['success_msg'] = 'Password changed successfully.';
        header('Location: dashboard.php');
        exit;
    }
    $error = is_string($result) ? $result : 'Could not update password.';
}

require_once 'includes/layout_header.php';
?>
<div class="sp-card sp-form">
    <div class="sp-card-head"><h3><i class="fas fa-lock"></i> Change Password</h3></div>
    <?php if ($error): ?><div class="sp-alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="POST">
        <div class="sp-field">
            <label for="current">Current Password</label>
            <div class="sp-login-input">
                <span class="sp-login-input-icon"><i class="fas fa-lock"></i></span>
                <input type="password" id="current" name="current" required placeholder="Enter current password">
            </div>
        </div>
        <div class="sp-field">
            <label for="new_password">New Password</label>
            <div class="sp-login-input sp-login-input-password">
                <span class="sp-login-input-icon"><i class="fas fa-key"></i></span>
                <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="At least 6 characters">
                <button type="button" class="sp-login-eye" id="spPwToggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
            </div>
        </div>
        <button type="submit" class="sp-btn sp-login-submit"><span>Update Password</span><i class="fas fa-check"></i></button>
    </form>

    <div class="sp-pass-tips">
        <strong><i class="fas fa-lightbulb"></i> Tips for a strong password</strong>
        <ul>
            <li>Use at least 6 characters (longer is better).</li>
            <li>Mix letters, numbers and a symbol.</li>
            <li>Don't reuse your admission number or birthday.</li>
        </ul>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('spPwToggle');
    var input = document.getElementById('new_password');
    if (btn && input) {
        btn.addEventListener('click', function () {
            var icon = btn.querySelector('i');
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            if (icon) { icon.classList.toggle('fa-eye', !show); icon.classList.toggle('fa-eye-slash', show); }
        });
    }
})();
</script>
<?php require_once 'includes/layout_footer.php'; ?>
