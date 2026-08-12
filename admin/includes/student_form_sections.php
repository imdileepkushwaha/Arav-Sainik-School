<?php
// admin/includes/student_form_sections.php
// Expects: $form_data, $class_options, $category_options, $mode ('add'|'edit'), $generated_ad_no (add), $ad_no (edit), $photo_url (edit optional), $aadhar_url (edit optional), $pdo
$is_edit = ($mode ?? 'add') === 'edit';
$section_options = [];
if (!empty($form_data['class']) && isset($pdo)) {
    $section_options = getSectionOptions($pdo, $form_data['class']);
}
$aadhar_url = $aadhar_url ?? '';
$aadhar_is_pdf = $aadhar_url !== '' && preg_match('/\.pdf$/i', $aadhar_url);
?>
    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-user-graduate"></i></div>
            <div>
                <h4>Basic Information</h4>
                <p>Student identity and academic details</p>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-field">
                <label><i class="fas fa-hashtag"></i> Serial No.</label>
                <?php if ($is_edit): ?>
                <div class="form-input-readonly">
                    <span class="ad-no-display"><?php echo htmlspecialchars($ad_no); ?></span>
                </div>
                <?php else: ?>
                <div class="form-input-readonly">
                    <span class="ad-no-display" id="adNoDisplay"><?php echo htmlspecialchars($generated_ad_no); ?></span>
                    <span class="auto-gen-tag"><i class="fas fa-magic"></i> Auto generated</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="name"><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
            </div>
            <div class="form-field">
                <label for="class"><i class="fas fa-chalkboard"></i> Class <span class="required">*</span></label>
                <select id="class" name="class" class="form-input form-select" required>
                    <option value="">Select class</option>
                    <?php foreach ($class_options as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $form_data['class'] === $opt ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="section"><i class="fas fa-table-columns"></i> Section</label>
                <select id="section" name="section" class="form-input form-select">
                    <?php if (empty($section_options)): ?>
                    <option value="">Select class first</option>
                    <?php else: ?>
                    <?php foreach ($section_options as $sec): ?>
                    <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo ($form_data['section'] ?? 'A') === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="dob"><i class="fas fa-cake-candles"></i> Date of Birth <span class="required">*</span></label>
                <input type="date" id="dob" name="dob" class="form-input" value="<?php echo htmlspecialchars($form_data['dob']); ?>" required>
            </div>
            <div class="form-field">
                <label for="gender"><i class="fas fa-venus-mars"></i> Gender <span class="required">*</span></label>
                <select id="gender" name="gender" class="form-input form-select" required>
                    <option value="Male" <?php echo $form_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $form_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $form_data['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-field">
                <label for="category"><i class="fas fa-tag"></i> Category</label>
                <select id="category" name="category" class="form-input form-select">
                    <?php foreach ($category_options as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $form_data['category'] === $opt ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="status"><i class="fas fa-circle-check"></i> Status</label>
                <select id="status" name="status" class="form-input form-select">
                    <option value="Active" <?php echo $form_data['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $form_data['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-parent"><i class="fas fa-users"></i></div>
            <div><h4>Family Details</h4><p>Parents and contact number</p></div>
        </div>
        <div class="form-grid form-grid-3">
            <div class="form-field">
                <label for="father_name"><i class="fas fa-male"></i> Father Name</label>
                <input type="text" id="father_name" name="father_name" class="form-input" value="<?php echo htmlspecialchars($form_data['father_name']); ?>">
            </div>
            <div class="form-field">
                <label for="mother_name"><i class="fas fa-female"></i> Mother Name</label>
                <input type="text" id="mother_name" name="mother_name" class="form-input" value="<?php echo htmlspecialchars($form_data['mother_name']); ?>">
            </div>
            <div class="form-field">
                <label for="mobile"><i class="fas fa-phone"></i> Contact Number <span class="required">*</span></label>
                <input type="tel" id="mobile" name="mobile" class="form-input" value="<?php echo htmlspecialchars($form_data['mobile']); ?>" required>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-address"><i class="fas fa-map-marker-alt"></i></div>
            <div><h4>Address</h4><p>Student residential address</p></div>
        </div>
        <?php $indianStates = getIndianStates(); ?>
        <div class="form-grid form-grid-2">
            <div class="form-field form-field-full">
                <label for="current_address_line">Address Line</label>
                <input type="text" id="current_address_line" name="current_address_line" class="form-input" value="<?php echo htmlspecialchars($form_data['current_address_line'] ?? ''); ?>" placeholder="House / Street / Landmark">
            </div>
            <div class="form-field">
                <label for="current_city">City</label>
                <input type="text" id="current_city" name="current_city" class="form-input" value="<?php echo htmlspecialchars($form_data['current_city'] ?? ''); ?>" placeholder="City">
            </div>
            <div class="form-field">
                <label for="current_state">State</label>
                <select id="current_state" name="current_state" class="form-input form-select">
                    <option value="">Select state</option>
                    <?php foreach ($indianStates as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($form_data['current_state'] ?? '') === $st ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                    <?php if (($form_data['current_state'] ?? '') !== '' && !in_array($form_data['current_state'], $indianStates, true)): ?>
                    <option value="<?php echo htmlspecialchars($form_data['current_state']); ?>" selected><?php echo htmlspecialchars($form_data['current_state']); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="current_country">Country</label>
                <input type="text" id="current_country" name="current_country" class="form-input" value="<?php echo htmlspecialchars($form_data['current_country'] ?? 'India'); ?>" placeholder="Country">
            </div>
            <div class="form-field">
                <label for="current_pincode">PIN Code</label>
                <input type="text" id="current_pincode" name="current_pincode" class="form-input" value="<?php echo htmlspecialchars($form_data['current_pincode'] ?? ''); ?>" placeholder="e.g. 110001" maxlength="10">
            </div>
        </div>
    </div>

    <?php
    $hasPreviousSchooling = trim(($form_data['previous_school'] ?? '') . ($form_data['previous_class'] ?? '') . ($form_data['previous_year'] ?? '') . ($form_data['previous_tc_no'] ?? '')) !== '';
    ?>
    <div class="form-section-card"<?php echo !$is_edit ? ' style="display: none;"' : ''; ?>>
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-school"></i></div>
            <div><h4>Previous Schooling</h4><p>Optional — fill only if the student studied elsewhere before</p></div>
        </div>
        <div class="form-grid">
            <div class="form-field form-field-full">
                <label>Has previous schooling?</label>
                <div class="prev-school-toggle" role="group" aria-label="Previous schooling">
                    <label class="prev-school-option">
                        <input type="radio" name="has_previous_schooling" value="0" <?php echo !$hasPreviousSchooling ? 'checked' : ''; ?>>
                        <span>No</span>
                    </label>
                    <label class="prev-school-option">
                        <input type="radio" name="has_previous_schooling" value="1" <?php echo $hasPreviousSchooling ? 'checked' : ''; ?>>
                        <span>Yes, if any</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-grid prev-school-fields" id="prevSchoolFields" <?php echo $hasPreviousSchooling ? '' : 'hidden'; ?>>
            <div class="form-field form-field-full">
                <label for="previous_school">Previous School Name</label>
                <input type="text" id="previous_school" name="previous_school" class="form-input" value="<?php echo htmlspecialchars($form_data['previous_school']); ?>" placeholder="e.g. ABC Public School">
            </div>
            <div class="form-field">
                <label for="previous_class">Last Class Studied</label>
                <input type="text" id="previous_class" name="previous_class" class="form-input" value="<?php echo htmlspecialchars($form_data['previous_class'] ?? ''); ?>" placeholder="e.g. Class 5">
            </div>
            <div class="form-field">
                <label for="previous_year">Year / Session</label>
                <input type="text" id="previous_year" name="previous_year" class="form-input" value="<?php echo htmlspecialchars($form_data['previous_year'] ?? ''); ?>" placeholder="e.g. 2024-25">
            </div>
            <div class="form-field">
                <label for="previous_tc_no">TC / SLC No. <span class="field-optional">(optional)</span></label>
                <input type="text" id="previous_tc_no" name="previous_tc_no" class="form-input" value="<?php echo htmlspecialchars($form_data['previous_tc_no'] ?? ''); ?>" placeholder="Transfer certificate no.">
            </div>
        </div>
    </div>

    <div class="details-grid"<?php echo !$is_edit ? ' style="display: none;"' : ''; ?>>
        <div class="form-section-card form-section-flush">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-bank"><i class="fas fa-university"></i></div>
                <div><h4>Bank Details</h4></div>
            </div>
            <div class="form-grid form-grid-1">
                <div class="form-field"><label>Bank Name</label><input type="text" name="bank_name" class="form-input" value="<?php echo htmlspecialchars($form_data['bank_name']); ?>"></div>
                <div class="form-field"><label>Branch</label><input type="text" name="bank_branch" class="form-input" value="<?php echo htmlspecialchars($form_data['bank_branch']); ?>"></div>
                <div class="form-field"><label>IFSC Code</label><input type="text" name="ifsc_code" class="form-input" value="<?php echo htmlspecialchars($form_data['ifsc_code']); ?>"></div>
            </div>
        </div>
        <div class="form-section-card form-section-flush">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-medical"><i class="fas fa-heartbeat"></i></div>
                <div><h4>Medical & Hostel</h4></div>
            </div>
            <div class="form-grid form-grid-1">
                <div class="form-field"><label>Blood Group</label><input type="text" name="blood_group" class="form-input" value="<?php echo htmlspecialchars($form_data['blood_group']); ?>" placeholder="e.g. O+"></div>
                <div class="form-field"><label>Height</label><input type="text" name="height" class="form-input" value="<?php echo htmlspecialchars($form_data['height']); ?>" placeholder="e.g. 5.2 ft"></div>
                <div class="form-field"><label>Weight</label><input type="text" name="weight" class="form-input" value="<?php echo htmlspecialchars($form_data['weight']); ?>" placeholder="e.g. 60 kg"></div>
                <div class="form-field"><label>Hostel</label><input type="text" name="hostel_name" class="form-input" value="<?php echo htmlspecialchars($form_data['hostel_name']); ?>"></div>
                <div class="form-field"><label>Room No.</label><input type="text" name="room_no" class="form-input" value="<?php echo htmlspecialchars($form_data['room_no']); ?>"></div>
                <div class="form-field"><label>Room Type</label><input type="text" name="room_type" class="form-input" value="<?php echo htmlspecialchars($form_data['room_type']); ?>"></div>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-docs"><i class="fas fa-camera"></i></div>
            <div><h4>Student Photo</h4><p>JPG, PNG — Max 2MB</p></div>
        </div>
        <div class="photo-upload-area">
            <div class="photo-upload-preview" id="photoPreview">
                <?php if (!empty($photo_url) && strpos($photo_url, 'ui-avatars') === false): ?>
                <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Photo">
                <?php else: ?>
                <i class="fas fa-user"></i><span>No photo</span>
                <?php endif; ?>
            </div>
            <div class="photo-upload-content">
                <p>Upload student profile photo</p>
                <label class="photo-upload-btn"><i class="fas fa-upload"></i> Choose File
                    <input type="file" name="photo" id="photo" accept="image/*" hidden>
                </label>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-docs"><i class="fas fa-id-card"></i></div>
            <div><h4>Aadhar Card</h4><p>JPG, PNG or PDF — Max 2MB</p></div>
        </div>
        <div class="photo-upload-area aadhar-upload-area">
            <div class="photo-upload-preview aadhar-upload-preview" id="aadharPreview">
                <?php if ($aadhar_url !== '' && !$aadhar_is_pdf): ?>
                <img src="<?php echo htmlspecialchars($aadhar_url); ?>" alt="Aadhar">
                <?php elseif ($aadhar_url !== '' && $aadhar_is_pdf): ?>
                <i class="fas fa-file-pdf"></i><span>PDF uploaded</span>
                <?php else: ?>
                <i class="fas fa-id-card"></i><span>No Aadhar</span>
                <?php endif; ?>
            </div>
            <div class="photo-upload-content">
                <p>Upload Aadhar card</p>
                <span class="photo-upload-hint">Preview appears after you choose a file</span>
                <label class="photo-upload-btn"><i class="fas fa-upload"></i> Choose File
                    <input type="file" name="aadhar" id="aadhar" accept="image/*,.pdf,application/pdf" hidden>
                </label>
            </div>
        </div>
    </div>
<script>
(function () {
    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var classSelect = document.getElementById('class');
    var sectionSelect = document.getElementById('section');
    if (!classSelect) return;

    var apiBase = '<?php echo $is_edit ? 'student_edit.php?id=' . (int) ($exclude_student_id ?? 0) : 'student_add.php'; ?>';

    function apiUrl(query) {
        return apiBase + (apiBase.indexOf('?') >= 0 ? '&' : '?') + query;
    }

    function fetchNextAdNo() {
        var adDisplay = document.getElementById('adNoDisplay');
        if (!adDisplay || isEdit) return;
        var cls = classSelect.value;
        var sec = sectionSelect ? sectionSelect.value : 'A';
        if (!cls) {
            adDisplay.textContent = 'Select class & section';
            return;
        }
        fetch(apiUrl('action=next_ad_no&class=' + encodeURIComponent(cls) + '&section=' + encodeURIComponent(sec || 'A')))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                adDisplay.textContent = data.ad_no || 'Select class & section';
            });
    }

    function loadSections(keepCurrent) {
        var cls = classSelect.value;
        if (!sectionSelect) return;
        if (!cls) {
            sectionSelect.innerHTML = '<option value="">Select class first</option>';
            fetchNextAdNo();
            return;
        }
        var prev = keepCurrent ? sectionSelect.value : '';
        fetch(apiUrl('action=sections&class=' + encodeURIComponent(cls)))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var sections = data.sections || [];
                sectionSelect.innerHTML = '';
                if (!sections.length) {
                    var empty = document.createElement('option');
                    empty.value = '';
                    empty.textContent = 'No sections';
                    sectionSelect.appendChild(empty);
                } else {
                    sections.forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = s;
                        o.textContent = s;
                        if (prev && s === prev) o.selected = true;
                        sectionSelect.appendChild(o);
                    });
                    if (!sectionSelect.value && sections.length) {
                        sectionSelect.selectedIndex = 0;
                    }
                }
                fetchNextAdNo();
            });
    }

    classSelect.addEventListener('change', function () {
        loadSections(false);
    });
    if (sectionSelect) {
        sectionSelect.addEventListener('change', fetchNextAdNo);
    }

    if (classSelect.value) loadSections(true);
})();
</script>
<script>
(function () {
    var fields = document.getElementById('prevSchoolFields');
    if (!fields) return;
    var radios = document.querySelectorAll('input[name="has_previous_schooling"]');
    function syncPrevSchool() {
        var yes = document.querySelector('input[name="has_previous_schooling"][value="1"]');
        var show = yes && yes.checked;
        fields.hidden = !show;
        if (!show) {
            ['previous_school', 'previous_class', 'previous_year', 'previous_tc_no'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
        }
    }
    radios.forEach(function (r) { r.addEventListener('change', syncPrevSchool); });
})();
</script>
