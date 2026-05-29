<?php

require __DIR__ . '/../config/db.php';

$phone = $_GET['phone'] ?? '';

if ($phone == '') {
    exit('');
}

$stmt = $conn->prepare(
    "SELECT email FROM users WHERE phone = ? LIMIT 1"
);

$stmt->bind_param("s", $phone);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo $row['email'];
} else {
    echo '';
}