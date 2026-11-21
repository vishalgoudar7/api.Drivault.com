<?php

declare(strict_types=1);

/**
 * Atomically reserves a notification. A failed/stale claim is retryable; a sent
 * notification or a currently-processing claim is not.
 */
function claimSubscriptionNotification(
    mysqli $conn,
    int $subscriptionId,
    string $razorpaySubscriptionId,
    string $cycleKey,
    string $type,
    string $recipient,
    ?string $paymentId = null,
    int $maxAttempts = 10,
    int $retryBackoffSeconds = 300,
    int $staleProcessingSeconds = 900
): ?int {
    $maxAttempts = max(1, $maxAttempts);
    $retryBackoffSeconds = max(300, $retryBackoffSeconds);
    $staleProcessingSeconds = max($retryBackoffSeconds, $staleProcessingSeconds);
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO subscription_email_notifications
         (subscription_id, razorpay_subscription_id, billing_cycle_key, razorpay_payment_id,
          notification_type, recipient, status, attempts)
         VALUES (?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, 'processing', 1)"
    );
    $paymentId = $paymentId ?? '';
    $stmt->bind_param('isssss', $subscriptionId, $razorpaySubscriptionId, $cycleKey, $paymentId, $type, $recipient);
    $stmt->execute();
    $id = $stmt->affected_rows === 1 ? (int) $conn->insert_id : 0;
    $stmt->close();

    if ($id > 0) {
        return $id;
    }

    $stmt = $conn->prepare(
        "UPDATE subscription_email_notifications
         SET status='processing', attempts=attempts+1, last_error=NULL,
             razorpay_payment_id=COALESCE(NULLIF(?, ''), razorpay_payment_id), recipient=?
         WHERE subscription_id=? AND billing_cycle_key=? AND notification_type=?
           AND attempts < ?
           AND ((status='failed' AND updated_at <= DATE_SUB(NOW(), INTERVAL ? SECOND))
             OR (status='processing' AND updated_at <= DATE_SUB(NOW(), INTERVAL ? SECOND)))"
    );
    $stmt->bind_param('ssissiii', $paymentId, $recipient, $subscriptionId, $cycleKey, $type, $maxAttempts, $retryBackoffSeconds, $staleProcessingSeconds);
    $stmt->execute();
    $claimed = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$claimed) {
        return null;
    }

    $stmt = $conn->prepare("SELECT id FROM subscription_email_notifications WHERE subscription_id=? AND billing_cycle_key=? AND notification_type=? LIMIT 1");
    $stmt->bind_param('iss', $subscriptionId, $cycleKey, $type);
    $stmt->execute();
    $id = (int) (($stmt->get_result()->fetch_assoc()['id'] ?? 0));
    $stmt->close();
    return $id > 0 ? $id : null;
}

function skipSubscriptionNotification(mysqli $conn, int $notificationId, string $reason): void
{
    $safeReason = mb_substr($reason, 0, 2000);
    $stmt = $conn->prepare("UPDATE subscription_email_notifications SET status='skipped', last_error=? WHERE id=? AND status IN ('failed','processing','pending')");
    $stmt->bind_param('si', $safeReason, $notificationId);
    $stmt->execute();
    $stmt->close();
}

/** @return array{send:bool,terminal:bool,reason:string,charge_at:int} */
function evaluateAutoPayReminderRetry(array $subscription, array $razorpaySubscription, DateTimeImmutable $now): array
{
    $isAutoPay = strtolower((string) ($subscription['renewal_mode'] ?? '')) === 'auto'
        && (int) ($subscription['auto_renew'] ?? 0) === 1
        && trim((string) ($subscription['razorpay_subscription_id'] ?? '')) !== '';
    if (!$isAutoPay) {
        return ['send' => false, 'terminal' => true, 'reason' => 'AutoPay is no longer enabled.', 'charge_at' => 0];
    }

    $localStatus = strtolower((string) ($subscription['status'] ?? ''));
    if (!in_array($localStatus, ['active'], true)) {
        return ['send' => false, 'terminal' => true, 'reason' => 'Subscription is no longer active.', 'charge_at' => 0];
    }

    $remoteStatus = strtolower((string) ($razorpaySubscription['status'] ?? ''));
    if (!in_array($remoteStatus, ['active', 'authenticated'], true)) {
        return ['send' => false, 'terminal' => true, 'reason' => 'Razorpay subscription is not chargeable.', 'charge_at' => 0];
    }

    $chargeAt = (int) ($razorpaySubscription['charge_at'] ?? $razorpaySubscription['current_end'] ?? 0);
    if ($chargeAt <= 0) {
        return ['send' => false, 'terminal' => false, 'reason' => 'Razorpay did not return a scheduled charge date.', 'charge_at' => 0];
    }

    $timezone = new DateTimeZone('Asia/Kolkata');
    $now = $now->setTimezone($timezone);
    $chargeDate = (new DateTimeImmutable('@' . $chargeAt))->setTimezone($timezone);
    $storedCycle = (string) ($subscription['billing_cycle_key'] ?? '');
    if ($chargeDate->format('Y-m-d') !== $storedCycle) {
        return ['send' => false, 'terminal' => true, 'reason' => 'Scheduled charge no longer matches the reminder billing cycle.', 'charge_at' => $chargeAt];
    }

    if ($chargeDate->format('Y-m-d') !== $now->modify('+1 day')->format('Y-m-d')) {
        return ['send' => false, 'terminal' => true, 'reason' => 'Reminder is no longer one calendar day before the charge.', 'charge_at' => $chargeAt];
    }

    return ['send' => true, 'terminal' => false, 'reason' => '', 'charge_at' => $chargeAt];
}

function finishSubscriptionNotification(mysqli $conn, int $notificationId, bool $sent, ?string $error = null): void
{
    if ($sent) {
        $stmt = $conn->prepare("UPDATE subscription_email_notifications SET status='sent', sent_at=NOW(), last_error=NULL WHERE id=? AND status='processing'");
        $stmt->bind_param('i', $notificationId);
    } else {
        $safeError = mb_substr((string) $error, 0, 2000);
        $stmt = $conn->prepare("UPDATE subscription_email_notifications SET status='failed', last_error=? WHERE id=? AND status='processing'");
        $stmt->bind_param('si', $safeError, $notificationId);
    }
    $stmt->execute();
    $stmt->close();
}

function notificationCycleKeyFromTimestamp(int $timestamp): string
{
    $timezone = new DateTimeZone('Asia/Kolkata');
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('Y-m-d');
}
