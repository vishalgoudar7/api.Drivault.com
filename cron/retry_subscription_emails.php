<?php

declare(strict_types=1);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../payment/invoice-helper.php';
require __DIR__ . '/send_reminder.php';
require __DIR__ . '/../includes/subscription-email-notifications.php';

use PHPMailer\PHPMailer\PHPMailer;
use Razorpay\Api\Api;

date_default_timezone_set('Asia/Kolkata');
$mailConfig = require __DIR__ . '/../config/mail.php';
$razorpayConfig = require __DIR__ . '/../config/razorpay.php';
$maxAttempts = max(1, (int) (getenv('SUBSCRIPTION_EMAIL_MAX_ATTEMPTS') ?: 10));
$retryBackoffSeconds = max(300, (int) (getenv('SUBSCRIPTION_EMAIL_RETRY_BACKOFF_SECONDS') ?: 300));
$staleProcessingSeconds = max($retryBackoffSeconds, (int) (getenv('SUBSCRIPTION_EMAIL_STALE_SECONDS') ?: 900));
$razorpay = new Api($razorpayConfig['key_id'], $razorpayConfig['key_secret']);

function retryNotificationMail(array $row, array $mailConfig): bool
{
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = (string) $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $mailConfig['username'];
    $mail->Password = (string) $mailConfig['password'];
    $mail->SMTPSecure = ($mailConfig['encryption'] ?? '') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) $mailConfig['port'];
    $mail->setFrom((string) ($mailConfig['from_address'] ?? $mailConfig['username']), (string) ($mailConfig['from_name'] ?? 'Drivault'));
    $mail->addAddress((string) $row['recipient'], (string) ($row['drivault_display_name'] ?? 'Customer'));
    $mail->isHTML(true);

    $name = htmlspecialchars((string) ($row['drivault_display_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $plan = htmlspecialchars((string) ($row['plan_name'] ?? 'Drivault Plan'), ENT_QUOTES, 'UTF-8');
    $amount = number_format((float) ($row['amount'] ?? $row['paid_amount'] ?? 0), 2);
    $paymentId = htmlspecialchars((string) ($row['razorpay_payment_id'] ?? '-'), ENT_QUOTES, 'UTF-8');

    if ($row['notification_type'] === 'payment_success') {
        $mail->Subject = 'Drivault AutoPay Payment Successful';
        $next = !empty($row['expiry_date']) ? date('d F Y', strtotime((string) $row['expiry_date'])) : '-';
        $mail->Body = "<div style='font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:28px;border-top:4px solid #12b76a'><h2>AutoPay Payment Successful</h2><p>Hello <strong>{$name}</strong>,</p><p>Your automatic subscription payment was successful.</p><p><strong>Plan:</strong> {$plan}<br><strong>Amount Paid:</strong> &#8377;{$amount}<br><strong>Payment ID:</strong> {$paymentId}<br><strong>Next Renewal:</strong> {$next}<br><strong>Status:</strong> Successful</p><p>Your Drivault subscription has been renewed successfully. Your invoice is attached.</p><p>Thank you,<br>Drivault Team</p></div>";
        $pdf = renderInvoicePdf(buildInvoiceHtml($row));
        $mail->addStringAttachment($pdf, 'Drivault-Invoice-' . (int) $row['subscription_id'] . '.pdf', 'base64', 'application/pdf');
    } else {
        $mail->Subject = 'Drivault AutoPay Payment Failed';
        $manageBase = rtrim((string) ($mailConfig['website_url'] ?? ''), '/');
        $manageUrl = htmlspecialchars($manageBase . '/pages/renew.php?subscription_id=' . (int) $row['subscription_id'], ENT_QUOTES, 'UTF-8');
        $mail->Body = "<div style='font-family:Arial,sans-serif;max-width:620px;margin:auto;padding:28px;border-top:4px solid #dc2626'><h2>AutoPay Payment Failed</h2><p>Hello <strong>{$name}</strong>,</p><p>We could not process your automatic subscription payment.</p><p><strong>Plan:</strong> {$plan}<br><strong>Amount:</strong> &#8377;{$amount}<br><strong>Payment Status:</strong> Failed</p><p>Please check your authorized payment method and manage your subscription to avoid interruption.</p><p><a href='{$manageUrl}' style='background:#12b76a;color:#fff;padding:12px 20px;text-decoration:none'>Manage Subscription</a></p><p>Thank you,<br>Drivault Team</p></div>";
    }

    $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body)));
    return $mail->send();
}

