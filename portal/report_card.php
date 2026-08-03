<?php
require_once 'includes/init.php';

$examId = (int) ($_GET['exam'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND class_name = ? AND status = 'Active'");
$stmt->execute([$examId, $student['class']]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
    http_response_code(404);
    die('Report card not available for this exam.');
}

$result = getStudentExamResult($pdo, (int) $student['id'], $examId);
$logoUrl = schoolBrandingUrl($school['logo'] ?? '', 'portal');
$brandName = $school['name'] ?: 'School';
$section = trim($student['section'] ?? '') ?: 'A';

$principalSig = getDefaultAuthoritySignature($pdo);
$principalSigUrl = schoolBrandingUrl($principalSig['signature'] ?? '', 'portal');
$principalName = $principalSig['name'] ?? ($school['principal'] ?? '');
$principalRole = $principalSig['designation'] ?? 'Principal';

$autoPrint = isset($_GET['print']);
$pct = $result['percentage'];
$isPass = $result['result'] === 'Pass';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card — <?php echo htmlspecialchars($student['name']); ?></title>
    <?php if (!empty($sp_favicon_url)): ?><link rel="icon" href="<?php echo htmlspecialchars($sp_favicon_url); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #eef1f6; color: #0f172a; padding: 28px 16px; }
        .rc-toolbar { max-width: 820px; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .rc-toolbar h1 { font-size: 1.15rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 9px; }
        .rc-toolbar h1 i { color: #7c3aed; }
        .rc-actions { display: flex; gap: 10px; }
        .rc-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 11px; font-size: 0.88rem; font-weight: 700; cursor: pointer; border: 1px solid transparent; text-decoration: none; transition: all .15s; font-family: inherit; }
        .rc-btn-primary { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; box-shadow: 0 8px 20px rgba(124,58,237,.3); }
        .rc-btn-primary:hover { transform: translateY(-1px); }
        .rc-btn-ghost { background: #fff; border-color: #e2e8f0; color: #334155; }
        .rc-btn-ghost:hover { border-color: #7c3aed; color: #7c3aed; }

        .rc-sheet { max-width: 820px; margin: 0 auto; background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 60px rgba(15,23,42,.12); }
        .rc-band { height: 6px; background: linear-gradient(90deg, #7c3aed, #a855f7, #38bdf8); }
        .rc-head { display: flex; align-items: center; gap: 18px; padding: 28px 34px 22px; border-bottom: 2px solid #ede9fe; }
        .rc-logo { width: 64px; height: 64px; border-radius: 15px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; overflow: hidden; flex-shrink: 0; }
        .rc-logo img { width: 100%; height: 100%; object-fit: contain; padding: 7px; background: #fff; }
        .rc-head-info { flex: 1; min-width: 0; }
        .rc-head-info h2 { font-size: 1.5rem; font-weight: 800; }
        .rc-head-info p { font-size: 0.82rem; color: #64748b; margin-top: 2px; }
        .rc-title-badge { text-align: right; }
        .rc-title-badge span { display: inline-block; font-size: 0.72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #7c3aed; background: #f3e8ff; padding: 6px 12px; border-radius: 8px; }
        .rc-title-badge small { display: block; margin-top: 6px; color: #64748b; font-size: 0.78rem; }

        .rc-student { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; padding: 20px 34px; background: #faf9ff; }
        .rc-student .f span { display: block; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
        .rc-student .f strong { font-size: 0.98rem; color: #0f172a; }

        .rc-body { padding: 24px 34px 8px; }
        .rc-table { width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .rc-table thead th { background: #1e1b4b; color: #fff; padding: 12px 16px; text-align: left; font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .rc-table th.c, .rc-table td.c { text-align: center; }
        .rc-table tbody td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; }
        .rc-table tbody tr:nth-child(even) { background: #faf9ff; }
        .rc-table tfoot td { padding: 13px 16px; font-weight: 800; background: #f3e8ff; color: #4c1d95; }
        .rc-gc { display: inline-block; min-width: 34px; padding: 2px 8px; border-radius: 6px; background: #ecfdf5; color: #059669; font-weight: 700; font-size: 0.82rem; }
        .rc-gc.fail { background: #fef2f2; color: #dc2626; }

        .rc-summary { display: flex; gap: 14px; flex-wrap: wrap; padding: 20px 34px; }
        .rc-sum { flex: 1; min-width: 130px; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; }
        .rc-sum span { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
        .rc-sum strong { font-size: 1.4rem; font-weight: 800; }
        .rc-sum.pass strong { color: #059669; } .rc-sum.fail strong { color: #dc2626; }

        .rc-foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; padding: 34px 34px 30px; flex-wrap: wrap; }
        .rc-sign { text-align: center; }
        .rc-sign img { display: block; max-height: 46px; max-width: 160px; object-fit: contain; margin: 0 auto -2px; }
        .rc-sign .nm { font-size: 0.82rem; font-weight: 700; color: #0f172a; }
        .rc-sign .ln { width: 150px; border-top: 1.5px solid #cbd5e1; margin: 4px auto 5px; }
        .rc-sign .role { font-size: 0.78rem; color: #64748b; font-weight: 600; }

        @media (max-width: 640px) {
            .rc-head { flex-direction: column; text-align: center; }
            .rc-title-badge { text-align: center; }
            .rc-student { grid-template-columns: 1fr 1fr; }
        }
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .rc-sheet { box-shadow: none; border-radius: 0; max-width: 100%; }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="rc-toolbar no-print">
        <h1><i class="fas fa-file-lines"></i> Report Card</h1>
        <div class="rc-actions">
            <a href="results.php?exam=<?php echo $examId; ?>" class="rc-btn rc-btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
            <button type="button" class="rc-btn rc-btn-primary" onclick="window.print()"><i class="fas fa-download"></i> Download / Print</button>
        </div>
    </div>

    <div class="rc-sheet">
        <div class="rc-band"></div>
        <div class="rc-head">
            <div class="rc-logo"><?php if ($logoUrl): ?><img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo"><?php else: ?><i class="fas fa-graduation-cap"></i><?php endif; ?></div>
            <div class="rc-head-info">
                <h2><?php echo htmlspecialchars($brandName); ?></h2>
                <?php if (!empty($school['tagline'])): ?><p><?php echo htmlspecialchars($school['tagline']); ?></p><?php endif; ?>
                <?php if (!empty($school['address'])): ?><p><?php echo htmlspecialchars($school['address']); ?></p><?php endif; ?>
            </div>
            <div class="rc-title-badge"><span>Report Card</span><small><?php echo htmlspecialchars($exam['name']); ?></small></div>
        </div>

        <div class="rc-student">
            <div class="f"><span>Name</span><strong><?php echo htmlspecialchars($student['name']); ?></strong></div>
            <div class="f"><span>Admission No</span><strong><?php echo htmlspecialchars($student['ad_no']); ?></strong></div>
            <div class="f"><span>Class &amp; Section</span><strong><?php echo htmlspecialchars($student['class']); ?> · <?php echo htmlspecialchars($section); ?></strong></div>
            <div class="f"><span>Roll No</span><strong><?php echo htmlspecialchars($student['roll']); ?></strong></div>
        </div>

        <div class="rc-body">
            <table class="rc-table">
                <thead><tr><th>Subject</th><th class="c">Max Marks</th><th class="c">Obtained</th><th class="c">Grade</th></tr></thead>
                <tbody>
                    <?php if (empty($result['marks'])): ?>
                    <tr><td colspan="4" style="text-align:center;color:#94a3b8">No subjects configured for this exam.</td></tr>
                    <?php else: foreach ($result['marks'] as $m):
                        $entered = $m['marks_obtained'] !== null && $m['marks_obtained'] !== '';
                        $sPct = ($entered && (int) $m['max_marks'] > 0) ? ((float) $m['marks_obtained'] / (int) $m['max_marks'] * 100) : 0;
                        $sGrade = $m['grade'] ?: ($entered ? examGradeFromPercent($sPct) : '—');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['subject_name']); ?></td>
                        <td class="c"><?php echo (int) $m['max_marks']; ?></td>
                        <td class="c"><?php echo $entered ? rtrim(rtrim(number_format((float) $m['marks_obtained'], 2), '0'), '.') : '—'; ?></td>
                        <td class="c"><span class="rc-gc<?php echo ($entered && $sPct < 33) ? ' fail' : ''; ?>"><?php echo htmlspecialchars($sGrade); ?></span></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
                <tfoot>
                    <tr><td>Total</td><td class="c"><?php echo (int) $result['total_max']; ?></td><td class="c"><?php echo rtrim(rtrim(number_format($result['total_obtained'], 2), '0'), '.'); ?></td><td class="c"><?php echo $pct; ?>%</td></tr>
                </tfoot>
            </table>
        </div>

        <div class="rc-summary">
            <div class="rc-sum"><span>Percentage</span><strong><?php echo $pct; ?>%</strong></div>
            <div class="rc-sum"><span>Overall Grade</span><strong><?php echo htmlspecialchars($result['grade']); ?></strong></div>
            <div class="rc-sum <?php echo $isPass ? 'pass' : 'fail'; ?>"><span>Result</span><strong><?php echo htmlspecialchars($result['result']); ?></strong></div>
        </div>

        <div class="rc-foot">
            <div class="rc-sign">
                <div class="ln"></div>
                <div class="role">Class Teacher</div>
            </div>
            <div class="rc-sign">
                <?php if ($principalSigUrl): ?><img src="<?php echo htmlspecialchars($principalSigUrl); ?>" alt="Signature"><?php endif; ?>
                <?php if ($principalName): ?><div class="nm"><?php echo htmlspecialchars($principalName); ?></div><?php endif; ?>
                <div class="ln"></div>
                <div class="role"><?php echo htmlspecialchars($principalRole); ?></div>
            </div>
        </div>
    </div>

    <?php if ($autoPrint): ?><script>window.addEventListener('load', function(){ window.print(); });</script><?php endif; ?>
</body>
</html>
