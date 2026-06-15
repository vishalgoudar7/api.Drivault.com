<?php
// Get user phone
$stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get plan quota
$stmt = $conn->prepare("SELECT quota FROM plans WHERE id = ?");
$stmt->bind_param("i", $planId);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

$quotaText = strtoupper(trim($plan['quota']));

if (strpos($quotaText, 'TB') !== false) {
    $planQuotaGB = (float)str_replace('TB', '', $quotaText) * 1024;
} else {
    $planQuotaGB = (float)str_replace('GB', '', $quotaText);
}
// $username = "9598979496";
// $purchasedGB = 100;
$username = $user['phone'];
$purchasedGB = $planQuotaGB;

$url = "https://login.drivault.com/ocs/v1.php/cloud/users/$username";

/*
|--------------------------------------------------------------------------
| Get User Details
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Current Quota in Bytes
|--------------------------------------------------------------------------
*/

$currentQuotaBytes = (int)$xml->data->quota->quota;

/*
|--------------------------------------------------------------------------
| Convert Bytes To GB
|--------------------------------------------------------------------------
*/

$currentGB = round(
    $currentQuotaBytes / 1024 / 1024 / 1024
);

/*
|--------------------------------------------------------------------------
| Add Purchased Storage
|--------------------------------------------------------------------------
*/

$newGB = $currentGB + $purchasedGB;

$newQuota = $newGB . "GB";

/*
|--------------------------------------------------------------------------
| Update Nextcloud Quota
|--------------------------------------------------------------------------
*/

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
curl_close($ch);

$quotaInfo = [
    'username'    => $username,
    'old_quota'   => $currentGB,
    'added_quota' => $purchasedGB,
    'new_quota'   => $newGB
];

// Update subscription status
$stmt = $conn->prepare("
    UPDATE subscriptions
    SET status='active',
        razorpay_payment_id=?
    WHERE id=?
");

$stmt->bind_param(
    "si",
    $paymentId,
    $subscription['id']
);

$stmt->execute();


// Insert payment record
$stmt = $conn->prepare("
    INSERT INTO payments
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
    )
");

$stmt->bind_param(
    "iissd",
    $userId,
    $subscription['id'],
    $orderId,
    $paymentId,
    $subscription['amount']
);

$stmt->execute();


// Final response
echo json_encode([
    'success' => true,
    'message' => 'Payment successful. Storage upgraded.',
    'quota' => $quotaInfo
]);