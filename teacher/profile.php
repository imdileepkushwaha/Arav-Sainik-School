<?php
$page_title = 'My Profile';
$page_subtitle = 'Your personal & professional information';
require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_signature') {
    $removeCurrent = function () use ($teacher) {
        if (!empty($teacher['signature'])) {
            $full = __DIR__ . '/../admin/' . ltrim($teacher['signature'], '/');
            if (is_file($full)) {
                @unlink($full);
            }
        }
    };
    if (!empty($_POST['remove_signature'])) {
        $removeCurrent();
        $pdo->prepare("UPDATE teachers SET signature = NULL WHERE id = ?")->execute([$teacherId]);
        tp_flash('profile.php', 'Signature removed.');
    }
    $uploaded = uploadTeacherSignature($_FILES['signature'] ?? [], $teacher['employee_id']);
    if ($uploaded === false) {
        tp_flash('profile.php', 'Signature upload failed. Use PNG, JPG or WEBP (max 2MB).', 'error');
    } elseif ($uploaded) {
        $removeCurrent();
        $pdo->prepare("UPDATE teachers SET signature = ? WHERE id = ?")->execute([$uploaded, $teacherId]);
        tp_flash('profile.php', 'Signature updated successfully.');
    } else {
        tp_flash('profile.php', 'Please choose a signature image to upload.', 'error');
    }
}

$tp_sign_url = schoolBrandingUrl($teacher['signature'] ?? '', 'teacher');
require_once 'includes/layout_header.php';

$class_assigned = $teacher['class_assigned']
    ? htmlspecialchars($teacher['class_assigned'] . ' (' . ($teacher['section_assigned'] ?: 'A') . ')')
    : 'Not assigned';
$is_active = strcasecmp(trim((string) ($teacher['status'] ?? '')), 'Active') === 0;
$dob_display = $teacher['dob'] ? date('d M Y', strtotime($teacher['dob'])) : '—';
$join_display = $teacher['join_date'] ? date('d M Y', strtotime($teacher['join_date'])) : '—';
$full_address = trim(implode(', ', array_filter([
    trim($teacher['address'] ?? ''),
    trim($teacher['city'] ?? ''),
    trim($teacher['state'] ?? ''),
    trim($teacher['pincode'] ?? ''),
])));
?>

<div class="tp-profile-hero">
    <div class="tp-profile-cover"></div>
    <div class="tp-profile-hero-inner">
        <div class="tp-profile-hero-card">
            <div class="tp-profile-photo-ring">
                <img src="<?php echo htmlspecialchars($tp_photo); ?>" alt="<?php echo $tp_name; ?>">
            </div>
            <div class="tp-profile-identity">
                <div class="tp-profile-badges">
                    <span class="tp-profile-badge <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>">
                        <i class="fas fa-circle"></i> <?php echo htmlspecialchars($teacher['status']); ?>
                    </span>
                    <span class="tp-profile-badge is-subject"><i class="fas fa-book"></i> <?php echo htmlspecialchars($teacher['subject']); ?></span>
                </div>
                <h2><?php echo $tp_name; ?></h2>
                <p class="tp-profile-role"><?php echo htmlspecialchars($teacher['subject']); ?> Teacher</p>
                <p class="tp-profile-id"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($teacher['employee_id']); ?></p>
            </div>
            <div class="tp-profile-hero-actions">
                <a href="change-password.php" class="tp-btn tp-btn-outline"><i class="fas fa-lock"></i> Change Password</a>
                <a href="timetable.php" class="tp-btn tp-btn-primary"><i class="fas fa-calendar-alt"></i> My Timetable</a>
            </div>
        </div>
    </div>
</div>

