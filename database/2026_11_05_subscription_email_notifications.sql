CREATE TABLE IF NOT EXISTS `subscription_email_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `razorpay_subscription_id` varchar(100) DEFAULT NULL,
  `billing_cycle_key` varchar(100) NOT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `notification_type` enum('auto_renew_reminder','payment_success','payment_failed') NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `status` enum('pending','processing','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `last_error` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscription_cycle_notification` (`subscription_id`,`billing_cycle_key`,`notification_type`),
  KEY `idx_notification_retry` (`status`,`updated_at`),
  KEY `idx_notification_payment` (`razorpay_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recommended payment-level protection. Run only after checking for existing
-- duplicate non-empty Razorpay payment IDs in legacy data.
-- ALTER TABLE `payments` ADD UNIQUE KEY `uq_payments_razorpay_payment_id` (`razorpay_payment_id`);
