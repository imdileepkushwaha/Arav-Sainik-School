<?php
$page_title = "Hostel Management";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_hostel') {
        $pdo->prepare("INSERT INTO hostels (name, address) VALUES (?,?)")->execute([trim($_POST['name']), trim($_POST['address'] ?? '')]);
        $_SESSION['success_msg'] = 'Hostel added.';
    } elseif ($action === 'add_room' && isset($_POST['hostel_id'])) {
        $pdo->prepare("INSERT INTO hostel_rooms (hostel_id, room_no, room_type, capacity) VALUES (?,?,?,?)")
            ->execute([(int)$_POST['hostel_id'], trim($_POST['room_no']), trim($_POST['room_type'] ?? 'Standard'), (int)($_POST['capacity'] ?? 2)]);
        $_SESSION['success_msg'] = 'Room added.';
    } elseif ($action === 'allot') {
        $studentId = (int) $_POST['student_id'];
        $roomId = (int) $_POST['room_id'];
        if ($studentId <= 0) {
            $_SESSION['error_msg'] = 'Please select a student.';
        } else {
            $room = $pdo->prepare("SELECT * FROM hostel_rooms WHERE id = ?");
            $room->execute([$roomId]);
            $room = $room->fetch(PDO::FETCH_ASSOC);
            if ($room && getHostelRoomOccupancy($pdo, $roomId) >= (int)$room['capacity']) {
                $_SESSION['error_msg'] = 'Room is full.';
            } else {
                $pdo->prepare(
                    "INSERT INTO hostel_allotments (student_id, room_id, allotted_from, status) VALUES (?,?,CURDATE(),'Active')
                     ON DUPLICATE KEY UPDATE room_id=VALUES(room_id), allotted_from=CURDATE(), allotted_to=NULL, status='Active'"
                )->execute([$studentId, $roomId]);
                $pdo->prepare("UPDATE students SET hostel_name=(SELECT h.name FROM hostels h INNER JOIN hostel_rooms hr ON hr.hostel_id=h.id WHERE hr.id=?), room_no=?, room_type=? WHERE id=?")
                    ->execute([$roomId, $room['room_no'], $room['room_type'], $studentId]);
                $_SESSION['success_msg'] = 'Room allotted.';
            }
        }
    } elseif ($action === 'delete_hostel' && isset($_POST['id'])) {
        $hostelId = (int) $_POST['id'];
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM hostel_allotments ha
             INNER JOIN hostel_rooms hr ON hr.id = ha.room_id
             WHERE hr.hostel_id = ? AND ha.status = 'Active'"
        );
        $stmt->execute([$hostelId]);
        $activeAllotments = (int) $stmt->fetchColumn();
        if ($activeAllotments > 0) {
            $_SESSION['error_msg'] = "Cannot delete — $activeAllotments student(s) still allotted. Vacate them first.";
        } else {
            $pdo->prepare("UPDATE hostel_rooms SET status = 'Inactive' WHERE hostel_id = ?")->execute([$hostelId]);
            $pdo->prepare("UPDATE hostels SET status = 'Inactive' WHERE id = ?")->execute([$hostelId]);
            $_SESSION['success_msg'] = 'Hostel deleted.';
        }
    } elseif ($action === 'delete_room' && isset($_POST['id'])) {
        $roomId = (int) $_POST['id'];
        $occupied = getHostelRoomOccupancy($pdo, $roomId);
        if ($occupied > 0) {
            $_SESSION['error_msg'] = "Cannot delete — room has $occupied active allotment(s). Vacate students first.";
        } else {
            $pdo->prepare("UPDATE hostel_rooms SET status = 'Inactive' WHERE id = ?")->execute([$roomId]);
            $_SESSION['success_msg'] = 'Room deleted.';
        }
    } elseif ($action === 'vacate' && isset($_POST['allotment_id'])) {
        $allotmentId = (int) $_POST['allotment_id'];
        $stmt = $pdo->prepare("SELECT student_id FROM hostel_allotments WHERE id = ? AND status = 'Active'");
        $stmt->execute([$allotmentId]);
        $allotment = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($allotment) {
            $pdo->prepare("UPDATE hostel_allotments SET status = 'Vacated', allotted_to = CURDATE() WHERE id = ?")->execute([$allotmentId]);
            $pdo->prepare("UPDATE students SET hostel_name = NULL, room_no = NULL, room_type = NULL WHERE id = ?")->execute([(int) $allotment['student_id']]);
            $_SESSION['success_msg'] = 'Hostel allotment vacated.';
        } else {
            $_SESSION['error_msg'] = 'Allotment not found or already vacated.';
        }
    }
    header('Location: hostel.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

require_once 'includes/header.php';
$hostels = $pdo->query("SELECT * FROM hostels WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query(
    "SELECT hr.*, h.name AS hostel_name, (SELECT COUNT(*) FROM hostel_allotments ha WHERE ha.room_id=hr.id AND ha.status='Active') AS occupied
     FROM hostel_rooms hr INNER JOIN hostels h ON h.id = hr.hostel_id WHERE hr.status='Active' ORDER BY h.name, hr.room_no"
)->fetchAll(PDO::FETCH_ASSOC);
$allotments = $pdo->query(
    "SELECT ha.*, s.name, s.ad_no, s.class, hr.room_no, h.name AS hostel_name FROM hostel_allotments ha
     INNER JOIN students s ON s.id = ha.student_id INNER JOIN hostel_rooms hr ON hr.id = ha.room_id
     INNER JOIN hostels h ON h.id = hr.hostel_id WHERE ha.status='Active' ORDER BY h.name, hr.room_no"
)->fetchAll(PDO::FETCH_ASSOC);

$search = trim($_GET['q'] ?? '');
$searchResults = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare("SELECT id, ad_no, name, class, section FROM students WHERE status='Active' AND (name LIKE ? OR ad_no LIKE ?) LIMIT 12");
    $stmt->execute([$like, $like]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalBeds = array_sum(array_column($rooms, 'capacity'));
$occupiedBeds = array_sum(array_column($rooms, 'occupied'));
$availableBeds = max(0, $totalBeds - $occupiedBeds);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-bed"></i></div>
        <div class="content-top-title">
            <h2>Hostel Management</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Hostel</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="hostel_fees.php" class="btn-header-action btn-header-primary"><i class="fas fa-file-invoice-dollar"></i> Hostel Fees</a>
        <a href="hostel_fee_collect.php" class="btn-header-action btn-header-outline"><i class="fas fa-hand-holding-usd"></i> Collect</a>
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-user-graduate"></i> Students</a>
    </div>
</div>

<div class="cls-stat-strip cols-4">
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-building"></i></div>
        <div><span>Hostels</span><strong><?php echo count($hostels); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-door-open"></i></div>
        <div><span>Rooms</span><strong><?php echo count($rooms); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-user-check"></i></div>
        <div><span>Occupied Beds</span><strong><?php echo $occupiedBeds; ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-bed"></i></div>
        <div><span>Available Beds</span><strong><?php echo $availableBeds; ?></strong></div>
    </div>
</div>

<div class="details-grid section-mb">
    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-building"></i></div>
            <div><h4>Add Hostel</h4><p>Register boys/girls hostel block</p></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_hostel">
            <div class="form-grid form-grid-1 form-grid-spaced">
                <div class="form-field"><label>Hostel Name</label><input type="text" name="name" class="form-input" placeholder="e.g. Boys Hostel Block A" required></div>
                <div class="form-field"><label>Address / Location</label><input type="text" name="address" class="form-input" placeholder="Campus location"></div>
            </div>
            <div class="form-actions-end"><button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Add Hostel</button></div>
        </form>
    </div>
    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-door-open"></i></div>
            <div><h4>Add Room</h4><p>Add rooms under a hostel</p></div>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_room">
            <div class="form-grid form-grid-2 form-grid-spaced">
                <div class="form-field"><label>Hostel</label>
                    <select name="hostel_id" class="form-input form-select" required>
                        <?php foreach ($hostels as $h): ?><option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field"><label>Room Number</label><input type="text" name="room_no" class="form-input" placeholder="e.g. 101" required></div>
                <div class="form-field"><label>Room Type</label><input type="text" name="room_type" class="form-input" value="Standard" placeholder="Standard / AC"></div>
                <div class="form-field"><label>Bed Capacity</label><input type="number" name="capacity" class="form-input" value="2" min="1"></div>
            </div>
            <div class="form-actions-end"><button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Add Room</button></div>
        </form>
    </div>
</div>

<?php if ($hostels): ?>
<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-hotel"></i></div>
        <div><h4>Hostel Blocks</h4><p><?php echo count($hostels); ?> active hostel(s)</p></div>
    </div>
    <div class="erp-hostel-grid">
        <?php foreach ($hostels as $h):
            $hostelRooms = array_filter($rooms, fn($r) => $r['hostel_name'] === $h['name']);
            $hostelOcc = array_sum(array_column($hostelRooms, 'occupied'));
            $hostelCap = array_sum(array_column($hostelRooms, 'capacity'));
        ?>
        <div class="erp-hostel-card">
            <div class="erp-hostel-icon"><i class="fas fa-building"></i></div>
            <div class="erp-hostel-body">
                <div class="erp-hostel-head">
                    <strong><?php echo htmlspecialchars($h['name']); ?></strong>
                    <button type="submit" form="hostel-delete-<?php echo (int) $h['id']; ?>" class="action-btn delete-btn erp-hostel-delete" title="Delete hostel" onclick="return confirm(<?php echo json_encode('Delete hostel "' . $h['name'] . '" and all its rooms?'); ?>);"><i class="fas fa-trash"></i></button>
                </div>
                <span><?php echo displayVal($h['address'], 'No address'); ?></span>
                <div class="erp-occupancy-bar">
                    <div class="erp-occupancy-fill" style="width:<?php echo $hostelCap ? min(100, round($hostelOcc / $hostelCap * 100)) : 0; ?>%"></div>
                </div>
                <small><?php echo count($hostelRooms); ?> rooms · <?php echo $hostelOcc; ?>/<?php echo $hostelCap; ?> beds occupied</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-user-plus"></i></div>
        <div><h4>Allot Room to Student</h4><p>Search student and pick an available room</p></div>
    </div>
    <form method="GET" class="category-add-row">
        <div class="form-field form-field-grow"><label>Find student</label><input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or admission no."></div>
        <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button></div>
    </form>
</div>

<?php if ($searchResults): ?>
<div class="erp-search-results student-search-results">
<?php foreach ($searchResults as $sr): ?>
<form method="POST" class="erp-search-item student-search-card student-portal-card">
    <input type="hidden" name="action" value="allot">
    <input type="hidden" name="student_id" value="<?php echo $sr['id']; ?>">
    <div class="student-search-main">
        <div class="student-search-avatar"><i class="fas fa-user-graduate"></i></div>
        <div class="student-search-info">
            <strong><?php echo htmlspecialchars($sr['name']); ?></strong>
            <span><?php echo htmlspecialchars($sr['ad_no']); ?></span>
            <div class="student-search-meta">
                <span class="student-search-class-pill"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($sr['class']); ?></span>
            </div>
        </div>
    </div>
    <div class="student-search-actions">
        <span class="student-search-actions-label">Select room</span>
        <select name="room_id" class="form-input form-select erp-assign-select" required>
            <?php foreach ($rooms as $rm):
                $free = (int)$rm['capacity'] - (int)$rm['occupied'];
            ?>
            <option value="<?php echo $rm['id']; ?>" <?php echo $free <= 0 ? 'disabled' : ''; ?>>
                <?php echo htmlspecialchars($rm['hostel_name'] . ' — Room ' . $rm['room_no']); ?> (<?php echo $rm['occupied']; ?>/<?php echo $rm['capacity']; ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-bed"></i> Allot</button>
    </div>
</form>
<?php endforeach; ?>
</div>
<?php elseif ($search !== ''): ?>
<div class="tab-empty-state tab-empty-pad-sm"><p>No students found.</p></div>
<?php endif; ?>

<div class="table-container section-mb">
    <div class="table-toolbar"><strong>Rooms</strong><span class="toolbar-meta"><?php echo $occupiedBeds; ?>/<?php echo $totalBeds; ?> beds occupied</span></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Hostel</th><th>Room</th><th>Type</th><th>Occupancy</th><th>Status</th><th class="th-actions">Actions</th></tr></thead>
            <tbody>
            <?php if ($rooms): foreach ($rooms as $rm):
                $occ = (int) $rm['occupied'];
                $cap = (int) $rm['capacity'];
                $pct = $cap ? round($occ / $cap * 100) : 0;
                $full = $occ >= $cap;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($rm['hostel_name']); ?></td>
                <td><strong><?php echo htmlspecialchars($rm['room_no']); ?></strong></td>
                <td><?php echo htmlspecialchars($rm['room_type']); ?></td>
                <td>
                    <div class="erp-occupancy-inline">
                        <div class="erp-occupancy-bar erp-occupancy-bar-sm"><div class="erp-occupancy-fill <?php echo $full ? 'is-full' : ''; ?>" style="width:<?php echo min(100, $pct); ?>%"></div></div>
                        <span><?php echo $occ; ?>/<?php echo $cap; ?></span>
                    </div>
                </td>
                <td><span class="status-badge <?php echo $full ? 'badge-inactive' : 'badge-active'; ?>"><?php echo $full ? 'Full' : 'Available'; ?></span></td>
                <td>
                    <?php if ($occ === 0): ?>
                    <button type="submit" form="room-delete-<?php echo (int) $rm['id']; ?>" class="action-btn delete-btn" title="Delete room" onclick="return confirm(<?php echo json_encode('Delete room ' . $rm['room_no'] . '?'); ?>);"><i class="fas fa-trash"></i></button>
                    <?php else: ?>
                    <span class="toolbar-meta">Vacate first</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="table-empty-cell">No rooms added yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($allotments): ?>
<div class="table-container">
    <div class="table-toolbar"><strong>Active Allotments</strong><span class="toolbar-meta"><?php echo count($allotments); ?> student(s)</span></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Student</th><th>Adm No</th><th>Class</th><th>Hostel</th><th>Room</th><th>From</th><th class="th-actions">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($allotments as $a): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($a['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($a['ad_no']); ?></td>
                <td><?php echo displayVal($a['class'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($a['hostel_name']); ?></td>
                <td><span class="promo-next-pill"><?php echo htmlspecialchars($a['room_no']); ?></span></td>
                <td><?php echo displayVal($a['allotted_from']); ?></td>
                <td>
                    <button type="submit" form="vacate-<?php echo (int) $a['id']; ?>" class="action-btn delete-btn" title="Vacate allotment" onclick="return confirm(<?php echo json_encode('Vacate ' . $a['name'] . ' from hostel?'); ?>);"><i class="fas fa-user-minus"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php foreach ($hostels as $h): ?>
<form method="POST" id="hostel-delete-<?php echo (int) $h['id']; ?>" class="hidden-form">
    <input type="hidden" name="action" value="delete_hostel">
    <input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
</form>
<?php endforeach; ?>
<?php foreach ($rooms as $rm): ?>
<form method="POST" id="room-delete-<?php echo (int) $rm['id']; ?>" class="hidden-form">
    <input type="hidden" name="action" value="delete_room">
    <input type="hidden" name="id" value="<?php echo (int) $rm['id']; ?>">
</form>
<?php endforeach; ?>
<?php foreach ($allotments as $a): ?>
<form method="POST" id="vacate-<?php echo (int) $a['id']; ?>" class="hidden-form">
    <input type="hidden" name="action" value="vacate">
    <input type="hidden" name="allotment_id" value="<?php echo (int) $a['id']; ?>">
</form>
<?php endforeach; ?>
<?php require_once 'includes/footer.php'; ?>
