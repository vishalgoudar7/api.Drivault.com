<?php

// Database
require_once __DIR__ . '/../config/db.php';

// Drivault API
require_once __DIR__ . '/../api/includes/drivault_api.php';
$drivaultConfig = require __DIR__ . '/../config/drivault.php';

// Reminder Mail
require_once __DIR__ . '/send_reminder.php';

date_default_timezone_set('Asia/Kolkata');

echo "<h2>Subscription Checker</h2>";

function checkerDebug(string $message): void
{
    error_log('[subscription_checker] ' . $message);
    echo htmlspecialchars($message) . "<br>";
}

function getSubscriptionUsername(array $row): string
{
    // Use the same Drivault identifier style as checkout/payment: skip empty or known invalid values.
    $candidates = [
        trim((string) ($row['drivault_phone'] ?? '')),
        trim((string) ($row['user_id'] ?? '')),
        trim((string) ($row['drivault_email'] ?? '')),
        trim((string) ($row['drivault_display_name'] ?? '')),
    ];

    $invalidValues = ['', '-', '0', '2147483647'];

    foreach ($candidates as $candidate) {
        if (!in_array($candidate, $invalidValues, true)) {
            return $candidate;
        }
    }

    return '';
}

function detectQuotaBytes(string $response): ?int
{
    // First try JSON because the OCS API normally returns JSON when requested.
    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $quota = $data['ocs']['data']['quota'] ?? [];

        if (isset($quota['total']) && is_numeric($quota['total'])) {
            return (int) $quota['total'];
        }

        if (isset($quota['quota']) && is_numeric($quota['quota'])) {
            return (int) $quota['quota'];
        }
    } else {
        checkerDebug('JSON parse error: ' . json_last_error_msg());
    }

    // If JSON fails or quota is missing, fall back to XML for older OCS responses.
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response);

    if ($xml === false) {
        $xmlErrors = array_map(
            static fn($error) => trim($error->message),
            libxml_get_errors()
        );
        libxml_clear_errors();
        checkerDebug('XML parse error: ' . implode(' | ', $xmlErrors));

        return null;
    }

    if (isset($xml->data->quota->total) && is_numeric((string) $xml->data->quota->total)) {
        return (int) $xml->data->quota->total;
    }

    if (isset($xml->data->quota->quota) && is_numeric((string) $xml->data->quota->quota)) {
        return (int) $xml->data->quota->quota;
    }

    checkerDebug('Missing quota in API response.');

    return null;
}

