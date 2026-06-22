-- Migration v26 — Restrict student document uploads to PDF only
-- (was: application/pdf,image/jpeg,image/png)
-- Reason: PDF is the canonical format for certificates/marksheets; limits
-- size and ensures consistent rendering across departments.

UPDATE `dept_document_requirements`
   SET `allowed_mime_types` = 'application/pdf';