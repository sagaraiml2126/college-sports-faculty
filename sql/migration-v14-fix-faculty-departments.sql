-- Restore the intended default faculty-to-department ownership.
-- Uses stable usernames and department codes instead of numeric IDs.

DELETE fd
  FROM `faculty_departments` fd
  JOIN `faculty` f ON f.id = fd.faculty_id
 WHERE f.username IN ('eng_faculty', 'poly_faculty', 'pharm_faculty', 'dpharm_faculty');

INSERT INTO `faculty_departments` (`faculty_id`,`department_id`)
SELECT f.id, d.id
  FROM `faculty` f
  JOIN `departments` d
    ON (f.username = 'eng_faculty'   AND d.code IN ('engineering', 'pharmacy', 'ytc_pharmacy'))
    OR (f.username = 'poly_faculty'  AND d.code IN ('polytechnic', 'dpharm'))
    OR (f.username = 'pharm_faculty' AND d.code IN ('management', 'architecture'));
