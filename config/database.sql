-- ============================================================
-- VUKA (InduTrack KE) Attachment & Internship Management Portal
-- Consolidated MySQL Database Schema and Seed Data
-- ============================================================

-- Create and select database
CREATE DATABASE IF NOT EXISTS `vuka_attachment_portal`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `vuka_attachment_portal`;

-- Disable foreign key checks during initialization
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. ROLES
-- 4-tier Role-Based Access Control (RBAC)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `level` INT NOT NULL COMMENT '1=super_admin, 2=hr_coordinator, 3=department_supervisor, 4=student',
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. DEPARTMENTS
-- Organizational departments with min/max admin caps
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `min_admins` INT NOT NULL DEFAULT 1,
    `max_admins` INT NOT NULL DEFAULT 2,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. USERS (Students / Applicants)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `national_id` VARCHAR(20) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `registration_date` DATE NOT NULL,
    `verified` TINYINT(1) NOT NULL DEFAULT 0,
    `verification_code` VARCHAR(10) NULL,
    `verification_sent_at` DATETIME NULL,
    `status` ENUM('active', 'inactive', 'suspended', 'pending') NOT NULL DEFAULT 'active',
    `profile_photo` VARCHAR(255) NULL,
    `profile_photo_blob` LONGBLOB NULL,
    `pending_email` VARCHAR(150) NULL,
    `email_change_token` VARCHAR(255) NULL,
    `email_change_expires` DATETIME NULL,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_national_id` (`national_id`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. ADMIN USERS (Super Admin, HR Coordinator, Dept Supervisor)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `national_id` VARCHAR(20) NOT NULL,
    `pf_number` VARCHAR(50) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `department` VARCHAR(100) NULL COMMENT 'Department name string for rapid scoping',
    `department_id` INT NULL,
    `role_id` INT NOT NULL,
    `status` ENUM('active', 'inactive', 'pending_activation', 'deleted') NOT NULL DEFAULT 'active',
    `profile_photo` VARCHAR(255) NULL,
    `profile_photo_blob` LONGBLOB NULL,
    `pending_email` VARCHAR(150) NULL,
    `email_change_token` VARCHAR(255) NULL,
    `email_change_expires` DATETIME NULL,
    `created_by` INT NULL,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_admin_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_admin_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    CONSTRAINT `fk_admin_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
    INDEX `idx_admin_pf` (`pf_number`),
    INDEX `idx_admin_email` (`email`),
    INDEX `idx_admin_department` (`department`),
    INDEX `idx_admin_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. ADMIN CREATION HISTORY
-- Compliance history of which Super Admin created which account
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `admin_creation_history`;
CREATE TABLE `admin_creation_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `created_admin_id` INT NOT NULL,
    `created_by_admin_id` INT NOT NULL,
    `role_id` INT NOT NULL,
    `department_id` INT NULL,
    `activation_status` VARCHAR(50) NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ach_created_admin` FOREIGN KEY (`created_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ach_created_by` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ach_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    CONSTRAINT `fk_ach_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. SESSIONS
-- DB-backed Bearer-token session store with 8-hour timeout
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` VARCHAR(64) NOT NULL UNIQUE,
    `user_id` INT NULL,
    `admin_id` INT NULL,
    `user_type` ENUM('student', 'admin') NOT NULL,
    `role_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `last_activity` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `is_valid` TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    INDEX `idx_sessions_token` (`session_id`),
    INDEX `idx_sessions_expires` (`expires_at`, `is_valid`),
    INDEX `idx_sessions_user` (`user_id`, `user_type`),
    INDEX `idx_sessions_admin` (`admin_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. VACANCIES
-- Attachment & Internship openings created by Dept & approved by HR
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `vacancies`;
CREATE TABLE `vacancies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `skills_required` TEXT NULL,
    `positions_count` INT NOT NULL DEFAULT 1,
    `vacancy_type` ENUM('attachment', 'internship') NOT NULL DEFAULT 'attachment',
    `deadline_at` DATETIME NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'closed') NOT NULL DEFAULT 'pending',
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_vacancies_created_by` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
    INDEX `idx_vacancies_dept` (`department_name`),
    INDEX `idx_vacancies_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. SUBMISSIONS
