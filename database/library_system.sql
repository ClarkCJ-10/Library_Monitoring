-- Library Seat Reservation and Monitoring System
-- Database Schema for MySQL
-- Database: library_system

CREATE DATABASE IF NOT EXISTS `library_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `library_system`;

-- Drop existing tables if needed (in reverse foreign key order)
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `seats`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `settings`;

-- 1. Settings Table
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_duration_minutes` INT UNSIGNED NOT NULL DEFAULT 120,
    `reservation_expiration_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
    `library_open_time` TIME NOT NULL DEFAULT '08:00:00',
    `library_close_time` TIME NOT NULL DEFAULT '18:00:00',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Students & Users Table (both students and admins)
CREATE TABLE `students` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(120) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `course` VARCHAR(120) NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_course` (`course`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seats Table
CREATE TABLE `seats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `seat_number` VARCHAR(20) NOT NULL UNIQUE,
    `seat_status` ENUM('available', 'reserved', 'occupied') NOT NULL DEFAULT 'available',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_seat_status` (`seat_status`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Reservations Table
CREATE TABLE `reservations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `seat_id` INT UNSIGNED NOT NULL,
    `reservation_start` DATETIME NOT NULL,
    `reservation_end` DATETIME NOT NULL,
    `status` ENUM('reserved', 'occupied', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'reserved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seat_id`) REFERENCES `seats`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_student_active` (`student_id`, `status`),
    INDEX `idx_seat_active` (`seat_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Sessions Table
CREATE TABLE `sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `seat_id` INT UNSIGNED NOT NULL,
    `reservation_id` INT UNSIGNED NOT NULL,
    `check_in_time` DATETIME NOT NULL,
    `expected_end_time` DATETIME NOT NULL,
    `check_out_time` DATETIME NULL,
    `status` ENUM('active', 'completed', 'expired') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seat_id`) REFERENCES `seats`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE CASCADE,
    INDEX `idx_session_status` (`status`),
    INDEX `idx_student_session` (`student_id`, `status`),
    INDEX `idx_seat_session` (`seat_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Audit Logs Table (tracks administrative & system events)
CREATE TABLE `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `students`(`id`) ON DELETE SET NULL,
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial Settings record
INSERT INTO `settings` (`id`, `session_duration_minutes`, `reservation_expiration_minutes`, `library_open_time`, `library_close_time`)
VALUES (1, 120, 5, '08:00:00', '18:00:00')
ON DUPLICATE KEY UPDATE `session_duration_minutes` = 120;

-- Sample Administrator Account
-- Password for admin: Admin@123
INSERT INTO `students` (`student_id`, `full_name`, `email`, `password_hash`, `role`, `is_active`)
VALUES
('ADMIN-001', 'Chief Librarian', 'admin@library.edu', '$2y$10$bzeX0N2ec.yAuUZfIpL30uozhj90Xylbrg.k4oy2TlXomIdYJSvsu', 'admin', 1);

-- Sample Student Accounts
-- Passwords for students: Student@123
INSERT INTO `students` (`student_id`, `full_name`, `email`, `course`, `password_hash`, `role`, `is_active`)
VALUES
('2024-0001', 'Juan Dela Cruz', 'juan.delacruz@student.edu', 'BS Information Technology', '$2y$10$AMbrjsKryUL41rlsIjvJoOJFUqyhzhHf7j.nkkPTSb1UQf7QXYM9K', 'student', 1),
('2024-0002', 'Maria Santos', 'maria.santos@student.edu', 'BS Computer Science', '$2y$10$AMbrjsKryUL41rlsIjvJoOJFUqyhzhHf7j.nkkPTSb1UQf7QXYM9K', 'student', 1),
('2024-0003', 'Carlos Reyes', 'carlos.reyes@student.edu', 'BS Computer Engineering', '$2y$10$AMbrjsKryUL41rlsIjvJoOJFUqyhzhHf7j.nkkPTSb1UQf7QXYM9K', 'student', 1),
('2024-0004', 'Andrea Gomez', 'andrea.gomez@student.edu', 'BS Business Administration', '$2y$10$AMbrjsKryUL41rlsIjvJoOJFUqyhzhHf7j.nkkPTSb1UQf7QXYM9K', 'student', 1),
('2024-0005', 'Mark Johnson', 'mark.johnson@student.edu', 'BS Nursing', '$2y$10$AMbrjsKryUL41rlsIjvJoOJFUqyhzhHf7j.nkkPTSb1UQf7QXYM9K', 'student', 1);

-- Generate 40 initial seats (Seat 1 to Seat 40)
INSERT INTO `seats` (`seat_number`, `seat_status`, `is_active`) VALUES
('S-001', 'available', 1), ('S-002', 'available', 1), ('S-003', 'available', 1), ('S-004', 'available', 1),
('S-005', 'available', 1), ('S-006', 'available', 1), ('S-007', 'available', 1), ('S-008', 'available', 1),
('S-009', 'available', 1), ('S-010', 'available', 1), ('S-011', 'available', 1), ('S-012', 'available', 1),
('S-013', 'available', 1), ('S-014', 'available', 1), ('S-015', 'available', 1), ('S-016', 'available', 1),
('S-017', 'available', 1), ('S-018', 'available', 1), ('S-019', 'available', 1), ('S-020', 'available', 1),
('S-021', 'available', 1), ('S-022', 'available', 1), ('S-023', 'available', 1), ('S-024', 'available', 1),
('S-025', 'available', 1), ('S-026', 'available', 1), ('S-027', 'available', 1), ('S-028', 'available', 1),
('S-029', 'available', 1), ('S-030', 'available', 1), ('S-031', 'available', 1), ('S-032', 'available', 1),
('S-033', 'available', 1), ('S-034', 'available', 1), ('S-035', 'available', 1), ('S-036', 'available', 1),
('S-037', 'available', 1), ('S-038', 'available', 1), ('S-039', 'available', 1), ('S-040', 'available', 1);
