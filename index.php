<?php
/**
 * Login Portal
 * Dual Role: Student & Administrator
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header("Location: " . BASE_URL . "admin_dashboard.php");
        exit;
    } else {
        header("Location: " . BASE_URL . "student_dashboard.php");
        exit;
    }
}

$activeTab = ($_GET['tab'] ?? '') === 'admin' ? 'admin' : 'student';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Library Seat Reservation and Monitoring System</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-header">
        <div class="auth-logo">📚</div>
        <h2 class="auth-title">Library Seat Monitoring</h2>
        <p class="auth-subtitle">School Library Seat Reservation & Session System</p>
    </div>

    <!-- Dual Role Tabs -->
    <div class="auth-tabs">
        <button type="button" id="tab-student-btn" class="auth-tab-btn <?= $activeTab === 'student' ? 'active' : ''; ?>">
            👨‍🎓 Student Login
        </button>
        <button type="button" id="tab-admin-btn" class="auth-tab-btn <?= $activeTab === 'admin' ? 'active' : ''; ?>">
            🔐 Administrator
        </button>
    </div>

    <div class="auth-body">
        <?php if ($flashSuccess): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;">
                <span>✅</span>
                <div><?= e($flashSuccess); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;">
                <span>❌</span>
                <div><?= e($flashError); ?></div>
            </div>
        <?php endif; ?>

        <!-- Student Login Form -->
        <form id="student-login-form" action="<?= BASE_URL; ?>login.php" method="POST" style="<?= $activeTab === 'student' ? 'display:block;' : 'display:none;'; ?>">
            <?= csrf_field(); ?>
            <input type="hidden" name="role" value="student">

            <div class="form-group">
                <label for="student_id" class="form-label">Student ID</label>
                <input type="text" id="student_id" name="student_id" class="form-control" placeholder="e.g. 2024-0001" required autofocus>
                <small class="form-text">Enter your official university student ID</small>
            </div>

            <div class="form-group">
                <label for="student_password" class="form-label">Password</label>
                <input type="password" id="student_password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Sign In as Student
            </button>

            <div style="text-align: center; margin-top: 14px; font-size: 13.5px;">
                <span style="color: var(--text-muted);">New student?</span> 
                <a href="<?= BASE_URL; ?>register.php"><strong>Register for an Account &rarr;</strong></a>
            </div>
        </form>

        <!-- Administrator Login Form -->
        <form id="admin-login-form" action="<?= BASE_URL; ?>login.php" method="POST" style="<?= $activeTab === 'admin' ? 'display:block;' : 'display:none;'; ?>">
            <?= csrf_field(); ?>
            <input type="hidden" name="role" value="admin">

            <div class="form-group">
                <label for="admin_identifier" class="form-label">Admin ID or Email</label>
                <input type="text" id="admin_identifier" name="admin_identifier" class="form-control" placeholder="ADMIN-001 or admin@library.edu" required>
                <small class="form-text">Authorized library administrators only</small>
            </div>

            <div class="form-group">
                <label for="admin_password" class="form-label">Password</label>
                <input type="password" id="admin_password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 10px;">
                Sign In as Administrator
            </button>
        </form>

        <!-- Test Account Helper Banner -->
        <div class="demo-credentials-box">
            <strong>Demo Credentials for Evaluation:</strong>
            <div style="margin-top: 6px; font-family: monospace; font-size: 12px; line-height: 1.6;">
                <div><strong>Student:</strong> 2024-0001 &nbsp;|&nbsp; Pass: <code>Student@123</code></div>
                <div><strong>Admin:</strong> admin@library.edu &nbsp;|&nbsp; Pass: <code>Admin@123</code></div>
            </div>
        </div>
    </div>

    <div class="auth-footer">
        <div><a href="<?= BASE_URL; ?>register.php"><strong>🎓 Student Registration</strong></a> &nbsp;•&nbsp; <a href="<?= BASE_URL; ?>setup.php">⚙️ Admin Setup Wizard</a></div>
        <div style="margin-top: 6px; color: var(--gray-400); font-size: 11.5px;">&copy; <?= date('Y'); ?> Library Monitoring System • School Project</div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/app.js"></script>
</body>
</html>

