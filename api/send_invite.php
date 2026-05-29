<?php
declare(strict_types=1);

session_start();

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../vendor/autoload.php';

$appConfig = require __DIR__ . '/../config/app.php';
$mailConfig = require __DIR__ . '/../config/mail.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


function formatDatabaseErrorMessage(mysqli_sql_exception $exception): string
{
    $errorCode = (int) $exception->getCode();
    $errorMessage = strtolower($exception->getMessage());

    if ($errorCode === 1932 || str_contains($errorMessage, "doesn't exist in engine")) {
        return 'The users table is unavailable in MySQL. Repair or recreate the users table and try again.';
    }

    if ($errorCode === 1406 || str_contains($errorMessage, 'data too long')) {
        return 'One of the invitation values is too long to save. Shorten the entered details and try again.';
    }

    if ($errorCode === 1062 || str_contains($errorMessage, 'duplicate entry')) {
        return 'This email already belongs to another account record. Use a different email or update the existing user first.';
    }

    return 'Unable to save the invitation. Check the database configuration.';
}

function buildPublicUrl(string $baseUrl, string $path): string
{
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$sessionInviterEmail = trim((string) ($_SESSION['admin_email'] ?? ''));
$inviter_email = trim((string) ($_POST['inviter_email'] ?? $sessionInviterEmail));

if ($name === '' || $email === '' || $phone === '') {
    http_response_code(422);
    exit('Name, email, and mobile number are required.');
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    http_response_code(422);
    exit('Invalid email address.');
}

if (preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) !== 1) {
    http_response_code(422);
    exit('Invalid mobile number.');
}

$token = bin2hex(random_bytes(32));

$verificationLink = buildPublicUrl(
    (string) ($appConfig['base_url'] ?? 'http://103.174.148.208'),
    sprintf(
        'api/generate_otp.php?token=%s',
        urlencode($token)
    )
);

$smtpHost = (string) ($mailConfig['host'] ?? '');
$smtpPort = (int) ($mailConfig['port'] ?? 0);
$smtpEncryption = (string) ($mailConfig['encryption'] ?? '');
$smtpUsername = trim((string) ($mailConfig['username'] ?? ''));
$smtpPassword = trim((string) ($mailConfig['password'] ?? ''));
$smtpFromName = trim((string) ($mailConfig['from_name'] ?? 'Team Drivault'));
$sessionInviterName = trim((string) ($_SESSION['admin_name'] ?? ''));
$inviterName = trim((string) ($_POST['inviter_name'] ?? ($sessionInviterName !== '' ? $sessionInviterName : ($mailConfig['inviter_name'] ?? $smtpFromName))));
$displayName = $inviterName;

/*
Example:
$inviter_email = 7892660797@login.drivault.com

userid becomes:
7892660797
*/

$inviterUserId = explode(
    '@',
    $inviter_email
)[0];

if($inviterUserId !== ''){

    $curlHandle = curl_init();

    curl_setopt_array($curlHandle,[

        CURLOPT_URL =>
        'http://login.drivault.com/ocs/v1.php/cloud/users/' .
        rawurlencode($inviterUserId),

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPAUTH =>
        CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
        'admin:kuRsef-gobno8-gankux',

        CURLOPT_HTTPHEADER => [

            'OCS-APIRequest: true',
            'Accept: application/json'

        ]

    ]);

    $responseBody =
        curl_exec($curlHandle);

    curl_close($curlHandle);

    if($responseBody){

        $userData =
            json_decode(
                $responseBody,
                true
            );

        $displayName =
            trim(
                (string)(
                    $userData['ocs']
                    ['data']
                    ['displayname']
                    ?? $inviterName
                )
            );
    }
}

$inviterName = $displayName;
$websiteUrl = trim((string) ($mailConfig['website_url'] ?? 'https://drivault.example.com'));
$supportEmail = trim((string) ($mailConfig['support_email'] ?? 'support@drivault.example.com'));
$googlePlayLink = trim((string) ($mailConfig['google_play_link'] ?? 'https://play.google.com/store'));
$appStoreLink = trim((string) ($mailConfig['app_store_link'] ?? 'https://www.apple.com/app-store/'));
$brandIconPath = __DIR__ . '/../assets/Photos/icon-192.png';
$googlePlayImagePath = __DIR__ . '/../assets/Photos/googlePlay.png';
$appStoreImagePath = __DIR__ . '/../assets/Photos/Apple store.png';

