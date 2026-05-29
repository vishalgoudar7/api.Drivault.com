<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/../config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function findPendingInvite(mysqli $conn, int $id, string $email, string $inviterEmail): ?array
{
    if ($id > 0) {
        if ($inviterEmail !== '') {
            $statement = $conn->prepare(
                "SELECT id, name, email, phone, inviter, inviter_email, invite_accepted
                 FROM users
                 WHERE id = ?
                   AND role = 'user'
                   AND inviter_email = ?
                 LIMIT 1"
            );
            $statement->bind_param('is', $id, $inviterEmail);
        } else {
            $statement = $conn->prepare(
                "SELECT id, name, email, phone, inviter, inviter_email, invite_accepted
                 FROM users
                 WHERE id = ?
                   AND role = 'user'
                 LIMIT 1"
            );
            $statement->bind_param('i', $id);
        }
    } elseif ($email !== '') {
        if ($inviterEmail !== '') {
            $statement = $conn->prepare(
                "SELECT id, name, email, phone, inviter, inviter_email, invite_accepted
                 FROM users
                 WHERE email = ?
                   AND role = 'user'
                   AND inviter_email = ?
                 LIMIT 1"
            );
            $statement->bind_param('ss', $email, $inviterEmail);
        } else {
            $statement = $conn->prepare(
                "SELECT id, name, email, phone, inviter, inviter_email, invite_accepted
                 FROM users
                 WHERE email = ?
                   AND role = 'user'
                 LIMIT 1"
            );
            $statement->bind_param('s', $email);
        }
    } else {
        return null;
    }

    $statement->execute();
    $result = $statement->get_result();
    $invite = $result->fetch_assoc();
    $statement->close();

    return is_array($invite) ? $invite : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'status' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
}

$conn->set_charset('utf8mb4');

$action = strtolower(trim((string) ($_POST['action'] ?? 'revoke')));
$id = (int) ($_POST['id'] ?? 0);
$email = trim((string) ($_POST['email'] ?? ''));
$inviterEmail = trim((string) ($_POST['inviter_email'] ?? ''));

if ($action !== 'revoke' && $action !== 'modify') {
    jsonResponse(422, [
        'status' => false,
        'message' => 'action must be either revoke or modify.',
    ]);
}

if ($id <= 0 && $email === '') {
    jsonResponse(422, [
        'status' => false,
        'message' => 'Provide invite id or email.',
    ]);
}

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(422, [
        'status' => false,
        'message' => 'Invalid email value.',
    ]);
}

if ($inviterEmail !== '') {

    $isEmail = filter_var(
        $inviterEmail,
        FILTER_VALIDATE_EMAIL
    );

    $isPhone = preg_match(
        '/^[0-9]{10}$/',
        $inviterEmail
    );

    if (!$isEmail && !$isPhone) {

        jsonResponse(422, [
            'status' => false,
            'message' => 'Enter valid email or mobile number.',
        ]);
    }
}

$existingInvite = findPendingInvite($conn, $id, $email, $inviterEmail);

if ($existingInvite === null) {
    jsonResponse(404, [
        'status' => false,
        'message' => 'Invite not found.',
    ]);
}

if (strtolower((string) ($existingInvite['invite_accepted'] ?? 'no')) === 'yes') {
    jsonResponse(409, [
        'status' => false,
        'message' => 'Accepted invites cannot be revoked or modified.',
    ]);
}