function sendExpiredQuotaReducedEmail(array $row): bool
{
    // Expired users keep account access, so this email explains quota reduction only.
    $to = (string) ($row['drivault_email'] ?? '');
    $customerName = trim((string) ($row['drivault_display_name'] ?? 'Customer'));
    $renewUrl = buildRenewButtonUrl((int) $row['id']);
    $mailConfig = require __DIR__ . '/../config/mail.php';

    $previousQuota = (int) ($row['previous_quota'] ?? 0);

    if ($previousQuota <= 0) {
        $previousQuota = (int) ($row['total_quota'] ?? 0);
    }

    $customerNameHtml = htmlspecialchars($customerName !== '' ? $customerName : 'Customer', ENT_QUOTES, 'UTF-8');
    $renewUrlHtml = htmlspecialchars($renewUrl, ENT_QUOTES, 'UTF-8');
    $currentStorageHtml = '1GB';
    $previousStorageHtml = $previousQuota > 0
        ? htmlspecialchars($previousQuota . 'GB', ENT_QUOTES, 'UTF-8')
        : htmlspecialchars((string) ($row['storage_quota'] ?? 'your previous storage'), ENT_QUOTES, 'UTF-8');
    $supportEmailHtml = htmlspecialchars((string) ($mailConfig['support_email'] ?? 'support@drivault.com'), ENT_QUOTES, 'UTF-8');
    $websiteDisplayHtml = htmlspecialchars(getMailWebsiteDisplay(), ENT_QUOTES, 'UTF-8');

    $message = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;padding:10px;color:#111827;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:4px;overflow:hidden;">
        <div style="text-align:center;padding:26px 20px 22px;border-bottom:3px solid #12b76a;">
            <img src="cid:drivault-logo" width="38" height="38" alt="Drivault" style="display:inline-block;border:0;vertical-align:middle;margin-right:8px;">
            <span style="display:inline-block;vertical-align:middle;font-size:34px;font-weight:700;color:#111827;line-height:38px;">Drivault</span>
            <div style="margin-top:8px;color:#4b5563;font-size:13px;line-height:18px;">Secure Cloud Storage</div>
        </div>

        <div style="padding:24px 42px 32px;">
            <div style="width:66px;height:66px;border-radius:50%;background:#fff7ed;margin:0 auto 14px;text-align:center;line-height:66px;color:#f59e0b;font-size:34px;font-weight:700;">!</div>
            <h1 style="margin:0;text-align:center;color:#f59e0b;font-size:28px;line-height:34px;">Subscription Expired</h1>
            <p style="margin:8px 0 32px;text-align:center;color:#4b5563;font-size:13px;line-height:20px;">Your Drivault storage plan has expired, but your files remain safe.</p>

            <p style="margin:0 0 14px;font-size:14px;line-height:22px;">Hello <strong style="color:#12b76a;">{$customerNameHtml}</strong>,</p>
            <p style="margin:0 0 22px;font-size:14px;color:#374151;line-height:22px;">Your Drivault subscription has expired. Your account remains accessible, but your storage quota has been reduced and premium features are temporarily limited until renewal.</p>

            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #dfe3e8;border-radius:7px;overflow:hidden;font-size:14px;margin:0 0 22px;">
                <tr>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Current Storage</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#dc2626;font-weight:700;">{$currentStorageHtml}</td>
                </tr>
                <tr>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Previous Storage</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;">{$previousStorageHtml}</td>
                </tr>
                <tr>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Files</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#12b76a;font-weight:700;">Safe &amp; Accessible</td>
                </tr>
                <tr>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;font-weight:600;">Uploads</td>
                    <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;text-align:right;color:#dc2626;font-weight:700;">Temporarily Restricted</td>
                </tr>
                <tr>
                    <td style="padding:14px 16px;font-weight:600;">Premium Features</td>
                    <td style="padding:14px 16px;text-align:right;color:#dc2626;font-weight:700;">Unavailable</td>
                </tr>
            </table>

            <div style="border:1px solid #f5d58a;background:#fffbeb;border-radius:6px;padding:16px 20px;margin:0 0 24px;color:#92400e;font-size:14px;line-height:22px;">
                <strong>Important Notice</strong><br>
                Your files remain safe and accessible. Renew your subscription to restore your previous storage quota of <strong>{$previousStorageHtml}</strong>, enable uploads again, and continue using all premium features without interruption.
            </div>

            <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;margin:0 0 24px;">
                <tr>
                    <td style="padding:0 6px 12px 0;width:50%;">
                        <div style="border:1px solid #dce5df;background:#f2faf5;border-radius:6px;padding:12px 14px;color:#374151;font-size:13px;line-height:20px;"><span style="color:#12b76a;font-weight:700;">&#10003;</span>&nbsp; Restore previous storage</div>
                    </td>
                    <td style="padding:0 0 12px 6px;width:50%;">
                        <div style="border:1px solid #dce5df;background:#f2faf5;border-radius:6px;padding:12px 14px;color:#374151;font-size:13px;line-height:20px;"><span style="color:#12b76a;font-weight:700;">&#10003;</span>&nbsp; No data loss</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 6px 0 0;width:50%;">
                        <div style="border:1px solid #dce5df;background:#f2faf5;border-radius:6px;padding:12px 14px;color:#374151;font-size:13px;line-height:20px;"><span style="color:#12b76a;font-weight:700;">&#10003;</span>&nbsp; Instant reactivation</div>
                    </td>
                    <td style="padding:0 0 0 6px;width:50%;">
                        <div style="border:1px solid #dce5df;background:#f2faf5;border-radius:6px;padding:12px 14px;color:#374151;font-size:13px;line-height:20px;"><span style="color:#12b76a;font-weight:700;">&#10003;</span>&nbsp; Secure payment</div>
                    </td>
                </tr>
            </table>

            <div style="text-align:center;margin:0 0 28px;">
                <a href="{$renewUrlHtml}" style="display:inline-block;background:#12b76a;color:#ffffff;text-decoration:none;border-radius:7px;padding:14px 28px;font-size:14px;font-weight:700;">Renew Subscription</a>
            </div>

            <div style="border:1px solid #dce5df;background:#f2faf5;border-radius:6px;padding:16px 20px;margin:0 0 26px;color:#374151;font-size:14px;line-height:22px;">
                Your account will be reactivated immediately after successful payment. Your previous storage quota will be restored automatically.
            </div>

            <p style="margin:0 0 4px;font-size:14px;">Thank you,</p>
            <p style="margin:0 0 24px;color:#12b76a;font-size:15px;font-weight:700;">Drivault Team</p>

            <div style="border-top:1px solid #d9dee5;padding-top:22px;">
                <table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;color:#4b5563;font-size:13px;">
                    <tr>
                        <td style="width:50%;text-align:center;padding:0 10px;border-right:1px solid #d9dee5;">&#9993;&nbsp;&nbsp;<a href="mailto:{$supportEmailHtml}" style="color:#4b5563;text-decoration:none;">{$supportEmailHtml}</a></td>
                        <td style="width:50%;text-align:center;padding:0 10px;">&#9711;&nbsp;&nbsp;{$websiteDisplayHtml}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
HTML;

    return sendMail($to, 'Your Drivault Subscription Has Expired', $message);
}

