<?php
$page_title = 'My Profile';
require_once 'includes/init.php';
$hostelInfo = getStudentHostelDetails($pdo, (int) $student['id']);
$transportInfo = getStudentTransportDetails($pdo, (int) $student['id']);
$guardians = getStudentGuardians($pdo, (int) $student['id']);
$profileDocs = getStudentDocuments($pdo, (int) $student['id']);
$academicYear = 'Jun ' . date('Y') . '/' . (date('Y') + 1);
$section = displayVal($student['section'] ?? 'A', 'A');
require_once 'includes/layout_header.php';
?>
<div class="sp-profile-hero">
    <img class="sp-profile-avatar" src="<?php echo htmlspecialchars($sp_photo); ?>" alt="">
    <div class="sp-profile-hero-info">
        <h2><?php echo $sp_name; ?></h2>
        <div class="sp-profile-hero-chips">
            <span class="sp-welcome-chip"><i class="fas fa-id-card"></i> <?php echo $sp_ad_no; ?></span>
            <span class="sp-welcome-chip"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($student['class']); ?> · <?php echo htmlspecialchars($student['section'] ?? 'A'); ?></span>
            <span class="sp-welcome-chip"><i class="fas fa-hashtag"></i> Roll <?php echo htmlspecialchars($student['roll']); ?></span>
            <span class="sp-welcome-chip"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($student['status']); ?></span>
        </div>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-head"><h3><i class="fas fa-address-card"></i> Personal Information</h3></div>
    <div class="sp-info-grid">
        <div class="sp-info-item"><i class="fas fa-school"></i><div><span>Class</span><strong><?php echo displayVal($student['class']); ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-table-columns"></i><div><span>Section</span><strong><?php echo $section; ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-hashtag"></i><div><span>Roll No</span><strong><?php echo displayVal($student['roll']); ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-venus-mars"></i><div><span>Gender</span><strong><?php echo displayVal($student['gender']); ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-cake-candles"></i><div><span>Date of Birth</span><strong><?php echo formatDobDisplay($student['dob']); ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-layer-group"></i><div><span>Category</span><strong><?php echo displayVal($student['category'] ?? '—'); ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-calendar-days"></i><div><span>Academic Year</span><strong><?php echo htmlspecialchars($academicYear); ?></strong></div></div>
        <div class="sp-info-item"><i class="fas fa-phone"></i><div><span>Mobile</span><strong><?php echo displayVal($student['mobile']); ?></strong></div></div>
        <div class="sp-info-item sp-info-full"><i class="fas fa-envelope"></i><div><span>Email</span><strong><?php echo displayVal($student['email']); ?></strong></div></div>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-head"><h3><i class="fas fa-users"></i> Parent &amp; Guardian Details</h3></div>
    <?php if (empty($guardians)): ?>
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-users"></i></div><strong>No guardian details</strong><p>Contact the school office to update your parent or guardian information.</p></div>
    <?php else: ?>
    <div class="sp-guardian-grid">
        <?php foreach ($guardians as $g):
            $gav = 'https://ui-avatars.com/api/?name=' . urlencode($g['name'] ?: 'G') . '&background=ede9fe&color=7c3aed&bold=true';
        ?>
        <div class="sp-guardian">
            <img class="sp-guardian-av" src="<?php echo $gav; ?>" alt="">
            <div class="sp-guardian-body">
                <strong><?php echo displayVal($g['name']); ?></strong>
                <span class="sp-guardian-rel"><i class="fas <?php echo guardianRoleIcon($g['relation']); ?>"></i> <?php echo htmlspecialchars($g['relation']); ?></span>
                <div class="sp-guardian-contact">
                    <span><i class="fas fa-phone"></i> <?php echo displayVal($g['phone']); ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo displayVal($g['email']); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="sp-grid-2">
    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="fas fa-map-marker-alt"></i> Address</h3></div>
        <div class="sp-info-grid">
            <div class="sp-info-item sp-info-full"><i class="fas fa-house"></i><div><span>Current Address</span><strong><?php echo displayVal($student['current_address']); ?></strong></div></div>
            <div class="sp-info-item sp-info-full"><i class="fas fa-location-dot"></i><div><span>Permanent Address</span><strong><?php echo displayVal($student['permanent_address']); ?></strong></div></div>
        </div>
    </div>
    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="fas fa-building-columns"></i> Academic Background</h3></div>
        <div class="sp-info-grid">
            <div class="sp-info-item sp-info-full"><i class="fas fa-school"></i><div><span>Previous School</span><strong><?php echo displayVal($student['previous_school'] ?? ''); ?></strong></div></div>
            <div class="sp-info-item sp-info-full"><i class="fas fa-graduation-cap"></i><div><span>Current School</span><strong><?php echo htmlspecialchars($school['name']); ?></strong></div></div>
        </div>
    </div>
