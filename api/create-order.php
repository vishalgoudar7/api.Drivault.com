<?php

require '../config/db.php';
require '../vendor/autoload.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

$config = require '../config/razorpay.php';

$api = new Api(
    $config['key_id'],
    $config['key_secret']
);
session_start();

$user_id = trim((string) ($_POST['user_id'] ?? ''));
$plan_id = (int) ($_POST['plan_id'] ?? 0);
$billing_cycle = strtolower(trim((string) ($_POST['billing_cycle'] ?? 'monthly')));
// ===== START RENEWAL MODE UPDATE =====
$renewalMode = $_POST['renewal_mode'] ?? 'manual';
// ===== END RENEWAL MODE UPDATE =====
$mode = $_POST['mode'] ?? 'new';
$subscriptionId = (int)($_POST['subscription_id'] ?? 0);
$name  = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = trim((string) ($_POST['phone'] ?? ''));

if ($phone === '' || $phone === '-') {
    $phone = $user_id;
}


if ($user_id === '' || !$plan_id) {
    die(json_encode([
        'success' => false,
        'message' => 'Missing user_id or plan_id'
    ]));
}

if (!in_array($billing_cycle, ['monthly', 'yearly'], true)) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid billing cycle'
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

// ===== START MANUAL QUOTA UPDATE =====
$freeQuota = $_SESSION['free_quota'] ?? 0;
$currentQuota = $_SESSION['current_quota'] ?? 0;

$planQuota = (int) filter_var(
    $plan['quota'],
    FILTER_SANITIZE_NUMBER_INT
);

$storageQuota = (int)$planQuota;

$totalQuota = $freeQuota + $storageQuota;
// ===== END MANUAL QUOTA UPDATE =====
/*
|--------------------------------------------------------------------------
| Plan Details
|--------------------------------------------------------------------------
*/

$planName        = $plan['name'];
$storageQuota    = $plan['quota'];

$monthlyPrice    = (float)$plan['monthly_price'];
$yearlyPrice     = (float)$plan['yearly_price'];

$discountPercent = (int)$plan['discount_percent'];
$saveAmount      = (float)$plan['save_amount'];

$originalPrice = ($billing_cycle === 'yearly')
    ? ($monthlyPrice * 12)
    : $monthlyPrice;

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

$totalAmount = ceil($price + $gst);

$amountPaise = $totalAmount * 100;

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

$orderId = (string) $order['id'];

if ($mode === 'renew') {

    echo json_encode([
        'success' => true,
        'subscription_id' => $subscriptionId,
        'order_id' => $orderId,
        'amount' => $amountPaise,
        'key' => $config['key_id']
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Save Pending Subscription
|--------------------------------------------------------------------------
*/

$paymentMethod = "Razorpay";
$paymentStatus = "Pending";
$subscriptionStatus = "Pending";
$paymentType = "upgrade";

// ===== START RENEWAL MODE UPDATE =====
$stmt = $conn->prepare("
INSERT INTO subscriptions
(
    user_id,
    plan_id,
    plan_name,
    storage_quota,
    billing_cycle,
    renewal_mode,
    paid_amount,
    razorpay_order_id,

    free_quota,
    current_quota,
    total_quota,

    drivault_display_name,
    drivault_email,
    drivault_phone,

    monthly_price,
    yearly_price,
    original_price,
    discount_percent,
    save_amount,

    payment_method,
    payment_type,
    payment_status,
    status
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)
");
// ===== END RENEWAL MODE UPDATE =====

$stmt->bind_param(
    "sissssdsiiisssdddidssss",

    $user_id,
    $plan_id,
    $planName,
    $storageQuota,
    $billing_cycle,
    // ===== START RENEWAL MODE UPDATE =====
    $renewalMode,
    // ===== END RENEWAL MODE UPDATE =====
    $totalAmount,
    $orderId,

    // ===== START MANUAL QUOTA UPDATE =====
    $freeQuota,
    $currentQuota,
    $totalQuota,
    // ===== END MANUAL QUOTA UPDATE =====

    $name,
    $email,
    $phone,

    $monthlyPrice,
    $yearlyPrice,
    $originalPrice,
    $discountPercent,
    $saveAmount,

    $paymentMethod,
    $paymentType,
    $paymentStatus,
    $subscriptionStatus
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save subscription: ' . $stmt->error
    ]);
    exit;
}

$subscriptionId = $conn->insert_id;

if ($subscriptionId <= 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Subscription was not saved'
    ]);
    exit;
}

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
