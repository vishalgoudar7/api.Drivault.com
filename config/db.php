<?php
// for production
// $host = "localhost";
// $dbname = "invitation_system";
// $username = "drivault_user";
// $password = "Drivault@123";


//for local testing
$host = "localhost";
$dbname = "invitation_system";
$username = "root";
$password = "";

$defaultAdminName = 'Drivault Admin';
$defaultAdminEmail = 'admin@drivault.com';
$defaultAdminPhone = '9999999999';
$defaultAdminPassword = 'Admin@123';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$usersTableResult = $conn->query("SHOW TABLES LIKE 'users'");

if ($usersTableResult instanceof mysqli_result && $usersTableResult->num_rows > 0) {
    $adminCountResult = $conn->query(
        "SELECT COUNT(*) AS admin_count FROM users WHERE role = 'admin'"
    );

    if ($adminCountResult instanceof mysqli_result) {
        $adminCountRow = $adminCountResult->fetch_assoc();
        $adminCount = (int) ($adminCountRow['admin_count'] ?? 0);
        $adminCountResult->free();

        if ($adminCount === 0) {
            $passwordHash = password_hash($defaultAdminPassword, PASSWORD_BCRYPT);
            $insertAdminStatement = $conn->prepare(
                'INSERT INTO users (name, email, phone, password, is_verified, is_active, role)
                 VALUES (?, ?, ?, ?, 1, 1, ?)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    phone = VALUES(phone),
                    password = VALUES(password),
                    is_verified = 1,
                    is_active = 1,
                    role = VALUES(role)'
            );
            $defaultAdminRole = 'admin';
            $insertAdminStatement->bind_param(
                'sssss',
                $defaultAdminName,
                $defaultAdminEmail,
                $defaultAdminPhone,
                $passwordHash,
                $defaultAdminRole
            );
            $insertAdminStatement->execute();
            $insertAdminStatement->close();
        }
    }
}

if ($usersTableResult instanceof mysqli_result) {
    $usersTableResult->free();
}
?>
