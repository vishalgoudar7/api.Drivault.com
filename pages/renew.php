<?php
session_start();

require '../config/db.php';

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

header(
    "Location: checkout.php?" .
    http_build_query([
        'plan_id'         => $subscription['plan_id'],
        'username'        => $subscription['user_id'],
        'billing_cycle'   => $subscription['billing_cycle'],
        'renewal'         => 1,
        'subscription_id' => $subscription['id']
    ])
);

exit;