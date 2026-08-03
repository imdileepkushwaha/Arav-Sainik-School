<?php
$page_title = "Settings";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/settings_helpers.php';
require_once 'includes/db_settings_helpers.php';

ensureSettingsSchema($pdo);

$activeTab = $_GET['tab'] ?? 'school';
$allowedTabs = ['school', 'signatures', 'email', 'sms', 'whatsapp', 'password'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'school';
}

$smtp = getSmtpSettings($pdo);
$sms = getSmsSettings($pdo);
$whatsapp = getWhatsAppSettings($pdo);
$school = getSchoolProfile($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_school') {
        $profile = [
            'name' => $_POST['school_name'] ?? '',
            'tagline' => $_POST['school_tagline'] ?? '',
            'address' => $_POST['school_address'] ?? '',
            'phone' => $_POST['school_phone'] ?? '',
            'email' => $_POST['school_email'] ?? '',
            'website' => $_POST['school_website'] ?? '',
            'principal' => $_POST['school_principal'] ?? '',
            'affiliation' => $_POST['school_affiliation'] ?? '',
        ];
        $current = getSchoolProfile($pdo);
        $profile['logo'] = $current['logo'];
        $profile['logo_light'] = $current['logo_light'];
        $profile['logo_icon'] = $current['logo_icon'];
        $profile['favicon'] = $current['favicon'];

        if (!empty($_POST['remove_logo'])) {
            deleteSchoolBrandingFile($profile['logo']);
            $profile['logo'] = '';
        } elseif (!empty($_FILES['school_logo']['name'])) {
            $uploaded = uploadSchoolBrandingFile($_FILES['school_logo'], 'logo');
            if ($uploaded === false) {
                $_SESSION['error_msg'] = 'Logo upload failed. Use JPG, PNG, WEBP or GIF (max 2MB).';
                header('Location: settings.php?tab=school');
                exit;
            }
            if ($uploaded) {
                deleteSchoolBrandingFile($current['logo']);
                $profile['logo'] = $uploaded;
            }
        }

        if (!empty($_POST['remove_logo_light'])) {
            deleteSchoolBrandingFile($profile['logo_light']);
            $profile['logo_light'] = '';
        } elseif (!empty($_FILES['school_logo_light']['name'])) {
            $uploaded = uploadSchoolBrandingFile($_FILES['school_logo_light'], 'logo_light');
            if ($uploaded === false) {
                $_SESSION['error_msg'] = 'Light logo upload failed. Use JPG, PNG, WEBP or GIF (max 2MB).';
                header('Location: settings.php?tab=school');
                exit;
            }
            if ($uploaded) {
                deleteSchoolBrandingFile($current['logo_light']);
                $profile['logo_light'] = $uploaded;
            }
        }

        if (!empty($_POST['remove_logo_icon'])) {
            deleteSchoolBrandingFile($profile['logo_icon']);
            $profile['logo_icon'] = '';
        } elseif (!empty($_FILES['school_logo_icon']['name'])) {
            $uploaded = uploadSchoolBrandingFile($_FILES['school_logo_icon'], 'logo_icon');
            if ($uploaded === false) {
                $_SESSION['error_msg'] = 'Logo icon upload failed. Use ICO, PNG or JPG (max 512KB).';
                header('Location: settings.php?tab=school');
                exit;
            }
            if ($uploaded) {
                deleteSchoolBrandingFile($current['logo_icon']);
                $profile['logo_icon'] = $uploaded;
            }
        }

        if (!empty($_POST['remove_favicon'])) {
            deleteSchoolBrandingFile($profile['favicon']);
            $profile['favicon'] = '';
        } elseif (!empty($_FILES['school_favicon']['name'])) {
            $uploaded = uploadSchoolBrandingFile($_FILES['school_favicon'], 'favicon');
            if ($uploaded === false) {
                $_SESSION['error_msg'] = 'Favicon upload failed. Use ICO, PNG or JPG (max 512KB).';
                header('Location: settings.php?tab=school');
                exit;
            }
            if ($uploaded) {
                deleteSchoolBrandingFile($current['favicon']);
                $profile['favicon'] = $uploaded;
            }
        }

        saveSchoolProfile($pdo, $profile);
        $_SESSION['success_msg'] = 'School profile saved.';
        header('Location: settings.php?tab=school');
        exit;
    }

    if ($action === 'save_signature') {
        $sigId = (int) ($_POST['sig_id'] ?? 0);
        $name = trim($_POST['sig_name'] ?? '');
        $designation = trim($_POST['sig_designation'] ?? '');
        if ($name === '' || $designation === '') {
            $_SESSION['error_msg'] = 'Signatory name and designation are required.';
            header('Location: settings.php?tab=signatures');
            exit;
        }
        $data = [
            'name' => $name,
            'designation' => $designation,
            'sort_order' => (int) ($_POST['sig_sort_order'] ?? 0),
            'status' => ($_POST['sig_status'] ?? 'Active') === 'Inactive' ? 'Inactive' : 'Active',
        ];
        $existing = $sigId ? getAuthoritySignatureById($pdo, $sigId) : null;
        if (!empty($_FILES['sig_image']['name'])) {
            $uploaded = uploadSignatureFile($_FILES['sig_image']);
            if ($uploaded === false) {
                $_SESSION['error_msg'] = 'Signature upload failed. Use a transparent PNG, JPG or WEBP (max 2MB).';
                header('Location: settings.php?tab=signatures');
                exit;
            }
            if ($uploaded) {
                if ($existing && !empty($existing['signature'])) {
                    deleteSchoolBrandingFile($existing['signature']);
                }
                $data['signature'] = $uploaded;
            }
        }
        $savedId = saveAuthoritySignature($pdo, $sigId, $data);
        if (!empty($_POST['sig_make_default'])) {
            setDefaultAuthoritySignature($pdo, $savedId);
        }
        $_SESSION['success_msg'] = $sigId ? 'Signatory updated.' : 'Signatory added.';
        header('Location: settings.php?tab=signatures');
        exit;
    }

    if ($action === 'delete_signature') {
        deleteAuthoritySignature($pdo, (int) ($_POST['sig_id'] ?? 0));
        $_SESSION['success_msg'] = 'Signatory removed.';
        header('Location: settings.php?tab=signatures');
        exit;
    }

    if ($action === 'default_signature') {
        setDefaultAuthoritySignature($pdo, (int) ($_POST['sig_id'] ?? 0));
        $_SESSION['success_msg'] = 'Default signatory updated.';
        header('Location: settings.php?tab=signatures');
        exit;
    }

    if ($action === 'save_smtp') {
        saveSettingsGroup($pdo, [
            'smtp_enabled'    => isset($_POST['smtp_enabled']) ? '1' : '0',
            'smtp_host'       => $_POST['smtp_host'] ?? '',
            'smtp_port'       => $_POST['smtp_port'] ?? '587',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'smtp_username'   => $_POST['smtp_username'] ?? '',
            'smtp_password'   => $_POST['smtp_password'] ?? '',
            'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
            'smtp_from_name'  => $_POST['smtp_from_name'] ?? '',
        ], ['smtp_password']);
        $_SESSION['success_msg'] = 'Email SMTP settings saved.';
        header('Location: settings.php?tab=email');
        exit;
    }

    if ($action === 'test_smtp') {
        $testCfg = [
            'host'        => trim($_POST['smtp_host'] ?? $smtp['host']),
            'port'        => trim($_POST['smtp_port'] ?? $smtp['port']),
            'encryption'  => trim($_POST['smtp_encryption'] ?? $smtp['encryption']),
            'username'    => trim($_POST['smtp_username'] ?? $smtp['username']),
            'password'    => trim($_POST['smtp_password'] ?? '') !== '' ? trim($_POST['smtp_password']) : $smtp['password'],
            'from_email'  => trim($_POST['smtp_from_email'] ?? $smtp['from_email']),
            'from_name'   => trim($_POST['smtp_from_name'] ?? $smtp['from_name']),
        ];
        $testTo = trim($_POST['test_email'] ?? $testCfg['from_email']);
        $err = '';
        $ok = sendSmtpEmail($testCfg, $testTo, 'EduDash SMTP Test', '<p>Your SMTP configuration is working correctly.</p><p>Sent at ' . date('Y-m-d H:i:s') . '</p>', $err);
        if ($ok) {
            $_SESSION['success_msg'] = 'Test email sent to ' . $testTo;
        } else {
            $_SESSION['error_msg'] = 'SMTP test failed: ' . $err;
        }
        header('Location: settings.php?tab=email');
        exit;
    }

    if ($action === 'save_sms') {
        saveSettingsGroup($pdo, [
            'sms_enabled'   => isset($_POST['sms_enabled']) ? '1' : '0',
            'sms_provider'  => $_POST['sms_provider'] ?? 'MSG91',
            'sms_api_key'   => $_POST['sms_api_key'] ?? '',
            'sms_sender_id' => $_POST['sms_sender_id'] ?? '',
            'sms_route'     => $_POST['sms_route'] ?? '4',
            'sms_api_url'   => $_POST['sms_api_url'] ?? '',
        ], ['sms_api_key']);
        $_SESSION['success_msg'] = 'SMS settings saved.';
        header('Location: settings.php?tab=sms');
        exit;
    }

    if ($action === 'test_sms') {
        $mobile = trim($_POST['test_mobile'] ?? '');
        $msg = 'EduDash SMS test — your MSG/SMS setup is working. ' . date('H:i:s');
        if (trim($_POST['sms_api_key'] ?? '') !== '') {
            setSetting($pdo, 'sms_api_key', trim($_POST['sms_api_key']));
        }
        foreach (['sms_provider', 'sms_sender_id', 'sms_route', 'sms_api_url'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') {
                setSetting($pdo, $k, trim($_POST[$k]));
            }
        }
        setSetting($pdo, 'sms_enabled', '1');
        $result = dispatchSms($pdo, $mobile, $msg);
        if ($result['ok']) {
            $_SESSION['success_msg'] = 'Test SMS sent to ' . $mobile;
        } else {
            $_SESSION['error_msg'] = $result['error'];
        }
        header('Location: settings.php?tab=sms');
        exit;
    }

    if ($action === 'save_whatsapp') {
        saveSettingsGroup($pdo, [
            'whatsapp_enabled'         => isset($_POST['whatsapp_enabled']) ? '1' : '0',
            'whatsapp_provider'        => $_POST['whatsapp_provider'] ?? 'Meta Cloud API',
            'whatsapp_api_token'       => $_POST['whatsapp_api_token'] ?? '',
            'whatsapp_phone_id'        => $_POST['whatsapp_phone_id'] ?? '',
            'whatsapp_business_number' => $_POST['whatsapp_business_number'] ?? '',
            'whatsapp_api_url'         => $_POST['whatsapp_api_url'] ?? '',
        ], ['whatsapp_api_token']);
        $_SESSION['success_msg'] = 'WhatsApp settings saved.';
        header('Location: settings.php?tab=whatsapp');
        exit;
    }

    if ($action === 'test_whatsapp') {
        $mobile = trim($_POST['test_mobile'] ?? '');
        $msg = 'EduDash WhatsApp test — your setup is working. ' . date('H:i:s');
        if (trim($_POST['whatsapp_api_token'] ?? '') !== '') {
            setSetting($pdo, 'whatsapp_api_token', trim($_POST['whatsapp_api_token']));
        }
        foreach (['whatsapp_provider', 'whatsapp_phone_id', 'whatsapp_business_number', 'whatsapp_api_url'] as $k) {
            if (isset($_POST[$k]) && $_POST[$k] !== '') {
                setSetting($pdo, $k, trim($_POST[$k]));
            }
        }
        setSetting($pdo, 'whatsapp_enabled', '1');
        $result = dispatchWhatsApp($pdo, $mobile, $msg);
        if ($result['ok']) {
            $_SESSION['success_msg'] = 'Test WhatsApp message sent to ' . $mobile;
        } else {
            $_SESSION['error_msg'] = $result['error'];
        }
        header('Location: settings.php?tab=whatsapp');
        exit;
    }

    if ($action === 'change_password') {
        $errors = changeAdminPassword(
            $pdo,
            (int) $_SESSION['admin_id'],
            $_POST['current_password'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['confirm_password'] ?? ''
        );
        if ($errors) {
            $_SESSION['error_msg'] = implode(' ', $errors);
        } else {
            $_SESSION['success_msg'] = 'Password changed successfully.';
        }
        header('Location: settings.php?tab=password');
        exit;
    }

    if ($action === 'save_database' || $action === 'test_db_online' || $action === 'test_db_offline') {
        header('Location: settings.php');
        exit;
    }
}

