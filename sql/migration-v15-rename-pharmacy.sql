-- =====================================================================
--  v15: Rename Pharmacy department to YCP(pharmacy)
--  YCP = Yashoda College of Pharmacy
--
--  Only display strings change. The departments.code value 'pharmacy'
--  is the stable identifier used by faculty_departments joins, exports,
--  and existing student records — do NOT change it.
--
--  Idempotent: safe to run more than once.
-- =====================================================================

USE `csf_portal`;

UPDATE `departments`
   SET `name`      = 'YCP(pharmacy)',
       `full_name` = 'Yashoda College of Pharmacy - B.Pharm Programs'
 WHERE `code` = 'pharmacy';