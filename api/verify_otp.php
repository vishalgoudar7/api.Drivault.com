<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$otp = trim((string) ($_POST['otp'] ?? ''));
$token = trim((string) ($_POST['token'] ?? ''));

if ($otp === '' || $token === '') {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'OTP and token are required.'
    ]);

    exit;
}

$statement = $conn->prepare(
    'SELECT id FROM users 
     WHERE invite_token = ? 
     AND otp = ? 
     AND otp_expiry > NOW() 
     LIMIT 1'
);

$statement->bind_param('ss', $token, $otp);

$statement->execute();

$result = $statement->get_result();

$user = $result->fetch_assoc();

$statement->close();

if (!$user) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Invalid OTP'
    ]);

    exit;
}

$updateStatement = $conn->prepare(
    'UPDATE users 
     SET is_verified = 1 
     WHERE id = ?'
);

$updateStatement->bind_param('i', $user['id']);

$updateStatement->execute();

$updateStatement->close();

$_SESSION['verified_user_id'] = (int) $user['id'];

$_SESSION['verified_invite_token'] = $token;

echo json_encode([
    'status' => true,
    'message' => 'OTP verified successfully',
    'redirect' => '../pages/set-password.php?token=' . urlencode($token)
]);

exit;
