-- Recovery for the 2026-10-21 seeded hire-date change only.
-- Original values were checked in raquel_hris before the update:
-- all 41 hire dates = 2020-01-06;
-- each untouched Mock Services Company history = 2007-01-08 through 2019-12-31.
-- Exact IDs/codes and expected changed dates are required; custom dates are not overwritten.
-- Do not import routinely: this intentionally restores the uniform old analytics data.
DROP TEMPORARY TABLE IF EXISTS seeded_hire_date_changes;
CREATE TEMPORARY TABLE seeded_hire_date_changes (
 employee_id INT PRIMARY KEY, employee_code VARCHAR(30) NOT NULL, hire_date DATE NOT NULL
);
INSERT INTO seeded_hire_date_changes VALUES
(20100,'AP-001','2015-03-02'),
(20101,'AP-002','2018-06-04'),
(20105,'AP-003','2019-08-12'),
(20108,'AP-004','2026-01-12'),
(20200,'AUD-001','2016-04-11'),
(20201,'AUD-002','2018-09-03'),
(20203,'AUD-003','2020-02-17'),
(20206,'AUD-004','2025-07-14'),
(20300,'BD-001','2017-05-08'),
(20302,'BD-002','2026-02-09'),
(20400,'COM-001','2017-10-02'),
(20401,'COM-002','2022-04-18'),
(20403,'COM-003','2026-03-16'),
(20500,'FIN-001','2015-07-06'),
(20501,'FIN-002','2018-01-15'),
(20502,'FIN-003','2023-06-05'),
(20506,'FIN-004','2019-11-18'),
(20511,'FIN-005','2025-11-10'),
(20516,'FIN-006','2026-04-20'),
(20600,'GS-001','2015-09-07'),
(20601,'GS-002','2017-02-13'),
(20605,'GS-003','2019-05-20'),
(20609,'GS-004','2026-05-11'),
(20700,'HRD-001','2016-08-01'),
(20705,'HRD-002','2019-03-11'),
(20710,'HRD-003','2026-06-08'),
(20800,'IT-001','2017-07-10'),
(20804,'IT-002','2020-10-19'),
(20810,'IT-003','2026-07-13'),
(20900,'MKT-001','2017-11-06'),
(20902,'MKT-002','2020-06-15'),
(20904,'MKT-003','2026-08-10'),
(21100,'OP-001','2014-01-06'),
(21101,'OP-002','2026-09-14'),
(21000,'OPS-001','2015-11-09'),
(21001,'OPS-002','2018-04-16'),
(21004,'OPS-003','2021-09-06'),
(21007,'OPS-004','2024-08-19'),
(21012,'OPS-005','2026-10-05'),
(21200,'PUR-001','2022-12-05'),
(21202,'PUR-002','2026-10-19');

START TRANSACTION;
UPDATE employee_work_experience w
JOIN seeded_hire_date_changes r ON r.employee_id=w.employee_id
JOIN employees e ON e.employee_id=r.employee_id AND e.employee_code=r.employee_code
SET w.date_from='2007-01-08',w.date_to='2019-12-31'
WHERE e.hire_date=r.hire_date AND w.company_name='Mock Services Company'
 AND w.date_from=DATE_SUB(r.hire_date, INTERVAL 3 YEAR)
 AND w.date_to=DATE_SUB(r.hire_date, INTERVAL 1 DAY);
UPDATE employees e JOIN seeded_hire_date_changes r
 ON e.employee_id=r.employee_id AND e.employee_code=r.employee_code
SET e.hire_date='2020-01-06' WHERE e.hire_date=r.hire_date;
COMMIT;
DROP TEMPORARY TABLE seeded_hire_date_changes;

