<?php
/**
 * Action: Check Out / End Session (Student or Admin)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Security session expired. Please try again.";
    header("Location: " . (is_admin() ? BASE_URL . "admin/manage_sessions.php" : BASE_URL . "student_dashboard.php"));
    exit;
}

$currentUser = current_user();
$isAdmin = is_admin();
$sessionId = !empty($_POST['session_id']) && $isAdmin ? (int)$_POST['session_id'] : null;
$studentId = !empty($_POST['student_id']) && $isAdmin ? (int)$_POST['student_id'] : $currentUser['id'];

$result = check_out_student($studentId, $isAdmin, $sessionId);

if ($result['success']) {
    $_SESSION['flash_success'] = $result['message'];
} else {
    $_SESSION['flash_error'] = $result['message'];
}

if ($isAdmin) {
    header("Location: " . BASE_URL . "admin/manage_sessions.php");
} else {
    header("Location: " . BASE_URL . "student_dashboard.php");
}
exit;

