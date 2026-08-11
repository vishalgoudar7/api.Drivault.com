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
```

## 6. Expected Auto-Renew Flow

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

## 7. Important Note

Do not expose real Razorpay secrets publicly. If any real key or secret has been shared in code, rotate it from Razorpay Dashboard.
