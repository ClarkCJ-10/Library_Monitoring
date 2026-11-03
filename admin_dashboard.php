<?php
/**
 * Administrator Dashboard Overview
 */

$pageTitle = "Admin Dashboard";
$activeNav = "dashboard";

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$db = get_db_connection();

// Refresh and fetch seat counts
$seatCounts = get_seat_counts();

// Admin Statistics
$totalStudents = (int)$db->query("SELECT COUNT(*) FROM students WHERE role = 'student'")->fetchColumn();
$activeSessions = (int)$db->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")->fetchColumn();
$todayReservations = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE DATE(reservation_start) = CURDATE()")->fetchColumn();

// Active Library Sessions (top 8)
$stmtActive = $db->query("
    SELECT s.id, s.check_in_time, s.expected_end_time,
           st.id AS student_id, st.student_id AS student_code, st.full_name, st.course,
           se.seat_number,
           TIMESTAMPDIFF(SECOND, NOW(), s.expected_end_time) AS remaining_seconds
    FROM sessions s
    JOIN students st ON st.id = s.student_id
    JOIN seats se ON se.id = s.seat_id
    WHERE s.status = 'active'
    ORDER BY s.check_in_time DESC
    LIMIT 8
");
$activeRows = $stmtActive->fetchAll();

// Recent Reservations
$stmtRecentRes = $db->query("
    SELECT r.id, r.reservation_start, r.reservation_end, r.status,
           st.student_id AS student_code, st.full_name, st.course,
           se.seat_number
    FROM reservations r
    JOIN students st ON st.id = r.student_id
    JOIN seats se ON se.id = r.seat_id
    ORDER BY r.id DESC
    LIMIT 8
");
$recentResRows = $stmtRecentRes->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-intro" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2>Library Administration Overview</h2>
        <p>Real-time monitoring of library seats, active student sessions, and daily reservations.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?= BASE_URL; ?>admin/manage_seats.php" class="btn btn-outline">🪑 Manage Seats</a>
        <a href="<?= BASE_URL; ?>admin/manage_students.php" class="btn btn-primary">+ Register Student</a>
    </div>
</div>

<!-- 7 System Statistic Cards -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="stat-icon-wrapper">🪑</div>
        <div class="stat-info">
            <span class="stat-label">Total Seats</span>
            <span class="stat-value" id="stat-total"><?= $seatCounts['total']; ?></span>
        </div>
    </div>

    <div class="stat-card available">
        <div class="stat-icon-wrapper">✅</div>
        <div class="stat-info">
            <span class="stat-label">Available Seats</span>
            <span class="stat-value" id="stat-available"><?= $seatCounts['available']; ?></span>
        </div>
    </div>

    <div class="stat-card reserved">
        <div class="stat-icon-wrapper">📌</div>
        <div class="stat-info">
            <span class="stat-label">Reserved Seats</span>
            <span class="stat-value" id="stat-reserved"><?= $seatCounts['reserved']; ?></span>
        </div>
    </div>

    <div class="stat-card occupied">
        <div class="stat-icon-wrapper">👤</div>
        <div class="stat-info">
            <span class="stat-label">Occupied Seats</span>
            <span class="stat-value" id="stat-occupied"><?= $seatCounts['occupied']; ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper" style="background: #ede9fe; color: #7c3aed;">👥</div>
        <div class="stat-info">
            <span class="stat-label">Total Students</span>
            <span class="stat-value"><?= $totalStudents; ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper" style="background: #e0f2fe; color: #0284c7;">⏱️</div>
        <div class="stat-info">
            <span class="stat-label">Active Sessions</span>
            <span class="stat-value"><?= $activeSessions; ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper" style="background: #fef3c7; color: #d97706;">📋</div>
        <div class="stat-info">
            <span class="stat-label">Reservations Today</span>
            <span class="stat-value"><?= $todayReservations; ?></span>
        </div>
    </div>
</div>

<!-- Active Student Sessions Quick Monitor -->
<div class="card">
    <div class="card-header">
        <h3>⏱️ Current Active Student Sessions</h3>
        <a href="<?= BASE_URL; ?>admin/manage_sessions.php" class="btn btn-sm btn-outline">View All Sessions &rarr;</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Assigned Seat</th>
                    <th>Check In Time</th>
                    <th>Expected End</th>
                    <th>Remaining Time</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activeRows)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No active library sessions right now. All checked-in students will appear here in real-time.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activeRows as $sess): ?>
                        <tr>
                            <td>
                                <strong><?= e($sess['full_name']); ?></strong>
                                <?php if (!empty($sess['course'])): ?>
                                    <br><small style="color: var(--primary); font-weight: 500;">🎓 <?= e($sess['course']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($sess['student_code']); ?></td>
                            <td><span class="badge badge-occupied"><?= e($sess['seat_number']); ?></span></td>
                            <td><?= date('g:i:s A', strtotime($sess['check_in_time'])); ?></td>
                            <td><?= date('g:i:s A', strtotime($sess['expected_end_time'])); ?></td>
                            <td>
                                <span class="badge badge-primary" style="font-family: monospace; font-size: 13px;" data-countdown-end="<?= date('c', strtotime($sess['expected_end_time'])); ?>">
                                    --:--:--
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <form action="<?= BASE_URL; ?>check_out.php" method="POST" style="display: inline;">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="session_id" value="<?= $sess['id']; ?>">
                                    <input type="hidden" name="student_id" value="<?= $sess['student_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Force checkout student <?= e(addslashes($sess['full_name'])); ?>?');">
                                        Check Out
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Reservations -->
<div class="card">
    <div class="card-header">
        <h3>📌 Recent Seat Bookings</h3>
        <a href="<?= BASE_URL; ?>admin/manage_reservations.php" class="btn btn-sm btn-outline">View All &rarr;</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Seat</th>
                    <th>Reserved Time</th>
                    <th>Expiry Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentResRows)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            No recent reservations found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentResRows as $res): ?>
                        <tr>
                            <td>#<?= $res['id']; ?></td>
                            <td>
                                <strong><?= e($res['full_name']); ?></strong>
                                <?php if (!empty($res['course'])): ?>
                                    <br><small style="color: var(--primary); font-weight: 500;">🎓 <?= e($res['course']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($res['student_code']); ?></td>
                            <td><?= e($res['seat_number']); ?></td>
                            <td><?= date('M d, g:i A', strtotime($res['reservation_start'])); ?></td>
                            <td><?= date('g:i A', strtotime($res['reservation_end'])); ?></td>
                            <td>
                                <span class="badge badge-<?= e($res['status']); ?>">
                                    <?= ucfirst(e($res['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

