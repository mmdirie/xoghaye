-- ==========================================================
-- XOGHAYE – RESULT MANAGER
-- Database Upgrade & University Schema Migration
-- ==========================================================

USE `campuspro_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Academic Years Table
CREATE TABLE IF NOT EXISTS `academic_years` (
  `year_id` INT AUTO_INCREMENT PRIMARY KEY,
  `year_name` VARCHAR(50) NOT NULL UNIQUE,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Semesters Table
CREATE TABLE IF NOT EXISTS `semesters` (
  `semester_id` INT AUTO_INCREMENT PRIMARY KEY,
  `semester_name` VARCHAR(50) NOT NULL,
  `academic_year_id` INT NULL,
  `is_current` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sem_year_restrict` FOREIGN KEY (`academic_year_id`) 
    REFERENCES `academic_years` (`year_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Faculties Table
CREATE TABLE IF NOT EXISTS `faculties` (
  `faculty_id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_name` VARCHAR(150) NOT NULL UNIQUE,
  `faculty_code` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
  `department_id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT NULL,
  `department_name` VARCHAR(150) NOT NULL,
  `department_code` VARCHAR(50) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_dept_faculty_restrict` FOREIGN KEY (`faculty_id`) 
    REFERENCES `faculties` (`faculty_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Academic Programs Table
CREATE TABLE IF NOT EXISTS `programs` (
  `program_id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NULL,
  `program_name` VARCHAR(150) NOT NULL,
  `program_code` VARCHAR(50) NOT NULL UNIQUE,
  `degree_type` VARCHAR(50) NOT NULL DEFAULT 'Bachelor',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_prog_dept_restrict` FOREIGN KEY (`department_id`) 
    REFERENCES `departments` (`department_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Harden existing installations so deleting a parent cannot cascade through
-- official records. These checks make the migration safe to rerun.
SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_course_teacher_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE courses DROP FOREIGN KEY fk_course_teacher, ADD CONSTRAINT fk_course_teacher_restrict FOREIGN KEY (teacher_id) REFERENCES teachers (teacher_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_enrollment_student_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE enrollments DROP FOREIGN KEY fk_enrollment_student, ADD CONSTRAINT fk_enrollment_student_restrict FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_enrollment_course_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE enrollments DROP FOREIGN KEY fk_enrollment_course, ADD CONSTRAINT fk_enrollment_course_restrict FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_attendance_student_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE attendance DROP FOREIGN KEY fk_attendance_student, ADD CONSTRAINT fk_attendance_student_restrict FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_attendance_course_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE attendance DROP FOREIGN KEY fk_attendance_course, ADD CONSTRAINT fk_attendance_course_restrict FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_grades_student_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE grades DROP FOREIGN KEY fk_grades_student, ADD CONSTRAINT fk_grades_student_restrict FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_grades_course_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE grades DROP FOREIGN KEY fk_grades_course, ADD CONSTRAINT fk_grades_course_restrict FOREIGN KEY (course_id) REFERENCES courses (course_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_dept_faculty_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE departments DROP FOREIGN KEY fk_dept_faculty, ADD CONSTRAINT fk_dept_faculty_restrict FOREIGN KEY (faculty_id) REFERENCES faculties (faculty_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_prog_dept_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE programs DROP FOREIGN KEY fk_prog_dept, ADD CONSTRAINT fk_prog_dept_restrict FOREIGN KEY (department_id) REFERENCES departments (department_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_rule = (SELECT DELETE_RULE FROM information_schema.referential_constraints
                WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_sem_year_restrict' LIMIT 1);
SET @ddl = IF(@fk_rule = 'RESTRICT',
    'SELECT 1',
    'ALTER TABLE semesters DROP FOREIGN KEY fk_sem_year, ADD CONSTRAINT fk_sem_year_restrict FOREIGN KEY (academic_year_id) REFERENCES academic_years (year_id) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
