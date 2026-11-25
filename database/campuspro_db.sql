-- ==========================================================
-- SCHOOL MANAGER - STUDENT MANAGEMENT SYSTEM
-- Database Schema & Initial Seed Data
-- Database Name: campuspro_db
-- Compatibility: MySQL 5.7+ / MariaDB 10.4+ / XAMPP
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `campuspro_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campuspro_db`;

-- Disable foreign key checks during initialization
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- Table structure for: users
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `grades`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `enrollments`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: students
-- ----------------------------------------------------------
CREATE TABLE `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `student_number` VARCHAR(50) NOT NULL UNIQUE,
  `phone` VARCHAR(25) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: teachers
-- ----------------------------------------------------------
CREATE TABLE `teachers` (
  `teacher_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `department` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(25) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: courses
-- ----------------------------------------------------------
CREATE TABLE `courses` (
  `course_id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_name` VARCHAR(150) NOT NULL,
  `course_code` VARCHAR(50) NOT NULL UNIQUE,
  `teacher_id` INT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_course_teacher_restrict` FOREIGN KEY (`teacher_id`) 
    REFERENCES `teachers` (`teacher_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: enrollments
-- Links students to courses they are currently attending
-- ----------------------------------------------------------
CREATE TABLE `enrollments` (
  `enrollment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `enrollment_date` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_course` (`student_id`, `course_id`),
  CONSTRAINT `fk_enrollment_student_restrict` FOREIGN KEY (`student_id`) 
    REFERENCES `students` (`student_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollment_course_restrict` FOREIGN KEY (`course_id`) 
    REFERENCES `courses` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: attendance
-- ----------------------------------------------------------
CREATE TABLE `attendance` (
  `attendance_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL DEFAULT 'Present',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_course_date` (`student_id`, `course_id`, `attendance_date`),
  CONSTRAINT `fk_attendance_student_restrict` FOREIGN KEY (`student_id`) 
    REFERENCES `students` (`student_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_course_restrict` FOREIGN KEY (`course_id`) 
    REFERENCES `courses` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: grades
-- ----------------------------------------------------------
CREATE TABLE `grades` (
  `grade_id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_id` INT NOT NULL,
  `score` DECIMAL(5,2) NOT NULL,
  `letter_grade` VARCHAR(5) NOT NULL,
  `remarks` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_course_grade` (`student_id`, `course_id`),
  CONSTRAINT `fk_grades_student_restrict` FOREIGN KEY (`student_id`) 
    REFERENCES `students` (`student_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_grades_course_restrict` FOREIGN KEY (`course_id`) 
    REFERENCES `courses` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================
-- SEED DATA
-- Default Passwords:
-- Admin:    Admin@123   ($2y$10$1FVmalJ8mItslF1O9iOacub1Uh4Z84fcVtvCJIO2iCylDHXIYAFUK)
-- Teachers: Teacher@123 ($2y$10$dvKOehnXCPVo6cnnysmWy.wXcvhuZuQZ4u/je1YPW7ch0sDbzZLYq)
-- Students: Student@123 ($2y$10$cKTlXRedeh3X3QSBA2fo/.sE7s.dxX25/w68ngn.uLnJp0orP6JLm)
-- ==========================================================

-- 1. Insert Users
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`) VALUES
-- Administrators
(1, 'System Administrator', 'admin@schoolmanager.edu', '$2y$10$1FVmalJ8mItslF1O9iOacub1Uh4Z84fcVtvCJIO2iCylDHXIYAFUK', 'admin'),
-- Teachers
(2, 'Dr. Sarah Jenkins', 'sarah.jenkins@schoolmanager.edu', '$2y$10$dvKOehnXCPVo6cnnysmWy.wXcvhuZuQZ4u/je1YPW7ch0sDbzZLYq', 'teacher'),
(3, 'Prof. Robert Chen', 'robert.chen@schoolmanager.edu', '$2y$10$dvKOehnXCPVo6cnnysmWy.wXcvhuZuQZ4u/je1YPW7ch0sDbzZLYq', 'teacher'),
(4, 'Dr. Emily Davis', 'emily.davis@schoolmanager.edu', '$2y$10$dvKOehnXCPVo6cnnysmWy.wXcvhuZuQZ4u/je1YPW7ch0sDbzZLYq', 'teacher'),
-- Students
(5, 'Alex Morgan', 'alex.morgan@schoolmanager.edu', '$2y$10$cKTlXRedeh3X3QSBA2fo/.sE7s.dxX25/w68ngn.uLnJp0orP6JLm', 'student'),
(6, 'Maria Garcia', 'maria.garcia@schoolmanager.edu', '$2y$10$cKTlXRedeh3X3QSBA2fo/.sE7s.dxX25/w68ngn.uLnJp0orP6JLm', 'student'),
(7, 'David Kim', 'david.kim@schoolmanager.edu', '$2y$10$cKTlXRedeh3X3QSBA2fo/.sE7s.dxX25/w68ngn.uLnJp0orP6JLm', 'student'),
(8, 'Sophia Taylor', 'sophia.taylor@schoolmanager.edu', '$2y$10$cKTlXRedeh3X3QSBA2fo/.sE7s.dxX25/w68ngn.uLnJp0orP6JLm', 'student'),
(9, 'James Wilson', 'james.wilson@schoolmanager.edu', '$2y$10$cKTlXRedeh3X3QSBA2fo/.sE7s.dxX25/w68ngn.uLnJp0orP6JLm', 'student');

-- 2. Insert Teachers
INSERT INTO `teachers` (`teacher_id`, `user_id`, `department`, `phone`) VALUES
(1, 2, 'Computer Science', '+1 (555) 234-5671'),
(2, 3, 'Mathematics', '+1 (555) 345-6782'),
(3, 4, 'Physics & Engineering', '+1 (555) 456-7893');

-- 3. Insert Students
INSERT INTO `students` (`student_id`, `user_id`, `student_number`, `phone`, `address`, `date_of_birth`) VALUES
(1, 5, 'STU-2024-001', '+1 (555) 111-2233', '742 Evergreen Terrace, Springfield', '2004-05-14'),
(2, 6, 'STU-2024-002', '+1 (555) 222-3344', '1204 Elm Street, Riverdale', '2003-09-22'),
(3, 7, 'STU-2024-003', '+1 (555) 333-4455', '88 Oxford Lane, Cambridge', '2004-11-03'),
(4, 8, 'STU-2024-004', '+1 (555) 444-5566', '45 Ocean View Ave, San Diego', '2003-03-18'),
(5, 9, 'STU-2024-005', '+1 (555) 555-6677', '312 Maple Drive, Austin', '2004-07-30');

-- 4. Insert Courses
INSERT INTO `courses` (`course_id`, `course_name`, `course_code`, `teacher_id`, `description`) VALUES
(1, 'Introduction to Computer Science', 'CS101', 1, 'Fundamental concepts of programming, algorithms, and computational problem solving.'),
(2, 'Data Structures & Algorithms', 'CS201', 1, 'In-depth study of stacks, queues, trees, graphs, sorting, and algorithmic complexity.'),
(3, 'Calculus for Engineers', 'MATH101', 2, 'Differential and integral calculus with engineering and computer science applications.'),
(4, 'General Physics I: Mechanics', 'PHYS101', 3, 'Classical mechanics, kinematics, Newton laws, energy, and thermodynamics.'),
(5, 'Database Management Systems', 'CS301', 1, 'Relational database design, SQL querying, indexing, and normalization principles.');

-- 5. Insert Course Enrollments
INSERT INTO `enrollments` (`student_id`, `course_id`, `enrollment_date`) VALUES
-- Alex Morgan enrolled in CS101, MATH101, PHYS101
(1, 1, '2024-09-01'),
(1, 3, '2024-09-01'),
(1, 4, '2024-09-01'),
-- Maria Garcia enrolled in CS101, CS201, CS301
(2, 1, '2024-09-01'),
(2, 2, '2024-09-01'),
(2, 5, '2024-09-01'),
-- David Kim enrolled in CS201, MATH101, CS301
(3, 2, '2024-09-01'),
(3, 3, '2024-09-01'),
(3, 5, '2024-09-01'),
-- Sophia Taylor enrolled in CS101, MATH101, PHYS101
(4, 1, '2024-09-01'),
(4, 3, '2024-09-01'),
(4, 4, '2024-09-01'),
-- James Wilson enrolled in CS101, CS201, PHYS101
(5, 1, '2024-09-01'),
(5, 2, '2024-09-01'),
(5, 4, '2024-09-01');

-- 6. Insert Attendance Records (Realistic sessions across courses)
INSERT INTO `attendance` (`student_id`, `course_id`, `attendance_date`, `status`) VALUES
-- CS101: 2024-10-14
(1, 1, '2024-10-14', 'Present'),
(2, 1, '2024-10-14', 'Present'),
(4, 1, '2024-10-14', 'Present'),
(5, 1, '2024-10-14', 'Late'),
-- CS101: 2024-10-16
(1, 1, '2024-10-16', 'Present'),
(2, 1, '2024-10-16', 'Absent'),
(4, 1, '2024-10-16', 'Present'),
(5, 1, '2024-10-16', 'Present'),
-- CS101: 2024-10-21
(1, 1, '2024-10-21', 'Present'),
(2, 1, '2024-10-21', 'Present'),
(4, 1, '2024-10-21', 'Excused'),
(5, 1, '2024-10-21', 'Present'),

-- CS201: 2024-10-15
(2, 2, '2024-10-15', 'Present'),
(3, 2, '2024-10-15', 'Present'),
(5, 2, '2024-10-15', 'Present'),
-- CS201: 2024-10-22
(2, 2, '2024-10-22', 'Present'),
(3, 2, '2024-10-22', 'Late'),
(5, 2, '2024-10-22', 'Present'),

-- MATH101: 2024-10-14
(1, 3, '2024-10-14', 'Present'),
(3, 3, '2024-10-14', 'Present'),
(4, 3, '2024-10-14', 'Present'),
-- MATH101: 2024-10-18
(1, 3, '2024-10-18', 'Late'),
(3, 3, '2024-10-18', 'Present'),
(4, 3, '2024-10-18', 'Absent'),

-- PHYS101: 2024-10-17
(1, 4, '2024-10-17', 'Present'),
(4, 4, '2024-10-17', 'Present'),
(5, 4, '2024-10-17', 'Excused');

-- 7. Insert Grades
INSERT INTO `grades` (`student_id`, `course_id`, `score`, `letter_grade`, `remarks`) VALUES
-- Alex Morgan
(1, 1, 94.50, 'A', 'Excellent understanding of programming fundamentals and lab assignments.'),
(1, 3, 88.00, 'B+', 'Consistent problem solver, very good mid-term test performance.'),
(1, 4, 91.00, 'A-', 'Solid grasp of mechanics concepts and physical experiments.'),
-- Maria Garcia
(2, 1, 96.00, 'A+', 'Outstanding coding projects and perfect score on final project.'),
(2, 2, 89.50, 'B+', 'Strong algorithm analysis and data structure implementation.'),
(2, 5, 93.00, 'A', 'Great database normalization and complex SQL query work.'),
-- David Kim
(3, 2, 90.00, 'A-', 'Very good grasp of trees and dynamic programming.'),
(3, 3, 84.50, 'B', 'Good comprehension of calculus principles; minor mid-term errors.'),
(3, 5, 87.00, 'B+', 'Solid relational modeling and store procedure work.'),
-- Sophia Taylor
(4, 1, 88.50, 'B+', 'Good participation and timely programming lab submissions.'),
(4, 3, 92.00, 'A-', 'Strong mathematical rigor and analytical clarity.'),
(4, 4, 85.00, 'B', 'Consistent work in lab practicals and homework problem sets.'),
-- James Wilson
(5, 1, 82.00, 'B-', 'Satisfactory work on introductory programming modules.'),
(5, 2, 79.50, 'C+', 'Acceptable work; needs more practice with recursion and graphs.'),
(5, 4, 88.00, 'B+', 'Great physical lab demonstrations and active participation.');
