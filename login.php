<?php
/**
 * Login Handler (POST)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Security session expired. Please try again.";
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$role = trim($_POST['role'] ?? 'student');
$password = trim($_POST['password'] ?? '');

$db = get_db_connection();

if ($role === 'student') {
    $studentId = trim($_POST['student_id'] ?? '');

    if (empty($studentId) || empty($password)) {
        $_SESSION['flash_error'] = "Please provide both Student ID and Password.";
        header("Location: " . BASE_URL . "index.php");
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM students WHERE student_id = ? AND role = 'student' LIMIT 1");
    $stmt->execute([$studentId]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!$user['is_active']) {
            $_SESSION['flash_error'] = "Your student account has been deactivated. Please contact the librarian.";
            header("Location: " . BASE_URL . "index.php");
            exit;
        }

        login_user($user);
        log_audit('login_success', "Student {$user['student_id']} logged in.", $user['id']);
        $_SESSION['flash_success'] = "Welcome back, " . $user['full_name'] . "!";
        header("Location: " . BASE_URL . "student_dashboard.php");
        exit;
    } else {
        log_audit('login_failed', "Failed login attempt for Student ID: {$studentId}.");
        $_SESSION['flash_error'] = "Invalid Student ID or password.";
        header("Location: " . BASE_URL . "index.php");
        exit;
    }

} elseif ($role === 'admin') {
    $identifier = trim($_POST['admin_identifier'] ?? '');

    if (empty($identifier) || empty($password)) {
        $_SESSION['flash_error'] = "Please provide your Administrator ID / Email and Password.";
        header("Location: " . BASE_URL . "index.php?tab=admin");
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM students WHERE (student_id = ? OR email = ?) AND role = 'admin' LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!$user['is_active']) {
            $_SESSION['flash_error'] = "This administrator account is currently deactivated.";
            header("Location: " . BASE_URL . "index.php?tab=admin");
            exit;
        }

        login_user($user);
        log_audit('admin_login_success', "Administrator {$user['email']} logged in.", $user['id']);
        $_SESSION['flash_success'] = "Welcome to the Administration Panel, " . $user['full_name'] . "!";
        header("Location: " . BASE_URL . "admin_dashboard.php");
        exit;
    } else {
        log_audit('admin_login_failed', "Failed administrator login attempt: {$identifier}.");
        $_SESSION['flash_error'] = "Invalid administrator credentials.";
        header("Location: " . BASE_URL . "index.php?tab=admin");
        exit;
    }

} else {
    $_SESSION['flash_error'] = "Invalid user role specified.";
    header("Location: " . BASE_URL . "index.php");
    exit;
}

