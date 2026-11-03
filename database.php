<?php
/**
 * Library Seat Reservation and Monitoring System
 * Database Connection via PDO
 */

require_once __DIR__ . '/config.php';

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log securely without exposing DB credentials to client
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please ensure MySQL is running in XAMPP and configuration in config.php is correct.");
        }
    }

    return $pdo;
}

