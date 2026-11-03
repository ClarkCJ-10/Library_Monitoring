-- Library Seat Reservation and Monitoring System
-- Migration Script: Add 'course' column to students table
-- Database: library_system

USE `library_system`;

-- 1. Add course column if it doesn't already exist
SET @dbname = DATABASE();
SET @tablename = "students";
SET @columnname = "course";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE students ADD COLUMN course VARCHAR(120) NULL DEFAULT NULL AFTER email;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Optional: Populate existing sample student records with sample courses
UPDATE `students` SET `course` = 'BS Information Technology' WHERE `student_id` = '2024-0001' AND `course` IS NULL;
UPDATE `students` SET `course` = 'BS Computer Science' WHERE `student_id` = '2024-0002' AND `course` IS NULL;
UPDATE `students` SET `course` = 'BS Computer Engineering' WHERE `student_id` = '2024-0003' AND `course` IS NULL;
UPDATE `students` SET `course` = 'BS Business Administration' WHERE `student_id` = '2024-0004' AND `course` IS NULL;
UPDATE `students` SET `course` = 'BS Nursing' WHERE `student_id` = '2024-0005' AND `course` IS NULL;

-- Note: Admin accounts maintain course = NULL as course applies only to students.

