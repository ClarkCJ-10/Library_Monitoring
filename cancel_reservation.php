<?php
/**
 * Action: Cancel Reservation (Student or Admin)
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
    header("Location: " . (is_admin() ? BASE_URL . "admin/manage_reservations.php" : BASE_URL . "student_dashboard.php"));
    exit;
}

$currentUser = current_user();
$isAdmin = is_admin();
$reservationId = !empty($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : null;
$targetStudentId = !empty($_POST['student_id']) && $isAdmin ? (int)$_POST['student_id'] : $currentUser['id'];

$result = cancel_reservation($targetStudentId, $reservationId, $isAdmin);

if ($result['success']) {
    $_SESSION['flash_success'] = $result['message'];
} else {
    $_SESSION['flash_error'] = $result['message'];
}

if ($isAdmin) {
    header("Location: " . BASE_URL . "admin/manage_reservations.php");
} else {
    header("Location: " . BASE_URL . "student_dashboard.php");
}
exit;

