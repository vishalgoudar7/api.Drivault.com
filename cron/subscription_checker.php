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

    $username = $row['user_id'];

    echo "<br><b>Disabling User:</b> " . htmlspecialchars($username) . "<br>";

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://login.drivault.com/ocs/v1.php/cloud/users/" . urlencode($username) . "/disable",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_USERPWD => $drivaultConfig['username'] . ":" . $drivaultConfig['password'],
        CURLOPT_HTTPHEADER => [
            "OCS-APIRequest: true",
            "Accept: application/json"
        ]
    ]);

    $response = curl_exec($curl);

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (curl_errno($curl)) {
        echo "<b>cURL Error:</b> " . curl_error($curl) . "<br>";
    }

    curl_close($curl);

    echo "<b>HTTP Code:</b> " . $httpCode . "<br>";
    echo "<pre>$response</pre>";

    if ($httpCode == 200) {

        mysqli_query($conn,"
            UPDATE subscriptions
            SET
                status='expired',
                disabled_at=NOW()
            WHERE id={$row['id']}
        ");

        echo "<span style='color:red;font-weight:bold'>
        ✓ User Disabled On Main Server
        </span><br>";

        if (sendAccountDisabledEmail($row)) {
            echo "<span style='color:green;font-weight:bold'>
            Account Disabled Email Sent
            </span><br>";
        } else {
            echo "<span style='color:red'>
            Account Disabled Email Failed
            </span><br>";
        }

    } else {

        echo "<span style='color:red'>
        Failed to Disable User
        </span><br>";
    }
}

}
echo "<h3>Subscription Checker Completed</h3>";
