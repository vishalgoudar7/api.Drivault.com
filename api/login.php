<?php
session_start();
require '../config/db.php';

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email='$email' AND is_active=1";

$result = $conn->query($sql);

if ($result->num_rows > 0) {

$user = $result->fetch_assoc();

if (password_verify($password, $user['password'])) {

$_SESSION['user_id'] = $user['id'];

echo "Login Successful";

} else {
echo "Wrong Password";
}

} else {
echo "Account not found";
}
?>
