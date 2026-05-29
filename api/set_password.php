<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../config/db.php';

$drivaultConfig = require __DIR__ . '/../config/drivault.php';

function extractDrivaultErrorMessage(string $responseBody): string
{
    $trimmed = trim($responseBody);

    if ($trimmed === '') {
        return 'Empty response from Drivault.';
    }

    $xml = @simplexml_load_string($trimmed);
    if ($xml !== false && isset($xml->meta->message)) {
        return trim((string) $xml->meta->message) ?: 'Drivault request failed.';
    }

    $json = json_decode($trimmed, true);
    if (is_array($json)) {
        $message = $json['ocs']['meta']['message'] ?? $json['message'] ?? null;
        if (is_string($message) && $message !== '') {
            return $message;
        }
    }

    return 'Drivault request failed.';
}

$plainPassword = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$token = trim((string) ($_POST['token'] ?? ''));
$verifiedUserId = isset($_SESSION['verified_user_id']) ? (int) $_SESSION['verified_user_id'] : 0;
$verifiedInviteToken = (string) ($_SESSION['verified_invite_token'] ?? '');

if ($token === '' && $verifiedUserId <= 0) {
    http_response_code(422);
    exit('Invalid token.');
}

if ($plainPassword === '' || $confirmPassword === '') {
    http_response_code(422);
    exit('Password and confirm password are required.');
}

if ($plainPassword !== $confirmPassword) {
    http_response_code(422);
    exit('Password and confirm password do not match.');
}

$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/';
if (preg_match($passwordPattern, $plainPassword) !== 1) {
    http_response_code(422);
    exit('Password must be at least 8 characters and include uppercase, lowercase, number, and special character.');
}

if ($verifiedUserId > 0) {
    $userStatement = $conn->prepare(
        'SELECT id, name, email, phone, invite_token, inviter_email FROM users WHERE id = ? LIMIT 1'
    );
    $userStatement->bind_param('i', $verifiedUserId);
} else {
    $userStatement = $conn->prepare(
        'SELECT id, name, email, phone, invite_token, inviter_email FROM users WHERE invite_token = ? LIMIT 1'
    );
    $userStatement->bind_param('s', $token);
}

$userStatement->execute();
$userResult = $userStatement->get_result();
$user = $userResult->fetch_assoc();
$userStatement->close();

if (!$user) {
    http_response_code(404);
    exit('Invitation token not found.');
}

if ($verifiedInviteToken !== '' && (string) ($user['invite_token'] ?? '') !== $verifiedInviteToken) {
    http_response_code(409);
    exit('This invitation link has been replaced by a newer invite.');
}

$endpoint = trim((string) ($drivaultConfig['endpoint'] ?? ''));
$apiUsername = trim((string) ($drivaultConfig['username'] ?? ''));
$apiPassword = trim((string) ($drivaultConfig['password'] ?? ''));

if ($endpoint === '' || $apiUsername === '' || $apiPassword === '') {
    http_response_code(500);
    exit('Drivault API is not configured.');
}

$passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn->begin_transaction();

    // Mark the invitation as accepted locally before syncing to Drivault.
    $updateStatement = $conn->prepare(
        'UPDATE users SET password = ?, is_active = 1, invite_accepted = ? WHERE id = ?'
    );
    $inviteAccepted = 'yes';
    $updateStatement->bind_param('ssi', $passwordHash, $inviteAccepted, $user['id']);
    $updateStatement->execute();
    $updateStatement->close();

    // Create the invited user's Drivault account.
    $curlHandle = curl_init($endpoint);
    curl_setopt_array($curlHandle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'displayName' => (string) ($user['name'] ?? ''),
            'userid' => (string) ($user['phone'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'password' => $plainPassword,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $apiUsername . ':' . $apiPassword,
        CURLOPT_HTTPHEADER => [
            'OCS-APIRequest: true',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $responseBody = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    $httpStatus = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
    curl_close($curlHandle);

    if ($responseBody === false) {
        throw new RuntimeException('Unable to connect to Drivault: ' . $curlError);
    }

    $responseText = (string) $responseBody;
    $xml = @simplexml_load_string($responseText);
    $ocsStatus = $xml !== false ? strtolower(trim((string) ($xml->meta->status ?? ''))) : '';
    $statusCode = $xml !== false ? (int) ($xml->meta->statuscode ?? 0) : 0;

    if ($httpStatus >= 400 || ($ocsStatus !== '' && $ocsStatus !== 'ok') || ($statusCode !== 0 && $statusCode !== 100)) {
        throw new RuntimeException(extractDrivaultErrorMessage($responseText));
    }

    // // Reward only the inviter by increasing the inviter's quota.
    // $inviterUserId = trim((string) ($user['inviter_email'] ?? ''));
    // $inviterUserId = preg_replace('/[^A-Za-z0-9@._-]/', '', $inviterUserId) ?? '';

$inviterUserId = trim(
    (string) ($user['inviter_email'] ?? '')
);

if ($inviterUserId !== '') {

    error_log(
        "Reward User: " .
        $inviterUserId
    );

    error_log(
        "Inviter User: "
        . $inviterUserId
    );

    error_log(
        "Inviter User: "
        . $inviterUserId
    );

    /*
    ==================================
    GET CURRENT QUOTA
    ==================================
    */

    $getUserCurl = curl_init(
        $endpoint .
        '/' .
        rawurlencode(
            $inviterUserId
        )
    );

    curl_setopt_array(
        $getUserCurl,
        [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
            $apiUsername .
            ':' .
            $apiPassword,

        CURLOPT_HTTPHEADER => [
            'OCS-APIRequest: true',
        ],

    ]);

    $getUserResponse =
        curl_exec(
            $getUserCurl
        );

    curl_close(
        $getUserCurl
    );

    /*
    ==================================
    CURRENT STORAGE
    ==================================
    */

    $currentQuotaGb = 100; // default user storage

if ($getUserResponse !== false) {

    $quotaXml =
        @simplexml_load_string(
            $getUserResponse
        );

    $currentQuotaBytes =
        (int)(
            $quotaXml
            ->data
            ->quota
            ->quota ?? 0
        );

    if ($currentQuotaBytes > 0) {

        $currentQuotaGb =
            round(
                $currentQuotaBytes /
                1024 /
                1024 /
                1024
            );
    }

    error_log(
        "Current Bytes: "
        . $currentQuotaBytes
    );

    error_log(
        "Current GB: "
        . $currentQuotaGb
    );
}

    /*
    ==================================
    ADD REWARD
    ==================================
    */

    $rewardGb = 100;

    $newQuotaGb =
        $currentQuotaGb +
        $rewardGb;

    error_log(
        "Current: "
        . $currentQuotaGb
        . "GB"
    );

    error_log(
        "Reward: +"
        . $rewardGb
        . "GB"
    );

    error_log(
        "Final: "
        . $newQuotaGb
        . "GB"
    );

    /*
    ==================================
    UPDATE QUOTA
    ==================================
    */

    $quotaCurlHandle =
        curl_init(
            $endpoint .
            '/' .
            rawurlencode(
                $inviterUserId
            )
        );

    curl_setopt_array(
        $quotaCurlHandle,
        [

        CURLOPT_CUSTOMREQUEST =>
            'PUT',

        CURLOPT_POSTFIELDS =>
            http_build_query([
                'key' => 'quota',
                'value' =>
                    $newQuotaGb
                    .'GB'
            ]),

        CURLOPT_RETURNTRANSFER =>
            true,

        CURLOPT_TIMEOUT =>
            30,

        CURLOPT_HTTPAUTH =>
            CURLAUTH_BASIC,

        CURLOPT_USERPWD =>
            $apiUsername .
            ':' .
            $apiPassword,

        CURLOPT_HTTPHEADER => [

            'OCS-APIRequest: true',

            'Content-Type: application/x-www-form-urlencoded',
        ],

    ]);

    $quotaResponseBody =
        curl_exec(
            $quotaCurlHandle
        );

    curl_close(
        $quotaCurlHandle
    );

    error_log(
        "Quota Updated: "
        . $newQuotaGb
        . "GB"
    );
}

// if ($inviterUserId !== '') {
//     error_log("Reward block started");

//     // Remove everything after @
//     if (str_contains($inviterUserId, '@')) {

//         $inviterUserId =
//             strstr($inviterUserId, '@', true);
//     }

//     // Keep only letters and numbers
//     $inviterUserId =
//         preg_replace(
//             '/[^A-Za-z0-9]/',
//             '',
//             $inviterUserId
//         ) ?? '';

//     error_log(
//         "Quota Increase User ID: "
//         . $inviterUserId
//     );

//     // $quotaCurlHandle = curl_init(
//     //     $endpoint . '/' . rawurlencode($inviterUserId)
//     // );
//     $quotaCurlHandle = curl_init(
//     $endpoint . '/' . rawurlencode($inviterUserId)
// );
//         $quotaCurlHandle = curl_init($endpoint . '/' . rawurlencode($inviterUserId));
//         // GET CURRENT USER QUOTA

// $getUserCurl = curl_init(
//     $endpoint . '/' . rawurlencode($inviterUserId)
// );

// curl_setopt_array($getUserCurl, [
//     CURLOPT_RETURNTRANSFER => true,
//     CURLOPT_TIMEOUT => 30,
//     CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
//     CURLOPT_USERPWD =>
//         $apiUsername . ':' . $apiPassword,
//     CURLOPT_HTTPHEADER => [
//         'OCS-APIRequest: true',
//     ],
// ]);

// $getUserResponse =
//     curl_exec($getUserCurl);
//     error_log("Get User Response: " . $getUserResponse);

// curl_close($getUserCurl);

// // Default quota
// // $currentQuotaGb = 0;

// // if ($getUserResponse !== false) {

// //     $quotaXml =
// //         @simplexml_load_string($getUserResponse);

// //     // Current quota in bytes
// //     $currentQuotaBytes =
// //     (int) (
// //         $quotaXml->data->quota ?? 0
// //     );

// //     // Convert bytes to GB
// //     if ($currentQuotaBytes > 0) {

// //         $currentQuotaGb =
// //             round(
// //                 $currentQuotaBytes /
// //                 1024 /
// //                 1024 /
// //                 1024
// //             );
// //     }
// }

// // Reward amount
// // $rewardGb = 100;

// // // Final new quota
// // // $newQuotaGb =
// // //     $currentQuotaGb + $rewardGb;
// // $newQuotaGb = 100;


// // Default quota
// $currentQuotaGb = 0;

// if ($getUserResponse !== false) {

//     $quotaXml =
//         @simplexml_load_string($getUserResponse);

//     // Current quota in bytes
//     $currentQuotaBytes =
//         (int)($quotaXml->data->quota ?? 0);

//     // Convert bytes to GB
//     if ($currentQuotaBytes > 0) {

//         $currentQuotaGb =
//             round(
//                 $currentQuotaBytes /
//                 1024 /
//                 1024 /
//                 1024
//             );
//     }
// }

// // Reward amount
// $rewardGb = 100;

// // Dynamic calculation
// $newQuotaGb =
//     $currentQuotaGb +
//     $rewardGb;

// error_log(
//     "Current: " .
//     $currentQuotaGb .
//     "GB"
// );

// error_log(
//     "Reward: " .
//     $rewardGb .
//     "GB"
// );

// error_log(
//     "Total: " .
//     $newQuotaGb .
//     "GB"
// );

//     error_log("Calculated New Quota: " . $newQuotaGb . "GB");

// error_log(
//     "Old Quota: "
//     . $currentQuotaGb . "GB"
// );

// error_log(
//     "New Quota: "
//     . $newQuotaGb . "GB"
// );
//       curl_setopt_array($quotaCurlHandle, [

//     CURLOPT_CUSTOMREQUEST => 'PUT',

//     CURLOPT_POSTFIELDS => http_build_query([
//         'key' => 'quota',
//         'value' => $newQuotaGb . 'GB',
//     ]),

//     CURLOPT_RETURNTRANSFER => true,
//     CURLOPT_TIMEOUT => 30,
//     CURLOPT_HTTPAUTH => CURLAUTH_BASIC,

//     CURLOPT_USERPWD =>
//         $apiUsername . ':' . $apiPassword,

//     CURLOPT_HTTPHEADER => [
//         'OCS-APIRequest: true',
//         'Content-Type: application/x-www-form-urlencoded',
//     ],
// ]);

//         $quotaResponseBody = curl_exec($quotaCurlHandle);
//         error_log("Quota API Response: " . $quotaResponseBody);
//         $quotaCurlError = curl_error($quotaCurlHandle);
//         $quotaHttpStatus = (int) curl_getinfo($quotaCurlHandle, CURLINFO_RESPONSE_CODE);
//         curl_close($quotaCurlHandle);

//         if ($quotaResponseBody === false) {
//             throw new RuntimeException('Unable to update inviter quota in Drivault: ' . $quotaCurlError);
//         }

//         $quotaResponseText = (string) $quotaResponseBody;
//         $quotaXml = @simplexml_load_string($quotaResponseText);
//         $quotaOcsStatus = $quotaXml !== false ? strtolower(trim((string) ($quotaXml->meta->status ?? ''))) : '';
//         $quotaStatusCode = $quotaXml !== false ? (int) ($quotaXml->meta->statuscode ?? 0) : 0;

//         if (
//             $quotaHttpStatus >= 400 ||
//             ($quotaOcsStatus !== '' && $quotaOcsStatus !== 'ok') ||
//             ($quotaStatusCode !== 0 && $quotaStatusCode !== 100)
//         ) {
//             throw new RuntimeException('Unable to update inviter quota: ' . extractDrivaultErrorMessage($quotaResponseText));
//         }
//     }

    $_SESSION['account_created_details'] = [
        'name' => (string) ($user['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
        'password' => $plainPassword,
    ];

    unset($_SESSION['verified_user_id'], $_SESSION['verified_invite_token']);

    $conn->commit();

    header('Location: ../pages/set-password.php?token=' . urlencode((string) ($user['invite_token'] ?? $token)) . '&created=1');
    exit;
} catch (Throwable $exception) {
    $conn->rollback();

    error_log(
        sprintf(
            '[set_password] Drivault sync error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    http_response_code(500);
    exit('Unable to create Drivault user: ' . $exception->getMessage());
}
