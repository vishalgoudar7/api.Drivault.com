<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../config/db.php';

$adminCreateMessage = $_SESSION['admin_create_message'] ?? '';
$message = '';

if ($adminCreateMessage !== '') {
    unset($_SESSION['admin_create_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $adminCount = 0;

    $adminCountResult = $conn->query(
        "SELECT COUNT(*) AS admin_count FROM users WHERE role = 'admin'"
    );

    if ($adminCountResult instanceof mysqli_result) {
        $adminCountRow = $adminCountResult->fetch_assoc();
        $adminCount = (int) ($adminCountRow['admin_count'] ?? 0);
        $adminCountResult->free();
    }

    if ($email === '' || $password === '') {

        $message = 'Email and password are required.';

    } else {

        $statement = $conn->prepare(
            'SELECT id, name, email, password, role, is_active
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        $statement->bind_param('s', $email);
        $statement->execute();

        $result = $statement->get_result();
        $user = $result->fetch_assoc();

        $statement->close();

        if (!$user) {

            $message = 'Account not found for that email address.';

        } elseif ((string) ($user['role'] ?? '') !== 'admin') {

            $message = $adminCount === 0
                ? 'No admin account exists yet. Create an admin account first.'
                : 'This account does not have admin access.';

        } elseif ((int) ($user['is_active'] ?? 0) !== 1) {

            $message = 'Admin account is inactive.';

        } elseif (
            !password_verify(
                $password,
                (string) ($user['password'] ?? '')
            )
        ) {

            $message = 'Wrong password.';

        } else {

            $_SESSION['admin_user_id'] = (int) $user['id'];
            $_SESSION['admin_name'] = (string) ($user['name'] ?? '');
            $_SESSION['admin_email'] = (string) ($user['email'] ?? '');
            $_SESSION['admin_role'] = 'admin';

            header('Location: admin-dashboard.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Login</title>

<link
    rel="icon"
    type="image/x-icon"
    href="/php_invitation_system/assets/Photos/favicon.ico"
>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter',sans-serif;
}

body{
    background:#f5f7f9;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    padding:20px;
}

.container{
    width:100%;
    max-width:440px;
}

.login-box{
    background:#ffffff;
    border-radius:24px;
    padding:40px;
    box-shadow:0 4px 20px rgba(0,0,0,0.05);
}

.brand{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:24px;
}

.brand-badge{
    width:52px;
    height:52px;
    border-radius:16px;
    background:#ecfdf5;
    border:1px solid #d1fae5;
    box-shadow:0 10px 24px rgba(74,222,128,0.18);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:8px;
}

.brand-badge img{
    width:100%;
    height:100%;
    object-fit:contain;
}

.brand span{
    font-size:24px;
    color:#0f172a;
    font-weight:700;
}

.login-box h2{
    font-size:30px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:10px;
}

.message{
    background:#ecfdf5;
    color:#166534;
    border:1px solid #bbf7d0;
    padding:12px;
    border-radius:12px;
    margin-bottom:20px;
}

.error-message{
    background:#fef2f2;
    color:#dc2626;
    border:1px solid #fecaca;
    padding:12px;
    border-radius:12px;
    margin-bottom:20px;
}

.form-group{
    margin-bottom:20px;
}

.form-group label{
    display:block;
    margin-bottom:8px;
    color:#0f172a;
    font-size:14px;
    font-weight:600;
}

.form-group input{
    width:100%;
    padding:14px 16px;
    border:1px solid #e2e8f0;
    border-radius:14px;
    outline:none;
    font-size:15px;
    background:#ffffff;
}

.form-group input:focus{
    border-color:#4ade80;
    box-shadow:0 0 0 4px rgba(74,222,128,0.15);
}

button{
    width:100%;
    padding:15px;
    border:none;
    border-radius:14px;
    background:#4ade80;
    color:#ffffff;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
}

button:hover{
    background:#22c55e;
}

.create-link{
    text-align:center;
    margin-top:20px;
    color:#64748b;
    font-size:14px;
    line-height:1.7;
}

.create-link strong{
    color:#0f172a;
}
</style>

</head>

<body>

<div class="container">

    <div class="login-box">

        <div class="brand">
            <div class="brand-badge">
                <img src="/assets/Photos/icon-192.png" alt="Drivault logo">
            </div>
            <span>Drivault</span>
        </div>

        <h2>Admin Login</h2>

        <?php if ($adminCreateMessage !== '') { ?>

        <div class="message">

            <?php
            echo htmlspecialchars(
                $adminCreateMessage,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>

        <?php } ?>

        <?php if ($message !== '') { ?>

        <div class="error-message">

            <?php
            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>

        </div>

        <?php } ?>

        <form method="POST">

            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    placeholder="Enter Email"
                    required
                >

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Enter Password"
                    required
                >

            </div>

            <button type="submit">
                Login as Admin
            </button>

        </form>

        <div class="create-link">
            Default admin email: <strong>admin@drivault.com</strong><br>
            Default password: <strong>Admin@123</strong>
        </div>

    </div>

</div>

</body>
</html>
