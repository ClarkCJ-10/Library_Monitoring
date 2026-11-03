<?php
/**
 * Authentication & Authorization Helper Functions
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

function is_student(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'student';
}

function require_login(string $redirect = 'index.php'): void {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . $redirect);
        exit;
    }
}

function require_admin(string $redirect = 'index.php'): void {
    require_login($redirect);
    if (!is_admin()) {
        $_SESSION['flash_error'] = "Access denied. Administrator privileges required.";
        header("Location: " . BASE_URL . "student_dashboard.php");
        exit;
    }
}

function require_student(string $redirect = 'index.php'): void {
    require_login($redirect);
    if (!is_student()) {
        $_SESSION['flash_error'] = "Access denied. Student account required.";
        header("Location: " . BASE_URL . "admin_dashboard.php");
        exit;
    }
}

function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'         => (int)$user['id'],
        'student_id' => $user['student_id'],
        'full_name'  => $user['full_name'],
        'email'      => $user['email'],
        'course'     => $user['course'] ?? null,
        'role'       => $user['role']
    ];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