</div>

<div class="sp-grid-2">
    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="fas fa-university"></i> Bank Details</h3></div>
        <div class="sp-info-grid">
            <div class="sp-info-item"><i class="fas fa-landmark"></i><div><span>Bank Name</span><strong><?php echo displayVal($student['bank_name'] ?? ''); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-code-branch"></i><div><span>Branch</span><strong><?php echo displayVal($student['bank_branch'] ?? ''); ?></strong></div></div>
            <div class="sp-info-item sp-info-full"><i class="fas fa-barcode"></i><div><span>IFSC Code</span><strong><?php echo displayVal($student['ifsc_code'] ?? ''); ?></strong></div></div>
        </div>
    </div>
    <div class="sp-card">
        <div class="sp-card-head"><h3><i class="fas fa-heart-pulse"></i> Medical Details</h3></div>
        <div class="sp-info-grid">
            <div class="sp-info-item"><i class="fas fa-droplet"></i><div><span>Blood Group</span><strong><?php echo displayVal($student['blood_group'] ?? ''); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-ruler-vertical"></i><div><span>Height</span><strong><?php echo displayVal($student['height'] ?? ''); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-weight-scale"></i><div><span>Weight</span><strong><?php echo displayVal($student['weight'] ?? ''); ?></strong></div></div>
        </div>
    </div>
</div>

<div class="sp-grid-2">
    <div class="sp-card">
        <div class="sp-card-head">
            <h3><i class="fas fa-bed"></i> Hostel Details</h3>
            <?php if ($hostelInfo): ?><span class="sp-badge present">Allotted</span><?php endif; ?>
        </div>
        <?php if ($hostelInfo): ?>
        <div class="sp-info-grid">
            <div class="sp-info-item"><i class="fas fa-building"></i><div><span>Hostel</span><strong><?php echo htmlspecialchars($hostelInfo['hostel_name']); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-door-closed"></i><div><span>Room No</span><strong><?php echo htmlspecialchars($hostelInfo['room_no']); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-bed"></i><div><span>Room Type</span><strong><?php echo displayVal($hostelInfo['room_type']); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-calendar-day"></i><div><span>Allotted From</span><strong><?php echo $hostelInfo['allotted_from'] ? date('d M Y', strtotime($hostelInfo['allotted_from'])) : '—'; ?></strong></div></div>
            <?php if (!empty($hostelInfo['hostel_address'])): ?>
            <div class="sp-info-item sp-info-full"><i class="fas fa-location-dot"></i><div><span>Address</span><strong><?php echo htmlspecialchars($hostelInfo['hostel_address']); ?></strong></div></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-bed"></i></div><strong>No hostel allotted</strong><p>Contact the school office if you require hostel facility.</p></div>
        <?php endif; ?>
    </div>

    <div class="sp-card">
        <div class="sp-card-head">
            <h3><i class="fas fa-bus"></i> Transport Details</h3>
            <?php if ($transportInfo): ?><span class="sp-badge present">Subscribed</span><?php endif; ?>
        </div>
        <?php if ($transportInfo): ?>
        <div class="sp-info-grid">
            <div class="sp-info-item"><i class="fas fa-route"></i><div><span>Route</span><strong><?php echo htmlspecialchars($transportInfo['route_name']); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-map-pin"></i><div><span>Pickup Stop</span><strong><?php echo displayVal($transportInfo['stop_name']); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-clock"></i><div><span>Pickup Time</span><strong><?php echo !empty($transportInfo['pickup_time']) ? date('g:i A', strtotime($transportInfo['pickup_time'])) : '—'; ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-indian-rupee-sign"></i><div><span>Route Fare</span><strong><?php echo $transportInfo['fare'] > 0 ? '₹' . number_format((float) $transportInfo['fare']) : '—'; ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-van-shuttle"></i><div><span>Vehicle</span><strong><?php echo displayVal(trim(($transportInfo['vehicle_no'] ?? '') . (!empty($transportInfo['vehicle_model']) ? ' · ' . $transportInfo['vehicle_model'] : ''))); ?></strong></div></div>
            <div class="sp-info-item"><i class="fas fa-user-tie"></i><div><span>Driver</span><strong><?php echo displayVal($transportInfo['driver_name']); ?></strong></div></div>
            <?php if (!empty($transportInfo['driver_phone'])): ?>
            <div class="sp-info-item"><i class="fas fa-phone"></i><div><span>Driver Phone</span><strong><?php echo htmlspecialchars($transportInfo['driver_phone']); ?></strong></div></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-bus"></i></div><strong>No transport subscribed</strong><p>Contact the school office if you require transport facility.</p></div>
        <?php endif; ?>
    </div>
