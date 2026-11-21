<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/subscription-email-notifications.php';

$logFile = __DIR__ . '/../payment-debug.log';
$mailConfig = require __DIR__ . '/../../config/mail.php';

function webhookFailedLog(string $message, array $context = []): void
{
    global $logFile;

    file_put_contents(
        $logFile,
        '[payment-failed-webhook] ' . date('Y-m-d H:i:s') . ' ' . $message .
        ($context ? ' ' . json_encode($context) : '') .
        PHP_EOL,
        FILE_APPEND
    );
}

function webhookFailedResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function getFailedWebhookPayload(): array
{
    if (isset($GLOBALS['razorpayWebhookPayload']) && is_array($GLOBALS['razorpayWebhookPayload'])) {
        return $GLOBALS['razorpayWebhookPayload'];
    }

    $rawPayload = file_get_contents('php://input');
    $decodedPayload = json_decode((string) $rawPayload, true);

    return is_array($decodedPayload) ? $decodedPayload : [];
}

function sendPaymentFailedEmail(array $subscription, array $payment, array $mailConfig): bool
{
    $customerEmail = trim((string) ($subscription['drivault_email'] ?? ''));

    if ($customerEmail === '' || filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $smtpHost = (string) ($mailConfig['host'] ?? '');
    $smtpPort = (int) ($mailConfig['port'] ?? 0);
    $smtpEncryption = (string) ($mailConfig['encryption'] ?? '');
    $smtpUsername = trim((string) ($mailConfig['username'] ?? ''));
    $smtpPassword = trim((string) ($mailConfig['password'] ?? ''));
    $smtpFromName = trim((string) ($mailConfig['from_name'] ?? 'Drivault'));
    $fromAddress = trim((string) ($mailConfig['from_address'] ?? $smtpUsername));

    if ($smtpHost === '' || $smtpPort <= 0 || $smtpUsername === '' || $smtpPassword === '') {
        return false;
    }

    $customerName = trim((string) ($subscription['drivault_display_name'] ?? 'Customer'));
    $planName = trim((string) ($subscription['plan_name'] ?? 'Drivault Plan'));
    $billingCycle = ucfirst(trim((string) ($subscription['billing_cycle'] ?? '')));
    $amount = isset($payment['amount']) && is_numeric($payment['amount'])
        ? number_format(((float) $payment['amount']) / 100, 2)
        : number_format((float) ($subscription['paid_amount'] ?? 0), 2);
    $paymentId = trim((string) ($payment['id'] ?? ''));
    $attemptDate = date('d F Y', (int) ($payment['created_at'] ?? time()));
    $errorDescription = trim((string) ($payment['error_description'] ?? $payment['error_reason'] ?? 'Payment failed.'));
    $supportEmail = trim((string) ($mailConfig['support_email'] ?? 'support@drivault.com'));
    $websiteUrl = trim((string) ($mailConfig['website_url'] ?? 'www.drivault.com'));
    $websiteBaseUrl = rtrim((string) $websiteUrl, '/');
    $websiteDisplay = preg_replace('#^https?://#', '', $websiteBaseUrl);
    $renewUrl = $websiteBaseUrl . '/pages/renew.php?' . http_build_query([
        'subscription_id' => (int) ($subscription['id'] ?? 0),
    ]);
    $logoPath = realpath(__DIR__ . '/../../assets/Photos/icon-192.png');
    $logoHtml = '<span style="display:inline-block;width:34px;height:34px;border:2px solid #12b76a;border-radius:10px;color:#12b76a;line-height:30px;font-size:18px;vertical-align:middle;">D</span>';
    $mail = new PHPMailer(true);

    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = $smtpEncryption === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtpPort;
    $mail->setFrom($fromAddress !== '' ? $fromAddress : $smtpUsername, $smtpFromName);
    $mail->addAddress($customerEmail, $customerName);
    $mail->isHTML(true);
    $mail->Subject = 'Drivault AutoPay Payment Failed';

    if ($logoPath) {
        $mail->addEmbeddedImage($logoPath, 'drivault-logo', 'drivault-logo.png');
        $logoHtml = '<img src="cid:drivault-logo" width="38" height="38" alt="Drivault" style="display:inline-block;border:0;vertical-align:middle;margin-right:8px;">';
    }

    $mail->Body = sprintf(
        '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;padding:10px;color:#111827;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:4px;overflow:hidden;">
                <div style="text-align:center;padding:26px 20px 22px;border-bottom:3px solid #dc2626;">
                    %8$s
                    <span style="display:inline-block;vertical-align:middle;font-size:34px;font-weight:700;color:#111827;line-height:38px;">Drivault</span>
                </div>
                <div style="padding:24px 42px 32px;">
                    <div style="width:66px;height:66px;border-radius:50%%;background:#fef2f2;margin:0 auto 14px;text-align:center;line-height:66px;color:#dc2626;font-size:34px;font-weight:700;">!</div>
                    <h1 style="margin:0;text-align:center;color:#dc2626;font-size:28px;line-height:34px;">Payment Failed</h1>
                    <p style="margin:8px 0 32px;text-align:center;color:#4b5563;font-size:13px;">We could not process your Drivault subscription payment.</p>
                    <p style="margin:0 0 14px;font-size:14px;">Hello <strong style="color:#12b76a;">%1$s</strong>,</p>
                    <p style="margin:0 0 22px;font-size:14px;color:#374151;line-height:22px;">Your recent payment attempt was unsuccessful. Please retry payment to keep your Drivault storage active.</p>
                    <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:separate;border-spacing:0;border:1px solid #dfe3e8;border-radius:7px;overflow:hidden;font-size:14px;margin:0 0 22px;">
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Plan</td><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;">%2$s</td></tr>
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Billing Cycle</td><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%3$s</td></tr>
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Amount</td><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">Rs %4$s</td></tr>
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Payment ID</td><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%5$s</td></tr>
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Attempt Date</td><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%12$s</td></tr>
                        <tr><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Payment Status</td><td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#dc2626;font-weight:700;">Failed</td></tr>
                        <tr><td style="padding:14px 16px;font-weight:600;">Reason</td><td style="padding:14px 16px;text-align:right;color:#dc2626;font-weight:700;">%6$s</td></tr>
                    </table>
                    <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:6px;padding:16px 20px;margin:0 0 28px;color:#991b1b;font-size:14px;line-height:22px;">
                        <strong>Action Required</strong><br>
                        Please retry the payment or contact support if the issue continues.
                    </div>
                    <div style="text-align:center;margin:0 0 30px;">
                        <a href="%11$s" style="display:inline-block;background:#12b76a;color:#ffffff;text-decoration:none;border-radius:7px;padding:14px 28px;font-size:14px;font-weight:700;">Renew Subscription</a>
                    </div>
                    <p style="margin:0 0 4px;font-size:14px;">Thank you,</p>
                    <p style="margin:0 0 24px;color:#12b76a;font-size:15px;font-weight:700;">Drivault Team</p>
                    <div style="border-top:1px solid #d9dee5;padding-top:22px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:collapse;color:#4b5563;font-size:13px;">
                            <tr><td style="width:50%%;text-align:center;padding:0 10px;border-right:1px solid #d9dee5;">&#9993;&nbsp;&nbsp;%9$s</td><td style="width:50%%;text-align:center;padding:0 10px;">&#9711;&nbsp;&nbsp;%10$s</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>',
        htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($billingCycle, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($errorDescription, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8'),
        $logoHtml,
        htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars((string) $websiteDisplay, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($renewUrl, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($attemptDate, ENT_QUOTES, 'UTF-8')
    );

    $mail->AltBody = sprintf(
        "Hello %s,\n\nYour Drivault subscription payment failed.\n\nPlan: %s\nBilling Cycle: %s\nAmount: Rs %s\nPayment ID: %s\nReason: %s\n\nRenew Subscription: %s\n\nPlease retry the payment or contact support if the issue continues.\n\nThank you,\nDrivault Team",
        $customerName,
        $planName,
        $billingCycle,
        $amount,
        $paymentId,
        $errorDescription,
        $renewUrl
    );

    $mail->send();

    return true;
}

try {
    $payload = getFailedWebhookPayload();
    $payment = $payload['payload']['payment']['entity'] ?? [];
    $subscriptionEntity = $payload['payload']['subscription']['entity'] ?? [];
    $razorpaySubscriptionId = trim((string) ($payment['subscription_id'] ?? $subscriptionEntity['id'] ?? ''));
    $paymentId = trim((string) ($payment['id'] ?? ''));
    $orderId = trim((string) ($payment['order_id'] ?? ''));
$invoiceId = trim((string) ($payment['invoice_id'] ?? ''));

if ($razorpaySubscriptionId === '' && $invoiceId !== '') {
    try {
        $razorpayConfig = require __DIR__ . '/../../config/razorpay.php';

        $razorpayApi = new \Razorpay\Api\Api(
            $razorpayConfig['key_id'],
            $razorpayConfig['key_secret']
        );

        $invoice = $razorpayApi->invoice->fetch($invoiceId);

        $razorpaySubscriptionId = trim(
            (string) ($invoice->subscription_id ?? '')
        );

        webhookFailedLog('Subscription resolved from invoice', [
            'invoice_id' => $invoiceId,
            'razorpay_subscription_id' => $razorpaySubscriptionId
        ]);

    } catch (Throwable $exception) {
        webhookFailedLog('Invoice lookup failed', [
            'invoice_id' => $invoiceId,
            'error' => $exception->getMessage()
        ]);

        throw $exception;
    }
}
    $amount = isset($payment['amount']) && is_numeric($payment['amount'])
        ? ((float) $payment['amount'] / 100)
        : 0.0;
    $rawResponse = json_encode($payload);

    webhookFailedLog('Webhook received', [
        'event' => $payload['event'] ?? '',
        'razorpay_subscription_id' => $razorpaySubscriptionId,
        'payment_id' => $paymentId,
    ]);

    if ($razorpaySubscriptionId === '' && $orderId === '') {
        webhookFailedResponse(422, [
            'success' => false,
            'message' => 'Missing subscription or order id.',
        ]);
    }

    $conn->begin_transaction();

    if ($razorpaySubscriptionId !== '') {
        $stmt = $conn->prepare('SELECT * FROM subscriptions WHERE razorpay_subscription_id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $razorpaySubscriptionId);
    } else {
        $stmt = $conn->prepare('SELECT * FROM subscriptions WHERE razorpay_order_id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $orderId);
    }

    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();

    if (!$subscription) {
        throw new RuntimeException('Subscription not found.');
    }

    $subscriptionId = (int) $subscription['id'];
    $userId = (string) $subscription['user_id'];

    if ($paymentId !== '') {
        $stmt = $conn->prepare("SELECT id FROM payments WHERE razorpay_payment_id = ? AND status = 'failed' LIMIT 1");
        $stmt->bind_param('s', $paymentId);
        $stmt->execute();
        $existingPayment = $stmt->get_result()->fetch_assoc();

        if ($existingPayment) {
            $conn->commit();
            webhookFailedLog('Duplicate failed webhook ignored', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscriptionId,
            ]);

            $cycleTimestamp = (int) ($subscriptionEntity['current_end'] ?? $subscriptionEntity['charge_at'] ?? strtotime((string) ($subscription['expiry_date'] ?? 'now')));
            $notificationId = claimSubscriptionNotification($conn, $subscriptionId, $razorpaySubscriptionId, notificationCycleKeyFromTimestamp($cycleTimestamp), 'payment_failed', (string) $subscription['drivault_email'], $paymentId);
            if ($notificationId !== null) {
                try {
                    $sent = sendPaymentFailedEmail($subscription, $payment, $mailConfig);
                    finishSubscriptionNotification($conn, $notificationId, $sent, $sent ? null : 'SMTP send returned false');
                } catch (Throwable $mailError) {
                    finishSubscriptionNotification($conn, $notificationId, false, $mailError->getMessage());
                }
            }
            webhookFailedResponse(200, [
                'success' => true,
                'message' => 'Duplicate failed webhook ignored.',
                'subscription_id' => $subscriptionId,
                'payment_id' => $paymentId,
            ]);
        }
    }

    // Keep already-paid, unexpired subscriptions active.
    $expiryDate = trim((string) ($subscription['expiry_date'] ?? ''));
    $paidUntil = $expiryDate !== ''
        ? new DateTimeImmutable($expiryDate, new DateTimeZone('Asia/Kolkata'))
        : null;

    $preservePaidAccess =
        strtolower((string) ($subscription['status'] ?? '')) === 'active'
        && strtolower((string) ($subscription['payment_status'] ?? '')) === 'success'
        && $paidUntil !== null
        && $paidUntil > new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));

    if ($preservePaidAccess) {
        webhookFailedLog('Paid subscription preserved after failed charge', [
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'expiry_date' => $expiryDate,
        ]);
    } else {
        $stmt = $conn->prepare(
            "UPDATE subscriptions
             SET payment_status='Failed',
                 status='pending',
                 razorpay_order_id=?,
                 razorpay_payment_id=?,
                 disabled_at=NULL
             WHERE id=?"
        );
        $stmt->bind_param('ssi', $orderId, $paymentId, $subscriptionId);

        if (!$stmt->execute()) {
            throw new RuntimeException('Unable to update subscription: ' . $stmt->error);
        }

        webhookFailedLog('Subscription marked failed', [
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
        ]);
    }

    $paymentType = 'renewal';
    $paymentStatus = 'failed';
    $paymentAmount = $amount > 0 ? $amount : (float) ($subscription['paid_amount'] ?? 0);
    $stmt = $conn->prepare(
        'INSERT INTO payments
        (
            user_id,
            subscription_id,
            razorpay_order_id,
            razorpay_subscription_id,
            razorpay_payment_id,
            amount,
            payment_type,
            status,
            response
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )'
    );
    $stmt->bind_param(
        'sisssdsss',
        $userId,
        $subscriptionId,
        $orderId,
        $razorpaySubscriptionId,
        $paymentId,
        $paymentAmount,
        $paymentType,
        $paymentStatus,
        $rawResponse
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to save failed payment: ' . $stmt->error);
    }

    webhookFailedLog('Failed payment row saved', [
        'payment_insert_id' => $conn->insert_id,
        'subscription_id' => $subscriptionId,
        'payment_id' => $paymentId,
    ]);

<<<<<<< HEAD
    if (!$preservePaidAccess) {
        $subscription['payment_status'] = 'Failed';
        $subscription['status'] = 'pending';
        $subscription['razorpay_payment_id'] = $paymentId;
    }

    $conn->commit();

    $emailSent = false;
    $cycleTimestamp = (int) ($subscriptionEntity['current_end'] ?? $subscriptionEntity['charge_at'] ?? strtotime((string) ($subscription['expiry_date'] ?? 'now')));
    $notificationId = claimSubscriptionNotification(
        $conn,
        $subscriptionId,
        $razorpaySubscriptionId,
        notificationCycleKeyFromTimestamp($cycleTimestamp),
        'payment_failed',
        (string) $subscription['drivault_email'],
        $paymentId
    );

    try {
        if ($notificationId === null) {
            throw new RuntimeException('Failure notification already sent or currently claimed.');
        }
        $emailSent = sendPaymentFailedEmail($subscription, $payment, $mailConfig);
        finishSubscriptionNotification($conn, $notificationId, $emailSent, $emailSent ? null : 'SMTP send returned false');
    } catch (MailerException $exception) {
        if ($notificationId !== null) finishSubscriptionNotification($conn, $notificationId, false, $exception->getMessage());
        webhookFailedLog('Payment failed email failed', ['error' => $exception->getMessage()]);
    } catch (Throwable $exception) {
        if ($notificationId !== null && $exception->getMessage() !== 'Failure notification already sent or currently claimed.') finishSubscriptionNotification($conn, $notificationId, false, $exception->getMessage());
        webhookFailedLog('Payment failed email error', ['error' => $exception->getMessage()]);
    }

=======
    $subscription['payment_status'] = 'Failed';
    $subscription['status'] = 'pending';
    $subscription['razorpay_payment_id'] = $paymentId;

    $conn->commit();

    $emailSent = false;
    $cycleTimestamp = (int) ($subscriptionEntity['current_end'] ?? $subscriptionEntity['charge_at'] ?? strtotime((string) ($subscription['expiry_date'] ?? 'now')));
    $notificationId = claimSubscriptionNotification(
        $conn,
        $subscriptionId,
        $razorpaySubscriptionId,
        notificationCycleKeyFromTimestamp($cycleTimestamp),
        'payment_failed',
        (string) $subscription['drivault_email'],
        $paymentId
    );

    try {
        if ($notificationId === null) {
            throw new RuntimeException('Failure notification already sent or currently claimed.');
        }
        $emailSent = sendPaymentFailedEmail($subscription, $payment, $mailConfig);
        finishSubscriptionNotification($conn, $notificationId, $emailSent, $emailSent ? null : 'SMTP send returned false');
    } catch (MailerException $exception) {
        if ($notificationId !== null) finishSubscriptionNotification($conn, $notificationId, false, $exception->getMessage());
        webhookFailedLog('Payment failed email failed', ['error' => $exception->getMessage()]);
    } catch (Throwable $exception) {
        if ($notificationId !== null && $exception->getMessage() !== 'Failure notification already sent or currently claimed.') finishSubscriptionNotification($conn, $notificationId, false, $exception->getMessage());
        webhookFailedLog('Payment failed email error', ['error' => $exception->getMessage()]);
    }

>>>>>>> fd87cdc9eb46419dda82712f7d0cf34583619e83
    webhookFailedLog('Webhook processed successfully', [
        'subscription_id' => $subscriptionId,
        'payment_id' => $paymentId,
        'email_sent' => $emailSent,
    ]);

    webhookFailedResponse(200, [
        'success' => true,
        'message' => 'Payment failed webhook processed.',
        'subscription_id' => $subscriptionId,
        'payment_id' => $paymentId,
        'email_sent' => $emailSent,
    ]);
} catch (Throwable $exception) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable) {
        }
    }

    webhookFailedLog('Webhook failed', [
        'error' => $exception->getMessage(),
    ]);

    webhookFailedResponse(500, [
        'success' => false,
        'message' => $exception->getMessage(),
    ]);
}
