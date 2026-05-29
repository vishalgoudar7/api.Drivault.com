<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../config/db.php';

$message = '';
$adminCount = 0;

$adminCountResult = $conn->query(
    "SELECT COUNT(*) AS admin_count FROM users WHERE role = 'admin'"
);

if ($adminCountResult instanceof mysqli_result) {
    $adminCountRow = $adminCountResult->fetch_assoc();
    $adminCount = (int) ($adminCountRow['admin_count'] ?? 0);
    $adminCountResult->free();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

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

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter',sans-serif;
}

body{

    background:
    linear-gradient(
        135deg,
        #f8fafc,
        #eef2ff,
        #ecfeff
    );

    display:flex;
    justify-content:center;
    align-items:center;

    min-height:100vh;

    padding:20px;
}

.container{
    width:100%;
    max-width:450px;
}

.card{

    background:
    rgba(255,255,255,0.92);

    backdrop-filter:blur(12px);

    border:
    1px solid rgba(255,255,255,0.6);

    border-radius:28px;

    padding:42px;

    box-shadow:
    0 10px 40px rgba(15,23,42,0.08);

    position:relative;

    overflow:hidden;
}

/* Top Glow */

.card::before{
    content:'';
    position:absolute;
    width:180px;
    height:180px;

    background:
    rgba(74,222,128,0.12);

    border-radius:50%;

    top:-60px;
    right:-60px;

    filter:blur(20px);
}

.logo{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:30px;
    position:relative;
    z-index:1;
}

.logo-box{
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

.logo-box img{
    width:100%;
    height:100%;
    object-fit:contain;
}

.logo h1{
    font-size:28px;
    color:#0f172a;
    font-weight:700;
    letter-spacing:-0.5px;
}

.title{
    font-size:34px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:10px;
    line-height:1.2;
}

.title span{
    color:#22c55e;
}

.subtitle{
    color:#64748b;
    margin-bottom:32px;
    font-size:15px;
    line-height:1.6;
}

.form-group{
    margin-bottom:22px;
}

label{
    display:block;
    margin-bottom:10px;
    color:#0f172a;
    font-weight:600;
    font-size:14px;
}

input{
    width:100%;

    padding:15px 18px;

    border:
    1px solid #e2e8f0;

    border-radius:16px;

    outline:none;

    font-size:15px;

    transition:0.3s;

    background:#ffffff;

    color:#0f172a;
}

input::placeholder{
    color:#94a3b8;
}

input:focus{

    border-color:#4ade80;

    box-shadow:
    0 0 0 5px rgba(74,222,128,0.12);
}

button{
    width:100%;

    padding:16px;

    border:none;

    border-radius:16px;

    background:
    linear-gradient(
        135deg,
        #4ade80,
        #22c55e
    );

    color:white;

    font-size:16px;

    font-weight:600;

    cursor:pointer;

    transition:0.3s;

    box-shadow:
    0 10px 20px rgba(74,222,128,0.28);
}

button:hover{

    transform:translateY(-2px);

    box-shadow:
    0 14px 28px rgba(74,222,128,0.38);
}

.footer{
    text-align:center;
    margin-top:24px;
    color:#94a3b8;
    font-size:14px;
}

</style>

</head>

<body>

<div class="container">

    <div class="login-box">

        <div class="logo">
            <div class="logo-box">
                <img src="/php_invitation_system/assets/Photos/icon-192.png" alt="Drivault logo">
            </div>
            <h1>Drivault</h1>
        </div>

        <h2>Admin Login</h2>

        <?php if (isset($_SESSION['admin_create_message'])): ?>

            <div class="message success">
                <?php
                echo htmlspecialchars(
                    $_SESSION['admin_create_message']
                );

                unset($_SESSION['admin_create_message']);
                ?>
            </div>

        <?php endif; ?>

        <?php if ($message !== ''): ?>

            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>

        <?php endif; ?>

        <form method="POST">

            <div class="input-box">

                <input
                    type="email"
                    name="email"
                    placeholder="Enter Email"
                    required
                >

            </div>

            <div class="input-box">

                <input
                    type="password"
                    name="password"
                    placeholder="Enter Password"
                    required
                >

            </div>

            <button type="submit">
                Login
            </button>

        </form>

        <div class="footer">
            Default admin email: <strong>admin@drivault.com</strong><br>
            Default password: <strong>Admin@123</strong>
        </div>

    </div>

</div>

</body>
</html>
