<?php
/**
 * Library Seat Reservation and Monitoring System
 * System Configuration
 */

// Timezone
date_default_timezone_set('Asia/Manila');

// Session configuration (strict & secure)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // If running over HTTPS, enable secure cookies
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'library_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL definition
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
// Handle root directory or subfolder
$baseSubdir = preg_replace('#/(api|admin|includes|assets.*)$#', '', $scriptDir);
$baseUrl = rtrim($protocol . $host . $baseSubdir, '/') . '/';
define('BASE_URL', $baseUrl);

// System Defaults
define('DEFAULT_SESSION_DURATION_MINUTES', 120);
define('DEFAULT_EXPIRATION_MINUTES', 5);
define('WARNING_REMAINING_MINUTES', 15);