-- Applications with complete lifecycle tracking
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `national_id` VARCHAR(20) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `vacancy_id` INT NULL,
    `application_type` ENUM('attachment', 'internship') NOT NULL DEFAULT 'attachment',
    `duration` VARCHAR(100) NULL,
    `insurance_cover` VARCHAR(100) NULL,
    `course_applying` VARCHAR(150) NULL,
    `institution_name` VARCHAR(150) NULL,
    `department_applied` VARCHAR(100) NOT NULL,
    `status` ENUM('not_applied', 'applied', 'pending', 'accepted', 'rejected', 'deployed', 'ongoing', 'completed', 'withdrawn', 'interview') NOT NULL DEFAULT 'applied',
    `placement_status` ENUM('pending', 'shortlisted', 'selected', 'placed', 'rejected') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT NULL,
    `review_notes` TEXT NULL,
    `intern_pf_number` VARCHAR(50) NULL,
    `assigned_role` VARCHAR(150) NULL,
    `assigned_station` VARCHAR(150) NULL,
    `last_updated_by` VARCHAR(50) NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_updated_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_submissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_submissions_vacancy` FOREIGN KEY (`vacancy_id`) REFERENCES `vacancies` (`id`) ON DELETE SET NULL,
    INDEX `idx_submissions_user` (`user_id`),
    INDEX `idx_submissions_dept` (`department_applied`),
    INDEX `idx_submissions_status` (`status`),
    INDEX `idx_submissions_vacancy` (`vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. DOCUMENTS
-- Supporting uploaded files stored directly as BLOBs
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL,
    `document_type` VARCHAR(50) NOT NULL COMMENT 'application_letter, campus_letter, insurance_cert, academic_certs, national_id_copy',
    `original_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NULL DEFAULT '',
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT NOT NULL DEFAULT 0,
    `file_content` LONGBLOB NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_documents_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
    INDEX `idx_documents_submission` (`submission_id`),
    INDEX `idx_documents_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. REVIEW HISTORY
