<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/settings_helpers.php';
require_once 'includes/student_helpers.php';
require_once 'includes/teacher_helpers.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

ensureErpSchema($pdo);
ensureSettingsSchema($pdo);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT c.*, s.* FROM certificates c INNER JOIN students s ON s.id = c.student_id WHERE c.id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    die('Certificate not found.');
}

$school = getSchoolProfile($pdo);
$logoUrl = schoolBrandingUrl($school['logo'] ?? '', 'admin');
$type = $row['cert_type'];

// Parent details from guardians
$guardians = getStudentGuardians($pdo, (int) $row['student_id']);
$father = '';
$mother = '';
foreach ($guardians as $gd) {
    $rel = strtolower($gd['relation'] ?? '');
    if ($rel === 'father' && trim($gd['name']) !== '') $father = trim($gd['name']);
    elseif ($rel === 'mother' && trim($gd['name']) !== '') $mother = trim($gd['name']);
}

// Gender-aware wording
$g = strtolower(trim($row['gender'] ?? ''));
$isM = $g === 'male';
$isF = $g === 'female';
$subj  = $isM ? 'He'  : ($isF ? 'She' : 'He/She');
$poss  = $isM ? 'his' : ($isF ? 'her' : 'his/her');
$refl  = $isM ? 'himself' : ($isF ? 'herself' : 'himself/herself');
$child = $isM ? 'son' : ($isF ? 'daughter' : 'son/daughter');
$honor = $isM ? 'Mr.' : ($isF ? 'Ms.' : '');

$section = trim($row['section'] ?? '') ?: 'A';

// Parentage sentence fragment
if ($father && $mother) {
    $parentage = "{$child} of Mr. {$father} and Mrs. {$mother}";
} elseif ($father) {
    $parentage = "{$child} of Mr. {$father}";
} elseif ($mother) {
    $parentage = "{$child} of Mrs. {$mother}";
} else {
    $parentage = "";
}

// Per-type presentation
$meta = [
    'TC'        => ['title' => 'Transfer Certificate',  'sub' => 'School Leaving Certificate', 'accent' => '#b45309', 'soft' => '#fff7ed', 'icon' => 'fa-right-from-bracket'],
    'Character' => ['title' => 'Character Certificate',  'sub' => 'Certificate of Good Conduct', 'accent' => '#6d28d9', 'soft' => '#f5f3ff', 'icon' => 'fa-award'],
    'Bonafide'  => ['title' => 'Bonafide Certificate',   'sub' => 'Certificate of Enrollment',  'accent' => '#047857', 'soft' => '#ecfdf5', 'icon' => 'fa-certificate'],
][$type] ?? ['title' => 'Certificate', 'sub' => '', 'accent' => '#047857', 'soft' => '#ecfdf5', 'icon' => 'fa-certificate'];

$accent = $meta['accent'];

