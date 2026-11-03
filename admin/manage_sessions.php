<?php
/**
 * Administrator: Session Monitoring & History
 */

$pageTitle = "Session Monitoring";
$activeNav = "sessions";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

// Auto-clean expired sessions
release_expired_reservations_and_sessions();

$db = get_db_connection();

$viewTab = ($_GET['view'] ?? '') === 'all' ? 'all' : 'active';

if ($viewTab === 'active') {
    $stmt = $db->query("
        SELECT s.*, 
               st.id AS student_id, st.student_id AS student_code, st.full_name, st.email, st.course,
               se.seat_number,
               TIMESTAMPDIFF(SECOND, NOW(), s.expected_end_time) AS remaining_seconds
        FROM sessions s
        JOIN students st ON st.id = s.student_id
        JOIN seats se ON se.id = s.seat_id
        WHERE s.status = 'active'
        ORDER BY s.check_in_time DESC
    ");
} else {
    $stmt = $db->query("
        SELECT s.*, 
               st.id AS student_id, st.student_id AS student_code, st.full_name, st.email, st.course,
               se.seat_number,
               TIMESTAMPDIFF(SECOND, NOW(), s.expected_end_time) AS remaining_seconds,
               TIMESTAMPDIFF(MINUTE, s.check_in_time, COALESCE(s.check_out_time, NOW())) AS elapsed_minutes
        FROM sessions s
        JOIN students st ON st.id = s.student_id
        JOIN seats se ON se.id = s.seat_id
        ORDER BY s.id DESC
        LIMIT 100
    ");
}
$sessions = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-intro" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2>Library Session Monitoring</h2>
        <p>Real-time countdown and monitoring of students actively occupying library seats.</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="<?= BASE_URL; ?>admin/manage_sessions.php?view=active" class="btn <?= $viewTab === 'active' ? 'btn-primary' : 'btn-outline'; ?>">
            ⏱️ Active Only
        </a>
        <a href="<?= BASE_URL; ?>admin/manage_sessions.php?view=all" class="btn <?= $viewTab === 'all' ? 'btn-primary' : 'btn-outline'; ?>">
            📜 All Sessions History
        </a>
    </div>
</div>

<!-- Sessions Table -->
<div class="card">
    <div class="card-header">
        <h3>
            <?= $viewTab === 'active' ? '🟢 Currently Active Students in Library (' . count($sessions) . ')' : '📋 Session History (' . count($sessions) . ' records)'; ?>
        </h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Session ID</th>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Seat Code</th>
                    <th>Check In Time</th>
                    <th>Expected End Time</th>
                    <th>Remaining Session Time</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sessions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 35px;">
                            <?= $viewTab === 'active' ? 'No active student sessions currently. When students check in, their sessions and live countdowns will appear here.' : 'No sessions recorded.'; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td><strong>#<?= $s['id']; ?></strong></td>
                            <td>
                                <strong><?= e($s['full_name']); ?></strong>
                                <?php if (!empty($s['course'])): ?>
                                    <br><small style="color: var(--primary); font-weight: 500;">🎓 <?= e($s['course']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-family: monospace;"><?= e($s['student_code']); ?></span></td>
                            <td><span class="badge badge-occupied"><?= e($s['seat_number']); ?></span></td>
                            <td><?= date('M d, g:i:s A', strtotime($s['check_in_time'])); ?></td>
                            <td><?= date('g:i:s A', strtotime($s['expected_end_time'])); ?></td>
                            <td>
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="badge badge-primary" style="font-family: monospace; font-size: 13px;" data-countdown-end="<?= date('c', strtotime($s['expected_end_time'])); ?>">
                                        --:--:--
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Ended (<?= date('g:i A', strtotime($s['check_out_time'] ?? $s['expected_end_time'])); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($s['status']); ?>">
                                    <?= ucfirst(e($s['status'])); ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($s['status'] === 'active'): ?>
                                    <form action="<?= BASE_URL; ?>check_out.php" method="POST" style="display: inline;">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="session_id" value="<?= $s['id']; ?>">
                                        <input type="hidden" name="student_id" value="<?= $s['student_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Force check out student <?= e(addslashes($s['full_name'])); ?>? This will release seat <?= e(addslashes($s['seat_number'])); ?>.');">
                                            🚪 Force Check Out
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