-- Audit trail of every submission review, decision, and status step
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `review_history`;
CREATE TABLE `review_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL,
    `reviewer_pf` VARCHAR(50) NULL,
    `reviewer_department` VARCHAR(100) NULL,
    `status` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
    `email_sent_at` DATETIME NULL,
    `reviewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rh_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
    INDEX `idx_review_submission` (`submission_id`),
    INDEX `idx_review_pf` (`reviewer_pf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. EVALUATIONS
-- End-of-placement intern evaluations by supervisors
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `evaluations`;
CREATE TABLE `evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL UNIQUE,
    `evaluator_id` INT NOT NULL,
    `attendance` TINYINT NOT NULL COMMENT 'Score 1 to 5',
    `technical` TINYINT NOT NULL COMMENT 'Score 1 to 5',
    `attitude` TINYINT NOT NULL COMMENT 'Score 1 to 5',
    `communication` TINYINT NOT NULL COMMENT 'Score 1 to 5',
    `initiative` TINYINT NOT NULL COMMENT 'Score 1 to 5',
    `overall_comment` TEXT NULL,
    `recommendation` ENUM('highly_recommended', 'recommended', 'not_recommended') NOT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_eval_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_eval_evaluator` FOREIGN KEY (`evaluator_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
    INDEX `idx_eval_evaluator` (`evaluator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. INTERVIEW SLOTS
-- Scheduled interview sessions for applicants
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `interview_slots`;
CREATE TABLE `interview_slots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL,
    `scheduled_at` DATETIME NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `notes` TEXT NULL,
    `status` ENUM('scheduled', 'rescheduled', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_slots_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_slots_creator` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
    INDEX `idx_slots_submission` (`submission_id`),
    INDEX `idx_slots_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. INTERVIEW RESPONSES
-- Applicant responses (confirm / conflict) to interview slots
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `interview_responses`;
CREATE TABLE `interview_responses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slot_id` INT NOT NULL,
    `response` ENUM('confirmed', 'conflict') NOT NULL,
    `student_notes` TEXT NULL,
    `responded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_resp_slot` FOREIGN KEY (`slot_id`) REFERENCES `interview_slots` (`id`) ON DELETE CASCADE,
    INDEX `idx_responses_slot` (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. NOTIFICATIONS
-- In-app notifications for students and administrators
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_id` INT NOT NULL,
    `role_type` ENUM('student', 'admin') NOT NULL,
    `title` VARCHAR(120) NOT NULL,
    `body` VARCHAR(255) NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_notifications_recipient` (`recipient_id`, `role_type`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. LOGIN ATTEMPTS (Rate Limiting)
-- Brute-force protection tracking failed login requests
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `endpoint` VARCHAR(64) NOT NULL,
    `identifier` VARCHAR(191) NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_login_ip_endpoint` (`ip_address`, `endpoint`, `success`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 16. PASSWORD RESET TOKENS
-- Single-use time-limited tokens for account password recovery
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `account_type` ENUM('student', 'admin') NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reset_token` (`token`, `used`, `expires_at`),
    INDEX `idx_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 17. AUDIT LOGS
-- Complete compliance audit trail for administrative activities
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `admin_pf` VARCHAR(50) NOT NULL,
    `admin_role` VARCHAR(50) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` INT NULL,
    `target_admin_id` INT NULL,
    `target_admin_pf` VARCHAR(50) NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `details` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_admin` (`admin_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- STORED PROCEDURE: sp_get_submission_stats
-- Aggregates application metrics scoped by department or ALL
-- ------------------------------------------------------------
DELIMITER //
DROP PROCEDURE IF EXISTS `sp_get_submission_stats` //
CREATE PROCEDURE `sp_get_submission_stats`(IN in_department VARCHAR(100))
BEGIN
    IF in_department IS NULL OR in_department = '' OR in_department = 'ALL' THEN
        SELECT
            COUNT(s.id) AS total_submissions,
            COALESCE(SUM(CASE WHEN s.status IN ('applied', 'pending') THEN 1 ELSE 0 END), 0) AS pending_submissions,
            COALESCE(SUM(CASE WHEN s.status IN ('accepted', 'deployed', 'ongoing') THEN 1 ELSE 0 END), 0) AS approved_submissions,
            COALESCE(SUM(CASE WHEN s.status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_submissions
        FROM `submissions` s;
    ELSE
        SELECT
            COUNT(s.id) AS total_submissions,
            COALESCE(SUM(CASE WHEN s.status IN ('applied', 'pending') THEN 1 ELSE 0 END), 0) AS pending_submissions,
            COALESCE(SUM(CASE WHEN s.status IN ('accepted', 'deployed', 'ongoing') THEN 1 ELSE 0 END), 0) AS approved_submissions,
            COALESCE(SUM(CASE WHEN s.status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_submissions
        FROM `submissions` s
        WHERE s.department_applied = in_department;
    END IF;
END //
DELIMITER ;

-- ============================================================
-- SEED DATA
-- ============================================================

-- 1. Insert Default RBAC Roles
INSERT INTO `roles` (`id`, `role_name`, `display_name`, `level`, `description`) VALUES
(1, 'super_admin', 'Super Admin', 1, 'Full system-wide administrative access'),
(2, 'hr_coordinator', 'HR Coordinator', 2, 'System-wide HR management, vacancy approval, and applicant oversight'),
(3, 'department_supervisor', 'Department Supervisor', 3, 'Department-level vacancy requests, applicant review, and deployment'),
(4, 'student', 'Student / Applicant', 4, 'Student attachment and internship applications and tracking')
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`), `level` = VALUES(`level`);

-- 2. Insert Default Departments
INSERT INTO `departments` (`name`, `description`, `min_admins`, `max_admins`) VALUES
('ICT', 'Information Communication and Technology Department', 1, 2),
('Health', 'Health and Sanitation Department', 1, 2),
('Procurement', 'Procurement and Supply Chain Management Department', 1, 2),
('Finance', 'Finance and Economic Planning Department', 1, 2),
('HR', 'Human Resource Management and Development Department', 1, 2),
('Education', 'Education, Youth, and Social Services Department', 1, 2),
('Agriculture', 'Agriculture, Livestock, and Fisheries Department', 1, 2),
('Water', 'Water, Irrigation, and Natural Resources Department', 1, 2),
('Transport', 'Transport, Roads, and Public Works Department', 1, 2)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- 3. Insert Default Super Admin Account
-- Credentials: PF Number = SUPER/ADMIN/001, National ID = 12345678, Password = admin123
INSERT INTO `admin_users` (
    `id`, `national_id`, `pf_number`, `full_name`, `email`,
    `password_hash`, `department`, `department_id`, `role_id`,
    `status`, `created_at`
) VALUES (
    1,
    '12345678',
    'SUPER/ADMIN/001',
    'System Super Administrator',
    'admin@vuka.local',
    '$2y$10$wLXkfWCLHAE6qZvWC/hxse7liJR.SFFwOWObwZMblxTRD2H3QwXzK',
    'ALL',
    NULL,
    1,
    'active',
    NOW()
)
ON DUPLICATE KEY UPDATE
    `full_name` = VALUES(`full_name`),
    `email` = VALUES(`email`),
    `status` = VALUES(`status`);
