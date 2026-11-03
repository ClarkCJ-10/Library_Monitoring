<?php
/**
 * System Setup & First Administrator Creation
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db_connection();

// Check if any admin already exists
$adminCountStmt = $db->query("SELECT COUNT(*) FROM students WHERE role = 'admin'");
$adminCount = (int)$adminCountStmt->fetchColumn();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "CSRF validation failed. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_admin') {
            // If admin already exists and user is not logged in as admin, require master check
            if ($adminCount > 0 && !is_admin()) {
                $error = "An administrator account already exists. Please log in as an administrator to create additional administrators.";
            } else {
                $fullName = trim($_POST['full_name'] ?? '');
                $email    = strtolower(trim($_POST['email'] ?? ''));
                $password = trim($_POST['password'] ?? '');
                $confirm  = trim($_POST['confirm_password'] ?? '');
                $adminId  = trim($_POST['admin_id'] ?? '');

                if (empty($adminId)) {
                    // Auto-generate unique Admin ID if not provided
                    $adminId = 'ADMIN-' . strtoupper(substr(uniqid(), -6));
                }

                if (empty($fullName) || empty($email) || empty($password) || empty($confirm)) {
                    $error = "All fields (Full Name, Gmail Address, Password, and Confirm Password) are required.";
                } elseif (!is_valid_gmail($email)) {
                    $error = "Please provide a valid Gmail address (must end with @gmail.com).";
                } elseif (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } elseif ($password !== $confirm) {
                    $error = "Passwords do not match.";
                } else {
                    // Check if duplicate student_id or email
                    $stmtCheck = $db->prepare("SELECT id FROM students WHERE student_id = ? OR email = ?");
                    $stmtCheck->execute([$adminId, $email]);
                    if ($stmtCheck->fetch()) {
                        $error = "An administrator account with this Gmail address already exists.";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmtIns = $db->prepare("
                            INSERT INTO students (student_id, full_name, email, course, password_hash, role, is_active, created_at)
                            VALUES (?, ?, ?, NULL, ?, 'admin', 1, NOW())
                        ");
                        $stmtIns->execute([$adminId, $fullName, $email, $hash]);

                        log_audit('admin_created', "Administrator account {$email} was created (ID: {$adminId}).");

                        $message = "Administrator account created successfully! You can now log in using your Gmail Address: " . e($email);
                        // Recheck count
                        $adminCount = (int)$db->query("SELECT COUNT(*) FROM students WHERE role = 'admin'")->fetchColumn();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup — Library Monitoring System</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-card" style="max-width: 540px;">
    <div class="auth-header">
        <div class="auth-logo">⚙️</div>
        <h2 class="auth-title">Administrator Setup Wizard</h2>
        <p class="auth-subtitle">Initialize your first secure administrator account</p>
    </div>

    <div class="auth-body">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <div><?= e($message); ?></div>
            </div>
            <div style="text-align: center; margin: 15px 0;">
                <a href="<?= BASE_URL; ?>index.php?tab=admin" class="btn btn-primary">Go to Admin Login</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>❌</span>
                <div><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($adminCount > 0 && !is_admin()): ?>
            <div class="alert alert-info">
                <span>ℹ️</span>
                <div>
                    <strong>Administrator Account Exists</strong><br>
                    There is already <strong><?= $adminCount; ?></strong> administrator account registered in the system.
                    To manage administrator accounts or students, please sign in.
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <a href="<?= BASE_URL; ?>index.php?tab=admin" class="btn btn-primary" style="flex: 1;">Login as Admin</a>
                <a href="<?= BASE_URL; ?>index.php" class="btn btn-outline" style="flex: 1;">Back to Student Portal</a>
            </div>
        <?php else: ?>
            <div class="alert alert-warning" style="font-size: 13px;">
                <span>🛡️</span>
                <div>
                    <strong>Secure Administrator Creation</strong><br>
                    Create your primary administrator account. Choose a strong password.
                </div>
            </div>

            <form action="<?= BASE_URL; ?>setup.php" method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="create_admin">

                <div class="form-group">
                    <label class="form-label">Full Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Maria Clara" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Gmail Address <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. librarian@gmail.com" pattern="^[a-zA-Z0-9._%+-]+@gmail\.com$" title="Must be a valid @gmail.com address" required>
                    <small class="form-text">Must be a valid Gmail address (@gmail.com)</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Password <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-type password" minlength="6" required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                    Create Administrator Account
                </button>
            </form>
        <?php endif; ?>
    </div>

    <div class="auth-footer">
        <a href="<?= BASE_URL; ?>index.php">&larr; Return to Login Portal</a>
    </div>
</div>

</body>
</html>

