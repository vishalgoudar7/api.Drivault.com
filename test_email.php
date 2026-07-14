<?php

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/cron/send_reminder.php';

$sql = "
SELECT *
FROM subscriptions
WHERE payment_status = 'Success'
AND LOWER(status) = 'active'
LIMIT 1
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Database Error: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) === 0) {
    die("No active subscription found.");
}

$row = mysqli_fetch_assoc($result);
$row['drivault_email'] = 'vishalgoudar05@gmail.com';

$today = new DateTime();
$expiry = new DateTime($row['expiry_date']);
$days = (int) $today->diff($expiry)->format('%r%a');

echo "<h2>Testing Reminder Email</h2>";
echo "<b>Name:</b> " . htmlspecialchars($row['drivault_display_name']) . "<br>";
echo "<b>Email:</b> " . htmlspecialchars($row['drivault_email']) . "<br>";
echo "<b>Plan:</b> " . htmlspecialchars($row['plan_name']) . "<br>";
echo "<b>Expiry Date:</b> " . htmlspecialchars($row['expiry_date']) . "<br>";
echo "<b>Days Left:</b> " . $days . "<br><br>";

if (sendReminder($row, $days)) {
    echo "<h2 style='color:green'>Email Sent Successfully</h2>";
} else {
    echo "<h2 style='color:red'>Email Failed</h2>";
}
