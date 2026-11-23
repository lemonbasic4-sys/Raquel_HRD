SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_presentation_education;
CREATE TEMPORARY TABLE tmp_presentation_education (
    employee_code VARCHAR(30) PRIMARY KEY,
    highest_level VARCHAR(30) NOT NULL,
    degree_course VARCHAR(255) NULL,
    higher_school VARCHAR(255) NULL
);

INSERT INTO tmp_presentation_education VALUES
('DEMO-AP-REG','College','BS Real Estate Management','Lyceum of the Philippines University'),
('DEMO-AP-P01','College','BS Business Administration','Manuel S. Enverga University Foundation'),
('DEMO-AP-P02','College','BS Marketing Management','Southern Luzon State University'),
('DEMO-AUD-REG','College','BS Accountancy','Manuel S. Enverga University Foundation'),
('DEMO-AUD-P01','College','BS Accountancy','Southern Luzon State University'),
('DEMO-AUD-P02','College','BS Internal Auditing','Polytechnic University of the Philippines'),
('DEMO-BD-REG','College','BS Entrepreneurship','De La Salle Lipa'),
('DEMO-BD-P01','College','BS Business Administration','Southern Luzon State University'),
('DEMO-BD-P02','College','BS Economics','University of the Philippines Los Banos'),
('DEMO-COM-REG','College','BS Legal Management','Manuel S. Enverga University Foundation'),
('DEMO-COM-P01','College','BS Legal Management','University of Batangas'),
('DEMO-COM-P02','College','BS Public Administration','Southern Luzon State University'),
('DEMO-FIN-REG','College','BS Accountancy','De La Salle Lipa'),
('DEMO-FIN-P01','College','BS Management Accounting','University of Perpetual Help System Laguna'),
('DEMO-FIN-P02','College','BS Financial Management','Manuel S. Enverga University Foundation'),
('DEMO-GS-REG','College','BS Business Administration','Southern Luzon State University'),
('DEMO-GS-P01','Secondary',NULL,NULL),
('DEMO-GS-P02','Vocational','Facilities Maintenance NC II','Quezon Provincial Training Center'),
('DEMO-HR-REG','College','BS Psychology','Manuel S. Enverga University Foundation'),
('DEMO-HR-P01','College','BS Human Resource Management','Southern Luzon State University'),
('DEMO-HR-P02','College','BS Psychology','University of Batangas'),
('DEMO-IT-REG','College','BS Information Technology','Mapua Malayan Colleges Laguna'),
('DEMO-IT-P01','College','BS Computer Science','Southern Luzon State University'),
('DEMO-IT-P02','College','BS Information Technology','Laguna State Polytechnic University'),
('DEMO-MKT-REG','College','BS Marketing Management','De La Salle Lipa'),
('DEMO-MKT-P01','College','BA Communication','Manuel S. Enverga University Foundation'),
('DEMO-MKT-P02','College','BS Marketing Management','Southern Luzon State University'),
('DEMO-OOP-REG','College','BA Communication','University of the Philippines Diliman'),
('DEMO-OOP-P01','College','BS Office Administration','Polytechnic University of the Philippines'),
('DEMO-OOP-P02','College','BA Political Science','Southern Luzon State University'),
('DEMO-OPS-REG','College','BS Operations Management','University of Batangas'),
('DEMO-OPS-P01','College','BS Business Administration','Southern Luzon State University'),
('DEMO-OPS-P02','College','BS Operations Management','Manuel S. Enverga University Foundation'),
('DEMO-PUR-REG','College','BS Supply Chain Management','De La Salle Lipa'),
('DEMO-PUR-P01','College','BS Business Administration','Southern Luzon State University'),
('DEMO-PUR-P02','College','BS Logistics and Supply Chain Management','University of Batangas');

-- Common primary and secondary history, with dates derived from each fictional birth date.
INSERT INTO employee_education
    (employee_id,education_level,school_name,period_from,period_to,year_graduated)
SELECT e.employee_id,'Elementary','Quezon Provincial Elementary School',
       MAKEDATE(YEAR(e.date_of_birth)+6,1),MAKEDATE(YEAR(e.date_of_birth)+12,1),YEAR(e.date_of_birth)+12
FROM tmp_presentation_education p JOIN employees e ON e.employee_code=p.employee_code
WHERE NOT EXISTS (SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level='Elementary');

INSERT INTO employee_education
    (employee_id,education_level,school_name,period_from,period_to,year_graduated)
SELECT e.employee_id,'Secondary','Quezon Provincial National High School',
       MAKEDATE(YEAR(e.date_of_birth)+12,1),MAKEDATE(YEAR(e.date_of_birth)+16,1),YEAR(e.date_of_birth)+16
FROM tmp_presentation_education p JOIN employees e ON e.employee_code=p.employee_code
WHERE NOT EXISTS (SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level='Secondary');

-- K-12 applies to the younger presentation cohort except the documented pre-K-12 driver case.
INSERT INTO employee_education
    (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated)
SELECT e.employee_id,'Senior High School','Quezon Provincial National High School','Accountancy, Business and Management',
       MAKEDATE(YEAR(e.date_of_birth)+16,1),MAKEDATE(YEAR(e.date_of_birth)+18,1),YEAR(e.date_of_birth)+18
FROM tmp_presentation_education p JOIN employees e ON e.employee_code=p.employee_code
WHERE YEAR(e.date_of_birth)>=1999
  AND NOT EXISTS (SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level='Senior High School');

INSERT INTO employee_education
    (employee_id,education_level,school_name,degree_course,period_from,period_to,year_graduated)
SELECT e.employee_id,p.highest_level,p.higher_school,p.degree_course,
       MAKEDATE(YEAR(e.date_of_birth)+IF(p.highest_level='Vocational',18,18),1),
       MAKEDATE(YEAR(e.date_of_birth)+IF(p.highest_level='Vocational',19,22),1),
       YEAR(e.date_of_birth)+IF(p.highest_level='Vocational',19,22)
FROM tmp_presentation_education p JOIN employees e ON e.employee_code=p.employee_code
WHERE p.highest_level IN ('College','Vocational')
  AND NOT EXISTS (SELECT 1 FROM employee_education x WHERE x.employee_id=e.employee_id AND x.education_level=p.highest_level);

DROP TEMPORARY TABLE tmp_presentation_education;
COMMIT;

SELECT COUNT(*) AS presentation_education_rows
FROM employee_education ee JOIN employees e ON e.employee_id=ee.employee_id
WHERE e.employee_code LIKE 'DEMO-%';