function numberWords($n) {
    $n = (int) $n;
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    if ($n < 20) return $ones[$n];
    if ($n < 100) return $tens[intval($n / 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
    if ($n < 1000) return $ones[intval($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . numberWords($n % 100) : '');
    $th = intval($n / 1000);
    return numberWords($th) . ' Thousand' . ($n % 1000 ? ' ' . numberWords($n % 1000) : '');
}

function dateInWords($date) {
    $ts = strtotime($date);
    if (!$ts) return '';
    $day = (int) date('j', $ts);
    $suffix = 'th';
    if (!in_array($day % 100, [11, 12, 13])) {
        $suffix = [1 => 'st', 2 => 'nd', 3 => 'rd'][$day % 10] ?? 'th';
    }
    return $day . $suffix . ' ' . date('F', $ts) . ', ' . date('Y', $ts) . ' (' . numberWords($day) . ' ' . date('F', $ts) . ' ' . numberWords((int) date('Y', $ts)) . ')';
}

$dobFigures = !empty($row['dob']) ? formatDobDisplay($row['dob']) : '—';
$dobWords = !empty($row['dob']) ? dateInWords($row['dob']) : '';
$issueWords = dateInWords($row['issue_date']);
$nameHonor = $honor ? ($honor . ' ') : '';

// Signatures: Principal (default authority) + Class Teacher (if any)
$principalSig = getDefaultAuthoritySignature($pdo);
$principalSigUrl = schoolBrandingUrl($principalSig['signature'] ?? '', 'admin');
$principalName = $principalSig['name'] ?? ($school['principal'] ?? '');
$principalRole = $principalSig['designation'] ?? 'Principal';

$classTeacher = getClassTeacherForClass($pdo, $row['class'], $section);
$classTeacherSigUrl = ($classTeacher && !empty($classTeacher['signature'])) ? schoolBrandingUrl($classTeacher['signature'], 'admin') : '';
$classTeacherName = $classTeacher['name'] ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($meta['title']); ?> — <?php echo htmlspecialchars($school['name']); ?></title>
    <?php if (!empty($school['favicon'])): ?><link rel="icon" href="<?php echo htmlspecialchars(schoolBrandingUrl($school['favicon'], 'admin')); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Playfair+Display:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --accent: <?php echo $accent; ?>; --soft: <?php echo $meta['soft']; ?>; --gold: #c19a3e; --ink: #1f2937; }
        body { font-family: 'Inter', sans-serif; background: #e9edf3; padding: 26px 16px; color: var(--ink); }

        .cert-print-bar { max-width: 860px; margin: 0 auto 20px; display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .cert-print-bar .title { font-size: 1.05rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 9px; }
        .cert-print-bar .title i { color: var(--accent); }
        .cert-print-bar .actions { display: flex; gap: 10px; }
        .cert-print-bar button, .cert-print-bar a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-size: 0.88rem; text-decoration: none; color: #334155; font-family: inherit; font-weight: 600; transition: all .15s; }
        .cert-print-bar a:hover { border-color: var(--accent); color: var(--accent); }
        .cert-print-bar button.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .cert-print-bar button.primary:hover { filter: brightness(1.05); transform: translateY(-1px); }

        .cert-sheet {
            position: relative; max-width: 860px; margin: 0 auto; background:
                radial-gradient(circle at top left, var(--soft), transparent 45%),
                radial-gradient(circle at bottom right, var(--soft), transparent 45%), #fffdf8;
            padding: 20px; border: 2px solid var(--gold);
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.16);
        }
        .cert-frame { border: 1.5px solid var(--accent); position: relative; padding: 46px 52px 40px; overflow: hidden; }
        .cert-frame::before { content: ''; position: absolute; inset: 6px; border: 1px solid rgba(193, 154, 62, 0.55); pointer-events: none; }

        .cert-corner { position: absolute; width: 34px; height: 34px; color: var(--gold); font-size: 1.5rem; z-index: 2; opacity: .9; }
        .cert-corner.tl { top: 12px; left: 12px; } .cert-corner.tr { top: 12px; right: 12px; }
        .cert-corner.bl { bottom: 12px; left: 12px; } .cert-corner.br { bottom: 12px; right: 12px; }

        .cert-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 0; }
        .cert-watermark i { font-size: 20rem; color: var(--accent); opacity: 0.04; }
        .cert-watermark img { width: 320px; height: 320px; object-fit: contain; opacity: 0.05; }

        .cert-inner { position: relative; z-index: 1; }

        .cert-header { text-align: center; padding-bottom: 18px; margin-bottom: 8px; border-bottom: 2px solid var(--gold); position: relative; }
        .cert-logo { width: 76px; height: 76px; margin: 0 auto 12px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: var(--soft); color: var(--accent); font-size: 2rem; border: 2px solid var(--gold); }
        .cert-logo img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
        .cert-school { font-family: 'Playfair Display', serif; font-size: 2.1rem; font-weight: 800; letter-spacing: 0.01em; color: #111827; line-height: 1.1; }
        .cert-tagline { font-family: 'Cormorant Garamond', serif; font-size: 1.05rem; color: #6b7280; margin-top: 4px; font-style: italic; }
        .cert-contact { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 4px 18px; justify-content: center; font-size: 0.78rem; color: #6b7280; }
        .cert-contact span { display: inline-flex; align-items: center; gap: 5px; }
        .cert-contact i { color: var(--accent); }
        .cert-affiliation { position: absolute; top: 0; right: 0; font-size: 0.72rem; font-weight: 700; color: var(--accent); background: var(--soft); border: 1px solid var(--gold); padding: 4px 10px; border-radius: 6px; }

        .cert-ribbon { text-align: center; margin: 26px 0 8px; }
        .cert-ribbon .type { display: inline-flex; align-items: center; gap: 12px; font-family: 'Playfair Display', serif; font-size: 1.55rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent); padding: 0 18px; }
        .cert-ribbon .type::before, .cert-ribbon .type::after { content: ''; width: 46px; height: 2px; background: linear-gradient(90deg, transparent, var(--gold)); }
        .cert-ribbon .type::after { background: linear-gradient(90deg, var(--gold), transparent); }
        .cert-ribbon .sub { font-family: 'Cormorant Garamond', serif; font-size: 1rem; color: #9ca3af; letter-spacing: 0.16em; text-transform: uppercase; margin-top: 2px; }

        .cert-meta { display: flex; justify-content: space-between; margin: 20px 0 6px; font-size: 0.86rem; color: #4b5563; }
        .cert-meta strong { color: var(--ink); }

        .cert-body { font-family: 'Cormorant Garamond', serif; font-size: 1.28rem; line-height: 2.05; color: #1f2937; text-align: justify; margin-top: 10px; }
        .cert-body p { margin-bottom: 14px; }
        .cert-body .nm { font-weight: 700; color: #111827; border-bottom: 1px dotted #9ca3af; padding: 0 3px; }
        .cert-body .hl { font-weight: 700; color: var(--accent); }

        .cert-tc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 18px 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; font-family: 'Inter', sans-serif; }
        .cert-tc-grid .cell { padding: 11px 16px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; gap: 12px; font-size: 0.9rem; }
        .cert-tc-grid .cell:nth-child(odd) { border-right: 1px solid #eef2f7; }
        .cert-tc-grid .cell span { color: #6b7280; }
        .cert-tc-grid .cell strong { color: #111827; text-align: right; }
        .cert-tc-grid .cell.full { grid-column: 1 / -1; border-right: none; }

        .cert-purpose { margin-top: 14px; font-family: 'Inter', sans-serif; font-size: 0.9rem; color: #4b5563; background: var(--soft); border-left: 3px solid var(--accent); padding: 12px 16px; border-radius: 0 8px 8px 0; }

        .cert-sign { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 64px; gap: 24px; }
        .cert-sign .block { text-align: center; flex: 1; }
        .cert-sign .line { border-top: 1.5px solid #9ca3af; margin: 0 auto 6px; width: 82%; }
        .cert-sign .role { font-size: 0.82rem; color: #6b7280; font-weight: 600; font-family: 'Inter', sans-serif; }
        .cert-sign .name { font-size: 0.9rem; color: #111827; font-weight: 700; font-family: 'Inter', sans-serif; margin-bottom: 3px; }
        .cert-sign .sign-img { display: block; max-height: 54px; max-width: 78%; object-fit: contain; margin: 0 auto -4px; }

        .cert-seal { width: 92px; height: 92px; border-radius: 50%; border: 2px dashed var(--accent); display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--accent); text-align: center; transform: rotate(-8deg); flex-shrink: 0; background: var(--soft); }
        .cert-seal i { font-size: 1.5rem; }
        .cert-seal small { font-size: 0.56rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; margin-top: 3px; }

        .cert-footer-note { text-align: center; margin-top: 26px; font-family: 'Inter', sans-serif; font-size: 0.72rem; color: #9ca3af; }

        @media (max-width: 640px) {
            .cert-frame { padding: 30px 22px; }
            .cert-school { font-size: 1.5rem; }
            .cert-ribbon .type { font-size: 1.15rem; }
            .cert-body { font-size: 1.1rem; line-height: 1.9; }
            .cert-tc-grid { grid-template-columns: 1fr; }
            .cert-tc-grid .cell:nth-child(odd) { border-right: none; }
            .cert-sign { flex-direction: column; align-items: center; gap: 32px; }
            .cert-affiliation { position: static; display: inline-block; margin-bottom: 10px; }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .cert-print-bar { display: none !important; }
            .cert-sheet { box-shadow: none; max-width: 100%; padding: 12px; }
            .cert-frame { padding: 30px 40px; }
            .cert-body { font-size: 1.14rem; line-height: 1.72; }
            .cert-sign { margin-top: 44px; }
            @page { size: A4 portrait; margin: 10mm; }
        }
        <?php if ($type === 'TC'): ?>
        /* Transfer Certificate is the longest — compact it to fit a single A4 page */
        @media print {
            .cert-sheet { padding: 8px; border-width: 1.5px; }
            .cert-frame { padding: 18px 30px; }
            .cert-frame::before { inset: 4px; }
            .cert-header { padding-bottom: 8px; }
            .cert-logo { width: 54px; height: 54px; font-size: 1.4rem; margin-bottom: 6px; border-width: 1.5px; }
            .cert-school { font-size: 1.5rem; }
            .cert-tagline { font-size: 0.88rem; margin-top: 2px; }
            .cert-contact { margin-top: 5px; font-size: 0.7rem; gap: 2px 12px; }
            .cert-ribbon { margin: 12px 0 4px; }
            .cert-ribbon .type { font-size: 1.2rem; }
            .cert-ribbon .sub { font-size: 0.82rem; margin-top: 0; }
            .cert-meta { margin: 10px 0 2px; font-size: 0.8rem; }
            .cert-body { font-size: 0.98rem; line-height: 1.5; margin-top: 6px; }
            .cert-body p { margin-bottom: 8px; }
            .cert-tc-grid { margin: 12px 0; }
            .cert-tc-grid .cell { padding: 6px 14px; font-size: 0.82rem; }
            .cert-purpose { margin-top: 10px; padding: 8px 14px; font-size: 0.82rem; }
            .cert-sign { margin-top: 26px; }
            .cert-sign .sign-img { max-height: 40px; }
            .cert-seal { width: 78px; height: 78px; }
            .cert-footer-note { margin-top: 12px; font-size: 0.66rem; }
            .cert-corner { font-size: 1.2rem; }
        }
        <?php endif; ?>
    </style>
</head>
<body>
<div class="cert-print-bar">
    <div class="title"><i class="fas <?php echo $meta['icon']; ?>"></i> <?php echo htmlspecialchars($meta['title']); ?></div>
    <div class="actions">
        <a href="certificates.php"><i class="fas fa-arrow-left"></i> Back</a>
        <button type="button" class="primary" onclick="window.print()"><i class="fas fa-download"></i> Download / Print</button>
    </div>
</div>

<div class="cert-sheet">
    <div class="cert-frame">
        <i class="fas fa-certificate cert-corner tl"></i>
        <i class="fas fa-certificate cert-corner tr"></i>
        <i class="fas fa-certificate cert-corner bl"></i>
        <i class="fas fa-certificate cert-corner br"></i>

        <div class="cert-watermark">
            <?php if ($logoUrl): ?><img src="<?php echo htmlspecialchars($logoUrl); ?>" alt=""><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
        </div>

        <div class="cert-inner">
            <div class="cert-header">
                <?php if (!empty($school['affiliation'])): ?><span class="cert-affiliation"><?php echo htmlspecialchars($school['affiliation']); ?></span><?php endif; ?>
                <div class="cert-logo">
                    <?php if ($logoUrl): ?><img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
                </div>
                <div class="cert-school"><?php echo htmlspecialchars($school['name']); ?></div>
                <?php if ($school['tagline']): ?><div class="cert-tagline"><?php echo htmlspecialchars($school['tagline']); ?></div><?php endif; ?>
                <div class="cert-contact">
                    <?php if ($school['address']): ?><span><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($school['address']); ?></span><?php endif; ?>
                    <?php if ($school['phone']): ?><span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($school['phone']); ?></span><?php endif; ?>
                    <?php if ($school['email']): ?><span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($school['email']); ?></span><?php endif; ?>
                </div>
            </div>

            <div class="cert-ribbon">
                <div class="type"><i class="fas <?php echo $meta['icon']; ?>" style="font-size:1.1rem"></i> <?php echo htmlspecialchars($meta['title']); ?></div>
                <?php if ($meta['sub']): ?><div class="sub"><?php echo htmlspecialchars($meta['sub']); ?></div><?php endif; ?>
            </div>

            <div class="cert-meta">
                <div>Certificate No: <strong><?php echo htmlspecialchars($row['certificate_no']); ?></strong></div>
                <div>Date of Issue: <strong><?php echo date('d M Y', strtotime($row['issue_date'])); ?></strong></div>
            </div>

            <div class="cert-body">
                <?php if ($type === 'TC'): ?>
                <p>This is to certify that <span class="nm"><?php echo $nameHonor . htmlspecialchars($row['name']); ?></span><?php echo $parentage ? ', ' . htmlspecialchars($parentage) . ',' : ','; ?> bearing Admission No. <span class="nm"><?php echo htmlspecialchars($row['ad_no']); ?></span>, was a bonafide student of this institution. The particulars of the student, as per the school records, are given below:</p>

                <div class="cert-tc-grid">
                    <div class="cell"><span>Admission Number</span><strong><?php echo htmlspecialchars($row['ad_no']); ?></strong></div>
                    <div class="cell"><span>Roll Number</span><strong><?php echo htmlspecialchars($row['roll'] ?: '—'); ?></strong></div>
                    <?php if ($father): ?><div class="cell"><span>Father's Name</span><strong><?php echo htmlspecialchars($father); ?></strong></div><?php endif; ?>
                    <?php if ($mother): ?><div class="cell"><span>Mother's Name</span><strong><?php echo htmlspecialchars($mother); ?></strong></div><?php endif; ?>
                    <div class="cell"><span>Class Last Studied</span><strong><?php echo htmlspecialchars($row['class']); ?> (<?php echo htmlspecialchars($section); ?>)</strong></div>
                    <div class="cell"><span>Category</span><strong><?php echo htmlspecialchars($row['category'] ?: '—'); ?></strong></div>
                    <div class="cell full"><span>Date of Birth</span><strong><?php echo htmlspecialchars($dobFigures); ?></strong></div>
                    <?php if ($dobWords): ?><div class="cell full"><span>Date of Birth (in words)</span><strong style="font-weight:600"><?php echo htmlspecialchars($dobWords); ?></strong></div><?php endif; ?>
                    <div class="cell"><span>Date of Leaving</span><strong><?php echo date('d M Y', strtotime($row['issue_date'])); ?></strong></div>
                    <div class="cell"><span>Conduct</span><strong>Good</strong></div>
                </div>

                <p><?php echo $subj; ?> has cleared all the dues payable to the school and no certificate or document of <?php echo $poss; ?> is pending with the institution. <?php echo $subj; ?> is hereby granted this <span class="hl">Transfer Certificate</span> and we wish <?php echo $poss; ?> success in all future endeavours.</p>

                <?php elseif ($type === 'Character'): ?>
                <p>This is to certify that <span class="nm"><?php echo $nameHonor . htmlspecialchars($row['name']); ?></span><?php echo $parentage ? ', ' . htmlspecialchars($parentage) . ',' : ','; ?> bearing Admission No. <span class="nm"><?php echo htmlspecialchars($row['ad_no']); ?></span>, has been a student of <span class="hl"><?php echo htmlspecialchars($school['name']); ?></span> in Class <span class="nm"><?php echo htmlspecialchars($row['class']); ?></span> (Section <?php echo htmlspecialchars($section); ?>).</p>
                <p>During <?php echo $poss; ?> period of study in this institution, <?php echo strtolower($subj); ?> has always borne a <span class="hl">good moral character</span> and conducted <?php echo $refl; ?> in an exemplary manner. <?php echo $subj; ?> has been sincere, disciplined and respectful towards teachers and fellow students.</p>
                <p>We wish <?php echo $poss; ?> a bright and successful future.</p>

                <?php else: /* Bonafide */ ?>
                <p>This is to certify that <span class="nm"><?php echo $nameHonor . htmlspecialchars($row['name']); ?></span><?php echo $parentage ? ', ' . htmlspecialchars($parentage) . ',' : ','; ?> bearing Admission No. <span class="nm"><?php echo htmlspecialchars($row['ad_no']); ?></span> and Roll No. <span class="nm"><?php echo htmlspecialchars($row['roll'] ?: '—'); ?></span>, is a <span class="hl">bonafide student</span> of this institution.</p>
                <p><?php echo $subj; ?> is currently studying in Class <span class="nm"><?php echo htmlspecialchars($row['class']); ?></span> (Section <?php echo htmlspecialchars($section); ?>)<?php if (!empty($row['dob'])): ?> and <?php echo $poss; ?> date of birth, as per the school records, is <span class="nm"><?php echo htmlspecialchars($dobFigures); ?></span><?php endif; ?>. This certificate is issued upon request for official purposes.</p>
                <?php endif; ?>

                <?php if (!empty($row['purpose'])): ?>
                <div class="cert-purpose"><i class="fas fa-circle-info" style="color:var(--accent)"></i> <strong>Purpose:</strong> <?php echo htmlspecialchars($row['purpose']); ?></div>
                <?php endif; ?>
            </div>

            <div class="cert-sign">
                <div class="block">
                    <?php if ($classTeacherSigUrl): ?><img class="sign-img" src="<?php echo htmlspecialchars($classTeacherSigUrl); ?>" alt="Signature"><?php endif; ?>
                    <?php if ($classTeacherName): ?><div class="name"><?php echo htmlspecialchars($classTeacherName); ?></div><?php endif; ?>
                    <div class="line"></div>
                    <div class="role">Class Teacher</div>
                </div>
                <div class="cert-seal">
                    <i class="fas fa-stamp"></i>
                    <small>School Seal</small>
                </div>
                <div class="block">
                    <?php if ($principalSigUrl): ?><img class="sign-img" src="<?php echo htmlspecialchars($principalSigUrl); ?>" alt="Signature"><?php endif; ?>
                    <?php if ($principalName): ?><div class="name"><?php echo htmlspecialchars($principalName); ?></div><?php endif; ?>
                    <div class="line"></div>
                    <div class="role"><?php echo htmlspecialchars($principalRole); ?></div>
                </div>
            </div>

            <div class="cert-footer-note">This certificate is issued based on the records maintained by the institution.</div>
        </div>
    </div>
</div>
</body>
</html>
