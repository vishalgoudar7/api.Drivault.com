ALTER TABLE `subscription_email_notifications`
  MODIFY COLUMN `status` enum('pending','processing','sent','failed','skipped')
  NOT NULL DEFAULT 'pending';
