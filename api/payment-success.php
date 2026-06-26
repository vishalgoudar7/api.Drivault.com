<?php

require '../config/db.php';
require '../vendor/autoload.php';
require __DIR__ . '/../payment/invoice-helper.php';
// require '../includes/drivault.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header('Content-Type: application/json');

$config = require '../config/razorpay.php';
$mailConfig = require '../config/mail.php';

$api = new Api(
    $config['key_id'],
    $config['key_secret']
);

function sendPaymentSuccessEmail(array $subscription, array $plan, array $mailConfig): bool
{
    $customerEmail = trim((string) ($subscription['customer_email'] ?? ''));

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

    $customerName = trim((string) ($subscription['customer_name'] ?? 'Customer'));
    $planName = (string) ($plan['name'] ?? $subscription['plan_name'] ?? 'Drivault Plan');
    $quota = (string) ($plan['quota'] ?? '');
    $billingCycle = ucfirst((string) ($subscription['billing_cycle'] ?? ''));
    $amount = number_format((float) ($subscription['amount'] ?? 0), 2);
    $paymentId = (string) ($subscription['razorpay_payment_id'] ?? '');
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . $subscription['id'];
    $supportEmail = trim((string) ($mailConfig['support_email'] ?? 'support@drivault.com'));
    $websiteUrl = trim((string) ($mailConfig['website_url'] ?? 'www.drivault.com'));
    $websiteDisplay = preg_replace('#^https?://#', '', rtrim($websiteUrl, '/'));
    $logoPath = realpath(__DIR__ . '/../assets/Photos/icon-192.png');
    $logoHtml = '<span style="display:inline-block;width:34px;height:34px;border:2px solid #12b76a;border-radius:10px;color:#12b76a;line-height:30px;font-size:18px;vertical-align:middle;">D</span>';

    $invoiceHtml = buildInvoiceHtml($subscription + [
        'plan_name' => $planName,
        'quota' => $quota,
    ]);
    $invoicePdf = renderInvoicePdf($invoiceHtml);

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
    $mail->Subject = 'Payment Successful - Your Drivault Invoice';

    if ($logoPath) {
        $mail->addEmbeddedImage($logoPath, 'drivault-logo', 'drivault-logo.png');
        $logoHtml = '<img src="cid:drivault-logo" width="38" height="38" alt="Drivault" style="display:inline-block;border:0;vertical-align:middle;margin-right:8px;">';
    }

    $mail->Body = sprintf(
        '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;padding:10px;color:#111827;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:4px;overflow:hidden;">
                <div style="text-align:center;padding:26px 20px 22px;border-bottom:3px solid #12b76a;">
                    %8$s
                    <span style="display:inline-block;vertical-align:middle;font-size:34px;font-weight:700;color:#111827;line-height:38px;">Drivault</span>
                </div>

                <div style="padding:24px 42px 32px;">
                    <div style="width:66px;height:66px;border-radius:50%%;background:#eaf8f0;margin:0 auto 14px;text-align:center;line-height:66px;color:#12b76a;font-size:36px;font-weight:700;">&#10003;</div>
                    <h1 style="margin:0;text-align:center;color:#12b76a;font-size:28px;line-height:34px;">Payment Successful!</h1>
                    <p style="margin:8px 0 32px;text-align:center;color:#4b5563;font-size:13px;">Your Drivault storage package has been activated successfully.</p>

                    <p style="margin:0 0 14px;font-size:14px;">Hello <strong style="color:#12b76a;">%1$s</strong>,</p>
                    <p style="margin:0 0 22px;font-size:14px;color:#374151;">Thank you for your payment. Below are your transaction details.</p>

                    <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:separate;border-spacing:0;border:1px solid #dfe3e8;border-radius:7px;overflow:hidden;font-size:14px;margin:0 0 22px;">
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Plan</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;">%2$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Storage</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%3$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Billing Cycle</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%4$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Amount Paid</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#12b76a;font-weight:700;">Rs %5$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Payment ID</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%6$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;font-weight:600;">Invoice No</td>
                            <td style="padding:14px 16px;text-align:right;">%7$s</td>
                        </tr>
                    </table>

                    <div style="border:1px solid #dce5df;background:#f2faf5;border-radius:6px;padding:16px 20px;margin:0 0 28px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:collapse;">
                            <tr>
                                <td style="width:40px;vertical-align:middle;">
                                    <div style="width:26px;height:32px;background:#7ee0a5;border-radius:3px;color:#ffffff;text-align:center;line-height:32px;font-size:10px;font-weight:700;">PDF</div>
                                </td>
                                <td style="vertical-align:middle;color:#374151;font-size:14px;">Your invoice PDF is attached with this email.</td>
                            </tr>
                        </table>
                    </div>

                    <p style="margin:0 0 4px;font-size:14px;">Thank you,</p>
                    <p style="margin:0 0 24px;color:#12b76a;font-size:15px;font-weight:700;">Drivault Team</p>

                    <div style="border-top:1px solid #d9dee5;padding-top:22px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:collapse;color:#4b5563;font-size:13px;">
                            <tr>
                                <td style="width:50%%;text-align:center;padding:0 10px;border-right:1px solid #d9dee5;">&#9993;&nbsp;&nbsp;%9$s</td>
                                <td style="width:50%%;text-align:center;padding:0 10px;">&#9711;&nbsp;&nbsp;%10$s</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>',
        htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($quota, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($billingCycle, ENT_QUOTES, 'UTF-8'),
        $amount,
        htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8'),
        $logoHtml,
        htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars((string) $websiteDisplay, ENT_QUOTES, 'UTF-8')
    );

    $mail->AltBody = sprintf(
        "Hello %s,\n\n"
        . "Thank you for your payment. Your Drivault storage package has been activated successfully.\n\n"
        . "Plan: %s\n"
        . "Storage: %s\n"
        . "Billing Cycle: %s\n"
        . "Amount Paid: Rs %s\n"
        . "Payment ID: %s\n"
        . "Invoice No: %s\n\n"
        . "Your invoice PDF is attached with this email.\n\n"
        . "Thank you,\nDrivault Team",
        $customerName,
        $planName,
        $quota,
        $billingCycle,
        $amount,
        $paymentId,
        $invoiceNumber
    );

    $mail->addStringAttachment(
        $invoicePdf,
        'Drivault-Invoice-' . $subscription['id'] . '.pdf',
        'base64',
        'application/pdf'
    );

    $mail->send();

    return true;
}


$orderId   = $_POST['razorpay_order_id'] ?? '';
$paymentId = $_POST['razorpay_payment_id'] ?? '';
$signature = $_POST['razorpay_signature'] ?? '';

$name  = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';


if (!$orderId || !$paymentId || !$signature) {

    echo json_encode([
        'success' => false,
        'message' => 'Missing payment details'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Razorpay Signature
|--------------------------------------------------------------------------
*/

try {

    $api->utility->verifyPaymentSignature([
        'razorpay_order_id'   => $orderId,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature'  => $signature
    ]);

} catch (SignatureVerificationError $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Payment verification failed'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Subscription
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT * FROM subscriptions
     WHERE razorpay_order_id = ?"
);

$stmt->bind_param("s", $orderId);
$stmt->execute();

$subscription = $stmt->get_result()->fetch_assoc();

if (!$subscription) {

    echo json_encode([
        'success' => false,
        'message' => 'Subscription not found'
    ]);
    exit;
}

$userId = $subscription['user_id'];
$planId = $subscription['plan_id'];

/*
|--------------------------------------------------------------------------
| Get Plan
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT * FROM plans WHERE id = ?"
);

$stmt->bind_param("i", $planId);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {

    echo json_encode([
        'success' => false,
        'message' => 'Plan not found'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Convert Quota
|--------------------------------------------------------------------------
*/

$quotaText = strtoupper(trim($plan['quota']));

if (strpos($quotaText, 'TB') !== false) {

    $planQuotaGB =
        (float) str_replace('TB', '', $quotaText) * 1024;

} else {

    $planQuotaGB =
        (float) str_replace('GB', '', $quotaText);
}

/*
|--------------------------------------------------------------------------
| Add Storage To Nextcloud
|--------------------------------------------------------------------------
*/
// Get user phone
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$username = $phone ?: ($user['phone'] ?? '');

if (!$username) {
    echo json_encode([
        'success' => false,
        'message' => 'User phone number not found'
    ]);
    exit;
}

$purchasedGB = $planQuotaGB;

$url = "https://login.drivault.com/ocs/v1.php/cloud/users/$username";

// Get current quota
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => "admin:kuRsef-gobno8-gankux",
    CURLOPT_HTTPHEADER => [
        "OCS-APIRequest: true"
    ]
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode([
        'success' => false,
        'message' => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

$xml = simplexml_load_string($response);

if (!$xml || !isset($xml->data->quota->quota)) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to read current storage quota'
    ]);
    exit;
}

$currentQuotaBytes = (int)$xml->data->quota->quota;

$currentGB = round(
    $currentQuotaBytes / 1024 / 1024 / 1024
);

// Add purchased quota
$newGB = $currentGB + $purchasedGB;

$newQuota = $newGB . "GB";

// Update Nextcloud quota
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => "admin:kuRsef-gobno8-gankux",
    CURLOPT_POSTFIELDS => http_build_query([
        "key" => "quota",
        "value" => $newQuota
    ]),
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_HTTPHEADER => [
        "OCS-APIRequest: true"
    ]
]);

$updateResponse = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode([
        'success' => false,
        'message' => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

$quotaResult = [
    'username' => $username,
    'old_quota' => $currentGB,
    'added_quota' => $purchasedGB,
    'new_quota' => $newGB
];
/*
|--------------------------------------------------------------------------
| Update Subscription
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "UPDATE subscriptions
     SET
        status = 'active',
        razorpay_payment_id = ?
     WHERE id = ?"
);

$stmt->bind_param(
    "si",
    $paymentId,
    $subscription['id']
);

$stmt->execute();

/*
|--------------------------------------------------------------------------
| Save Payment
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "INSERT INTO payments
    (
        user_id,
        subscription_id,
        razorpay_order_id,
        razorpay_payment_id,
        amount,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, ?, 'success'
    )"
);

$stmt->bind_param(
    "iissd",
    $userId,
    $subscription['id'],
    $orderId,
    $paymentId,
    $subscription['amount']
);

$stmt->execute();

/*
|--------------------------------------------------------------------------
| Send Payment Email With Invoice
|--------------------------------------------------------------------------
*/

$emailSent = false;
$subscriptionForEmail = $subscription;
$subscriptionForEmail['status'] = 'active';
$subscriptionForEmail['razorpay_payment_id'] = $paymentId;
$subscriptionForEmail['plan_name'] = $plan['name'] ?? '';
$subscriptionForEmail['quota'] = $plan['quota'] ?? '';

try {
    $emailSent = sendPaymentSuccessEmail($subscriptionForEmail, $plan, $mailConfig);
} catch (MailerException $exception) {
    error_log('[payment-success] Invoice email failed: ' . $exception->getMessage());
} catch (Throwable $exception) {
    error_log('[payment-success] Invoice email error: ' . $exception->getMessage());
}

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Payment successful. Storage upgraded.',
    'payment_id' => $paymentId,
    'order_id' => $orderId,
    'subscription_id' => $subscription['id'],
    'email_sent' => $emailSent,
    'plan_id' => $planId,
    'quota' => $quotaResult
]);
