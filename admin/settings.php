<?php
/**
 * Administrator: System Settings & Configuration
 */

$pageTitle = "System Settings";
$activeNav = "settings";

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();
$settings = get_system_settings();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Security session expired. Please try again.";
        header("Location: " . BASE_URL . "admin/settings.php");
        exit;
    }

    $sessionDuration = (int)($_POST['session_duration_minutes'] ?? 120);
    $resExpiration   = (int)($_POST['reservation_expiration_minutes'] ?? 5);
    $openTime        = trim($_POST['library_open_time'] ?? '08:00:00');
    $closeTime       = trim($_POST['library_close_time'] ?? '18:00:00');

    if ($sessionDuration < 10 || $sessionDuration > 480) {
        $_SESSION['flash_error'] = "Session duration must be between 10 minutes and 480 minutes (8 hours).";
    } elseif ($resExpiration < 1 || $resExpiration > 60) {
        $_SESSION['flash_error'] = "Reservation expiration window must be between 1 and 60 minutes.";
    } else {
        $stmt = $db->prepare("
            UPDATE settings 
            SET session_duration_minutes = ?, reservation_expiration_minutes = ?, library_open_time = ?, library_close_time = ?
            WHERE id = 1
        ");
        $stmt->execute([$sessionDuration, $resExpiration, $openTime, $closeTime]);

        log_audit('settings_updated', "Updated session duration to {$sessionDuration}m, expiry window to {$resExpiration}m.", current_user()['id']);
        $_SESSION['flash_success'] = "Library settings updated successfully!";
    }

    header("Location: " . BASE_URL . "admin/settings.php");
    exit;
}

// Fetch recent audit logs
$auditLogs = $db->query("
    SELECT a.*, st.full_name, st.student_id 
    FROM audit_logs a 
    LEFT JOIN students st ON st.id = a.user_id 
    ORDER BY a.id DESC 
    LIMIT 20
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-intro">
    <h2>System Configuration & Parameters</h2>
    <p>Configure default session times, reservation cancellation windows, and inspect security audit logs.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; margin-bottom: 24px;">
    <!-- Settings Form Card -->
    <div class="card">
        <div class="card-header">
            <h3>⚙️ General Library Settings</h3>
        </div>
        <div class="card-body">
            <form action="<?= BASE_URL; ?>admin/settings.php" method="POST">
                <?= csrf_field(); ?>

                <div class="form-group">
                    <label class="form-label">Library Session Duration (Minutes)</label>
                    <input type="number" name="session_duration_minutes" class="form-control" value="<?= (int)$settings['session_duration_minutes']; ?>" min="10" max="480" required>
                    <small class="form-text">Default: 120 minutes (2 hours). Maximum allowed library session per check-in.</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Reservation Check-In Window (Minutes)</label>
                    <input type="number" name="reservation_expiration_minutes" class="form-control" value="<?= (int)$settings['reservation_expiration_minutes']; ?>" min="1" max="60" required>
                    <small class="form-text">Default: 5 minutes. If a student does not check in before this window expires, the seat is released.</small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label">Library Opening Time</label>
                        <input type="time" name="library_open_time" class="form-control" value="<?= e($settings['library_open_time']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Library Closing Time</label>
                        <input type="time" name="library_close_time" class="form-control" value="<?= e($settings['library_close_time']); ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                    💾 Save System Settings
                </button>
            </form>
        </div>
    </div>

    <!-- System Status & Specs Card -->
    <div class="card">
        <div class="card-header">
            <h3>🛡️ Security & Environment Status</h3>
        </div>
        <div class="card-body">
            <table class="data-table" style="font-size: 13.5px;">
                <tr>
                    <td><strong>PHP Version</strong></td>
                    <td><?= phpversion(); ?></td>
                </tr>
                <tr>
                    <td><strong>Database Driver</strong></td>
                    <td>PDO MySQL (Strict Mode)</td>
                </tr>
                <tr>
                    <td><strong>Authentication Method</strong></td>
                    <td>Native PHP Sessions + <code>password_hash()</code></td>
                </tr>
                <tr>
                    <td><strong>Concurrency Engine</strong></td>
                    <td>InnoDB Transactions with <code>FOR UPDATE</code> locking</td>
                </tr>
                <tr>
                    <td><strong>CSRF Protection</strong></td>
                    <td><span class="badge badge-success">Enabled & Active</span></td>
                </tr>
                <tr>
                    <td><strong>Expiration Handler</strong></td>
                    <td><span class="badge badge-success">Automated Server-Side Cleanup</span></td>
                </tr>
            </table>

            <div style="margin-top: 20px; text-align: center;">
                <a href="<?= BASE_URL; ?>setup.php" class="btn btn-outline" style="width: 100%;">
                    🔧 Open Administrator Setup Wizard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Security Audit Log Card -->
<div class="card">
    <div class="card-header">
        <h3>📜 Recent System Audit Logs (Security Tracking)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table" style="font-size: 13px;">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Timestamp</th>
                    <th>User / Actor</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($auditLogs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 25px;">
                            No audit logs generated yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td>#<?= $log['id']; ?></td>
                            <td><?= date('M d, g:i:s A', strtotime($log['created_at'])); ?></td>
                            <td><?= $log['full_name'] ? e($log['full_name']) . " (" . e($log['student_id']) . ")" : 'System / Guest'; ?></td>
                            <td><span style="font-family: monospace; font-weight: 600;"><?= e($log['action']); ?></span></td>
                            <td><?= e($log['details']); ?></td>
                            <td><code><?= e($log['ip_address']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

