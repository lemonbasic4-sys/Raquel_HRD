-- Durable delivery history for automatically generated probation reminders.
-- Keep this table when clearing the notifications inbox.
CREATE TABLE IF NOT EXISTS notification_reminder_deliveries (
    user_id INT NOT NULL,
    reminder_key CHAR(64) NOT NULL,
    delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, reminder_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
