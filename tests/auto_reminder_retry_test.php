<?php

declare(strict_types=1);

require __DIR__ . '/../includes/subscription-email-notifications.php';

$failures = [];
function checkRetryTest(bool $condition, string $name): void
{
    global $failures;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$condition) $failures[] = $name;
}

$timezone = new DateTimeZone('Asia/Kolkata');
$now = new DateTimeImmutable('2026-11-05 08:00:00', $timezone);
$charge = new DateTimeImmutable('2026-11-06 10:00:00', $timezone);
$base = [
    'renewal_mode' => 'auto',
    'auto_renew' => 1,
    'razorpay_subscription_id' => 'sub_test',
    'status' => 'active',
    'billing_cycle_key' => '2026-11-06',
];
$remote = ['status' => 'active', 'charge_at' => $charge->getTimestamp()];

$eligible = evaluateAutoPayReminderRetry($base, $remote, $now);
checkRetryTest($eligible['send'], 'Failed reminder is eligible for retry on the same reminder day');

$onDebitDate = evaluateAutoPayReminderRetry($base, $remote, new DateTimeImmutable('2026-11-06 00:01:00', $timezone));
checkRetryTest(!$onDebitDate['send'] && $onDebitDate['terminal'], 'Reminder is terminal on the debit date');

$cancelled = $base;
$cancelled['status'] = 'cancelled';
$cancelledResult = evaluateAutoPayReminderRetry($cancelled, ['status' => 'cancelled', 'charge_at' => $charge->getTimestamp()], $now);
checkRetryTest(!$cancelledResult['send'] && $cancelledResult['terminal'], 'Cancelled AutoPay subscription is not reminded');

$movedCharge = evaluateAutoPayReminderRetry($base, ['status' => 'active', 'charge_at' => $charge->modify('+1 month')->getTimestamp()], $now);
checkRetryTest(!$movedCharge['send'] && $movedCharge['terminal'], 'Already-processed or rescheduled billing cycle is skipped');

$claimSource = file_get_contents(__DIR__ . '/../includes/subscription-email-notifications.php');
$workerSource = file_get_contents(__DIR__ . '/../cron/retry_subscription_emails.php');
$migrationSource = file_get_contents(__DIR__ . '/../database/2026_11_05_subscription_email_notifications.sql');
checkRetryTest(str_contains($claimSource, 'INSERT IGNORE') && str_contains($migrationSource, 'uq_subscription_cycle_notification'), 'Concurrent claims retain unique insert protection');
checkRetryTest(str_contains($claimSource, 'attempts < ?') && str_contains($claimSource, 'status=\'processing\''), 'Maximum attempts also constrain stale processing claims');
checkRetryTest(str_contains($workerSource, "'auto_renew_reminder','payment_success','payment_failed'"), 'Reminder, success, and failure retries remain enabled');
checkRetryTest(str_contains($claimSource, "status='sent'") && str_contains($claimSource, "WHERE id=? AND status='processing'"), 'Sent notification cannot be reclaimed or marked sent without an active claim');
checkRetryTest(str_contains($workerSource, 'sendAutoPayReminder(') && !str_contains($workerSource, "notification_type'] === 'auto_renew_reminder') {\n        \$sent = retryNotificationMail"), 'Reminder retry reuses the AutoPay reminder sender');

exit($failures ? 1 : 0);
