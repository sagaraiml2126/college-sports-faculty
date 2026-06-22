-- Migration v27 — Add a "Passport-size Photo" required document to every department
-- Format: JPEG/JPG only, max 1 MB (same cap as PDFs)
-- Why: Standardize a student photo across all dept profile flows; the photo
-- comes through the same doc_<id> upload pipeline as PDFs.

-- Add a photo row per department (skips if one already exists with that name).
-- We let AUTO_INCREMENT pick the id; UI queries will use a boolean trick
-- (document_name = 'Passport-size Photo' DESC) to keep the photo first.
INSERT INTO `dept_document_requirements`
    (`department_id`, `document_name`, `is_required`, `allowed_mime_types`)
SELECT d.id, 'Passport-size Photo', 1, 'image/jpeg'
  FROM `departments` d
  WHERE NOT EXISTS (
      SELECT 1 FROM `dept_document_requirements` r
       WHERE r.department_id = d.id
         AND r.document_name = 'Passport-size Photo'
  );