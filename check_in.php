<?php
/**
 * Action: Check In to Reserved Seat (Student)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

require_student();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "student_dashboard.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Security session expired. Please try again.";
    header("Location: " . BASE_URL . "student_dashboard.php");
    exit;
}

$currentUser = current_user();
$result = check_in_student($currentUser['id']);

if ($result['success']) {
    $_SESSION['flash_success'] = $result['message'];
} else {
    $_SESSION['flash_error'] = $result['message'];
}

header("Location: " . BASE_URL . "student_dashboard.php");
exit;

