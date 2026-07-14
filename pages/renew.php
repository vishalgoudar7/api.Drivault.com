<?php
session_start();

require '../config/db.php';

function getRenewalUsername(array $subscription): string
{
    $candidates = [
        $subscription['drivault_phone'] ?? '',
        $subscription['drivault_email'] ?? '',
        $subscription['drivault_display_name'] ?? '',
        $subscription['user_id'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);

        if ($candidate !== '' && $candidate !== '-' && $candidate !== '0') {
            return $candidate;
        }
    }

    return '';
}

if (!isset($_GET['subscription_id'])) {
    die("Subscription not found.");
}

$subscriptionId = (int) $_GET['subscription_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM subscriptions
    WHERE id = ?
      AND payment_status = 'Success'
    LIMIT 1
");

$stmt->bind_param("i", $subscriptionId);
$stmt->execute();

$subscription = $stmt->get_result()->fetch_assoc();

if (!$subscription) {
    die("Invalid subscription.");
}

$username = getRenewalUsername($subscription);

if ($username === '') {
    die("Drivault user not found for this subscription.");
}

header(
    "Location: checkout.php?" .
    http_build_query([
        'plan_id'         => $subscription['plan_id'],
        'username'        => $username,
        'billing_cycle'   => $subscription['billing_cycle'],
        'mode'            => 'renew',
        'subscription_id' => $subscription['id']
    ])
);

exit;
