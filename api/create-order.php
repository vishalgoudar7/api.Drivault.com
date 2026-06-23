<?php

require '../config/db.php';
require '../vendor/autoload.php';

use Razorpay\Api\Api;

$config = require '../config/razorpay.php';

$api = new Api(
    $config['key_id'],
    $config['key_secret']
);
session_start();

$user_id = $_POST['user_id'] ?? 0;
$plan_id = $_POST['plan_id'] ?? 0;
$billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
$name  = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';


if (!$user_id || !$plan_id) {
    die(json_encode([
        'success' => false,
        'message' => 'Missing user_id or plan_id'
    ]));
}

/*
|--------------------------------------------------------------------------
| Get Plan
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT * FROM plans WHERE id = ?"
);

$stmt->bind_param("i", $plan_id);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    die(json_encode([
        'success' => false,
        'message' => 'Plan not found'
    ]));
}

/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
*/

// $amount = $billing_cycle === 'yearly'
//     ? $plan['yearly_price']
//     : $plan['monthly_price'];

// $amountPaise = $amount * 100;

$price = $billing_cycle === 'yearly'
    ? $plan['yearly_price']
    : $plan['monthly_price'];

$gstPercent = 18; // or $plan['gst_percent']

$gst = round(
    $price * ($gstPercent / 100),
    2
);

$totalAmount = $price + $gst;

$amountPaise = round($totalAmount * 100);

/*
|--------------------------------------------------------------------------
| Create Razorpay Order
|--------------------------------------------------------------------------
*/

$order = $api->order->create([
    'receipt' => 'DRV_' . time(),
    'amount' => $amountPaise,
    'currency' => 'INR'
]);

$orderId = $order['id'];

/*
|--------------------------------------------------------------------------
| Save Pending Subscription
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "INSERT INTO subscriptions
    (
        user_id,
        plan_id,
        billing_cycle,
        amount,
        razorpay_order_id,
        customer_name,
        customer_email,
        customer_phone,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
    )"
);

$stmt->bind_param(
    "iisdssss",
    $user_id,
    $plan_id,
    $billing_cycle,
    $totalAmount,
    $orderId,
    $name,
    $email,
    $phone
);

$stmt->execute();

$subscriptionId = $conn->insert_id;

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'subscription_id' => $subscriptionId,
    'order_id' => $orderId,
    'amount' => $amountPaise,
    'key' => $config['key_id']
]);