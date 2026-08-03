<?php
require_once 'includes/init.php';
$idAcademicYearHead = date('Y') . '/' . (date('Y') + 1);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card — <?php echo htmlspecialchars($student['name']); ?></title>
    <?php if (!empty($sp_favicon_url)): ?><link rel="icon" href="<?php echo htmlspecialchars($sp_favicon_url); ?>"><?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/id-card.css">
</head>
<body class="id-card-page">
    <div class="id-card-print-actions">
        <a href="profile.php" class="id-card-print-btn outline"><i class="fas fa-arrow-left"></i> Back</a>
        <button type="button" class="id-card-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Both Sides</button>
    </div>

    <div class="id-card-page-header">
        <h2>Student ID Card — <?php echo htmlspecialchars($student['name']); ?></h2>
        <p>Front &amp; Back · Academic Year <?php echo $idAcademicYearHead; ?></p>
    </div>

    <?php require 'includes/id_card_body.php'; ?>

    <?php if (isset($_GET['print'])): ?><script>window.addEventListener('load', function(){ window.print(); });</script><?php endif; ?>
</body>
</html>
