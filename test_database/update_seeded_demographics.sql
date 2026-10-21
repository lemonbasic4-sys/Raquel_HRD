-- Existing major-employee test fixture: diversify gender and highest education.
-- Run once against the imported test database. Exact employee IDs/codes and
-- untouched mock values are required; custom profile edits are preserved.
DROP TEMPORARY TABLE IF EXISTS seeded_demographics;
CREATE TEMPORARY TABLE seeded_demographics (employee_id INT PRIMARY KEY, employee_code VARCHAR(30), gender VARCHAR(10), education_level VARCHAR(40));
INSERT INTO seeded_demographics VALUES
(20100,'AP-001','Female','Graduate Studies'),
(20101,'AP-002','Male','College'),
(20105,'AP-003','Male','College'),
(20108,'AP-004','Female','Vocational'),
(20200,'AUD-001','Male','Graduate Studies'),
(20201,'AUD-002','Female','College'),
(20203,'AUD-003','Male','College'),
(20206,'AUD-004','Female','College'),
(20300,'BD-001','Male','College'),
(20302,'BD-002','Female','College'),
(20400,'COM-001','Male','College'),
(20401,'COM-002','Female','College'),
(20403,'COM-003','Male','College'),
(20500,'FIN-001','Female','Graduate Studies'),
(20501,'FIN-002','Male','College'),
(20502,'FIN-003','Female','College'),
(20506,'FIN-004','Male','College'),
(20511,'FIN-005','Female','College'),
(20516,'FIN-006','Male','Vocational'),
(20600,'GS-001','Female','Graduate Studies'),
(20601,'GS-002','Male','College'),
(20605,'GS-003','Female','College'),
(20609,'GS-004','Male','Vocational'),
(20700,'HRD-001','Female','Graduate Studies'),
(20705,'HRD-002','Female','College'),
(20710,'HRD-003','Male','College'),
(20800,'IT-001','Male','College'),
(20804,'IT-002','Female','College'),
(20810,'IT-003','Male','Vocational'),
(20900,'MKT-001','Female','Graduate Studies'),
(20902,'MKT-002','Male','College'),
(20904,'MKT-003','Female','College'),
(21100,'OP-001','Female','Graduate Studies'),
(21101,'OP-002','Male','Secondary'),
(21000,'OPS-001','Male','Graduate Studies'),
(21001,'OPS-002','Female','College'),
(21004,'OPS-003','Male','College'),
(21007,'OPS-004','Female','College'),
(21012,'OPS-005','Male','Secondary'),
(21200,'PUR-001','Female','College'),
(21202,'PUR-002','Male','Vocational');

START TRANSACTION;
UPDATE employees e JOIN seeded_demographics d ON d.employee_id=e.employee_id AND d.employee_code=e.employee_code
SET e.gender=d.gender
WHERE d.gender='Female' AND e.gender='Male' AND e.deleted_at IS NULL;
SELECT ROW_COUNT() AS updated_gender_rows;

UPDATE employees e JOIN seeded_demographics d ON d.employee_id=e.employee_id AND d.employee_code=e.employee_code
SET e.profile_picture='avatar_f.jpg'
WHERE d.gender='Female' AND e.gender='Female' AND e.profile_picture='avatar_m.jpg';

UPDATE users u JOIN seeded_demographics d ON d.employee_id=u.employee_id
JOIN employees e ON e.employee_id=d.employee_id AND e.employee_code=d.employee_code
SET u.profile_picture='avatar_f.jpg'
WHERE d.gender='Female' AND e.gender='Female' AND u.profile_picture='avatar_m.jpg';

UPDATE employee_education ed JOIN seeded_demographics d ON d.employee_id=ed.employee_id
JOIN employees e ON e.employee_id=d.employee_id AND e.employee_code=d.employee_code
SET ed.education_level=d.education_level,
    ed.school_name=CASE d.education_level WHEN 'Graduate Studies' THEN 'Mock State University' WHEN 'Vocational' THEN 'Mock Technical Institute' WHEN 'Secondary' THEN 'Mock Secondary School' ELSE 'Mock Business College' END,
    ed.degree_course=CASE d.education_level WHEN 'Graduate Studies' THEN 'Master of Business Administration' WHEN 'Vocational' THEN 'Certificate in Office and Technical Services' WHEN 'Secondary' THEN NULL ELSE 'Bachelor of Science in Business Administration' END,
    ed.period_from=CASE d.education_level WHEN 'Graduate Studies' THEN '2007-06-01' WHEN 'Vocational' THEN '2003-06-01' WHEN 'Secondary' THEN '1997-06-01' ELSE '2002-06-01' END,
    ed.period_to=CASE d.education_level WHEN 'Graduate Studies' THEN '2009-04-01' WHEN 'Vocational' THEN '2005-04-01' WHEN 'Secondary' THEN '2001-04-01' ELSE '2006-04-01' END,
    ed.year_graduated=CASE d.education_level WHEN 'Graduate Studies' THEN '2009' WHEN 'Vocational' THEN '2005' WHEN 'Secondary' THEN '2001' ELSE '2006' END
WHERE d.education_level<>'College'
  AND ed.education_level='College'
  AND ed.school_name='Mock Business College'
  AND ed.degree_course='Bachelor of Science in Business Administration'
  AND ed.period_from='2002-06-01' AND ed.period_to='2006-04-01'
  AND ed.year_graduated='2006';
SELECT ROW_COUNT() AS updated_education_rows;
COMMIT;
DROP TEMPORARY TABLE seeded_demographics;

