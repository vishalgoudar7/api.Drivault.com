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

    $message = sprintf(
        '<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f6f8;padding:10px;color:#111827;">
            <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:4px;overflow:hidden;">
                <div style="padding:24px 42px 32px;">
                    <h1 style="margin:0 0 18px;color:#12b76a;font-size:26px;">Subscription Expired</h1>
                    <p style="margin:0 0 14px;font-size:14px;">Hello <strong style="color:#12b76a;">%1$s</strong>,</p>
                    <p style="margin:0 0 18px;font-size:14px;line-height:22px;color:#374151;">
                        Your subscription has expired.<br>
                        Your storage quota has been reduced to 1GB.<br>
                        Your files remain available.<br>
                        Renew your subscription to restore your previous storage.
                    </p>
                    <div style="text-align:center;margin:26px 0 10px;">
                        <a href="%2$s" style="display:inline-block;background:#12b76a;color:#ffffff;text-decoration:none;border-radius:7px;padding:13px 24px;font-size:14px;font-weight:700;">Renew Subscription</a>
                    </div>
                </div>
            </div>
        </div>',
        htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($renewUrl, ENT_QUOTES, 'UTF-8')
    );

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
