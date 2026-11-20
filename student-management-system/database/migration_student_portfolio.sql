USE auspify_sms;

-- Safe to run whether the earlier portfolio migration was already applied or not.
DROP PROCEDURE IF EXISTS sms_add_portfolio_columns;
DELIMITER $$
CREATE PROCEDURE sms_add_portfolio_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'photo_path') THEN
        ALTER TABLE students ADD COLUMN photo_path VARCHAR(500) NULL AFTER address;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'qualifications') THEN
        ALTER TABLE students ADD COLUMN qualifications TEXT NULL AFTER photo_path;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'achievements') THEN
        ALTER TABLE students ADD COLUMN achievements TEXT NULL AFTER qualifications;
    END IF;
END$$
DELIMITER ;
CALL sms_add_portfolio_columns();
DROP PROCEDURE sms_add_portfolio_columns;
