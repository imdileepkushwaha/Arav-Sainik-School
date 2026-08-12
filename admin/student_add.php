<?php
$page_title = "Add New Student";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);
handleRollApiRequest($pdo);
handleClassApiRequest($pdo);

$class_options = getClassOptions($pdo);
$category_options = getCategoryOptions($pdo);
$form_data = getDefaultStudentFormData();
$mode = 'add';
$generated_roll = '';
$generated_ad_no = $form_data['class'] !== ''
    ? generateAdmissionNo($pdo, $form_data['class'])
    : 'Select class first';

if ($form_data['class'] !== '') {
    $generated_roll = $form_data['roll'] !== ''
        ? $form_data['roll']
        : getNextRollNumber($pdo, $form_data['class'], $form_data['section']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = array_keys(getDefaultStudentFormData());
    foreach ($keys as $key) {
        $form_data[$key] = trim($_POST[$key] ?? '');
    }

    $errors = [];
    if ($form_data['name'] === '') $errors[] = 'Student name is required.';
    if ($form_data['class'] === '') $errors[] = 'Class is required.';
    if ($form_data['dob'] === '') $errors[] = 'Date of birth is required.';
    if ($form_data['mobile'] === '') $errors[] = 'Mobile number is required.';

    if ($form_data['roll'] === '' && $form_data['class'] !== '') {
        $form_data['roll'] = getNextRollNumber($pdo, $form_data['class'], $form_data['section']);
    }
    $errors = array_merge($errors, validateStudentRoll($pdo, $form_data['roll'], $form_data['class'], $form_data['section']));
    $errors = array_merge($errors, validateClassAndSection($pdo, $form_data['class'], $form_data['section']));

    if (empty($errors)) {
        try {
            $ad_no = generateAdmissionNo($pdo, $form_data['class']);
            if ($ad_no === '') {
                throw new RuntimeException('Could not generate admission number for the selected class.');
            }

            $photo = null;
            if (!empty($_FILES['photo']['name'])) {
                $photo = uploadStudentPhoto($_FILES['photo'], $ad_no);
                if ($photo === false) {
                    $_SESSION['error_msg'] = 'Invalid photo. Use JPG/PNG under 2MB.';
                    header('Location: student_add.php');
                    exit;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO students (
                ad_no, name, roll, class, section, dob, gender, mobile, email, category, status, avatar_id,
                photo, current_address, permanent_address,
                current_address_line, current_city, current_state, current_country, current_pincode,
                permanent_address_line, permanent_city, permanent_state, permanent_country, permanent_pincode,
                previous_school, previous_class, previous_year, previous_tc_no,
                bank_name, bank_branch, ifsc_code, blood_group, height, weight,
                hostel_name, room_no, room_type, description
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

            if (($_POST['has_previous_schooling'] ?? '0') !== '1') {
                $form_data['previous_school'] = '';
                $form_data['previous_class'] = '';
                $form_data['previous_year'] = '';
                $form_data['previous_tc_no'] = '';
            }

            applyStudentAddressFromPost($form_data, $_POST);

            $stmt->execute([
                $ad_no, $form_data['name'], $form_data['roll'], $form_data['class'], $form_data['section'],
                $form_data['dob'], $form_data['gender'], $form_data['mobile'], $form_data['email'],
                $form_data['category'], $form_data['status'], rand(1, 10),
                $photo, $form_data['current_address'], $form_data['permanent_address'],
                $form_data['current_address_line'], $form_data['current_city'], $form_data['current_state'], $form_data['current_country'], $form_data['current_pincode'],
                $form_data['permanent_address_line'], $form_data['permanent_city'], $form_data['permanent_state'], $form_data['permanent_country'], $form_data['permanent_pincode'],
                $form_data['previous_school'], $form_data['previous_class'], $form_data['previous_year'], $form_data['previous_tc_no'],
                $form_data['bank_name'], $form_data['bank_branch'], $form_data['ifsc_code'],
                $form_data['blood_group'], $form_data['height'], $form_data['weight'],
                $form_data['hostel_name'], $form_data['room_no'], $form_data['room_type'], $form_data['description'],
            ]);

            $student_id = $pdo->lastInsertId();
            saveStudentGuardians($pdo, $student_id, guardiansFromForm($_POST));

            $_SESSION['success_msg'] = 'Student added! Admission No: ' . $ad_no;
            header('Location: students.php');
            exit;
        } catch (RuntimeException $e) {
            $_SESSION['error_msg'] = $e->getMessage();
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'Failed to add student.';
        }
    } else {
        $_SESSION['error_msg'] = implode(' ', $errors);
    }
    $generated_ad_no = $form_data['class'] !== ''
    ? generateAdmissionNo($pdo, $form_data['class'])
    : 'Select class first';
    if ($form_data['class'] !== '') {
        $generated_roll = $form_data['roll'] !== ''
            ? $form_data['roll']
            : getNextRollNumber($pdo, $form_data['class'], $form_data['section']);
    }
}

require_once 'includes/header.php';
?>
<div class="student-view-header">
    <div class="student-view-header-card">
        <div class="student-view-header-main">
            <a href="students.php" class="student-back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="student-header-avatar add-student-icon"><i class="fas fa-user-plus"></i></div>
            <div class="student-header-info">
                <div class="student-header-title-row"><h1>Add New Student</h1></div>
                <p class="student-view-breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="students.php"><i class="fas fa-user-graduate"></i> Students</a>
                    <i class="fas fa-chevron-right"></i><span>Add New Student</span>
                </p>
            </div>
        </div>
    </div>
</div>

<form method="POST" class="student-form" enctype="multipart/form-data">
<?php include 'includes/student_form_sections.php'; ?>
    <div class="form-actions-bar">
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Save Student</button>
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
