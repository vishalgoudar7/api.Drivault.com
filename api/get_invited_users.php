<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => false,
        'message' => 'Method not allowed. Use GET.',
    ]);
    exit;
}

$inviterEmail = trim((string) ($_GET['inviter_email'] ?? ''));

if ($inviterEmail === '') {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'message' => 'inviter_email is required.',
    ]);
    exit;
}

// if (
//     preg_match(
//         '/^[A-Za-z0-9]{3,15}$/',
//         $inviterEmail
//     ) !== 1
// ) {
//     http_response_code(422);

//     echo json_encode([
//         'status' => false,
//         'message' => 'Invalid inviter value.',
//     ]);

//     exit;
// }

$isEmail = filter_var(
    $inviterEmail,
    FILTER_VALIDATE_EMAIL
);

$isPhone = preg_match(
    '/^[0-9]{10}$/',
    $inviterEmail
);

if (!$isEmail && !$isPhone) {

    http_response_code(422);

    echo json_encode([
        'status' => false,
        'message' => 'Enter valid email or mobile number.',
    ]);

    exit;
}
$conn->set_charset('utf8mb4');

$statement = $conn->prepare(
    "SELECT
        name,
        email,
        phone AS mobile_no,
        invite_accepted AS accepted
     FROM users
     WHERE role = 'user'
       AND inviter_email = ?
     ORDER BY id DESC"
);

$statement->bind_param('s', $inviterEmail);
$statement->execute();
$result = $statement->get_result();
$invitedUsers = $result->fetch_all(MYSQLI_ASSOC);
$statement->close();

echo json_encode([
    'status' => true,
    'inviter_email' => $inviterEmail,
    'count' => count($invitedUsers),
    'invited_users' => $invitedUsers,
], JSON_UNESCAPED_UNICODE);