<div class="tp-profile-stat-row">
    <div class="tp-profile-stat">
        <div class="tp-profile-stat-icon blue"><i class="fas fa-chalkboard"></i></div>
        <div><span>Class Teacher</span><strong><?php echo $class_assigned; ?></strong></div>
    </div>
    <div class="tp-profile-stat">
        <div class="tp-profile-stat-icon green"><i class="fas fa-graduation-cap"></i></div>
        <div><span>Qualification</span><strong><?php echo displayVal($teacher['qualification']); ?></strong></div>
    </div>
    <div class="tp-profile-stat">
        <div class="tp-profile-stat-icon orange"><i class="fas fa-briefcase"></i></div>
        <div><span>Experience</span><strong><?php echo displayVal($teacher['experience_years']); ?></strong></div>
    </div>
    <div class="tp-profile-stat">
        <div class="tp-profile-stat-icon purple"><i class="fas fa-calendar-check"></i></div>
        <div><span>Joined</span><strong><?php echo $join_display; ?></strong></div>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card tp-profile-section">
        <div class="tp-card-head">
            <h3><i class="fas fa-user"></i> Personal Details</h3>
        </div>
        <div class="tp-profile-fields">
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-user"></i></div>
                <div><label>Full Name</label><span><?php echo $tp_name; ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-venus-mars"></i></div>
                <div><label>Gender</label><span><?php echo displayVal($teacher['gender']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-birthday-cake"></i></div>
                <div><label>Date of Birth</label><span><?php echo $dob_display; ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-phone"></i></div>
                <div><label>Mobile</label><span><?php echo htmlspecialchars($teacher['phone']); ?></span></div>
            </div>
            <div class="tp-profile-field tp-profile-field-full">
                <div class="tp-profile-field-icon"><i class="fas fa-envelope"></i></div>
                <div><label>Email</label><span><?php echo displayVal($teacher['email']); ?></span></div>
            </div>
        </div>
    </div>

    <div class="tp-card tp-profile-section">
        <div class="tp-card-head">
            <h3><i class="fas fa-briefcase"></i> Professional</h3>
        </div>
        <div class="tp-profile-fields">
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-book-open"></i></div>
                <div><label>Primary Subject</label><span><?php echo htmlspecialchars($teacher['subject']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-graduation-cap"></i></div>
                <div><label>Qualification</label><span><?php echo displayVal($teacher['qualification']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-clock"></i></div>
                <div><label>Experience</label><span><?php echo displayVal($teacher['experience_years']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-users"></i></div>
                <div><label>Class Teacher</label><span><?php echo $class_assigned; ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-calendar-plus"></i></div>
                <div><label>Join Date</label><span><?php echo $join_display; ?></span></div>
            </div>
        </div>
    </div>
</div>

<div class="tp-grid-2">
    <div class="tp-card tp-profile-section">
        <div class="tp-card-head">
            <h3><i class="fas fa-map-marker-alt"></i> Address</h3>
        </div>
        <div class="tp-profile-fields">
            <div class="tp-profile-field tp-profile-field-full">
                <div class="tp-profile-field-icon"><i class="fas fa-home"></i></div>
                <div><label>Street Address</label><span><?php echo displayVal($teacher['address']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-city"></i></div>
                <div><label>City</label><span><?php echo displayVal($teacher['city']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-map"></i></div>
                <div><label>State</label><span><?php echo displayVal($teacher['state']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-mail-bulk"></i></div>
                <div><label>Pincode</label><span><?php echo displayVal($teacher['pincode']); ?></span></div>
            </div>
            <?php if ($full_address && $full_address !== '—'): ?>
            <div class="tp-profile-address-summary">
                <i class="fas fa-location-dot"></i>
                <span><?php echo htmlspecialchars($full_address); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tp-card tp-profile-section">
        <div class="tp-card-head">
            <h3><i class="fas fa-university"></i> Bank &amp; Salary</h3>
        </div>
        <div class="tp-profile-fields">
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-building-columns"></i></div>
                <div><label>Bank Name</label><span><?php echo displayVal($teacher['bank_name']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-credit-card"></i></div>
                <div><label>Account No.</label><span><?php echo displayVal($teacher['bank_account']); ?></span></div>
            </div>
            <div class="tp-profile-field">
                <div class="tp-profile-field-icon"><i class="fas fa-barcode"></i></div>
                <div><label>IFSC Code</label><span><?php echo displayVal($teacher['ifsc_code']); ?></span></div>
            </div>
            <div class="tp-profile-field tp-profile-salary">
                <div class="tp-profile-field-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                <div><label>Monthly Salary</label><span><?php echo $teacher['salary'] ? '₹ ' . number_format($teacher['salary'], 2) : '—'; ?></span></div>
            </div>
        </div>
        <div class="tp-profile-note">
            <i class="fas fa-shield-halved"></i>
            Bank details are confidential and visible only to you and admin.
        </div>
    </div>
</div>

<div class="tp-card tp-profile-section">
    <div class="tp-card-head">
        <h3><i class="fas fa-signature"></i> My Signature</h3>
    </div>
    <p class="tp-sign-desc">Upload your signature to appear on certificates and official documents for your class. Use a clear image on a white or transparent background.</p>
    <div class="tp-sign-wrap">
        <div class="tp-sign-preview<?php echo $tp_sign_url ? ' has-image' : ''; ?>">
            <?php if ($tp_sign_url): ?>
            <img src="<?php echo htmlspecialchars($tp_sign_url); ?>" alt="My signature">
            <?php else: ?>
            <span class="tp-sign-empty"><i class="fas fa-signature"></i> No signature uploaded</span>
            <?php endif; ?>
        </div>
        <form method="POST" enctype="multipart/form-data" class="tp-sign-form">
            <input type="hidden" name="action" value="upload_signature">
            <label class="tp-sign-file-label">
                <i class="fas fa-image"></i> <span>Choose signature image</span>
                <input type="file" name="signature" accept="image/png,image/jpeg,image/webp" required>
            </label>
            <span class="tp-sign-hint">PNG (transparent) recommended · JPG / WEBP · max 2MB</span>
            <div class="tp-sign-actions">
                <button type="submit" class="tp-btn tp-btn-primary"><i class="fas fa-upload"></i> <?php echo $tp_sign_url ? 'Replace' : 'Upload'; ?> Signature</button>
                <?php if ($tp_sign_url): ?>
                <button type="submit" name="remove_signature" value="1" class="tp-btn tp-btn-outline" onclick="return confirm('Remove your signature?');" formnovalidate><i class="fas fa-trash"></i> Remove</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($teacher['description']): ?>
<div class="tp-card tp-profile-section">
    <div class="tp-card-head">
        <h3><i class="fas fa-align-left"></i> Remarks</h3>
    </div>
    <div class="tp-profile-remarks">
        <?php echo nl2br(htmlspecialchars($teacher['description'])); ?>
    </div>
</div>
<?php endif; ?>

<div class="tp-profile-footer-note">
    <i class="fas fa-info-circle"></i>
    Profile details are managed by the school admin. Contact the office to request updates to your information.
</div>

<?php require_once 'includes/layout_footer.php'; ?>
