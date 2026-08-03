<?php
$page_title = "Transport Management";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_vehicle') {
        $pdo->prepare("INSERT INTO transport_vehicles (vehicle_no, model, capacity, driver_name, driver_phone) VALUES (?,?,?,?,?)")
            ->execute([
                trim($_POST['vehicle_no'] ?? ''),
                trim($_POST['model'] ?? ''),
                (int) ($_POST['capacity'] ?? 40),
                trim($_POST['driver_name'] ?? ''),
                trim($_POST['driver_phone'] ?? ''),
            ]);
        $_SESSION['success_msg'] = 'Vehicle added.';
    } elseif ($action === 'add_route') {
        $pdo->prepare("INSERT INTO transport_routes (name, vehicle_id, fare) VALUES (?,?,?)")
            ->execute([
                trim($_POST['name'] ?? ''),
                (int) ($_POST['vehicle_id'] ?? 0) ?: null,
                (float) ($_POST['fare'] ?? 0),
            ]);
        $_SESSION['success_msg'] = 'Route added.';
    } elseif ($action === 'add_stop' && isset($_POST['route_id'])) {
        $pdo->prepare("INSERT INTO transport_stops (route_id, stop_name, pickup_time, sort_order) VALUES (?,?,?,?)")
            ->execute([
                (int) $_POST['route_id'],
                trim($_POST['stop_name'] ?? ''),
                trim($_POST['pickup_time'] ?? '') ?: null,
                (int) ($_POST['sort_order'] ?? 0),
            ]);
        $_SESSION['success_msg'] = 'Stop added.';
    } elseif ($action === 'assign') {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $routeId = (int) ($_POST['route_id'] ?? 0);
        $stopId = (int) ($_POST['stop_id'] ?? 0) ?: null;
        if ($studentId <= 0 || $routeId <= 0) {
            $_SESSION['error_msg'] = 'Select student and route.';
        } else {
            $pdo->prepare(
                "INSERT INTO student_transport (student_id, route_id, stop_id) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE route_id=VALUES(route_id), stop_id=VALUES(stop_id)"
            )->execute([$studentId, $routeId, $stopId]);
            $_SESSION['success_msg'] = 'Student assigned to transport route.';
        }
    } elseif ($action === 'unassign' && isset($_POST['student_id'])) {
        $pdo->prepare("DELETE FROM student_transport WHERE student_id = ?")->execute([(int) $_POST['student_id']]);
        $_SESSION['success_msg'] = 'Transport assignment removed.';
    } elseif ($action === 'delete_vehicle' && isset($_POST['id'])) {
        $pdo->prepare("UPDATE transport_vehicles SET status='Inactive' WHERE id=?")->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Vehicle deactivated.';
    } elseif ($action === 'delete_route' && isset($_POST['id'])) {
        $routeId = (int) $_POST['id'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_transport WHERE route_id=?");
        $stmt->execute([$routeId]);
        $cnt = (int) $stmt->fetchColumn();
        if ($cnt > 0) {
            $_SESSION['error_msg'] = "Cannot delete — $cnt student(s) assigned. Unassign first.";
        } else {
            $pdo->prepare("DELETE FROM transport_stops WHERE route_id=?")->execute([$routeId]);
            $pdo->prepare("UPDATE transport_routes SET status='Inactive' WHERE id=?")->execute([$routeId]);
            $_SESSION['success_msg'] = 'Route deactivated.';
        }
    }
    header('Location: transport.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

require_once 'includes/header.php';

$vehicles = $pdo->query("SELECT * FROM transport_vehicles WHERE status='Active' ORDER BY vehicle_no")->fetchAll(PDO::FETCH_ASSOC);
$routes = $pdo->query(
    "SELECT r.*, v.vehicle_no, v.capacity AS vehicle_capacity, v.driver_name, v.driver_phone
     FROM transport_routes r
     LEFT JOIN transport_vehicles v ON v.id = r.vehicle_id
     WHERE r.status='Active'
     ORDER BY r.name"
)->fetchAll(PDO::FETCH_ASSOC);

$stopsByRoute = [];
foreach ($routes as $r) {
    $stmt = $pdo->prepare("SELECT * FROM transport_stops WHERE route_id = ? ORDER BY sort_order, stop_name");
    $stmt->execute([(int) $r['id']]);
    $stopsByRoute[$r['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$assignments = $pdo->query(
    "SELECT st.*, s.name, s.ad_no, s.class, s.section, r.name AS route_name, r.fare,
            ts.stop_name
     FROM student_transport st
     INNER JOIN students s ON s.id = st.student_id
     INNER JOIN transport_routes r ON r.id = st.route_id
     LEFT JOIN transport_stops ts ON ts.id = st.stop_id
     ORDER BY s.name"
)->fetchAll(PDO::FETCH_ASSOC);

$search = trim($_GET['q'] ?? '');
$searchResults = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        "SELECT id, ad_no, name, class, section FROM students
         WHERE status='Active' AND (name LIKE ? OR ad_no LIKE ?) LIMIT 12"
    );
    $stmt->execute([$like, $like]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$routeAssignedCounts = [];
foreach ($assignments as $a) {
    $rid = (int) $a['route_id'];
    $routeAssignedCounts[$rid] = ($routeAssignedCounts[$rid] ?? 0) + 1;
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-bus"></i></div>
        <div class="content-top-title">
            <h2>Transport Management</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Transport</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="transport_fees.php" class="btn-header-action btn-header-primary"><i class="fas fa-file-invoice-dollar"></i> Transport Fees</a>
        <a href="transport_fee_collect.php" class="btn-header-action btn-header-outline"><i class="fas fa-hand-holding-usd"></i> Collect</a>
    </div>
</div>

<div class="cls-stat-strip">
    <div class="cls-stat-card">
        <div class="cls-stat-icon"><i class="fas fa-bus"></i></div>
        <div><span>Vehicles</span><strong><?php echo count($vehicles); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-blue"><i class="fas fa-route"></i></div>
        <div><span>Routes</span><strong><?php echo count($routes); ?></strong></div>
    </div>
    <div class="cls-stat-card">
        <div class="cls-stat-icon cls-stat-green"><i class="fas fa-user-check"></i></div>
        <div><span>Assigned Students</span><strong><?php echo count($assignments); ?></strong></div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-bus"></i></div>
        <div><h4>Add Vehicle</h4><p>Bus / van with driver details</p></div>
    </div>
    <form method="POST" class="category-add-form">
        <input type="hidden" name="action" value="add_vehicle">
        <div class="category-add-row" style="flex-wrap:wrap">
            <div class="form-field"><label>Vehicle No</label><input type="text" name="vehicle_no" class="form-input" required placeholder="RJ14 AB 1234"></div>
            <div class="form-field"><label>Model</label><input type="text" name="model" class="form-input" placeholder="e.g. Tata Starbus"></div>
            <div class="form-field"><label>Capacity</label><input type="number" name="capacity" class="form-input" value="40" min="1"></div>
            <div class="form-field"><label>Driver Name</label><input type="text" name="driver_name" class="form-input"></div>
            <div class="form-field"><label>Driver Phone</label><input type="text" name="driver_phone" class="form-input"></div>
            <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-plus"></i> Add</button></div>
        </div>
    </form>
</div>

<?php if ($vehicles): ?>
<div class="erp-vehicle-grid section-mb">
    <?php foreach ($vehicles as $v): ?>
    <article class="erp-vehicle-card">
        <div class="erp-vehicle-card-top">
            <div class="erp-vehicle-plate">
                <span class="erp-vehicle-plate-label">Vehicle No.</span>
                <strong><?php echo htmlspecialchars($v['vehicle_no']); ?></strong>
            </div>
            <span class="erp-vehicle-status">Active</span>
        </div>
        <div class="erp-vehicle-visual">
            <div class="erp-vehicle-bus-icon"><i class="fas fa-bus-alt"></i></div>
            <p class="erp-vehicle-model"><?php echo htmlspecialchars(displayVal($v['model'], 'Model not specified')); ?></p>
        </div>
        <div class="erp-vehicle-stats">
            <div class="erp-vehicle-stat"><i class="fas fa-users"></i><div><strong><?php echo (int) $v['capacity']; ?></strong><span>Seats</span></div></div>
            <div class="erp-vehicle-stat"><i class="fas fa-user-tie"></i><div><strong><?php echo htmlspecialchars(displayVal($v['driver_name'], '—')); ?></strong><span>Driver</span></div></div>
            <div class="erp-vehicle-stat"><i class="fas fa-phone"></i><div><strong><?php echo htmlspecialchars(displayVal($v['driver_phone'], '—')); ?></strong><span>Phone</span></div></div>
        </div>
        <form method="POST" onsubmit="return confirm('Deactivate this vehicle?');" style="padding:0 16px 16px">
            <input type="hidden" name="action" value="delete_vehicle">
            <input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
            <button type="submit" class="btn-header-action btn-header-outline btn-sm"><i class="fas fa-trash"></i> Deactivate</button>
        </form>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-route"></i></div>
        <div><h4>Add Route</h4><p>Route name, vehicle and monthly fare (used for fee collect if set)</p></div>
    </div>
    <form method="POST" class="category-add-form">
        <input type="hidden" name="action" value="add_route">
        <div class="category-add-row" style="flex-wrap:wrap">
            <div class="form-field"><label>Route Name</label><input type="text" name="name" class="form-input" required placeholder="e.g. City Center Route"></div>
            <div class="form-field">
                <label>Vehicle</label>
                <select name="vehicle_id" class="form-input form-select">
                    <option value="">— Optional —</option>
                    <?php foreach ($vehicles as $v): ?>
                    <option value="<?php echo (int) $v['id']; ?>"><?php echo htmlspecialchars($v['vehicle_no']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Monthly Fare (₹)</label><input type="number" step="0.01" min="0" name="fare" class="form-input" value="0"></div>
            <div class="form-field category-add-btn-wrap"><label>&nbsp;</label><button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-plus"></i> Add Route</button></div>
        </div>
    </form>
</div>

<?php foreach ($routes as $r):
    $stops = $stopsByRoute[$r['id']] ?? [];
    $assigned = $routeAssignedCounts[$r['id']] ?? 0;
    $cap = (int) ($r['vehicle_capacity'] ?? 0);
?>
<div class="erp-route-panel">
    <div class="erp-route-header">
        <div class="erp-route-title">
            <span class="erp-route-icon"><i class="fas fa-route"></i></span>
            <div>
                <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                <span>
                    <?php echo $r['vehicle_no'] ? htmlspecialchars($r['vehicle_no']) : 'No vehicle'; ?>
                    · Fare ₹<?php echo number_format((float) $r['fare'], 0); ?>/mo
                    · <?php echo $assigned; ?><?php echo $cap ? '/' . $cap : ''; ?> students
                </span>
            </div>
        </div>
        <form method="POST" onsubmit="return confirm('Deactivate this route?');">
            <input type="hidden" name="action" value="delete_route">
            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
            <button type="submit" class="btn-header-action btn-header-outline btn-sm"><i class="fas fa-trash"></i></button>
        </form>
    </div>
    <form method="POST" class="erp-stop-add-row">
        <input type="hidden" name="action" value="add_stop">
        <input type="hidden" name="route_id" value="<?php echo (int) $r['id']; ?>">
        <div class="erp-stop-field"><input type="text" name="stop_name" class="form-input" placeholder="Stop name" required></div>
        <div class="erp-stop-field erp-stop-field-sm"><input type="time" name="pickup_time" class="form-input"></div>
        <div class="erp-stop-field erp-stop-field-sm"><input type="number" name="sort_order" class="form-input" value="0" title="Order"></div>
        <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-plus"></i> Stop</button>
    </form>
    <?php if ($stops): ?>
    <div class="erp-stop-chips">
        <?php foreach ($stops as $i => $st): ?>
        <div class="erp-stop-chip">
            <span class="erp-stop-num"><?php echo $i + 1; ?></span>
            <strong><?php echo htmlspecialchars($st['stop_name']); ?></strong>
            <?php if (!empty($st['pickup_time'])): ?>
            <span><?php echo date('g:i A', strtotime($st['pickup_time'])); ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="erp-route-empty">No stops on this route yet.</p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-user-plus"></i></div>
        <div><h4>Assign Student to Route</h4><p>Search student and assign route / stop</p></div>
    </div>
    <form method="GET" class="category-add-row">
        <div class="form-field form-field-grow">
            <label>Search student</label>
            <input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or admission no.">
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <?php if ($searchResults): ?>
    <div class="erp-search-results student-search-results">
        <?php foreach ($searchResults as $sr): ?>
        <form method="POST" class="erp-search-item student-search-card student-portal-card">
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="student_id" value="<?php echo (int) $sr['id']; ?>">
            <div class="student-search-body" style="flex:1">
                <strong><?php echo htmlspecialchars($sr['name']); ?></strong>
                <span><?php echo htmlspecialchars($sr['ad_no']); ?> · <?php echo htmlspecialchars($sr['class']); ?>-<?php echo htmlspecialchars($sr['section'] ?? 'A'); ?></span>
            </div>
            <select name="route_id" class="form-input form-select erp-assign-select" required data-student="<?php echo (int) $sr['id']; ?>">
                <option value="">Route</option>
                <?php foreach ($routes as $r): ?>
                <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="stop_id" class="form-input form-select erp-assign-select erp-stop-select" data-student="<?php echo (int) $sr['id']; ?>">
                <option value="">Stop (optional)</option>
            </select>
            <button type="submit" class="btn-header-action btn-header-primary btn-sm"><i class="fas fa-check"></i> Assign</button>
        </form>
        <?php endforeach; ?>
    </div>
    <?php elseif ($search !== ''): ?>
    <p class="erp-route-empty">No students found.</p>
    <?php endif; ?>
</div>

<div class="table-container">
    <div class="table-toolbar">
        <strong>Assigned Students</strong>
        <span class="toolbar-meta"><?php echo count($assignments); ?> student(s) on transport</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Student</th><th>Class</th><th>Route</th><th>Stop</th><th>Fare</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php if (!$assignments): ?>
                <tr><td colspan="6" class="table-empty-cell">No students assigned to transport yet.</td></tr>
                <?php else: foreach ($assignments as $a): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($a['name']); ?></strong><br><small><?php echo htmlspecialchars($a['ad_no']); ?></small></td>
                    <td><?php echo htmlspecialchars($a['class']); ?>-<?php echo htmlspecialchars($a['section'] ?? 'A'); ?></td>
                    <td><?php echo htmlspecialchars($a['route_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['stop_name'] ?: '—'); ?></td>
                    <td>₹<?php echo number_format((float) $a['fare'], 0); ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Remove transport assignment?');">
                            <input type="hidden" name="action" value="unassign">
                            <input type="hidden" name="student_id" value="<?php echo (int) $a['student_id']; ?>">
                            <button type="submit" class="action-btn delete-btn" title="Unassign"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
var stopsByRoute = <?php
    $jsStops = [];
    foreach ($stopsByRoute as $rid => $stops) {
        $jsStops[(string) $rid] = array_map(function ($s) {
            return ['id' => (int) $s['id'], 'name' => $s['stop_name']];
        }, $stops);
    }
    echo json_encode($jsStops);
?>;
document.querySelectorAll('select[name="route_id"].erp-assign-select').forEach(function (routeSel) {
    routeSel.addEventListener('change', function () {
        var sid = this.getAttribute('data-student');
        var stopSel = document.querySelector('select.erp-stop-select[data-student="' + sid + '"]');
        if (!stopSel) return;
        var stops = stopsByRoute[this.value] || [];
        stopSel.innerHTML = '<option value="">Stop (optional)</option>';
        stops.forEach(function (st) {
            var opt = document.createElement('option');
            opt.value = st.id;
            opt.textContent = st.name;
            stopSel.appendChild(opt);
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
