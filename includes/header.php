<?php
/**
 * Shared Header Template
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? 'Library Monitoring System';
$activeNav = $activeNav ?? 'dashboard';

// Check for flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashWarning = $_SESSION['flash_warning'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle); ?> — LibraryMS</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="sidebar-overlay"></div>

<div class="app-layout">
    <!-- Sidebar -->
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <div class="brand-icon">📚</div>
            <div class="brand-text">
                <h1>LibraryMS</h1>
                <span>Seat Monitoring</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php if (is_admin()): ?>
                <div class="nav-section-title">Administration</div>
                <a href="<?= BASE_URL; ?>admin_dashboard.php" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL; ?>admin/manage_seats.php" class="nav-link <?= $activeNav === 'seats' ? 'active' : ''; ?>">
                    <span class="nav-icon">🪑</span>
                    <span>Seat Management</span>
                </a>
                <a href="<?= BASE_URL; ?>admin/manage_students.php" class="nav-link <?= $activeNav === 'students' ? 'active' : ''; ?>">
                    <span class="nav-icon">👥</span>
                    <span>Students</span>
                </a>
                <a href="<?= BASE_URL; ?>admin/manage_reservations.php" class="nav-link <?= $activeNav === 'reservations' ? 'active' : ''; ?>">
                    <span class="nav-icon">📌</span>
                    <span>Reservations</span>
                </a>
                <a href="<?= BASE_URL; ?>admin/manage_sessions.php" class="nav-link <?= $activeNav === 'sessions' ? 'active' : ''; ?>">
                    <span class="nav-icon">⏱️</span>
                    <span>Active Sessions</span>
                </a>
                <a href="<?= BASE_URL; ?>admin/reports.php" class="nav-link <?= $activeNav === 'reports' ? 'active' : ''; ?>">
                    <span class="nav-icon">📈</span>
                    <span>Usage Reports</span>
                </a>
                <a href="<?= BASE_URL; ?>admin/settings.php" class="nav-link <?= $activeNav === 'settings' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙️</span>
                    <span>System Settings</span>
                </a>
            <?php else: ?>
                <div class="nav-section-title">Student Portal</div>
                <a href="<?= BASE_URL; ?>student_dashboard.php" class="nav-link <?= $activeNav === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">🏠</span>
                    <span>My Dashboard</span>
                </a>
            <?php endif; ?>

            <div class="nav-section-title" style="margin-top: 20px;">Account</div>
            <a href="<?= BASE_URL; ?>logout.php" class="nav-link" style="color: var(--danger);">
                <span class="nav-icon">🚪</span>
                <span>Sign Out</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div>Library System v2.0</div>
            <small style="color: var(--gray-400);">PHP • MySQL • PDO</small>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Top Sticky Header -->
        <header class="app-header">
            <div class="header-left">
                <button type="button" class="sidebar-toggle-btn" aria-label="Toggle navigation">☰</button>
                <div class="header-title"><?= e($pageTitle); ?></div>
            </div>

            <div class="header-right">
                <div class="live-indicator" title="Live Seat Availability Sync every 5 seconds">
                    <span class="live-pulse"></span>
                    <span>Live Sync (5s)</span>
                </div>

                <?php if ($currentUser): ?>
                    <div class="user-profile-menu">
                        <div class="user-avatar" title="<?= e($currentUser['full_name']); ?>">
                            <?= strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?= e($currentUser['full_name']); ?></span>
                            <span class="user-role-badge"><?= e($currentUser['role'] === 'admin' ? 'Administrator' : $currentUser['student_id'] . (!empty($currentUser['course']) ? ' • ' . $currentUser['course'] : '')); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Wrapper -->
        <main class="content-wrapper">
            <!-- Flash Message Alerts -->
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div><?= e($flashSuccess); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-danger">
                    <span>❌</span>
                    <div><?= e($flashError); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashWarning): ?>
                <div class="alert alert-warning">
                    <span>⚠️</span>
                    <div><?= e($flashWarning); ?></div>
                </div>
            <?php endif; ?>

