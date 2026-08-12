<?php
$page_title = "Edit Student";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);
handleRollApiRequest($pdo);
handleClassApiRequest($pdo);

if (!isset($_GET['id'])) {
    header('Location: students.php');
    exit;
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    require_once 'includes/header.php';
    echo "<div class='table-container student-not-found'><h3>Student not found.</h3></div>";
    require_once 'includes/footer.php';
    exit;
}

$guardians = getStudentGuardians($pdo, $id);
$form_data = studentFromRow($student, $guardians);
$class_options = getClassOptions($pdo);
$category_options = getCategoryOptions($pdo);
$mode = 'edit';
$ad_no = $student['ad_no'];
$photo_url = getStudentPhotoUrl($student);
$exclude_student_id = $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = array_keys(getDefaultStudentFormData());
    foreach ($keys as $key) {
        $form_data[$key] = trim($_POST[$key] ?? '');
    }

    $errors = [];
    if ($form_data['name'] === '') $errors[] = 'Student name is required.';
    if ($form_data['class'] === '') $errors[] = 'Class is required.';
    if ($form_data['mobile'] === '') $errors[] = 'Mobile is required.';
    $errors = array_merge($errors, validateStudentRoll($pdo, $form_data['roll'], $form_data['class'], $form_data['section'], $id));
    $errors = array_merge($errors, validateClassAndSection($pdo, $form_data['class'], $form_data['section']));

    if (empty($errors)) {
        try {
            $photo = $student['photo'];
            if (!empty($_FILES['photo']['name'])) {
                $uploaded = uploadStudentPhoto($_FILES['photo'], $ad_no);
                if ($uploaded === false) {
                    $_SESSION['error_msg'] = 'Invalid photo.';
                    header('Location: student_edit.php?id=' . $id);
                    exit;
                }
                if ($uploaded) $photo = $uploaded;
            }

            $stmt = $pdo->prepare("UPDATE students SET
                name=?, roll=?, class=?, section=?, dob=?, gender=?, mobile=?, email=?, category=?, status=?,
                photo=?, current_address=?, permanent_address=?,
                current_address_line=?, current_city=?, current_state=?, current_country=?, current_pincode=?,
                permanent_address_line=?, permanent_city=?, permanent_state=?, permanent_country=?, permanent_pincode=?,
                previous_school=?, previous_class=?, previous_year=?, previous_tc_no=?,
                bank_name=?, bank_branch=?, ifsc_code=?, blood_group=?, height=?, weight=?,
                hostel_name=?, room_no=?, room_type=?, description=?
                WHERE id=?");

            if (($_POST['has_previous_schooling'] ?? '0') !== '1') {
                $form_data['previous_school'] = '';
                $form_data['previous_class'] = '';
                $form_data['previous_year'] = '';
                $form_data['previous_tc_no'] = '';
            }

            applyStudentAddressFromPost($form_data, $_POST);

            $stmt->execute([
                $form_data['name'], $form_data['roll'], $form_data['class'], $form_data['section'],
                $form_data['dob'], $form_data['gender'], $form_data['mobile'], $form_data['email'],
                $form_data['category'], $form_data['status'], $photo,
                $form_data['current_address'], $form_data['permanent_address'],
                $form_data['current_address_line'], $form_data['current_city'], $form_data['current_state'], $form_data['current_country'], $form_data['current_pincode'],
                $form_data['permanent_address_line'], $form_data['permanent_city'], $form_data['permanent_state'], $form_data['permanent_country'], $form_data['permanent_pincode'],
                $form_data['previous_school'], $form_data['previous_class'], $form_data['previous_year'], $form_data['previous_tc_no'],
                $form_data['bank_name'], $form_data['bank_branch'], $form_data['ifsc_code'],
                $form_data['blood_group'], $form_data['height'], $form_data['weight'],
                $form_data['hostel_name'], $form_data['room_no'], $form_data['room_type'],
                $form_data['description'], $id,
            ]);

            saveStudentGuardians($pdo, $id, guardiansFromForm($_POST));
            $_SESSION['success_msg'] = 'Student updated successfully!';
            header('Location: student_view.php?id=' . $id);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'Failed to update student.';
        }
    } else {
        $_SESSION['error_msg'] = implode(' ', $errors);
    }
}

require_once 'includes/header.php';
?>
<div class="student-view-header">
    <div class="student-view-header-card">
        <div class="student-view-header-main">
            <a href="student_view.php?id=<?php echo $id; ?>" class="student-back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="student-header-avatar add-student-icon"><i class="fas fa-pen"></i></div>
            <div class="student-header-info">
                <div class="student-header-title-row"><h1>Edit Student</h1></div>
                <p class="student-view-breadcrumb">
                    <a href="students.php">Students</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?php echo htmlspecialchars($student['name']); ?></span>
                </p>
                <div class="student-header-meta">
                    <span class="header-meta-chip"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($ad_no); ?></span>
                </div>
            </div>
        </div>
        <div class="student-view-header-actions">
            <a href="student_id_card.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-outline" target="_blank"><i class="fas fa-id-card"></i> ID Card</a>
        </div>
    </div>
</div>

<form method="POST" class="student-form" enctype="multipart/form-data">
<?php include 'includes/student_form_sections.php'; ?>
    <div class="form-actions-bar">
        <a href="student_view.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-outline"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Update Student</button>
    </div>
</form>
<script>
document.getElementById('photo').addEventListener('change', function (e) {
    var file = e.target.files[0], preview = document.getElementById('photoPreview');
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (ev) { preview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview">'; };
    reader.readAsDataURL(file);
});
</script>
<?php require_once 'includes/footer.php'; ?>
