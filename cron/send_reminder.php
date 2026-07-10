<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function sendMail(string $to, string $subject, string $message): bool
{
    $mailConfig = require __DIR__ . '/../config/mail.php';

    $smtpHost = (string) ($mailConfig['host'] ?? '');
    $smtpPort = (int) ($mailConfig['port'] ?? 0);
    $smtpEncryption = (string) ($mailConfig['encryption'] ?? '');
    $smtpUsername = trim((string) ($mailConfig['username'] ?? ''));
    $smtpPassword = trim((string) ($mailConfig['password'] ?? ''));
    $smtpFromName = trim((string) ($mailConfig['from_name'] ?? 'Drivault'));
    $fromAddress = trim((string) ($mailConfig['from_address'] ?? $smtpUsername));

    if (
        $smtpHost === '' ||
        $smtpPort <= 0 ||
        $smtpUsername === '' ||
        $smtpPassword === ''
    ) {
        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
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
        $mail->setFrom(
            $fromAddress !== '' ? $fromAddress : $smtpUsername,
            $smtpFromName
        );
        $mail->addAddress($to);

        $logoPath = realpath(__DIR__ . '/../assets/Photos/icon-192.png');

        if ($logoPath) {
            $mail->addEmbeddedImage($logoPath, 'drivault-logo', 'drivault-logo.png');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message)));

        return $mail->send();
    } catch (Exception) {
        error_log($mail->ErrorInfo);

        return false;
    }
}

function sendReminder(array $row, int $days): bool
{
    $to = (string) ($row['drivault_email'] ?? '');
    $customerName = trim((string) ($row['drivault_display_name'] ?? 'Customer'));
    $planName = trim((string) ($row['plan_name'] ?? 'Drivault Plan'));
    $expiryDate = trim((string) ($row['expiry_date'] ?? ''));
    $supportEmail = 'support@drivault.com';
    $websiteDisplay = 'api.drivault.com';
    $renewUrl = 'https://api.drivault.com/pages/pricing.php';

    $subject = "Your Drivault Subscription Expires in {$days} Day(s)";

    $message = sprintf(
        '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;padding:10px;color:#111827;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:4px;overflow:hidden;">
                <div style="text-align:center;padding:26px 20px 22px;border-bottom:3px solid #12b76a;">
                    <img src="cid:drivault-logo" width="38" height="38" alt="Drivault" style="display:inline-block;border:0;vertical-align:middle;margin-right:8px;">
                    <span style="display:inline-block;vertical-align:middle;font-size:34px;font-weight:700;color:#111827;line-height:38px;">Drivault</span>
                </div>

                <div style="padding:24px 42px 32px;">
                    <div style="width:66px;height:66px;border-radius:50%%;background:#fff7ed;margin:0 auto 14px;text-align:center;line-height:66px;color:#f59e0b;font-size:34px;font-weight:700;">!</div>
                    <h1 style="margin:0;text-align:center;color:#f59e0b;font-size:28px;line-height:34px;">Subscription Reminder</h1>
                    <p style="margin:8px 0 32px;text-align:center;color:#4b5563;font-size:13px;">Your Drivault storage plan is close to expiry.</p>

                    <p style="margin:0 0 14px;font-size:14px;">Hello <strong style="color:#12b76a;">%1$s</strong>,</p>
                    <p style="margin:0 0 22px;font-size:14px;color:#374151;line-height:22px;">This is a friendly reminder that your subscription will expire in <strong style="color:#dc2626;">%2$d day(s)</strong>. Please renew before expiry to continue using your cloud storage without interruption.</p>

                    <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:separate;border-spacing:0;border:1px solid #dfe3e8;border-radius:7px;overflow:hidden;font-size:14px;margin:0 0 22px;">
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Plan</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;">%3$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Email</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%4$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Expiry Date</td>
                            <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;">%5$s</td>
                        </tr>
                        <tr>
                            <td style="padding:14px 16px;font-weight:600;">Days Remaining</td>
                            <td style="padding:14px 16px;text-align:right;color:#dc2626;font-weight:700;">%2$d Day(s)</td>
                        </tr>
                    </table>

                    <div style="border:1px solid #f5d58a;background:#fffbeb;border-radius:6px;padding:16px 20px;margin:0 0 26px;color:#92400e;font-size:14px;line-height:22px;">
                        <strong>Important Notice</strong><br>
                        If your subscription expires, your Drivault account may be temporarily disabled.
                    </div>

                    <div style="text-align:center;margin:0 0 30px;">
                        <a href="%6$s" style="display:inline-block;background:#12b76a;color:#ffffff;text-decoration:none;border-radius:7px;padding:14px 28px;font-size:14px;font-weight:700;">Renew Subscription</a>
                    </div>

                    <p style="margin:0 0 4px;font-size:14px;">Thank you,</p>
                    <p style="margin:0 0 24px;color:#12b76a;font-size:15px;font-weight:700;">Drivault Team</p>

                    <div style="border-top:1px solid #d9dee5;padding-top:22px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%%;border-collapse:collapse;color:#4b5563;font-size:13px;">
                            <tr>
                                <td style="width:50%%;text-align:center;padding:0 10px;border-right:1px solid #d9dee5;">&#9993;&nbsp;&nbsp;%7$s</td>
                                <td style="width:50%%;text-align:center;padding:0 10px;">&#9711;&nbsp;&nbsp;%8$s</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>',
        htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
        $days,
        htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($to, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($expiryDate, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($renewUrl, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($websiteDisplay, ENT_QUOTES, 'UTF-8')
    );

    return sendMail($to, $subject, $message);
}
