-- Migration v6: Student Document Management
SET FOREIGN_KEY_CHECKS=0;
USE csf_portal;

DROP TABLE IF EXISTS `student_documents`;
DROP TABLE IF EXISTS `dept_document_requirements`;

CREATE TABLE `dept_document_requirements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `department_id` TINYINT UNSIGNED NOT NULL,
    `document_name` VARCHAR(100) NOT NULL,
    `is_required` TINYINT(1) DEFAULT 1,
    `allowed_mime_types` VARCHAR(255) DEFAULT 'application/pdf',
    CONSTRAINT `fk_req_dept` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `requirement_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_doc_student` FOREIGN KEY (`student_id`) 
        REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_doc_req` FOREIGN KEY (`requirement_id`) 
        REFERENCES `dept_document_requirements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Polytechnic requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES 
((SELECT id FROM departments WHERE name = 'Polytechnic' LIMIT 1), 'Leaving Certificate', 1),
((SELECT id FROM departments WHERE name = 'Polytechnic' LIMIT 1), 'Hall Ticket', 1),
((SELECT id FROM departments WHERE name = 'Polytechnic' LIMIT 1), 'Aadhaar Card', 1);

-- Seed D.Pharm requirements (same documents as Polytechnic)
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES 
((SELECT id FROM departments WHERE code = 'dpharm' LIMIT 1), 'Leaving Certificate', 1),
((SELECT id FROM departments WHERE code = 'dpharm' LIMIT 1), 'Hall Ticket', 1),
((SELECT id FROM departments WHERE code = 'dpharm' LIMIT 1), 'Aadhaar Card', 1);
