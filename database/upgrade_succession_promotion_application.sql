-- Existing installations only. Fresh imports already have these columns in 1st_schema_tables.sql.
ALTER TABLE succession_endorsements ADD COLUMN previous_job_title VARCHAR(150) NULL;
ALTER TABLE succession_endorsements ADD COLUMN applied_at DATETIME NULL;
