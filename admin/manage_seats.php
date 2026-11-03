<?php
/**
 * Administrator: Seat Management
 */

$pageTitle = "Seat Management";
$activeNav = "seats";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();

// Handle Seat Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Security session expired. Please try again.";
        header("Location: " . BASE_URL . "admin/manage_seats.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Add New Seat
    if ($action === 'add_seat') {
        $seatNumber = trim($_POST['seat_number'] ?? '');
        if (empty($seatNumber)) {
            $_SESSION['flash_error'] = "Please provide a valid seat identifier/number.";
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM seats WHERE seat_number = ?");
            $chk->execute([$seatNumber]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "Seat number '{$seatNumber}' already exists.";
            } else {
                $stmt = $db->prepare("INSERT INTO seats (seat_number, seat_status, is_active, created_at) VALUES (?, 'available', 1, NOW())");
                $stmt->execute([$seatNumber]);
                log_audit('seat_added', "New seat added: {$seatNumber}.", current_user()['id']);
                $_SESSION['flash_success'] = "Seat {$seatNumber} created successfully.";
            }
        }
        header("Location: " . BASE_URL . "admin/manage_seats.php");
        exit;
    }

    // 2. Toggle Active Status (Deactivate / Activate)
    if ($action === 'toggle_active') {
        $seatId = (int)($_POST['seat_id'] ?? 0);

        // Fetch seat details
        $stmtSeat = $db->prepare("SELECT * FROM seats WHERE id = ?");
        $stmtSeat->execute([$seatId]);
        $seat = $stmtSeat->fetch();

        if (!$seat) {
            $_SESSION['flash_error'] = "Seat not found.";
        } else {
            $newStatus = $seat['is_active'] ? 0 : 1;

            // RULE: Prevent deactivation of occupied seats until active session handled
            if ($newStatus === 0 && $seat['seat_status'] === 'occupied') {
                $_SESSION['flash_error'] = "Cannot deactivate seat {$seat['seat_number']}: It is currently OCCUPIED by an active student session. Please check out the student session first.";
            } elseif ($newStatus === 0 && $seat['seat_status'] === 'reserved') {
                $_SESSION['flash_error'] = "Cannot deactivate seat {$seat['seat_number']}: It is currently RESERVED for an upcoming student check-in. Cancel the reservation first.";
            } else {
                $upd = $db->prepare("UPDATE seats SET is_active = ? WHERE id = ?");
                $upd->execute([$newStatus, $seatId]);

                $label = $newStatus ? 'activated' : 'deactivated';
                log_audit('seat_status_toggled', "Seat {$seat['seat_number']} {$label}.", current_user()['id']);
                $_SESSION['flash_success'] = "Seat {$seat['seat_number']} has been {$label}.";
            }
        }
        header("Location: " . BASE_URL . "admin/manage_seats.php");
        exit;
    }

    // 3. Edit Seat Number
    if ($action === 'edit_seat') {
        $seatId = (int)($_POST['seat_id'] ?? 0);
        $newNumber = trim($_POST['seat_number'] ?? '');

        if (empty($newNumber)) {
            $_SESSION['flash_error'] = "Seat number cannot be blank.";
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM seats WHERE seat_number = ? AND id != ?");
            $chk->execute([$newNumber, $seatId]);
            if ($chk->fetch()) {
                $_SESSION['flash_error'] = "Seat number '{$newNumber}' is already in use.";
            } else {
                $upd = $db->prepare("UPDATE seats SET seat_number = ? WHERE id = ?");
                $upd->execute([$newNumber, $seatId]);
                log_audit('seat_updated', "Seat ID #{$seatId} renamed to {$newNumber}.", current_user()['id']);
                $_SESSION['flash_success'] = "Seat updated successfully.";
            }
        }
        header("Location: " . BASE_URL . "admin/manage_seats.php");
        exit;
    }
}

// Seat Counts
$counts = get_seat_counts();

