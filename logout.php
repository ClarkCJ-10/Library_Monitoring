<?php
/**
 * Logout Page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = current_user();
if ($currentUser) {
    log_audit('user_logout', "User {$currentUser['student_id']} logged out.", $currentUser['id']);
}

logout_user();

session_start();
$_SESSION['flash_success'] = "You have been logged out successfully.";
header("Location: " . BASE_URL . "index.php");
exit;

