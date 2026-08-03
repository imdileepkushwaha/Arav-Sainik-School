<?php
$page_title = "Student List";
require_once 'includes/header.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);

try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY id ASC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database query failed: " . $e->getMessage());
}
?>

<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="content-top-title">
            <h2>Student List</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Student List</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="student_import.php" class="btn-header-action btn-header-outline"><i class="fas fa-file-import"></i> Import</a>
        <a href="student_promote.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-up"></i> Promote</a>
        <a href="student_add.php" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Add Student</a>
    </div>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <div class="toolbar-left">
            <a href="student_export.php" class="toolbar-btn toolbar-link-plain">
                <i class="far fa-file-excel icon-excel"></i> Export to Excel
            </a>
            <div class="toolbar-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search students...">
            </div>
        </div>
        <div class="toolbar-right">
            <button class="toolbar-btn">
                Filter <i class="fas fa-chevron-down icon-chevron-xs"></i>
            </button>
            <div class="toolbar-rows-wrap">
                Rows per page:
                <select class="toolbar-select" id="rowsPerPageSelect">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="1000">All</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="table-wrapper">
        <?php if (count($students) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th class="th-w-check"><input type="checkbox" class="custom-checkbox" id="selectAll"></th>
                    <th>S.L <i class="fas fa-sort icon-sort-muted"></i></th>
                    <th>Admission No</th>
                    <th>Name <i class="fas fa-sort icon-sort-muted"></i></th>
                    <th>Class <i class="fas fa-sort icon-sort-muted"></i></th>
                    <th>Mobile Number <i class="fas fa-sort icon-sort-muted"></i></th>
                    <th>Status <i class="fas fa-sort icon-sort-muted"></i></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php foreach($students as $student): ?>
                <tr class="student-row">
                    <td><input type="checkbox" class="custom-checkbox row-checkbox" value="<?php echo $student['id']; ?>"></td>
                    <td><?php echo str_pad($student['id'], 2, '0', STR_PAD_LEFT); ?></td>
                    <td><a href="student_view.php?id=<?php echo $student['id']; ?>" class="teal-link"><?php echo htmlspecialchars($student['ad_no']); ?></a></td>
                    <td>
                        <div class="student-name-cell">
                            <img src="<?php echo htmlspecialchars(getStudentPhotoUrl($student)); ?>" alt="Avatar">
                            <div class="name-info">
                                <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                <span>Roll No: <?php echo htmlspecialchars($student['roll']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php
                        echo htmlspecialchars($student['class'] . (!empty($student['section']) ? ' (' . $student['section'] . ')' : ''));
                    ?></td>
                    <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                    <td>
                        <span class="status-badge <?php echo $student['status'] == 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo htmlspecialchars($student['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions-row">
                            <a href="student_id_card.php?id=<?php echo $student['id']; ?>" class="action-btn view-btn" title="ID Card" target="_blank">
                                <i class="fas fa-id-card"></i>
                            </a>
                            <a href="student_view.php?id=<?php echo $student['id']; ?>" class="action-btn view-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="student_edit.php?id=<?php echo $student['id']; ?>" class="action-btn edit-btn" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <a href="student_delete.php?id=<?php echo $student['id']; ?>" class="action-btn delete-btn btn-delete-confirm" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr id="noResultsRow" class="row-hidden">
                    <td colspan="8" class="no-results-cell">
                        <div class="empty-state empty-state-md">
                            <i class="fas fa-search empty-state-icon empty-search-icon"></i>
                            <h3>No matching records found</h3>
                            <p>We couldn't find any students matching your search criteria.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-graduate empty-state-icon"></i>
            <h3>No Students Found</h3>
            <p>It looks like there are currently no students in the database. Add a new student to start managing their records here.</p>
            <a href="student_add.php" class="btn-header-action btn-header-primary">
                <i class="fas fa-plus"></i> Add First Student
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (count($students) > 0): ?>
    <div class="table-footer">
        <div class="showing-entries" id="showingEntriesText">
            Showing 1 to <?php echo count($students); ?> of <?php echo count($students); ?> entries
        </div>
        <div class="pagination">
            <div class="page-item"><i class="fas fa-angle-double-left page-nav-icon-xs"></i></div>
            <div class="page-item"><i class="fas fa-angle-left page-nav-icon-sm"></i></div>
            <div class="page-item active">1</div>
            <div class="page-item"><i class="fas fa-angle-right page-nav-icon-sm"></i></div>
            <div class="page-item"><i class="fas fa-angle-double-right page-nav-icon-xs"></i></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Checkbox Select All Logic
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowCheckboxes.forEach(cb => {
                // Only check visible rows
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = selectAll.checked;
                }
            });
        });

        rowCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const visibleCheckboxes = Array.from(rowCheckboxes).filter(c => c.closest('tr').style.display !== 'none');
                const allChecked = visibleCheckboxes.every(c => c.checked);
                const someChecked = visibleCheckboxes.some(c => c.checked);
                selectAll.checked = allChecked && visibleCheckboxes.length > 0;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    }

    // 2. Search and Pagination Logic
    const searchInput = document.getElementById('searchInput');
    const rowsSelect = document.getElementById('rowsPerPageSelect');
    const tableRows = document.querySelectorAll('#studentTableBody tr.student-row');
    const entriesText = document.getElementById('showingEntriesText');
    const noResultsRow = document.getElementById('noResultsRow');

    function applyFilters() {
        if (!searchInput || !rowsSelect) return;
        
        const searchTerm = searchInput.value.toLowerCase();
        const limit = parseInt(rowsSelect.value);
        let matchCount = 0;
        let visibleCount = 0;

        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchesSearch = text.includes(searchTerm);

            if (matchesSearch) {
                matchCount++;
                if (visibleCount < limit) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            } else {
                row.style.display = 'none';
                // uncheck hidden rows
                const cb = row.querySelector('.row-checkbox');
                if (cb) cb.checked = false;
            }
        });

        // Show/Hide no results row
        if (noResultsRow) {
            if (matchCount === 0 && tableRows.length > 0) {
                noResultsRow.classList.remove('row-hidden');
            } else {
                noResultsRow.classList.add('row-hidden');
            }
        }

        // Update footer text
        if (entriesText) {
            if (matchCount === 0) {
                entriesText.textContent = `No entries found matching "${searchInput.value}"`;
            } else {
                entriesText.textContent = `Showing 1 to ${visibleCount} of ${matchCount} entries`;
            }
        }
        
        // Reset select all state when filter changes
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (rowsSelect) rowsSelect.addEventListener('change', applyFilters);
});
</script>

<?php require_once 'includes/footer.php'; ?>