// Fetch all seats
$seatsList = $db->query("
    SELECT s.*, 
           (SELECT st.full_name FROM sessions sess JOIN students st ON st.id = sess.student_id WHERE sess.seat_id = s.id AND sess.status = 'active' LIMIT 1) AS current_user_name
    FROM seats s 
    ORDER BY s.id ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-intro" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2>Library Seat Management</h2>
        <p>Add new seats, toggle active status, and monitor seat occupancy.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('add-seat-modal')">
        ➕ Add New Seat
    </button>
</div>

<!-- Seat Summary Cards -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="stat-icon-wrapper">🪑</div>
        <div class="stat-info">
            <span class="stat-label">Active Total</span>
            <span class="stat-value" id="stat-total"><?= $counts['total']; ?></span>
        </div>
    </div>
    <div class="stat-card available">
        <div class="stat-icon-wrapper">✅</div>
        <div class="stat-info">
            <span class="stat-label">Available</span>
            <span class="stat-value" id="stat-available"><?= $counts['available']; ?></span>
        </div>
    </div>
    <div class="stat-card reserved">
        <div class="stat-icon-wrapper">📌</div>
        <div class="stat-info">
            <span class="stat-label">Reserved</span>
            <span class="stat-value" id="stat-reserved"><?= $counts['reserved']; ?></span>
        </div>
    </div>
    <div class="stat-card occupied">
        <div class="stat-icon-wrapper">👤</div>
        <div class="stat-info">
            <span class="stat-label">Occupied</span>
            <span class="stat-value" id="stat-occupied"><?= $counts['occupied']; ?></span>
        </div>
    </div>
</div>

<!-- Seats Table Card -->
<div class="card">
    <div class="card-header">
        <h3>📋 Registered Library Seats (<?= count($seatsList); ?> total)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Seat ID</th>
                    <th>Seat Code</th>
                    <th>Availability Status</th>
                    <th>Current Occupant</th>
                    <th>Operational State</th>
                    <th>Added On</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($seatsList as $seat): ?>
                    <tr>
                        <td><strong>#<?= $seat['id']; ?></strong></td>
                        <td><span style="font-family: monospace; font-weight: 700;"><?= e($seat['seat_number']); ?></span></td>
                        <td>
                            <span class="badge badge-<?= e($seat['seat_status']); ?>">
                                <?= ucfirst(e($seat['seat_status'])); ?>
                            </span>
                        </td>
                        <td>
                            <?= $seat['current_user_name'] ? '<strong>' . e($seat['current_user_name']) . '</strong>' : '<span style="color: var(--gray-400);">None</span>'; ?>
                        </td>
                        <td>
                            <?php if ($seat['is_active']): ?>
                                <span class="badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Deactivated</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($seat['created_at'])); ?></td>
                        <td style="text-align: right; white-space: nowrap;">
                            <!-- Edit Button -->
                            <button type="button" class="btn btn-sm btn-outline" onclick="openEditSeatModal(<?= $seat['id']; ?>, '<?= e(addslashes($seat['seat_number'])); ?>')">
                                ✏️ Edit
                            </button>

                            <!-- Toggle Active/Deactivate Form -->
                            <form action="<?= BASE_URL; ?>admin/manage_seats.php" method="POST" style="display: inline;">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="seat_id" value="<?= $seat['id']; ?>">
                                <?php if ($seat['is_active']): ?>
                                    <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Deactivate seat <?= e(addslashes($seat['seat_number'])); ?>? It will not be offered to students.');">
                                        Deactivate
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success">
                                        Activate
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Seat -->
<div id="add-seat-modal" class="modal-overlay">
    <div class="modal-container">
        <form action="<?= BASE_URL; ?>admin/manage_seats.php" method="POST">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add_seat">

            <div class="modal-header">
                <h3>Add New Library Seat</h3>
                <button type="button" class="modal-close" onclick="closeModal('add-seat-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Seat Code / Number</label>
                    <input type="text" name="seat_number" class="form-control" placeholder="e.g. S-041 or Desk-12" required>
                    <small class="form-text">Seat codes must be unique across the library.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('add-seat-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Seat</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Seat -->
<div id="edit-seat-modal" class="modal-overlay">
    <div class="modal-container">
        <form action="<?= BASE_URL; ?>admin/manage_seats.php" method="POST">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="edit_seat">
            <input type="hidden" name="seat_id" id="edit-seat-id" value="">

            <div class="modal-header">
                <h3>Edit Seat Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('edit-seat-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Seat Code / Number</label>
                    <input type="text" name="seat_number" id="edit-seat-number" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('edit-seat-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Seat</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSeatModal(id, code) {
    document.getElementById('edit-seat-id').value = id;
    document.getElementById('edit-seat-number').value = code;
    openModal('edit-seat-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