// Fetch all active subscriptions
$sql = "
SELECT *
FROM subscriptions
WHERE LOWER(status)='active'
AND payment_status='Success'
";

$result = mysqli_query($conn, $sql);

// Check SQL error
if (!$result) {
    die("SQL Error: " . mysqli_error($conn));
}

echo "<h3>Total Active Subscriptions : " . mysqli_num_rows($result) . "</h3>";
echo "<hr>";

while ($row = mysqli_fetch_assoc($result)) {

    $today  = new DateTime();
    $expiry = new DateTime($row['expiry_date']);

    // Calculate remaining days
    $daysLeft = (int)$today->diff($expiry)->format('%r%a');

    echo "<b>User :</b> " . htmlspecialchars($row['drivault_display_name']) . "<br>";
    echo "<b>Email :</b> " . htmlspecialchars($row['drivault_email']) . "<br>";
    echo "<b>Plan :</b> " . htmlspecialchars($row['plan_name']) . "<br>";
    echo "<b>Expiry :</b> " . htmlspecialchars($row['expiry_date']) . "<br>";
    echo "<b>Days Left :</b> " . $daysLeft . "<br>";

    // 7 Day Reminder
    if ($daysLeft == 7 && !$row['reminder_7_sent']) {

        sendReminder($row, 7);

        mysqli_query($conn, "
            UPDATE subscriptions
            SET reminder_7_sent = 1
            WHERE id = {$row['id']}
        ");

        echo "<span style='color:green;'>✓ 7 Day Reminder Sent</span><br>";
    }

    // 3 Day Reminder
    if ($daysLeft == 3 && !$row['reminder_3_sent']) {

        sendReminder($row, 3);

        mysqli_query($conn, "
            UPDATE subscriptions
            SET reminder_3_sent = 1
            WHERE id = {$row['id']}
        ");

        echo "<span style='color:orange;'>✓ 3 Day Reminder Sent</span><br>";
    }

    // 1 Day Reminder
    if ($daysLeft == 1 && !$row['reminder_1_sent']) {

        sendReminder($row, 1);

        mysqli_query($conn, "
            UPDATE subscriptions
            SET reminder_1_sent = 1
            WHERE id = {$row['id']}
        ");

        echo "<span style='color:red;'>✓ 1 Day Reminder Sent</span><br>";
    }

    // Subscription Expired
    if ($daysLeft <= 0 && strtolower($row['status']) == 'active') {

        $username = getSubscriptionUsername($row);

        if ($username === '') {
            checkerDebug('Missing username for subscription ID: ' . $row['id']);
            echo "<span style='color:red'>Missing Drivault username. Skipping expiry update.</span><br>";
            continue;
        }

        checkerDebug('username: ' . $username);
        echo "<br><b>Expiring User:</b> " . htmlspecialchars($username) . "<br>";

        $url = rtrim($drivaultConfig['endpoint'], '/')
            . '/'
            . rawurlencode($username);

    $curl = curl_init();



curl_setopt_array($curl, [

    CURLOPT_URL => $url,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_USERPWD =>
        $drivaultConfig['username']
        . ":"
        . $drivaultConfig['password'],

    CURLOPT_HTTPHEADER => [

        "OCS-APIRequest: true",

        "Accept: application/json"

    ]

]);

$response = curl_exec($curl);
$quotaHttpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {

    checkerDebug('Quota read cURL error: ' . curl_error($curl));

    curl_close($curl);

    continue;
}

curl_close($curl);
checkerDebug('HTTP status code: ' . $quotaHttpCode);
checkerDebug('API response: ' . (string) $response);

if ($quotaHttpCode != 200) {
    echo "<span style='color:red'>Unable to read current quota. HTTP " . $quotaHttpCode . "</span><br>";
    continue;
}

$currentQuota = detectQuotaBytes((string) $response);
checkerDebug('detected quota: ' . ($currentQuota === null ? 'missing' : (string) $currentQuota));

if ($currentQuota === null) {
    echo "<span style='color:red'>Missing quota in Drivault API response.</span><br>";
    continue;
}

// Store previous_quota only once; this preserves the real quota for renewal restore.
// $stmt = $conn->prepare("
//     UPDATE subscriptions
//     SET previous_quota=?
//     WHERE id=?
//     AND previous_quota IS NULL
// ");

// if (!$stmt) {
//     checkerDebug('SQL prepare error: ' . $conn->error);
//     continue;
// }

// $stmt->bind_param(

//     "ii",

//     $currentQuota,

//     $row['id']

// );

// if (!$stmt->execute()) {
//     checkerDebug('SQL execute error: ' . $stmt->error);
//     $stmt->close();
//     continue;
// }

// $affectedRows = $stmt->affected_rows;
// checkerDebug('SQL affected rows: ' . $affectedRows);
// $stmt->close();

// if ($affectedRows <= 0 && empty($row['previous_quota'])) {
//     echo "<span style='color:red'>Previous quota was not saved. Skipping quota reduction.</span><br>";
//     continue;
// }

// previous_quota is already saved during purchase/upgrade.
// Before reducing quota, make sure it exists.

// if (empty($row['previous_quota'])) {

//     echo "<span style='color:red'>
//     Previous quota not found.
//     </span><br>";

//     continue;
// }

// Restore user to their original free quota
// $freeQuota = (int)$row['free_quota'];

// $restoreQuota = $freeQuota . "GB";
// Expired users stay enabled; only their quota is reduced to 1GB.
// Save current total quota before reducing storage
$totalQuota = (int)$row['total_quota'];

$stmt = $conn->prepare("
    UPDATE subscriptions
    SET previous_quota = ?
    WHERE id = ?
");

$stmt->bind_param(
    "ii",
    $totalQuota,
    $row['id']
);

$stmt->execute();
$stmt->close();

$restoreQuota = "1GB";
$curl = curl_init();

curl_setopt_array($curl,[

    CURLOPT_URL=>$url,

    CURLOPT_RETURNTRANSFER=>true,

    CURLOPT_CUSTOMREQUEST=>"PUT",

    CURLOPT_POSTFIELDS => http_build_query([

    "key" => "quota",

    "value" => $restoreQuota

]),

    CURLOPT_USERPWD=>

        $drivaultConfig['username']
        .":"
        .$drivaultConfig['password'],

    CURLOPT_HTTPHEADER=>[

        "OCS-APIRequest: true",

        "Accept: application/json"

    ]

]);

$updateResponse = curl_exec($curl);

$httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
    checkerDebug('Quota update cURL error: ' . curl_error($curl));
    curl_close($curl);
    continue;
}

curl_close($curl);

checkerDebug('HTTP status code: ' . $httpCode);
checkerDebug('API response: ' . (string) $updateResponse);

echo "<b>HTTP Code:</b> " . $httpCode . "<br>";
echo "<pre>" . htmlspecialchars((string) $updateResponse) . "</pre>";

    if ($httpCode != 200) {
        echo "<span style='color:red'>Failed to reduce user quota to 1GB.</span><br>";
        continue;
    }

$stmt = $conn->prepare("
    UPDATE subscriptions
    SET status='expired'
    WHERE id=?
");

if (!$stmt) {
    checkerDebug('SQL prepare error: ' . $conn->error);
    continue;
}

$stmt->bind_param("i", $row['id']);

if (!$stmt->execute()) {
    checkerDebug('SQL execute error: ' . $stmt->error);
    $stmt->close();
    continue;
}

$stmt->close();

       echo "<span style='color:red;font-weight:bold'>
✓ User Quota Reduced To 1 GB
</span><br>";

        if (sendExpiredQuotaReducedEmail($row)) {
            echo "<span style='color:green;font-weight:bold'>
            Expiry Email Sent
            </span><br>";
        } else {
            echo "<span style='color:red'>
            Expiry Email Failed
            </span><br>";
        }
}

}
echo "<h3>Subscription Checker Completed</h3>";
