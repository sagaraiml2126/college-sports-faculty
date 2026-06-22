-- =====================================================================
--  Migration v8 — Fix Document Requirement Names for Pharmacy, MBA,
--  MCA, BBA, BCA, and Architecture Departments
--
--  Replaces the numeric document placeholders (10, 12, 0) with the actual
--  document names (10th board certificate, 12th marksheet, etc.) matching
--  the Engineering department requirements.
--
--  Safe to re-run: deletes existing records for these departments
--  before inserting new ones.
-- =====================================================================

USE `csf_portal`;

-- Delete existing incorrect document requirements for Pharmacy (3), MBA (4),
-- MCA (5), BBA (6), BCA (7), and Architecture (8).
DELETE FROM `dept_document_requirements` WHERE `department_id` IN (3, 4, 5, 6, 7, 8);

-- 1. Pharmacy (3) Requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
(3, '10th board certificate', 1),
(3, '12th marksheet', 1),
(3, 'Gap certificate (optional)', 1),
(3, 'Birth certificate or Leaving certificate', 1),
(3, 'Last year marksheet', 1),
(3, 'College ID Card', 1),
(3, 'Aadhaar Card', 1),
(3, 'Bank passbook', 1);

-- 2. MBA (4) Requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
(4, '10th board certificate', 1),
(4, '12th marksheet', 1),
(4, 'Gap certificate (optional)', 1),
(4, 'Birth certificate or Leaving certificate', 1),
(4, 'Last year marksheet', 1),
(4, 'College ID Card', 1),
(4, 'Aadhaar Card', 1),
(4, 'Bank passbook', 1);

-- 3. MCA (5) Requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
(5, '10th board certificate', 1),
(5, '12th marksheet', 1),
(5, 'Gap certificate (optional)', 1),
(5, 'Birth certificate or Leaving certificate', 1),
(5, 'Last year marksheet', 1),
(5, 'College ID Card', 1),
(5, 'Aadhaar Card', 1),
(5, 'Bank passbook', 1);

-- 4. BBA (6) Requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
(6, '10th board certificate', 1),
(6, '12th marksheet', 1),
(6, 'Gap certificate (optional)', 1),
(6, 'Birth certificate or Leaving certificate', 1),
(6, 'Last year marksheet', 1),
(6, 'College ID Card', 1),
(6, 'Aadhaar Card', 1),
(6, 'Bank passbook', 1);

-- 5. BCA (7) Requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
(7, '10th board certificate', 1),
(7, '12th marksheet', 1),
(7, 'Gap certificate (optional)', 1),
(7, 'Birth certificate or Leaving certificate', 1),
(7, 'Last year marksheet', 1),
(7, 'College ID Card', 1),
(7, 'Aadhaar Card', 1),
(7, 'Bank passbook', 1);

-- 6. Architecture (8) Requirements
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
(8, '10th board certificate', 1),
(8, '12th marksheet', 1),
(8, 'Gap certificate (optional)', 1),
(8, 'Birth certificate or Leaving certificate', 1),
(8, 'Last year marksheet', 1),
(8, 'College ID Card', 1),
(8, 'Aadhaar Card', 1),
(8, 'Bank passbook', 1);

-- =====================================================================
--  END OF MIGRATION v8
-- =====================================================================