if (
    $smtpHost === '' ||
    $smtpPort <= 0 ||
    $smtpUsername === '' ||
    $smtpPassword === '' ||
    strtolower($smtpUsername) === 'your_email@gmail.com' ||
    $smtpPassword === 'your_app_password'
) {
    http_response_code(500);
    exit('SMTP is not configured. Update config/mail.php with your real mail credentials.');
}

try {
    $transactionStarted = false;
    $conn->set_charset('utf8mb4');
    $conn->begin_transaction();
    $transactionStarted = true;

    // $statement = $conn->prepare(
    //     'INSERT INTO users (name, email, phone, invite_token, otp, otp_expiry, is_verified, role, inviter, invite_accepted)
    //      VALUES (?, ?, ?, ?, NULL, NULL, 0, ?, ?, ?)
    //      ON DUPLICATE KEY UPDATE
    //         name = VALUES(name),
    //         phone = VALUES(phone),
    //         invite_token = VALUES(invite_token),
    //         otp = NULL,
    //         otp_expiry = NULL,
    //         is_verified = 0,
    //         role = VALUES(role),
    //         inviter = VALUES(inviter),
    //         invite_accepted = VALUES(invite_accepted)'
    // );
    // $userRole = 'user';
    // $inviteAccepted = 'no';
    // $statement->bind_param('sssssss', $name, $email, $phone, $token, $userRole, $inviterName, $inviteAccepted);

$statement = $conn->prepare(
    'INSERT INTO users (
        name,
        email,
        phone,
        invite_token,
        otp,
        otp_expiry,
        is_verified,
        role,
        inviter,
        inviter_email,
        invite_accepted
    )
    VALUES (?, ?, ?, ?, NULL, NULL, 0, ?, ?, ?, ?)

    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        phone = VALUES(phone),
        invite_token = VALUES(invite_token),
        otp = NULL,
        otp_expiry = NULL,
        is_verified = 0,
        role = VALUES(role),
        inviter = VALUES(inviter),
        inviter_email = VALUES(inviter_email),
        invite_accepted = VALUES(invite_accepted)'
);

$userRole = 'user';
$inviteAccepted = 'no';

$statement->bind_param(
    'ssssssss',
    $name,
    $email,
    $phone,
    $token,
    $userRole,
    $inviterName,
    $inviter_email,
    $inviteAccepted
);

    $statement->execute();
    $statement->close();

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
    $mail->setFrom($smtpUsername, $smtpFromName);
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    if (is_file($brandIconPath)) {
        $mail->addEmbeddedImage($brandIconPath, 'drivault-brand-icon', 'icon-192.png');
    }
    if (is_file($googlePlayImagePath)) {
        $mail->addEmbeddedImage($googlePlayImagePath, 'google-play-badge', 'googlePlay.png');
    }
    if (is_file($appStoreImagePath)) {
        $mail->addEmbeddedImage($appStoreImagePath, 'app-store-badge', 'Apple store.png');
    }
   $mail->Subject = "Your Friend Invited You to Join Drivault";

