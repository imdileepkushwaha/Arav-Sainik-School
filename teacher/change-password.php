<?php
$page_title = 'Change Password';
$page_subtitle = 'Update your portal login password';
require_once 'includes/init.php';

$required = isset($_GET['required']) || $tp_must_change;
$message = '';
$error = '';
$defaultPass = getTeacherPortalDefaultPassword();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $teacher['portal_password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new === $defaultPass) {
        $error = 'Please choose a different password than the default.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        updateTeacherPortalPassword($pdo, $teacherId, $new);
        $teacher = getTeacherById($pdo, $teacherId);
        $tp_must_change = false;
        if ($required) {
            header('Location: dashboard.php?pwd_changed=1');
            exit;
        }
        $message = 'Password updated successfully.';
    }
}

require_once 'includes/layout_header.php';
?>

<?php if ($required && !$message): ?>
<div class="tp-alert-warning">
    <i class="fas fa-shield-halved"></i>
    <div>
        <strong>Please change your default password</strong>
        <p>For security, set a new password before using the portal. Current default: <code><?php echo htmlspecialchars($defaultPass); ?></code></p>
    </div>
</div>
<?php endif; ?>

<div class="tp-tt-hero is-slate">
    <div>
        <h2><i class="fas fa-shield-halved"></i> <?php echo $required ? 'Secure Your Account' : 'Change Password'; ?></h2>
        <p><?php echo $required ? 'Set a new password to continue using the teacher portal.' : 'Update your portal login password to keep your account secure.'; ?></p>
        <div class="tp-tt-hero-chips">
            <span class="tp-tt-hero-chip"><i class="fas fa-id-badge"></i> <?php echo $tp_emp_id; ?></span>
            <span class="tp-tt-hero-chip"><i class="fas fa-user"></i> <?php echo $tp_name; ?></span>
            <?php if ($required): ?><span class="tp-tt-hero-chip"><i class="fas fa-exclamation-triangle"></i> Required</span><?php endif; ?>
        </div>
    </div>
    <div class="tp-tt-hero-actions">
        <a href="profile.php" class="tp-tt-hero-btn"><i class="fas fa-user"></i> Profile</a>
        <a href="#pwdForm" class="tp-tt-hero-btn is-solid"><i class="fas fa-lock"></i> Update Below</a>
    </div>
</div>

<div class="tp-pwd-layout">
    <div class="tp-card tp-pwd-card" id="pwdForm">
        <div class="tp-pwd-icon-wrap"><i class="fas fa-lock"></i></div>
        <div class="tp-card-head" style="border-bottom:none;padding-bottom:0;margin-bottom:8px">
            <h3><?php echo $required ? 'Set New Password' : 'Update Password'; ?></h3>
        </div>
        <p style="margin:0 0 20px;font-size:0.88rem;color:var(--tp-muted)">Choose a strong password that you haven't used elsewhere.</p>

        <?php if ($message): ?><div class="tp-alert-success" style="margin-bottom:16px"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="tp-alert-error" style="margin-bottom:16px"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST" class="tp-form-grid" style="grid-template-columns:1fr">
            <div class="tp-field">
                <label><i class="fas fa-key" style="color:#2563eb;margin-right:4px"></i> Current Password</label>
                <input type="password" name="current_password" placeholder="<?php echo $required ? 'Enter default: ' . htmlspecialchars($defaultPass) : 'Enter current password'; ?>" required autocomplete="current-password">
            </div>
            <div class="tp-field">
                <label><i class="fas fa-lock" style="color:#2563eb;margin-right:4px"></i> New Password</label>
                <input type="password" name="new_password" id="newPwd" minlength="6" placeholder="At least 6 characters" required autocomplete="new-password">
            </div>
            <div class="tp-field">
                <label><i class="fas fa-check-double" style="color:#2563eb;margin-right:4px"></i> Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirmPwd" minlength="6" placeholder="Re-enter new password" required autocomplete="new-password">
            </div>
            <div>
                <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-save"></i> <?php echo $required ? 'Save &amp; Continue' : 'Update Password'; ?></button>
            </div>
        </form>
    </div>

    <div class="tp-card tp-pwd-tips">
        <h4><i class="fas fa-shield-halved"></i> Password Tips</h4>
        <ul class="tp-pwd-tip-list">
            <li><i class="fas fa-check-circle"></i> Use at least 6 characters — longer is better</li>
            <li><i class="fas fa-check-circle"></i> Mix letters, numbers, and symbols</li>
            <li><i class="fas fa-check-circle"></i> Don't reuse the default password <code><?php echo htmlspecialchars($defaultPass); ?></code></li>
            <li><i class="fas fa-check-circle"></i> Never share your password with anyone</li>
            <li><i class="fas fa-check-circle"></i> Change your password if you suspect it's been compromised</li>
        </ul>
        <div class="tp-profile-note" style="margin-top:18px;margin-bottom:0">
            <i class="fas fa-user-shield"></i>
            Logged in as <strong><?php echo $tp_emp_id; ?></strong>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_footer.php'; ?>
