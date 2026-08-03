<?php
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';
require_once 'includes/settings_helpers.php';

ensureStudentSchema($pdo);
ensureSettingsSchema($pdo);
$schoolProfile = getSchoolProfile($pdo);
$schoolLogoUrl = schoolBrandingUrl($schoolProfile['logo'] ?? '', 'admin');
$schoolDisplayName = $schoolProfile['name'] ?: 'EduDash School';
$signatory = getDefaultAuthoritySignature($pdo);
$signatureUrl = schoolBrandingUrl($signatory['signature'] ?? '', 'admin');
$signatureRole = $signatory['designation'] ?? 'Principal';

if (!isset($_GET['id'])) {
    die('Student ID required.');
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    die('Student not found.');
}

$photo_url = getStudentPhotoUrl($student);
$guardians = getStudentGuardians($pdo, $id);
$father = $mother = $guardian = null;
foreach ($guardians as $g) {
    if ($g['relation'] === 'Father') $father = $g;
    if ($g['relation'] === 'Mother') $mother = $g;
    if ($g['relation'] === 'Guardian') $guardian = $g;
}

$emergency_name = $father['name'] ?? ($mother['name'] ?? ($guardian['name'] ?? '-'));
$emergency_phone = $father['phone'] ?? ($mother['phone'] ?? ($guardian['phone'] ?? $student['mobile']));
$academic_year = date('Y') . '/' . (date('Y') + 1);

$raw_address = !empty($student['current_address']) ? $student['current_address'] : ($student['permanent_address'] ?? '');
$address = trim($raw_address) !== '' ? $raw_address : 'EduDash School Campus, Main Road, City - 110001';

function idCardText($text, $max = 40) {
    $text = trim((string) $text);
    if ($text === '' || $text === '-') return '-';
    if (mb_strlen($text) > $max) {
        return mb_substr($text, 0, $max - 1) . '…';
    }
    return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card — <?php echo htmlspecialchars($student['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="id-card-page">
    <div class="id-card-print-actions">
        <button type="button" class="id-card-print-btn outline" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
        <button type="button" class="id-card-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Both Sides</button>
    </div>

    <div class="id-card-page-header">
        <h2>Student ID Card — <?php echo htmlspecialchars($student['name']); ?></h2>
        <p>Front &amp; Back · Academic Year <?php echo $academic_year; ?></p>
    </div>

    <div class="id-cards-wrap">
        <div class="id-card-col">
            <span class="id-card-label">Front Side</span>
            <div class="id-card id-front">
                <div class="card-top">
                    <div class="school-logo"><?php if ($schoolLogoUrl): ?><img src="<?php echo htmlspecialchars($schoolLogoUrl); ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?></div>
                    <div class="school-info">
                        <h1><?php echo htmlspecialchars($schoolDisplayName); ?></h1>
                        <p>Student Identity Card · Session <?php echo $academic_year; ?></p>
                    </div>
                    <span class="card-type-badge">Student</span>
                </div>
                <div class="card-main">
                    <div class="photo-block">
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Photo" class="id-photo">
                        <span class="photo-tag">Photo</span>
                    </div>
                    <div class="student-block">
                        <div class="student-name" title="<?php echo htmlspecialchars($student['name']); ?>">
                            <?php echo htmlspecialchars($student['name']); ?>
                        </div>
                        <div class="student-meta">
                            <span class="meta-pill">Class <?php echo htmlspecialchars(idCardText($student['class'], 18)); ?></span>
                            <span class="meta-pill">Sec <?php echo htmlspecialchars($student['section'] ?? 'A'); ?></span>
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
                        <?php if ($signatureUrl): ?><img class="sign-img" src="<?php echo htmlspecialchars($signatureUrl); ?>" alt="Signature"><?php endif; ?>
                        <div class="sign-line"></div>
                        <span><?php echo htmlspecialchars($signatureRole); ?></span>
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
                            <p title="<?php echo htmlspecialchars($address); ?>"><?php echo htmlspecialchars(idCardText($address, 90)); ?></p>
                        </div>
                        <div class="back-box">
                            <h3><i class="fas fa-phone"></i> Emergency</h3>
                            <div class="back-line"><span>Name</span><strong title="<?php echo htmlspecialchars($emergency_name); ?>"><?php echo htmlspecialchars(idCardText($emergency_name, 16)); ?></strong></div>
                            <div class="back-line"><span>Phone</span><strong><?php echo htmlspecialchars($emergency_phone); ?></strong></div>
                        </div>
                        <div class="back-box">
                            <h3><i class="fas fa-user"></i> Details</h3>
                            <div class="back-line"><span>Category</span><strong><?php echo displayVal($student['category']); ?></strong></div>
                            <div class="back-line"><span>Gender</span><strong><?php echo displayVal($student['gender']); ?></strong></div>
                            <?php if ($father): ?>
                            <div class="back-line"><span>Father</span><strong title="<?php echo htmlspecialchars($father['name']); ?>"><?php echo htmlspecialchars(idCardText($father['name'], 14)); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="back-terms">Property of EduDash School. If found, return to school office. Valid for current session only.</p>
                </div>
                <div class="card-bottom">
                    <span class="back-footer-text"><i class="fas fa-shield-alt"></i> Authorized by EduDash School · <?php echo $academic_year; ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
