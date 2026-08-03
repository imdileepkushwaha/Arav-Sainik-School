<?php
// admin/includes/student_form_sections.php
// Expects: $form_data, $class_options, $category_options, $mode ('add'|'edit'), $generated_ad_no (add), $ad_no (edit), $photo_url (edit optional), $pdo
$is_edit = ($mode ?? 'add') === 'edit';
$section_options = [];
if (!empty($form_data['class']) && isset($pdo)) {
    $section_options = getSectionOptions($pdo, $form_data['class']);
}
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
                <label><i class="fas fa-id-card"></i> Admission No</label>
                <?php if ($is_edit): ?>
                <div class="form-input-readonly">
                    <span class="ad-no-display"><?php echo htmlspecialchars($ad_no); ?></span>
                </div>
                <?php else: ?>
                <div class="form-input-readonly">
                    <span class="ad-no-display" id="adNoDisplay"><?php echo htmlspecialchars($generated_ad_no); ?></span>
                    <span class="auto-gen-tag"><i class="fas fa-magic"></i> Class-wise auto</span>
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
            <div class="form-field" id="rollFieldWrap">
                <label><i class="fas fa-hashtag"></i> Roll Number <span class="required">*</span></label>
                <?php if ($is_edit): ?>
                <input type="text" id="roll" name="roll" class="form-input" value="<?php echo htmlspecialchars($form_data['roll']); ?>" required autocomplete="off">
                <span class="roll-field-msg" id="rollMsg" hidden></span>
                <?php else: ?>
                <div class="form-input-readonly" id="rollAutoBox">
                    <span class="ad-no-display <?php echo $generated_roll === '' ? 'roll-pending' : ''; ?>" id="rollDisplay"><?php echo $generated_roll !== '' ? htmlspecialchars($generated_roll) : 'Select class & section'; ?></span>
                    <span class="auto-gen-tag"><i class="fas fa-magic"></i> Auto generated</span>
                </div>
                <input type="hidden" name="roll" id="roll" value="<?php echo htmlspecialchars($form_data['roll'] ?: $generated_roll); ?>">
                <div id="rollManualWrap" class="roll-manual-wrap" hidden>
                    <input type="text" id="rollManual" class="form-input" placeholder="Enter custom roll number" autocomplete="off">
                </div>
                <button type="button" class="roll-override-link" id="rollOverrideBtn"><i class="fas fa-pen"></i> Use custom roll</button>
                <span class="roll-field-msg" id="rollMsg" hidden></span>
                <?php endif; ?>
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
            <div class="section-card-icon section-icon-address"><i class="fas fa-phone"></i></div>
            <div><h4>Contact Information</h4><p>Phone and email</p></div>
        </div>
        <div class="form-grid form-grid-2">
            <div class="form-field">
                <label for="mobile"><i class="fas fa-mobile-alt"></i> Mobile <span class="required">*</span></label>
                <input type="tel" id="mobile" name="mobile" class="form-input" value="<?php echo htmlspecialchars($form_data['mobile']); ?>" required>
            </div>
            <div class="form-field">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($form_data['email']); ?>">
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-parent"><i class="fas fa-users"></i></div>
            <div><h4>Parent & Guardian</h4><p>Family contact details</p></div>
        </div>
        <div class="form-grid form-grid-3">
            <div class="form-field-group">
                <h5><i class="fas fa-male"></i> Father</h5>
                <div class="form-field"><label>Name</label><input type="text" name="father_name" class="form-input" value="<?php echo htmlspecialchars($form_data['father_name']); ?>"></div>
                <div class="form-field"><label>Phone</label><input type="tel" name="father_phone" class="form-input" value="<?php echo htmlspecialchars($form_data['father_phone']); ?>"></div>
                <div class="form-field"><label>Email</label><input type="email" name="father_email" class="form-input" value="<?php echo htmlspecialchars($form_data['father_email']); ?>"></div>
            </div>
            <div class="form-field-group">
                <h5><i class="fas fa-female"></i> Mother</h5>
                <div class="form-field"><label>Name</label><input type="text" name="mother_name" class="form-input" value="<?php echo htmlspecialchars($form_data['mother_name']); ?>"></div>
                <div class="form-field"><label>Phone</label><input type="tel" name="mother_phone" class="form-input" value="<?php echo htmlspecialchars($form_data['mother_phone']); ?>"></div>
                <div class="form-field"><label>Email</label><input type="email" name="mother_email" class="form-input" value="<?php echo htmlspecialchars($form_data['mother_email']); ?>"></div>
            </div>
            <div class="form-field-group">
                <h5><i class="fas fa-user-shield"></i> Guardian</h5>
                <div class="form-field"><label>Name</label><input type="text" name="guardian_name" class="form-input" value="<?php echo htmlspecialchars($form_data['guardian_name']); ?>"></div>
                <div class="form-field"><label>Phone</label><input type="tel" name="guardian_phone" class="form-input" value="<?php echo htmlspecialchars($form_data['guardian_phone']); ?>"></div>
                <div class="form-field"><label>Email</label><input type="email" name="guardian_email" class="form-input" value="<?php echo htmlspecialchars($form_data['guardian_email']); ?>"></div>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-address"><i class="fas fa-map-marker-alt"></i></div>
            <div><h4>Address</h4><p>Current and permanent location</p></div>
        </div>
        <div class="form-grid">
            <div class="form-field form-field-full">
                <label for="current_address">Current Address</label>
                <textarea id="current_address" name="current_address" class="form-input form-textarea" rows="2"><?php echo htmlspecialchars($form_data['current_address']); ?></textarea>
            </div>
            <div class="form-field form-field-full">
                <label for="permanent_address">Permanent Address</label>
                <textarea id="permanent_address" name="permanent_address" class="form-input form-textarea" rows="2"><?php echo htmlspecialchars($form_data['permanent_address']); ?></textarea>
            </div>
        </div>
    </div>

    <?php
    $hasPreviousSchooling = trim(($form_data['previous_school'] ?? '') . ($form_data['previous_class'] ?? '') . ($form_data['previous_year'] ?? '') . ($form_data['previous_tc_no'] ?? '')) !== '';
    ?>
    <div class="form-section-card">
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

    <div class="details-grid">
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
            <div class="section-card-icon section-icon-desc"><i class="fas fa-align-left"></i></div>
            <div><h4>Description</h4><p>Remarks about the student</p></div>
        </div>
        <textarea name="description" class="form-input form-textarea" rows="4" placeholder="Optional remarks..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
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
<script>
(function () {
    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var classSelect = document.getElementById('class');
    var sectionSelect = document.getElementById('section');
    var rollMsg = document.getElementById('rollMsg');
    if (!classSelect) return;

    var rollInput = document.getElementById('roll');
    var rollDisplay = document.getElementById('rollDisplay');
    var rollAutoBox = document.getElementById('rollAutoBox');
    var rollManualWrap = document.getElementById('rollManualWrap');
    var rollManual = document.getElementById('rollManual');
    var rollOverrideBtn = document.getElementById('rollOverrideBtn');
    var useCustomRoll = false;

    var excludeId = <?php echo isset($exclude_student_id) ? (int) $exclude_student_id : 0; ?>;
    var apiBase = '<?php echo $is_edit ? 'student_edit.php?id=' . (int) ($exclude_student_id ?? 0) : 'student_add.php'; ?>';

    function apiUrl(query) {
        return apiBase + (apiBase.indexOf('?') >= 0 ? '&' : '?') + query;
    }

    function setRollMsg(text, type) {
        if (!rollMsg) return;
        if (!text) {
            rollMsg.hidden = true;
            rollMsg.textContent = '';
            rollMsg.className = 'roll-field-msg';
            return;
        }
        rollMsg.hidden = false;
        rollMsg.textContent = text;
        rollMsg.className = 'roll-field-msg roll-field-msg--' + type;
    }

    function getRollValue() {
        if (isEdit) return rollInput ? rollInput.value.trim() : '';
        if (useCustomRoll && rollManual) return rollManual.value.trim();
        return rollInput ? rollInput.value.trim() : '';
    }

    function setAutoRoll(value) {
        if (!rollInput) return;
        rollInput.value = value;
        if (rollDisplay) {
            rollDisplay.textContent = value || 'Select class & section';
            rollDisplay.classList.toggle('roll-pending', !value);
        }
    }

    function fetchNextAdNo() {
        var adDisplay = document.getElementById('adNoDisplay');
        if (!adDisplay || isEdit) return;
        var cls = classSelect.value;
        if (!cls) {
            adDisplay.textContent = 'Select class first';
            return;
        }
        fetch(apiUrl('action=next_ad_no&class=' + encodeURIComponent(cls)))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                adDisplay.textContent = data.ad_no || 'Select class first';
            });
    }

    function loadSections(keepCurrent) {
        var cls = classSelect.value;
        if (!sectionSelect) return;
        if (!cls) {
            sectionSelect.innerHTML = '<option value="">Select class first</option>';
            setAutoRoll('');
            setRollMsg('', '');
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
                fetchNextRoll();
            });
    }

    function fetchNextRoll() {
        var cls = classSelect.value;
        var sec = sectionSelect ? sectionSelect.value : 'A';
        if (!cls) {
            setAutoRoll('');
            setRollMsg('', '');
            return;
        }
        if (isEdit && useCustomRoll) return;
        if (!isEdit && useCustomRoll) return;

        fetch(apiUrl('action=next_roll&class=' + encodeURIComponent(cls) + '&section=' + encodeURIComponent(sec)))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.roll) {
                    if (isEdit && rollInput) rollInput.value = data.roll;
                    else setAutoRoll(data.roll);
                }
                checkRollDuplicate();
            });
    }

    function checkRollDuplicate() {
        var roll = getRollValue();
        var cls = classSelect.value;
        var sec = sectionSelect ? sectionSelect.value : 'A';
        if (!roll || !cls) {
            setRollMsg('', '');
            return;
        }
        var url = apiUrl('action=check_roll&roll=' + encodeURIComponent(roll)
            + '&class=' + encodeURIComponent(cls) + '&section=' + encodeURIComponent(sec));
        if (excludeId) url += '&exclude_id=' + excludeId;
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.taken) {
                    setRollMsg('Roll ' + roll + ' is already used in this class & section.', 'error');
                } else if (roll) {
                    setRollMsg('Roll ' + roll + ' is available.', 'ok');
                }
            });
    }

    if (!isEdit && rollOverrideBtn) {
        rollOverrideBtn.addEventListener('click', function () {
            useCustomRoll = !useCustomRoll;
            if (useCustomRoll) {
                rollAutoBox.hidden = true;
                rollManualWrap.hidden = false;
                rollOverrideBtn.innerHTML = '<i class="fas fa-magic"></i> Use auto roll';
                if (rollManual) {
                    rollManual.value = rollInput.value;
                    rollManual.focus();
                }
            } else {
                rollAutoBox.hidden = false;
                rollManualWrap.hidden = true;
                rollOverrideBtn.innerHTML = '<i class="fas fa-pen"></i> Use custom roll';
                if (rollManual) rollInput.value = rollManual.value.trim() || rollInput.value;
                fetchNextRoll();
            }
        });
    }

    if (isEdit && rollInput) {
        rollInput.addEventListener('input', checkRollDuplicate);
        rollInput.addEventListener('blur', checkRollDuplicate);
    }

    if (!isEdit && rollManual) {
        rollManual.addEventListener('input', function () {
            rollInput.value = rollManual.value.trim();
            checkRollDuplicate();
        });
    }

    classSelect.addEventListener('change', function () {
        loadSections(false);
        fetchNextAdNo();
    });
    if (sectionSelect) sectionSelect.addEventListener('change', fetchNextRoll);

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