try {
    $conn->begin_transaction();

    if ($action === 'revoke') {
        $deleteStatement = $conn->prepare(
            "DELETE FROM users
             WHERE id = ?
               AND role = 'user'
               AND invite_accepted = 'no'
             LIMIT 1"
        );
        $deleteStatement->bind_param('i', $existingInvite['id']);
        $deleteStatement->execute();
        $affectedRows = $deleteStatement->affected_rows;
        $deleteStatement->close();

        if ($affectedRows !== 1) {
            throw new RuntimeException('Unable to revoke the invite.');
        }

        $conn->commit();

        jsonResponse(200, [
            'status' => true,
            'action' => 'revoke',
            'message' => 'Invite revoked successfully.',
            'revoked_invite' => [
                'id' => (int) $existingInvite['id'],
                'name' => (string) ($existingInvite['name'] ?? ''),
                'email' => (string) ($existingInvite['email'] ?? ''),
                'mobile_no' => (string) ($existingInvite['phone'] ?? ''),
                'inviter_email' => (string) ($existingInvite['inviter_email'] ?? ''),
                'accepted' => 'no',
            ],
        ]);
    }

    $name = trim((string) ($_POST['name'] ?? (string) ($existingInvite['name'] ?? '')));
    $newEmail = trim((string) ($_POST['new_email'] ?? $_POST['email'] ?? (string) ($existingInvite['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? (string) ($existingInvite['phone'] ?? '')));
    $inviterName = trim((string) ($_POST['inviter_name'] ?? (string) ($existingInvite['inviter'] ?? '')));
    $newInviterEmail = trim((string) ($_POST['new_inviter_email'] ?? $_POST['inviter_email'] ?? (string) ($existingInvite['inviter_email'] ?? '')));

    if ($name === '' || $newEmail === '' || $phone === '') {
        jsonResponse(422, [
            'status' => false,
            'message' => 'name, email, and phone are required for modify.',
        ]);
    }

    if (filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false) {
        jsonResponse(422, [
            'status' => false,
            'message' => 'Invalid new email value.',
        ]);
    }

    if ($newInviterEmail !== '' && filter_var($newInviterEmail, FILTER_VALIDATE_EMAIL) === false) {
        jsonResponse(422, [
            'status' => false,
            'message' => 'Invalid new_inviter_email value.',
        ]);
    }

    if (preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) !== 1) {
        jsonResponse(422, [
            'status' => false,
            'message' => 'Invalid phone value.',
        ]);
    }

    $newToken = bin2hex(random_bytes(32));

    $updateStatement = $conn->prepare(
        "UPDATE users
         SET name = ?,
             email = ?,
             phone = ?,
             inviter = ?,
             inviter_email = ?,
             invite_token = ?,
             otp = NULL,
             otp_expiry = NULL,
             is_verified = 0,
             is_active = 0,
             invite_accepted = 'no'
         WHERE id = ?
           AND role = 'user'
           AND invite_accepted = 'no'"
    );
    $updateStatement->bind_param(
        'ssssssi',
        $name,
        $newEmail,
        $phone,
        $inviterName,
        $newInviterEmail,
        $newToken,
        $existingInvite['id']
    );
    $updateStatement->execute();
    $affectedRows = $updateStatement->affected_rows;
    $updateStatement->close();

    if ($affectedRows < 0) {
        throw new RuntimeException('Unable to modify the invite.');
    }

    $conn->commit();

    jsonResponse(200, [
        'status' => true,
        'action' => 'modify',
        'message' => 'Invite updated successfully.',
        'invite' => [
            'id' => (int) $existingInvite['id'],
            'name' => $name,
            'email' => $newEmail,
            'mobile_no' => $phone,
            'inviter_name' => $inviterName,
            'inviter_email' => $newInviterEmail,
            'accepted' => 'no',
            'invite_token' => $newToken,
        ],
    ]);
} catch (mysqli_sql_exception $exception) {
    $conn->rollback();

    $errorCode = (int) $exception->getCode();
    if ($errorCode === 1062) {
        jsonResponse(409, [
            'status' => false,
            'message' => 'The updated email already exists on another account.',
        ]);
    }

    error_log(
        sprintf(
            '[revoke_invite] Database error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonResponse(500, [
        'status' => false,
        'message' => 'Unable to process invite request.',
    ]);
} catch (Throwable $exception) {
    $conn->rollback();

    error_log(
        sprintf(
            '[revoke_invite] Error: %s in %s on line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        )
    );

    jsonResponse(500, [
        'status' => false,
        'message' => 'Unable to process invite request.',
    ]);
}