$sql = "SELECT n.*, s.*, n.id AS notification_id, n.subscription_id AS notification_subscription_id,
               n.razorpay_subscription_id AS notification_razorpay_subscription_id,
               p.amount, p.status AS recorded_payment_status
        FROM subscription_email_notifications n
        JOIN subscriptions s ON s.id=n.subscription_id
        LEFT JOIN payments p ON p.razorpay_payment_id=n.razorpay_payment_id
        WHERE n.status='failed' AND n.notification_type IN ('auto_renew_reminder','payment_success','payment_failed')
          AND n.attempts < ?
        ORDER BY n.updated_at ASC LIMIT 50";
$workerStmt = $conn->prepare($sql);
$workerStmt->bind_param('i', $maxAttempts);
$workerStmt->execute();
$result = $workerStmt->get_result();

while ($row = $result->fetch_assoc()) {
    $razorpaySubscriptionId = (string) ($row['notification_razorpay_subscription_id'] ?? $row['razorpay_subscription_id'] ?? '');
    $id = claimSubscriptionNotification(
        $conn,
        (int) $row['notification_subscription_id'],
        $razorpaySubscriptionId,
        (string) $row['billing_cycle_key'],
        (string) $row['notification_type'],
        (string) $row['recipient'],
        (string) ($row['razorpay_payment_id'] ?? ''),
        $maxAttempts,
        $retryBackoffSeconds,
        $staleProcessingSeconds
    );
    if ($id === null) continue;
    try {
        if ($row['notification_type'] === 'auto_renew_reminder') {
            $successStmt = $conn->prepare(
                "SELECT id FROM subscription_email_notifications
                 WHERE subscription_id=? AND notification_type='payment_success'
                   AND created_at >= (SELECT created_at FROM subscription_email_notifications WHERE id=? LIMIT 1)
                 LIMIT 1"
            );
            $subscriptionId = (int) $row['notification_subscription_id'];
            $successStmt->bind_param('ii', $subscriptionId, $id);
            $successStmt->execute();
            $paymentAlreadySucceeded = $successStmt->get_result()->fetch_assoc() !== null;
            $successStmt->close();
            if ($paymentAlreadySucceeded) {
                skipSubscriptionNotification($conn, $id, 'Payment already succeeded for this subscription after the reminder was created.');
                continue;
            }

            $remoteEntity = $razorpay->subscription->fetch($razorpaySubscriptionId);
            $remote = $remoteEntity->toArray();
            $eligibility = evaluateAutoPayReminderRetry(
                $row,
                $remote,
                new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'))
            );

            if (!$eligibility['send']) {
                if ($eligibility['terminal']) {
                    skipSubscriptionNotification($conn, $id, $eligibility['reason']);
                } else {
                    finishSubscriptionNotification($conn, $id, false, $eligibility['reason']);
                }
                continue;
            }

            $renewalDate = (new DateTimeImmutable('@' . $eligibility['charge_at']))
                ->setTimezone(new DateTimeZone('Asia/Kolkata'));
            $sent = sendAutoPayReminder($row, $renewalDate, (float) ($row['paid_amount'] ?? 0));
        } else {
            $sent = retryNotificationMail($row, $mailConfig);
        }
        finishSubscriptionNotification($conn, $id, $sent, $sent ? null : 'PHPMailer SMTP submission returned false.');
    } catch (Throwable $exception) {
        finishSubscriptionNotification($conn, $id, false, 'Retry failed: ' . $exception->getMessage());
    }
}
$workerStmt->close();
