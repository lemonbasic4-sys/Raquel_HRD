-- Repair the exact box-drawing sequence produced when UTF-8 ñ was imported
-- through the Windows mysql client with its cp850 default connection charset.
-- Back up affected rows before running against an existing database.
SET NAMES utf8mb4;
USE raquel_hris;
START TRANSACTION;

UPDATE branches
SET branch_name = REPLACE(branch_name, CONVERT(0xE2949CE29692 USING utf8mb4), CONVERT(0xC3B1 USING utf8mb4)),
    location = REPLACE(location, CONVERT(0xE2949CE29692 USING utf8mb4), CONVERT(0xC3B1 USING utf8mb4))
WHERE LOCATE(CONVERT(0xE2949CE29692 USING utf8mb4), branch_name) > 0
   OR LOCATE(CONVERT(0xE2949CE29692 USING utf8mb4), location) > 0;

UPDATE employee_addresses
SET city = REPLACE(city, CONVERT(0xE2949CE29692 USING utf8mb4), CONVERT(0xC3B1 USING utf8mb4))
WHERE LOCATE(CONVERT(0xE2949CE29692 USING utf8mb4), city) > 0;

COMMIT;
