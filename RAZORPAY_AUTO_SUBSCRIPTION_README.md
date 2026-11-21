# Razorpay Auto Subscription Setup

Use this checklist to make Razorpay auto-debit renewals work correctly in this project.

## 1. Add Webhook Secret

Edit `config/razorpay.php` and add `webhook_secret`.

Example:

```php
<?php

return [
    'key_id' => 'YOUR_RAZORPAY_KEY_ID',
    'key_secret' => 'YOUR_RAZORPAY_KEY_SECRET',
    'webhook_secret' => 'YOUR_RAZORPAY_WEBHOOK_SECRET'
];
```

Get `webhook_secret` from Razorpay Dashboard after creating the webhook.

## 2. Set auto_renew for Auto Pay

Edit `api/create-subscription.php`.

After this line:

```php
$renewalMode = $_POST['renewal_mode'] ?? 'auto';
```

Add:

```php
$autoRenew = $renewalMode === 'auto' ? 1 : 0;
```

Then update the `INSERT INTO subscriptions` query to include:

```sql
auto_renew,
```

Also add one more `?` in the `VALUES` list and bind `$autoRenew` in `bind_param`.

Expected saved values:

```text
Auto Pay:
renewal_mode = auto
auto_renew = 1

Manual Pay:
renewal_mode = manual
auto_renew = 0
```

## 3. Update Existing Auto Subscriptions

Run this once in phpMyAdmin:

```sql
UPDATE subscriptions
SET auto_renew = 1
WHERE renewal_mode = 'auto'
  AND razorpay_subscription_id IS NOT NULL
  AND razorpay_subscription_id <> '';
```

## 4. Prevent Cron From Expiring Auto-Renew Users Too Early

Edit `cron/subscription_checker.php`.

Find:

```php
if ($daysLeft <= 0 && strtolower($row['status']) == 'active') {
```

Add this at the top of that block:

```php
if (
    ($row['renewal_mode'] ?? '') === 'auto'
    && (int)($row['auto_renew'] ?? 0) === 1
    && !empty($row['razorpay_subscription_id'])
) {
    echo "<span style='color:blue'>Auto-renew subscription skipped. Waiting for Razorpay webhook.</span><br>";
    continue;
}
```

This prevents the cron from reducing quota before Razorpay sends the auto-renew webhook.

## 5. Create Razorpay Webhook

In Razorpay Dashboard, create a webhook with this URL:

```text
https://your-domain.com/api/razorpay-webhook.php
```

Enable these events:

```text
subscription.charged
payment.failed
subscription.cancelled
subscription.pending
subscription.halted
```

## 6. Install the notification migration

Apply `database/2026_11_05_subscription_email_notifications.sql` before deploying
the PHP changes. It is additive and does not modify existing subscription data.

## 7. Production cron entries

Run the subscription checker frequently enough to cover the calendar-day reminder
window, and retry transient SMTP failures separately:

```cron
15 8 * * * /usr/bin/php /var/www/html/cron/subscription_checker.php >> /var/log/drivault-subscriptions.log 2>&1
*/10 * * * * /usr/bin/php /var/www/html/cron/retry_subscription_emails.php >> /var/log/drivault-email-retries.log 2>&1
```

Optional worker settings (defaults shown) can be exported by the cron environment:

```text
SUBSCRIPTION_EMAIL_MAX_ATTEMPTS=10
SUBSCRIPTION_EMAIL_RETRY_BACKOFF_SECONDS=300
SUBSCRIPTION_EMAIL_STALE_SECONDS=900
```

The retry backoff is clamped to a minimum of 300 seconds. Apply
`database/2026_11_05_subscription_email_notifications_skipped.sql` when upgrading
an existing installation so obsolete reminders can be closed with `skipped` status.

Replace `/var/www/html` with the production release path. Do not run overlapping
copies through another scheduler unless they use the same notification database.

## 8. Expected Auto-Renew Flow

When Razorpay auto-debits the customer:

```text
Razorpay
-> subscription.charged webhook
-> api/razorpay-webhook.php
-> api/webhook/subscription-charged.php
```

The project should then:

```text
extend expiry_date
set status = active
set payment_status = Success
insert payment row
restore/update Drivault quota
send invoice email
```

## 9. Important Note

Do not expose real Razorpay secrets publicly. If any real key or secret has been shared in code, rotate it from Razorpay Dashboard.
