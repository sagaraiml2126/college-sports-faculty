-- =====================================================================
--  Migration v7 — Add Document Requirements for D.Pharm Department
--
--  Adds the same 3 document requirements that Polytechnic has
--  (Leaving Certificate, Hall Ticket, Aadhaar Card) to the D.Pharm
--  department so the upload feature works identically for both.
--
--  Safe to re-run: uses sub-select for department_id lookup.
-- =====================================================================

USE `csf_portal`;

-- Seed D.Pharm document requirements (same as Polytechnic)
INSERT INTO `dept_document_requirements` (`department_id`, `document_name`, `is_required`) VALUES
((SELECT id FROM departments WHERE code = 'dpharm' LIMIT 1), 'Leaving Certificate', 1),
((SELECT id FROM departments WHERE code = 'dpharm' LIMIT 1), 'Hall Ticket', 1),
((SELECT id FROM departments WHERE code = 'dpharm' LIMIT 1), 'Aadhaar Card', 1);

-- =====================================================================
--  END OF MIGRATION v7
-- =====================================================================
