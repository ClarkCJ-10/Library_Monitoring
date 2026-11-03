<?php
/**
 * Administrator: Reservation Management
 */

$pageTitle = "Reservation Management";
$activeNav = "reservations";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Auto-clean expired reservations
release_expired_reservations_and_sessions();

$db = get_db_connection();

// Filters
$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');

$sql = "
    SELECT r.*, 
           st.student_id AS student_code, st.full_name, st.email, st.course,
           se.seat_number,
           TIMESTAMPDIFF(SECOND, NOW(), r.reservation_end) AS remaining_seconds
    FROM reservations r
    JOIN students st ON st.id = r.student_id
    JOIN seats se ON se.id = r.seat_id
    WHERE 1=1
";
$params = [];

if (!empty($statusFilter)) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (st.full_name LIKE ? OR st.student_id LIKE ? OR se.seat_number LIKE ? OR st.course LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($dateFilter)) {
    $sql .= " AND DATE(r.reservation_start) = ?";
    $params[] = $dateFilter;
}

$sql .= " ORDER BY r.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-intro">
    <h2>Library Seat Reservations</h2>
    <p>Track all student reservations, monitor pending check-ins, and manage cancellations.</p>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="<?= BASE_URL; ?>admin/manage_reservations.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="form-control" placeholder="Search student name, ID, or seat..." value="<?= e($search); ?>">
            </div>
            <div style="width: 170px;">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="reserved" <?= $statusFilter === 'reserved' ? 'selected' : ''; ?>>Reserved (Pending)</option>
                    <option value="occupied" <?= $statusFilter === 'occupied' ? 'selected' : ''; ?>>Occupied (Checked In)</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <div style="width: 160px;">
                <input type="date" name="date" class="form-control" value="<?= e($dateFilter); ?>">
            </div>
            <button type="submit" class="btn btn-outline">Filter</button>
            <?php if (!empty($statusFilter) || !empty($search) || !empty($dateFilter)): ?>
                <a href="<?= BASE_URL; ?>admin/manage_reservations.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Reservations Table -->
<div class="card">
    <div class="card-header">
        <h3>📋 Reservation History (<?= count($reservations); ?> results)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Seat Number</th>
                    <th>Reservation Start</th>
                    <th>Check-In Deadline</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservations)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No reservation records match your criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reservations as $r): ?>
                        <tr>
                            <td><strong>#<?= $r['id']; ?></strong></td>
                            <td>
                                <strong><?= e($r['full_name']); ?></strong>
                                <?php if (!empty($r['course'])): ?>
                                    <br><small style="color: var(--primary); font-weight: 500;">🎓 <?= e($r['course']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-family: monospace;"><?= e($r['student_code']); ?></span></td>
                            <td><span class="badge badge-primary"><?= e($r['seat_number']); ?></span></td>
                            <td><?= date('M d, Y g:i A', strtotime($r['reservation_start'])); ?></td>
                            <td>
                                <?= date('g:i A', strtotime($r['reservation_end'])); ?>
                                <?php if ($r['status'] === 'reserved'): ?>
                                    <br><small style="color: var(--warning); font-weight: 600;">
                                        (<?= max(0, round($r['remaining_seconds'] / 60, 1)); ?> mins left)
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($r['status']); ?>">
                                    <?= ucfirst(e($r['status'])); ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($r['status'] === 'reserved'): ?>
                                    <form action="<?= BASE_URL; ?>cancel_reservation.php" method="POST" style="display: inline;">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="reservation_id" value="<?= $r['id']; ?>">
                                        <input type="hidden" name="student_id" value="<?= $r['student_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this active reservation for <?= e(addslashes($r['full_name'])); ?>? The seat will be released immediately.');">
                                            Cancel
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--gray-400); font-size: 13px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

