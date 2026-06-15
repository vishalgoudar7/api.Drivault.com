<?php

require '../config/db.php';
require '../vendor/autoload.php';
// require '../includes/drivault.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header('Content-Type: application/json');

$config = require '../config/razorpay.php';

$api = new Api(
    $config['key_id'],
    $config['key_secret']
);


$orderId   = $_POST['razorpay_order_id'] ?? '';
$paymentId = $_POST['razorpay_payment_id'] ?? '';
$signature = $_POST['razorpay_signature'] ?? '';

$name  = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';


if (!$orderId || !$paymentId || !$signature) {

    echo json_encode([
        'success' => false,
        'message' => 'Missing payment details'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify Razorpay Signature
|--------------------------------------------------------------------------
*/

try {

    $api->utility->verifyPaymentSignature([
        'razorpay_order_id'   => $orderId,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature'  => $signature
    ]);

} catch (SignatureVerificationError $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Payment verification failed'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Subscription
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT * FROM subscriptions
     WHERE razorpay_order_id = ?"
);

$stmt->bind_param("s", $orderId);
$stmt->execute();

$subscription = $stmt->get_result()->fetch_assoc();

if (!$subscription) {

    echo json_encode([
        'success' => false,
        'message' => 'Subscription not found'
    ]);
    exit;
}

$userId = $subscription['user_id'];
$planId = $subscription['plan_id'];

/*
|--------------------------------------------------------------------------
| Get Plan
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT * FROM plans WHERE id = ?"
);

$stmt->bind_param("i", $planId);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {

    echo json_encode([
        'success' => false,
        'message' => 'Plan not found'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Convert Quota
|--------------------------------------------------------------------------
*/

$quotaText = strtoupper(trim($plan['quota']));

if (strpos($quotaText, 'TB') !== false) {

    $planQuotaGB =
        (float) str_replace('TB', '', $quotaText) * 1024;

} else {

    $planQuotaGB =
        (float) str_replace('GB', '', $quotaText);
}

/*
|--------------------------------------------------------------------------
| Add Storage To Nextcloud
|--------------------------------------------------------------------------
*/
// Get user phone
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
// $user = $stmt->get_result()->fetch_assoc();

$user = $stmt->get_result()->fetch_assoc();

$username = $phone; // or $email or $name

$purchasedGB = $planQuotaGB;

$url = "https://login.drivault.com/ocs/v1.php/cloud/users/$username";

// Get current quota
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => "admin:kuRsef-gobno8-gankux",
    CURLOPT_HTTPHEADER => [
        "OCS-APIRequest: true"
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$xml = simplexml_load_string($response);

$currentQuotaBytes = (int)$xml->data->quota->quota;

$currentGB = round(
    $currentQuotaBytes / 1024 / 1024 / 1024
);

// Add purchased quota
$newGB = $currentGB + $purchasedGB;

$newQuota = $newGB . "GB";

// Update Nextcloud quota
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => "admin:kuRsef-gobno8-gankux",
    CURLOPT_POSTFIELDS => http_build_query([
        "key" => "quota",
        "value" => $newQuota
    ]),
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_HTTPHEADER => [
        "OCS-APIRequest: true"
    ]
]);

$updateResponse = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode([
        'success' => false,
        'message' => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

echo json_encode([
    'debug' => true,
    'username' => $username,
    'currentGB' => $currentGB,
    'purchasedGB' => $purchasedGB,
    'newQuota' => $newQuota,
    'nextcloud_response' => $updateResponse
]);
exit;
$quotaResult = [
    'username' => $username,
    'old_quota' => $currentGB,
    'added_quota' => $purchasedGB,
    'new_quota' => $newGB
];
/*
|--------------------------------------------------------------------------
| Update Subscription
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "UPDATE subscriptions
     SET
        status = 'active',
        razorpay_payment_id = ?
     WHERE id = ?"
);

$stmt->bind_param(
    "si",
    $paymentId,
    $subscription['id']
);

$stmt->execute();

/*
|--------------------------------------------------------------------------
| Save Payment
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "INSERT INTO payments
    (
        user_id,
        subscription_id,
        razorpay_order_id,
        razorpay_payment_id,
        amount,
        status
    )
    VALUES
    (
        ?, ?, ?, ?, ?, 'success'
    )"
);

$stmt->bind_param(
    "iissd",
    $userId,
    $subscription['id'],
    $orderId,
    $paymentId,
    $subscription['amount']
);

$stmt->execute();

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Payment successful. Storage upgraded.',
    'payment_id' => $paymentId,
    'order_id' => $orderId,
    'quota' => $quotaResult
]);