$mail->Body = sprintf(

'<div style="font-family:Arial,sans-serif;padding:30px;background:#f5f7fa;">

<div style="max-width:650px;margin:auto;background:#fff;border-radius:12px;padding:35px;">

<div style="text-align:center;">

%9$s

<h2 style="color:#43E08B;">
Welcome to Drivault 
</h2>

</div>

<p>Hi <strong>%1$s</strong>,</p>

<p>

Your friend
<strong>%2$s</strong>
has invited you to join
<strong>Drivault</strong> 🎉

</p>

<p>

Store, access and share your files securely from anywhere.

</p>

<h3>Benefits:</h3>

<ul>

<li>☁ Secure cloud storage</li>

<li>📁 Access anywhere</li>

<li>🔒 Safe file sharing</li>

<li>🎁 Earn free storage rewards</li>

</ul>

<div style="
background:#eefbf4;
padding:15px;
margin-top:20px;
border-left:4px solid #43E08B;
">

<b>Your Account Details</b>

<p>

Username:
<strong>%6$s</strong>

</p>

<p>

Password:
Create during setup

</p>

</div>

<div style="
text-align:center;
margin-top:30px;
">

<a href="%3$s"

style="
background:#43E08B;
padding:15px 30px;
color:white;
text-decoration:none;
border-radius:8px;
font-weight:bold;
">

Accept Invitation

</a>

</div>

<p style="margin-top:30px;">

Please verify your mobile number using OTP.

</p>

<p>

If you were not expecting this invitation,
you can safely ignore this email.

</p>

<div style="
background:#f8fafc;
border:1px solid #e5e7eb;
border-radius:12px;
padding:20px;
margin-top:28px;
text-align:center;
">

<h3 style="margin:0 0 8px;color:#111827;">
Download Drivault App
</h3>

<p style="margin:0 0 18px;color:#475569;">
Install the Drivault mobile app to access your files anytime.
</p>

<table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto;border-collapse:separate;border-spacing:12px 0;">
<tr>
<td style="vertical-align:middle;">
<a href="%4$s" style="display:inline-block;text-decoration:none;">
%10$s
</a>
</td>
<td style="vertical-align:middle;">
<a href="%5$s" style="display:inline-block;text-decoration:none;">
%11$s
</a>
</td>
</tr>
</table>

</div>

<hr>

<p>

Thanks,<br>

Team Drivault

</p>

</div>

</div>',

htmlspecialchars($name,ENT_QUOTES,'UTF-8'),
htmlspecialchars($inviterName,ENT_QUOTES,'UTF-8'),
htmlspecialchars($verificationLink,ENT_QUOTES,'UTF-8'),
htmlspecialchars($googlePlayLink,ENT_QUOTES,'UTF-8'),
htmlspecialchars($appStoreLink,ENT_QUOTES,'UTF-8'),
htmlspecialchars($phone,ENT_QUOTES,'UTF-8'),
htmlspecialchars($websiteUrl,ENT_QUOTES,'UTF-8'),
htmlspecialchars($supportEmail,ENT_QUOTES,'UTF-8'),

is_file($brandIconPath)
? '<img src="cid:drivault-brand-icon" style="width:70px;">'
: '<strong>Drivault</strong>',

is_file($googlePlayImagePath)
? '<img src="cid:google-play-badge" style="width:180px;">'
: 'Google Play'
,

is_file($appStoreImagePath)
? '<img src="cid:app-store-badge" style="width:180px;">'
: 'Download on the App Store'

);
    $mail->AltBody = sprintf(
        "Hi %s,\n\n"
        . "Your friend %s has invited you to join Drivault.\n\n"
        . "Accept invitation:\n%s\n\n"
        . "Your login username: %s\n"
        . "Password: Create during setup\n\n"
        . "Download Drivault App:\n"
        . "Google Play: %s\n"
        . "App Store: %s\n\n"
        . "Thanks,\nTeam Drivault",
        $name,
        $inviterName,
        $verificationLink,
        $phone,
        $googlePlayLink,
        $appStoreLink
    );
    $mail->send();

    $conn->commit();
    exit('Invitation sent successfully.');
} catch (mysqli_sql_exception $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }

    error_log(
        sprintf(
            '[send_invite] Database error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    http_response_code(500);
    exit(formatDatabaseErrorMessage($exception));
} catch (MailerException $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }

    error_log(
        sprintf(
            '[send_invite] Mailer error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    http_response_code(500);
    exit('Unable to send invitation email: ' . $exception->getMessage());
} catch (Throwable $exception) {
    if (!empty($transactionStarted)) {
        $conn->rollback();
    }

    error_log(
        sprintf(
            '[send_invite] Unhandled error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    http_response_code(500);
    exit('Unable to process the invitation request.');
}
