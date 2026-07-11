<?php

$drivaultConfig = require __DIR__ . '/config/drivault.php';

$username = "9598979496v"; // Username to disable

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://login.drivault.com/ocs/v1.php/cloud/users/" . urlencode($username) . "/disable",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "PUT",
    CURLOPT_USERPWD => $drivaultConfig['username'] . ":" . $drivaultConfig['password'],
    CURLOPT_HTTPHEADER => [
        "OCS-APIRequest: true",
        "Accept: application/json"
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
    die("cURL Error: " . curl_error($curl));
}

curl_close($curl);

$data = json_decode($response, true);

echo "<h2>Disable User Result</h2>";
echo "<p><strong>HTTP Code:</strong> {$httpCode}</p>";

if (
    isset($data['ocs']['meta']['statuscode']) &&
    $data['ocs']['meta']['statuscode'] == 100
) {
    echo "<p style='color:green;font-size:18px;'>
            ✅ User <strong>{$username}</strong> has been disabled successfully.
          </p>";
} else {
    echo "<p style='color:red;font-size:18px;'>
            ❌ Failed to disable user.
          </p>";

    echo "<pre>";
    print_r($data);
    echo "</pre>";
}