require_once 'includes/header.php';
$smtp = getSmtpSettings($pdo);
$sms = getSmsSettings($pdo);
$whatsapp = getWhatsAppSettings($pdo);
$school = getSchoolProfile($pdo);
$logoPreviewUrl = schoolBrandingUrl($school['logo'] ?? '', 'admin');
$logoLightPreviewUrl = schoolBrandingUrl($school['logo_light'] ?? '', 'admin');
$logoIconPreviewUrl = schoolBrandingUrl($school['logo_icon'] ?? '', 'admin');
$faviconPreviewUrl = schoolBrandingUrl($school['favicon'] ?? '', 'admin');
$dbSettings = getDatabaseSettingsForm();
$dbActiveProfile = $db_active_profile ?? 'offline';
$dbConnectionMode = $db_connection_mode ?? 'offline';
$signatures = getAuthoritySignatures($pdo);
$editSig = null;
if ($activeTab === 'signatures' && !empty($_GET['edit'])) {
    $editSig = getAuthoritySignatureById($pdo, (int) $_GET['edit']);
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-cog"></i></div>
        <div class="content-top-title">
            <h2>Settings</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Settings</span>
            </p>
        </div>
    </div>
</div>

<div class="settings-layout">
    <aside class="settings-vtabs" role="tablist">
        <a href="settings.php?tab=school" class="settings-vtab <?php echo $activeTab === 'school' ? 'active' : ''; ?>">
            <span class="settings-vtab-icon"><i class="fas fa-school"></i></span>
            <span class="settings-vtab-text"><strong>School Profile</strong><small>Name, address &amp; branding</small></span>
        </a>
        <a href="settings.php?tab=signatures" class="settings-vtab <?php echo $activeTab === 'signatures' ? 'active' : ''; ?>">
            <span class="settings-vtab-icon"><i class="fas fa-signature"></i></span>
            <span class="settings-vtab-text"><strong>Signatures</strong><small>Principal &amp; authorities</small></span>
        </a>
        <a href="settings.php?tab=email" class="settings-vtab <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
            <span class="settings-vtab-icon"><i class="fas fa-envelope"></i></span>
            <span class="settings-vtab-text"><strong>Email SMTP</strong><small>Outgoing mail server</small></span>
        </a>
        <a href="settings.php?tab=sms" class="settings-vtab <?php echo $activeTab === 'sms' ? 'active' : ''; ?>">
            <span class="settings-vtab-icon"><i class="fas fa-sms"></i></span>
            <span class="settings-vtab-text"><strong>SMS / MSG</strong><small>MSG91 &amp; SMS gateway</small></span>
        </a>
        <a href="settings.php?tab=whatsapp" class="settings-vtab <?php echo $activeTab === 'whatsapp' ? 'active' : ''; ?>">
            <span class="settings-vtab-icon"><i class="fab fa-whatsapp"></i></span>
            <span class="settings-vtab-text"><strong>WhatsApp</strong><small>Business API setup</small></span>
        </a>
        <a href="settings.php?tab=password" class="settings-vtab <?php echo $activeTab === 'password' ? 'active' : ''; ?>">
            <span class="settings-vtab-icon"><i class="fas fa-lock"></i></span>
            <span class="settings-vtab-text"><strong>Change Password</strong><small>Admin account security</small></span>
        </a>
    </aside>

    <div class="settings-panels">
        <?php if ($activeTab === 'school'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>School Profile</h3>
                <p>Used on report cards, ID cards, certificates and dashboard.</p>
            </div>
            <form method="POST" class="settings-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_school">
                <div class="settings-branding-block">
                    <h4><i class="fas fa-palette"></i> Logo &amp; Favicon</h4>
                    <p>Shown on the public website, admin sidebar, login pages, certificates, ID cards and browser tab.</p>
                    <div class="settings-branding-grid">
                        <div class="settings-brand-card">
                            <label>School Logo</label>
                            <div class="settings-brand-preview<?php echo $logoPreviewUrl ? ' has-image' : ''; ?>">
                                <?php if ($logoPreviewUrl): ?>
                                <img src="<?php echo htmlspecialchars($logoPreviewUrl); ?>" alt="School logo">
                                <?php else: ?>
                                <span class="settings-brand-placeholder"><i class="fas fa-image"></i> No logo</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="school_logo" class="form-input" accept="image/png,image/jpeg,image/webp,image/gif">
                            <span class="field-hint">Main logo for light backgrounds. PNG or JPG, max 2MB.</span>
                            <?php if ($logoPreviewUrl): ?>
                            <label class="settings-remove-check"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
                            <?php endif; ?>
                        </div>
                        <div class="settings-brand-card">
                            <label>Light Logo</label>
                            <div class="settings-brand-preview is-dark-bg<?php echo $logoLightPreviewUrl ? ' has-image' : ''; ?>">
                                <?php if ($logoLightPreviewUrl): ?>
                                <img src="<?php echo htmlspecialchars($logoLightPreviewUrl); ?>" alt="Light logo">
                                <?php else: ?>
                                <span class="settings-brand-placeholder is-light"><i class="fas fa-image"></i> No light logo</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="school_logo_light" class="form-input" accept="image/png,image/jpeg,image/webp,image/gif">
                            <span class="field-hint">White or light version for dark headers, hero and footer. Max 2MB.</span>
                            <?php if ($logoLightPreviewUrl): ?>
                            <label class="settings-remove-check"><input type="checkbox" name="remove_logo_light" value="1"> Remove light logo</label>
                            <?php endif; ?>
                        </div>
                        <div class="settings-brand-card">
                            <label>Logo Icon</label>
                            <div class="settings-brand-preview is-icon<?php echo $logoIconPreviewUrl ? ' has-image' : ''; ?>">
                                <?php if ($logoIconPreviewUrl): ?>
                                <img src="<?php echo htmlspecialchars($logoIconPreviewUrl); ?>" alt="Logo icon">
                                <?php else: ?>
                                <span class="settings-brand-placeholder"><i class="fas fa-shapes"></i> No icon</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="school_logo_icon" class="form-input" accept="image/png,image/jpeg,image/webp,image/gif,image/x-icon,image/vnd.microsoft.icon,.ico">
                            <span class="field-hint">Compact mark for navbar and mobile menu. Square, max 512KB.</span>
                            <?php if ($logoIconPreviewUrl): ?>
                            <label class="settings-remove-check"><input type="checkbox" name="remove_logo_icon" value="1"> Remove logo icon</label>
                            <?php endif; ?>
                        </div>
                        <div class="settings-brand-card">
                            <label>Favicon</label>
                            <div class="settings-brand-preview is-favicon<?php echo $faviconPreviewUrl ? ' has-image' : ''; ?>">
                                <?php if ($faviconPreviewUrl): ?>
                                <img src="<?php echo htmlspecialchars($faviconPreviewUrl); ?>" alt="Favicon">
                                <?php else: ?>
                                <span class="settings-brand-placeholder"><i class="fas fa-star"></i> No favicon</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="school_favicon" class="form-input" accept="image/png,image/jpeg,image/x-icon,image/vnd.microsoft.icon,.ico">
                            <span class="field-hint">ICO or PNG, max 512KB. Recommended 32×32 or 64×64.</span>
                            <?php if ($faviconPreviewUrl): ?>
                            <label class="settings-remove-check"><input type="checkbox" name="remove_favicon" value="1"> Remove current favicon</label>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-field"><label>School Name</label><input type="text" name="school_name" class="form-input" value="<?php echo htmlspecialchars($school['name']); ?>" required></div>
                    <div class="form-field"><label>Tagline</label><input type="text" name="school_tagline" class="form-input" value="<?php echo htmlspecialchars($school['tagline']); ?>"></div>
                    <div class="form-field form-field-full"><label>Address</label><textarea name="school_address" class="form-input form-textarea" rows="2"><?php echo htmlspecialchars($school['address']); ?></textarea></div>
                    <div class="form-field"><label>Phone</label><input type="text" name="school_phone" class="form-input" value="<?php echo htmlspecialchars($school['phone']); ?>"></div>
                    <div class="form-field"><label>Email</label><input type="email" name="school_email" class="form-input" value="<?php echo htmlspecialchars($school['email']); ?>"></div>
                    <div class="form-field"><label>Website</label><input type="text" name="school_website" class="form-input" value="<?php echo htmlspecialchars($school['website']); ?>"></div>
                    <div class="form-field"><label>Principal Name</label><input type="text" name="school_principal" class="form-input" value="<?php echo htmlspecialchars($school['principal']); ?>"></div>
                    <div class="form-field"><label>Affiliation</label><input type="text" name="school_affiliation" class="form-input" value="<?php echo htmlspecialchars($school['affiliation']); ?>" placeholder="CBSE"></div>
                </div>
                <div class="settings-form-actions"><button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Profile</button></div>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'signatures'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>Authorized Signatories</h3>
                <p>Upload signatures for the Principal and other authorities. These appear on ID cards, certificates and fee receipts. The <strong>default</strong> signatory is used wherever a single signature is needed.</p>
            </div>

            <div class="sig-list">
                <?php if (empty($signatures)): ?>
                <div class="sig-empty"><i class="fas fa-signature"></i><p>No signatories added yet. Add the Principal's signature below to get started.</p></div>
                <?php else: foreach ($signatures as $sig): ?>
                <?php $sigUrl = schoolBrandingUrl($sig['signature'] ?? '', 'admin'); ?>
                <div class="sig-item<?php echo (int) $sig['is_default'] === 1 ? ' is-default' : ''; ?>">
                    <div class="sig-item-preview">
                        <?php if ($sigUrl): ?><img src="<?php echo htmlspecialchars($sigUrl); ?>" alt="Signature"><?php else: ?><span class="sig-noimg"><i class="fas fa-image"></i> No image</span><?php endif; ?>
                    </div>
                    <div class="sig-item-info">
                        <strong><?php echo htmlspecialchars($sig['name']); ?>
                            <?php if ((int) $sig['is_default'] === 1): ?><span class="sig-badge-default"><i class="fas fa-star"></i> Default</span><?php endif; ?>
                            <?php if ($sig['status'] === 'Inactive'): ?><span class="sig-badge-off">Inactive</span><?php endif; ?>
                        </strong>
                        <span><?php echo htmlspecialchars($sig['designation']); ?></span>
                    </div>
                    <div class="sig-item-actions">
                        <?php if ((int) $sig['is_default'] !== 1 && $sig['status'] !== 'Inactive'): ?>
                        <form method="POST"><input type="hidden" name="action" value="default_signature"><input type="hidden" name="sig_id" value="<?php echo (int) $sig['id']; ?>"><button type="submit" class="sig-btn" title="Set as default"><i class="fas fa-star"></i> Set default</button></form>
                        <?php endif; ?>
                        <a href="settings.php?tab=signatures&edit=<?php echo (int) $sig['id']; ?>" class="sig-btn"><i class="fas fa-pen"></i> Edit</a>
                        <form method="POST" onsubmit="return confirm('Remove this signatory?');"><input type="hidden" name="action" value="delete_signature"><input type="hidden" name="sig_id" value="<?php echo (int) $sig['id']; ?>"><button type="submit" class="sig-btn sig-btn-danger"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="settings-branding-block">
                <h4><i class="fas fa-<?php echo $editSig ? 'pen' : 'plus'; ?>"></i> <?php echo $editSig ? 'Edit Signatory' : 'Add New Signatory'; ?></h4>
                <p>Use a clear signature image on a transparent or white background for best print results.</p>
                <form method="POST" class="settings-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_signature">
                    <input type="hidden" name="sig_id" value="<?php echo $editSig ? (int) $editSig['id'] : 0; ?>">
                    <div class="settings-branding-grid">
                        <div class="settings-brand-card">
                            <label>Signature Image</label>
                            <div class="settings-brand-preview<?php echo ($editSig && !empty($editSig['signature'])) ? ' has-image' : ''; ?>" style="background:#fff">
                                <?php $editSigUrl = $editSig ? schoolBrandingUrl($editSig['signature'] ?? '', 'admin') : ''; ?>
                                <?php if ($editSigUrl): ?>
                                <img src="<?php echo htmlspecialchars($editSigUrl); ?>" alt="Signature">
                                <?php else: ?>
                                <span class="settings-brand-placeholder"><i class="fas fa-signature"></i> No signature</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="sig_image" class="form-input" accept="image/png,image/jpeg,image/webp"<?php echo $editSig ? '' : ' required'; ?>>
                            <span class="field-hint">PNG (transparent) recommended, max 2MB.<?php echo $editSig ? ' Leave blank to keep current.' : ''; ?></span>
                        </div>
                        <div style="flex:1;min-width:260px">
                            <div class="form-grid form-grid-1">
                                <div class="form-field"><label>Signatory Name</label><input type="text" name="sig_name" class="form-input" value="<?php echo htmlspecialchars($editSig['name'] ?? $school['principal'] ?? ''); ?>" placeholder="e.g. Dr. A. Sharma" required></div>
                                <div class="form-field"><label>Designation</label><input type="text" name="sig_designation" class="form-input" value="<?php echo htmlspecialchars($editSig['designation'] ?? 'Principal'); ?>" placeholder="e.g. Principal / Vice Principal / Exam Controller" required></div>
                                <div class="form-grid form-grid-2" style="padding:0">
                                    <div class="form-field"><label>Display Order</label><input type="number" name="sig_sort_order" class="form-input" value="<?php echo (int) ($editSig['sort_order'] ?? 0); ?>"></div>
                                    <div class="form-field"><label>Status</label><select name="sig_status" class="form-input form-select"><option value="Active" <?php echo (($editSig['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo (($editSig['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option></select></div>
                                </div>
                                <label class="settings-toggle"><input type="checkbox" name="sig_make_default" value="1" <?php echo ($editSig && (int) $editSig['is_default'] === 1) ? 'checked' : (empty($signatures) ? 'checked' : ''); ?>><span>Set as default signatory (used on ID cards &amp; receipts)</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="settings-form-actions">
                        <?php if ($editSig): ?><a href="settings.php?tab=signatures" class="btn-header-action btn-header-outline"><i class="fas fa-times"></i> Cancel</a><?php endif; ?>
                        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> <?php echo $editSig ? 'Update Signatory' : 'Add Signatory'; ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'email'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>Email SMTP Setup</h3>
                <p>Configure outgoing email for receipts, alerts, and notifications.</p>
            </div>
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="save_smtp">
                <label class="settings-toggle">
                    <input type="checkbox" name="smtp_enabled" value="1" <?php echo $smtp['enabled'] === '1' ? 'checked' : ''; ?>>
                    <span>Enable SMTP email</span>
                </label>
                <div class="form-grid form-grid-2">
                    <div class="form-field"><label>SMTP Host</label><input type="text" name="smtp_host" class="form-input" value="<?php echo htmlspecialchars($smtp['host']); ?>" placeholder="smtp.gmail.com"></div>
                    <div class="form-field"><label>SMTP Port</label><input type="number" name="smtp_port" class="form-input" value="<?php echo htmlspecialchars($smtp['port']); ?>"></div>
                    <div class="form-field"><label>Encryption</label><select name="smtp_encryption" class="form-input form-select"><option value="tls" <?php echo $smtp['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option><option value="ssl" <?php echo $smtp['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option><option value="none" <?php echo $smtp['encryption'] === 'none' ? 'selected' : ''; ?>>None</option></select></div>
                    <div class="form-field"><label>Username</label><input type="text" name="smtp_username" class="form-input" value="<?php echo htmlspecialchars($smtp['username']); ?>"></div>
                    <div class="form-field"><label>Password</label><input type="password" name="smtp_password" class="form-input" placeholder="<?php echo $smtp['password'] ? '•••••••• (leave blank to keep)' : 'SMTP password'; ?>" autocomplete="new-password"></div>
                    <div class="form-field"><label>From Email</label><input type="email" name="smtp_from_email" class="form-input" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" placeholder="noreply@school.com"></div>
                    <div class="form-field"><label>From Name</label><input type="text" name="smtp_from_name" class="form-input" value="<?php echo htmlspecialchars($smtp['from_name']); ?>"></div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save SMTP Settings</button>
                </div>
            </form>
            <div class="settings-test-box">
                <h4><i class="fas fa-paper-plane"></i> Send Test Email</h4>
                <form method="POST" class="category-add-row">
                    <input type="hidden" name="action" value="test_smtp">
                    <input type="hidden" name="smtp_host" value="<?php echo htmlspecialchars($smtp['host']); ?>">
                    <input type="hidden" name="smtp_port" value="<?php echo htmlspecialchars($smtp['port']); ?>">
                    <input type="hidden" name="smtp_encryption" value="<?php echo htmlspecialchars($smtp['encryption']); ?>">
                    <input type="hidden" name="smtp_username" value="<?php echo htmlspecialchars($smtp['username']); ?>">
                    <input type="hidden" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp['from_email']); ?>">
                    <input type="hidden" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp['from_name']); ?>">
                    <div class="form-field form-field-grow"><label>Test recipient</label><input type="email" name="test_email" class="form-input" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" required></div>
                    <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-outline category-add-btn">Send Test</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'sms'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>SMS / MSG Setup</h3>
                <p>Connect MSG91 or a custom SMS gateway for fee and attendance alerts.</p>
            </div>
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="save_sms">
                <label class="settings-toggle">
                    <input type="checkbox" name="sms_enabled" value="1" <?php echo $sms['enabled'] === '1' ? 'checked' : ''; ?>>
                    <span>Enable SMS notifications</span>
                </label>
                <div class="form-grid form-grid-2">
                    <div class="form-field"><label>Provider</label><select name="sms_provider" class="form-input form-select"><option value="MSG91" <?php echo $sms['provider'] === 'MSG91' ? 'selected' : ''; ?>>MSG91</option><option value="Custom" <?php echo $sms['provider'] === 'Custom' ? 'selected' : ''; ?>>Custom</option></select></div>
                    <div class="form-field"><label>Auth Key / API Key</label><input type="password" name="sms_api_key" class="form-input" placeholder="<?php echo $sms['api_key'] ? '••••••••' : 'Your MSG91 auth key'; ?>"></div>
                    <div class="form-field"><label>Sender ID</label><input type="text" name="sms_sender_id" class="form-input" value="<?php echo htmlspecialchars($sms['sender_id']); ?>" maxlength="6" placeholder="EDUDSH"></div>
                    <div class="form-field"><label>Route</label><input type="text" name="sms_route" class="form-input" value="<?php echo htmlspecialchars($sms['route']); ?>" placeholder="4"></div>
                    <div class="form-field form-field-full"><label>Custom API URL (optional)</label><input type="text" name="sms_api_url" class="form-input" value="<?php echo htmlspecialchars($sms['api_url']); ?>" placeholder="https://api.example.com/sms?mobile={mobile}&msg={message}&key={api_key}"></div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save SMS Settings</button>
                </div>
            </form>
            <div class="settings-test-box">
                <h4><i class="fas fa-mobile-alt"></i> Send Test SMS</h4>
                <form method="POST" class="category-add-row">
                    <input type="hidden" name="action" value="test_sms">
                    <input type="hidden" name="sms_provider" value="<?php echo htmlspecialchars($sms['provider']); ?>">
                    <input type="hidden" name="sms_sender_id" value="<?php echo htmlspecialchars($sms['sender_id']); ?>">
                    <input type="hidden" name="sms_route" value="<?php echo htmlspecialchars($sms['route']); ?>">
                    <input type="hidden" name="sms_api_url" value="<?php echo htmlspecialchars($sms['api_url']); ?>">
                    <div class="form-field form-field-grow"><label>Mobile number</label><input type="text" name="test_mobile" class="form-input" placeholder="9876543210" required></div>
                    <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-outline category-add-btn">Send Test</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'whatsapp'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>WhatsApp Setup</h3>
                <p>Meta Cloud API or custom webhook for WhatsApp Business messages.</p>
            </div>
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="save_whatsapp">
                <label class="settings-toggle">
                    <input type="checkbox" name="whatsapp_enabled" value="1" <?php echo $whatsapp['enabled'] === '1' ? 'checked' : ''; ?>>
                    <span>Enable WhatsApp notifications</span>
                </label>
                <div class="form-grid form-grid-2">
                    <div class="form-field"><label>Provider</label><select name="whatsapp_provider" class="form-input form-select"><option value="Meta Cloud API" <?php echo $whatsapp['provider'] === 'Meta Cloud API' ? 'selected' : ''; ?>>Meta Cloud API</option><option value="Custom" <?php echo $whatsapp['provider'] === 'Custom' ? 'selected' : ''; ?>>Custom</option></select></div>
                    <div class="form-field"><label>Business Number</label><input type="text" name="whatsapp_business_number" class="form-input" value="<?php echo htmlspecialchars($whatsapp['business_number']); ?>" placeholder="+91XXXXXXXXXX"></div>
                    <div class="form-field"><label>Phone Number ID</label><input type="text" name="whatsapp_phone_id" class="form-input" value="<?php echo htmlspecialchars($whatsapp['phone_id']); ?>" placeholder="Meta Phone Number ID"></div>
                    <div class="form-field"><label>Permanent Access Token</label><input type="password" name="whatsapp_api_token" class="form-input" placeholder="<?php echo $whatsapp['api_token'] ? '••••••••' : 'WhatsApp API token'; ?>"></div>
                    <div class="form-field form-field-full"><label>Custom API URL (optional)</label><input type="text" name="whatsapp_api_url" class="form-input" value="<?php echo htmlspecialchars($whatsapp['api_url']); ?>" placeholder="https://your-api.com/wa?to={mobile}&text={message}&token={token}"></div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save WhatsApp Settings</button>
                </div>
            </form>
            <div class="settings-test-box">
                <h4><i class="fab fa-whatsapp"></i> Send Test Message</h4>
                <form method="POST" class="category-add-row">
                    <input type="hidden" name="action" value="test_whatsapp">
                    <input type="hidden" name="whatsapp_provider" value="<?php echo htmlspecialchars($whatsapp['provider']); ?>">
                    <input type="hidden" name="whatsapp_phone_id" value="<?php echo htmlspecialchars($whatsapp['phone_id']); ?>">
                    <input type="hidden" name="whatsapp_business_number" value="<?php echo htmlspecialchars($whatsapp['business_number']); ?>">
                    <input type="hidden" name="whatsapp_api_url" value="<?php echo htmlspecialchars($whatsapp['api_url']); ?>">
                    <div class="form-field form-field-grow"><label>Mobile (with country code)</label><input type="text" name="test_mobile" class="form-input" placeholder="919876543210" required></div>
                    <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-outline category-add-btn">Send Test</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'database'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>Online &amp; Offline Database</h3>
                <p>Configure cloud (online) and local XAMPP (offline) databases. Use <strong>Auto</strong> to connect online when internet is available and fall back to local when offline.</p>
            </div>

            <div class="db-flow-status">
                <div class="db-flow-status-card <?php echo $dbActiveProfile === 'online' ? 'is-online' : 'is-offline'; ?>">
                    <span class="db-flow-status-icon"><i class="fas fa-circle"></i></span>
                    <div>
                        <small>Currently connected</small>
                        <strong><?php echo $dbActiveProfile === 'online' ? 'Online Database' : 'Offline Database'; ?></strong>
                        <em>Mode: <?php echo htmlspecialchars(ucfirst($dbConnectionMode)); ?></em>
                    </div>
                </div>
            </div>

            <div class="db-flow-steps">
                <div class="db-flow-step">
                    <span class="db-flow-num">1</span>
                    <div><strong>Online DB</strong><p>Hosting / cloud MySQL — shared when internet works</p></div>
                </div>
                <div class="db-flow-arrow"><i class="fas fa-arrows-left-right"></i></div>
                <div class="db-flow-step is-auto">
                    <span class="db-flow-num">2</span>
                    <div><strong>Auto Mode</strong><p>Tries online first, switches to local offline DB</p></div>
                </div>
                <div class="db-flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="db-flow-step">
                    <span class="db-flow-num">3</span>
                    <div><strong>Offline DB</strong><p>Local XAMPP MySQL — works without internet</p></div>
                </div>
            </div>

            <form method="POST" class="settings-form" id="dbSettingsForm">
                <input type="hidden" name="action" value="save_database">

                <div class="db-mode-picker">
                    <label class="db-mode-option<?php echo $dbSettings['mode'] === 'auto' ? ' active' : ''; ?>">
                        <input type="radio" name="db_mode" value="auto" <?php echo $dbSettings['mode'] === 'auto' ? 'checked' : ''; ?>>
                        <strong>Auto (Recommended)</strong>
                        <span>Online when available → offline fallback</span>
                    </label>
                    <label class="db-mode-option<?php echo $dbSettings['mode'] === 'online' ? ' active' : ''; ?>">
                        <input type="radio" name="db_mode" value="online" <?php echo $dbSettings['mode'] === 'online' ? 'checked' : ''; ?>>
                        <strong>Online Only</strong>
                        <span>Always use cloud server</span>
                    </label>
                    <label class="db-mode-option<?php echo $dbSettings['mode'] === 'offline' ? ' active' : ''; ?>">
                        <input type="radio" name="db_mode" value="offline" <?php echo $dbSettings['mode'] === 'offline' ? 'checked' : ''; ?>>
                        <strong>Offline Only</strong>
                        <span>Always use local XAMPP</span>
                    </label>
                </div>

                <div class="db-profile-grid">
                    <div class="db-profile-card">
                        <div class="db-profile-head is-online">
                            <h4><i class="fas fa-cloud"></i> Online Database</h4>
                            <p>Remote hosting / VPS / cloud MySQL</p>
                        </div>
                        <div class="form-grid form-grid-2">
                            <div class="form-field form-field-full"><label>Label</label><input type="text" name="db_online_label" class="form-input" value="<?php echo htmlspecialchars($dbSettings['online']['label']); ?>"></div>
                            <div class="form-field"><label>Host</label><input type="text" name="db_online_host" class="form-input" value="<?php echo htmlspecialchars($dbSettings['online']['host']); ?>" placeholder="db.yourhost.com"></div>
                            <div class="form-field"><label>Port</label><input type="number" name="db_online_port" class="form-input" value="<?php echo (int) $dbSettings['online']['port']; ?>"></div>
                            <div class="form-field"><label>Database Name</label><input type="text" name="db_online_dbname" class="form-input" value="<?php echo htmlspecialchars($dbSettings['online']['dbname']); ?>"></div>
                            <div class="form-field"><label>Username</label><input type="text" name="db_online_username" class="form-input" value="<?php echo htmlspecialchars($dbSettings['online']['username']); ?>"></div>
                            <div class="form-field form-field-full"><label>Password</label><input type="password" name="db_online_password" class="form-input" placeholder="<?php echo $dbSettings['online']['password'] !== '' ? '••••••••' : 'Database password'; ?>" autocomplete="new-password"></div>
                        </div>
                        <button type="submit" formaction="settings.php?tab=database" formmethod="POST" name="action" value="test_db_online" class="btn-header-action btn-header-outline"><i class="fas fa-plug"></i> Test Online Connection</button>
                    </div>

                    <div class="db-profile-card">
                        <div class="db-profile-head is-offline">
                            <h4><i class="fas fa-server"></i> Offline Database</h4>
                            <p>Local XAMPP / WAMP on this computer</p>
                        </div>
                        <div class="form-grid form-grid-2">
                            <div class="form-field form-field-full"><label>Label</label><input type="text" name="db_offline_label" class="form-input" value="<?php echo htmlspecialchars($dbSettings['offline']['label']); ?>"></div>
                            <div class="form-field"><label>Host</label><input type="text" name="db_offline_host" class="form-input" value="<?php echo htmlspecialchars($dbSettings['offline']['host']); ?>" placeholder="localhost"></div>
                            <div class="form-field"><label>Port</label><input type="number" name="db_offline_port" class="form-input" value="<?php echo (int) $dbSettings['offline']['port']; ?>"></div>
                            <div class="form-field"><label>Database Name</label><input type="text" name="db_offline_dbname" class="form-input" value="<?php echo htmlspecialchars($dbSettings['offline']['dbname']); ?>"></div>
                            <div class="form-field"><label>Username</label><input type="text" name="db_offline_username" class="form-input" value="<?php echo htmlspecialchars($dbSettings['offline']['username']); ?>"></div>
                            <div class="form-field form-field-full"><label>Password</label><input type="password" name="db_offline_password" class="form-input" placeholder="<?php echo $dbSettings['offline']['password'] !== '' ? '••••••••' : 'Usually empty on XAMPP'; ?>" autocomplete="new-password"></div>
                        </div>
                        <button type="submit" formaction="settings.php?tab=database" formmethod="POST" name="action" value="test_db_offline" class="btn-header-action btn-header-outline"><i class="fas fa-plug"></i> Test Offline Connection</button>
                    </div>
                </div>

                <div class="settings-info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>How it works</strong><br>
                        Settings are saved in <code>includes/db_profiles.local.php</code>. In <strong>Auto</strong> mode: on your <strong>local PC (XAMPP)</strong> the offline database is used; on the <strong>live server</strong> the online database is used. If the preferred connection fails, it falls back to the other profile.
                    </div>
                </div>

                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Database Settings</button>
                </div>
            </form>
        </div>
        <script>
        document.querySelectorAll('.db-mode-option input').forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.querySelectorAll('.db-mode-option').forEach(function (el) { el.classList.remove('active'); });
                radio.closest('.db-mode-option').classList.add('active');
            });
        });
        </script>
        <?php endif; ?>

        <?php if ($activeTab === 'password'): ?>
        <div class="settings-panel active">
            <div class="settings-panel-head">
                <h3>Change Password</h3>
                <p>Update your admin login password. You must enter your current password to confirm.</p>
            </div>
            <form method="POST" class="settings-form settings-form-narrow">
                <input type="hidden" name="action" value="change_password">
                <div class="form-grid form-grid-1">
                    <div class="form-field">
                        <label><i class="fas fa-lock"></i> Current Password</label>
                        <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-key"></i> New Password</label>
                        <input type="password" name="new_password" class="form-input" required minlength="6" autocomplete="new-password">
                        <span class="field-hint">Minimum 6 characters</span>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-check-double"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required minlength="6" autocomplete="new-password">
                    </div>
                </div>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary" onclick="return confirm('Change your admin password?');"><i class="fas fa-shield-alt"></i> Update Password</button>
                </div>
            </form>
            <div class="settings-info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['admin_username']); ?><br>
                    After changing password, you will stay logged in on this device. Use the new password on your next login.
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