</div>

<div class="sp-card">
    <div class="sp-card-head">
        <h3><i class="fas fa-folder-open"></i> Documents</h3>
        <a href="documents.php" class="sp-card-link">View all <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php if (empty($profileDocs)): ?>
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-folder-open"></i></div><strong>No documents uploaded</strong><p>Documents uploaded by the school office will appear here.</p></div>
    <?php else: ?>
    <div class="sp-list">
        <?php foreach (array_slice($profileDocs, 0, 4) as $d):
            $dext = strtoupper(pathinfo($d['file_path'], PATHINFO_EXTENSION) ?: 'FILE');
            $durl = '../admin/' . ltrim($d['file_path'], '/');
        ?>
        <div class="sp-list-row">
            <div class="sp-list-ico"><i class="fas fa-file"></i></div>
            <div class="sp-list-main">
                <strong><?php echo htmlspecialchars($d['original_name'] ?? ($d['doc_type'] . ' Document')); ?></strong>
                <small><i class="fas fa-tag"></i> <?php echo htmlspecialchars($d['doc_type'] ?: 'Document'); ?> · <?php echo $dext; ?> · <?php echo !empty($d['uploaded_at']) ? date('d M Y', strtotime($d['uploaded_at'])) : '—'; ?></small>
            </div>
            <a href="<?php echo htmlspecialchars($durl); ?>" target="_blank" class="sp-receipt-btn"><i class="fas fa-eye"></i> View</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="sp-card">
    <div class="sp-card-head"><h3><i class="fas fa-align-left"></i> Remarks</h3></div>
    <div class="sp-remark">
        <i class="fas fa-quote-left"></i>
        <p><?php echo !empty($student['description']) ? nl2br(htmlspecialchars($student['description'])) : 'No remarks added for your profile.'; ?></p>
    </div>
</div>

<link rel="stylesheet" href="assets/css/id-card.css">
<div class="sp-card">
    <div class="sp-card-head">
        <h3><i class="fas fa-id-card"></i> My ID Card</h3>
        <div class="sp-idcard-actions">
            <button type="button" class="sp-card-link" onclick="spFlipIdCard(event)"><i class="fas fa-rotate"></i> Flip</button>
            <a href="id_card.php" target="_blank" class="sp-card-link"><i class="fas fa-download"></i> Download / Print</a>
        </div>
    </div>
    <div id="spIdCard" class="id-card-page id-card-embed id-card-flip" onclick="spFlipIdCard(event)" title="Click to flip">
        <?php require 'includes/id_card_body.php'; ?>
    </div>
    <p class="sp-idcard-hint"><i class="fas fa-hand-pointer"></i> Click the card to switch between front &amp; back</p>
</div>
<script>
function spFlipIdCard(e) {
    if (e) e.stopPropagation();
    var el = document.getElementById('spIdCard');
    if (el) el.classList.toggle('is-flipped');
}
</script>

<div class="sp-card">
    <div class="sp-card-head">
        <h3><i class="fas fa-shield-halved"></i> Account Security</h3>
        <a href="change-password.php" class="sp-card-link">Change password <i class="fas fa-arrow-right"></i></a>
    </div>
    <p style="margin:0;color:var(--sp-muted);font-size:0.9rem">Keep your account safe. If you notice anything wrong in your details, please contact the school office.</p>
</div>
<?php require_once 'includes/layout_footer.php'; ?>
