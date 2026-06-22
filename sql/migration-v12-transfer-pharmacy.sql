-- Transfer Pharmacy department access from pharm_faculty to eng_faculty.

USE `csf_portal`;

INSERT IGNORE INTO `faculty_departments` (`faculty_id`, `department_id`)
SELECT f.id, d.id
  FROM `faculty` f
  JOIN `departments` d ON d.code = 'pharmacy'
 WHERE f.username = 'eng_faculty';

DELETE fd
  FROM `faculty_departments` fd
  JOIN `faculty` f ON f.id = fd.faculty_id
  JOIN `departments` d ON d.id = fd.department_id
 WHERE f.username = 'pharm_faculty'
   AND d.code = 'pharmacy';
