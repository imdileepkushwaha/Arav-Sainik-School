<?php
// Reusable student ID card (front + back) — identical to the admin ID card.
// Expects: $student, $sp_photo, $school, $sp_logo_url, $pdo
$idGuardians = getStudentGuardians($pdo, (int) $student['id']);
$idFather = $idMother = $idGuardian = null;
foreach ($idGuardians as $g) {
    if ($g['relation'] === 'Father') $idFather = $g;
    if ($g['relation'] === 'Mother') $idMother = $g;
    if ($g['relation'] === 'Guardian') $idGuardian = $g;
}
$idEmergencyName = $idFather['name'] ?? ($idMother['name'] ?? ($idGuardian['name'] ?? '-'));
$idEmergencyPhone = $idFather['phone'] ?? ($idMother['phone'] ?? ($idGuardian['phone'] ?? ($student['mobile'] ?? '-')));
$idAcademicYear = date('Y') . '/' . (date('Y') + 1);
$idSchoolName = $school['name'] ?: 'EduDash School';
$idSignatory = getDefaultAuthoritySignature($pdo);
$idSignatureUrl = schoolBrandingUrl($idSignatory['signature'] ?? '', 'portal');
$idSignatureRole = $idSignatory['designation'] ?? 'Principal';
$idRawAddress = !empty($student['current_address']) ? $student['current_address'] : ($student['permanent_address'] ?? '');
$idAddress = trim($idRawAddress) !== '' ? $idRawAddress : ($idSchoolName . ' Campus, Main Road, City - 110001');

if (!function_exists('idCardText')) {
    function idCardText($text, $max = 40) {
        $text = trim((string) $text);
        if ($text === '' || $text === '-') return '-';
        if (mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max - 1) . '…';
        }
        return $text;
    }
}
?>
<div class="id-cards-wrap">
    <div class="id-card-col">
        <span class="id-card-label">Front Side</span>
        <div class="id-card id-front">
            <div class="card-top">
                <div class="school-logo"><?php if (!empty($sp_logo_url)): ?><img src="<?php echo htmlspecialchars($sp_logo_url); ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?></div>
                <div class="school-info">
                    <h1><?php echo htmlspecialchars($idSchoolName); ?></h1>
                    <p>Student Identity Card · Session <?php echo $idAcademicYear; ?></p>
                </div>
                <span class="card-type-badge">Student</span>
            </div>
            <div class="card-main">
                <div class="photo-block">
                    <img src="<?php echo htmlspecialchars($sp_photo); ?>" alt="Photo" class="id-photo">
                    <span class="photo-tag">Photo</span>
                </div>
                <div class="student-block">
                    <div class="student-name" title="<?php echo htmlspecialchars($student['name']); ?>">
                        <?php echo htmlspecialchars($student['name']); ?>
                    </div>
                    <div class="student-meta">
                        <span class="meta-pill">Class <?php echo htmlspecialchars(idCardText($student['class'], 18)); ?></span>
                        <span class="meta-pill">Sec <?php echo htmlspecialchars($student['section'] ?: 'A'); ?></span>
                        <span class="meta-pill">Roll <?php echo htmlspecialchars($student['roll']); ?></span>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="lbl">Admission No</span>
                            <span class="val"><?php echo htmlspecialchars($student['ad_no']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="lbl">Date of Birth</span>
                            <span class="val"><?php echo formatDobDisplay($student['dob']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="lbl">Blood Group</span>
                            <span class="val"><?php echo displayVal($student['blood_group'] ?? ''); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="lbl">Mobile</span>
                            <span class="val"><?php echo htmlspecialchars($student['mobile']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-bottom">
                <div class="bottom-left">
                    <span class="valid-tag"><span class="valid-dot"></span> Valid Student</span>
                    <span class="issue-date">Issued <?php echo date('d M Y'); ?></span>
                </div>
                <div class="sign-area">
                    <?php if ($idSignatureUrl): ?><img class="sign-img" src="<?php echo htmlspecialchars($idSignatureUrl); ?>" alt="Signature"><?php endif; ?>
                    <div class="sign-line"></div>
                    <span><?php echo htmlspecialchars($idSignatureRole); ?></span>
                </div>
                <div class="id-code-block">
                    <div class="qr-box"><i class="fas fa-qrcode"></i></div>
                    <div class="id-code"><?php echo htmlspecialchars($student['ad_no']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="id-card-col">
        <span class="id-card-label">Back Side</span>
        <div class="id-card id-back">
            <div class="card-top">
                <div class="mag-line"></div>
                <h2><i class="fas fa-shield-alt"></i> Official Use Only</h2>
            </div>
            <div class="card-main">
                <div class="back-grid">
                    <div class="back-box full">
                        <h3><i class="fas fa-map-marker-alt"></i> School Address</h3>
                        <p title="<?php echo htmlspecialchars($idAddress); ?>"><?php echo htmlspecialchars(idCardText($idAddress, 90)); ?></p>
                    </div>
                    <div class="back-box">
                        <h3><i class="fas fa-phone"></i> Emergency</h3>
                        <div class="back-line"><span>Name</span><strong title="<?php echo htmlspecialchars($idEmergencyName); ?>"><?php echo htmlspecialchars(idCardText($idEmergencyName, 16)); ?></strong></div>
                        <div class="back-line"><span>Phone</span><strong><?php echo htmlspecialchars($idEmergencyPhone); ?></strong></div>
                    </div>
                    <div class="back-box">
                        <h3><i class="fas fa-user"></i> Details</h3>
                        <div class="back-line"><span>Category</span><strong><?php echo displayVal($student['category'] ?? ''); ?></strong></div>
                        <div class="back-line"><span>Gender</span><strong><?php echo displayVal($student['gender']); ?></strong></div>
                        <?php if ($idFather): ?>
                        <div class="back-line"><span>Father</span><strong title="<?php echo htmlspecialchars($idFather['name']); ?>"><?php echo htmlspecialchars(idCardText($idFather['name'], 14)); ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="back-terms">Property of <?php echo htmlspecialchars($idSchoolName); ?>. If found, return to school office. Valid for current session only.</p>
            </div>
            <div class="card-bottom">
                <span class="back-footer-text"><i class="fas fa-shield-alt"></i> Authorized by <?php echo htmlspecialchars($idSchoolName); ?> · <?php echo $idAcademicYear; ?></span>
            </div>
        </div>
    </div>
</div>
