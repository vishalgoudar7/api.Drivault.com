<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/../config/db.php';

$message = '';

if (
    !isset($_SESSION['admin_role']) ||
    $_SESSION['admin_role'] !== 'admin'
) {
    http_response_code(403);
    exit('Only admins can create a new admin account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (
        $name === '' ||
        $email === '' ||
        $phone === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {
        $message = 'All admin fields are required.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $message = 'Invalid email address.';
    } elseif ($password !== $confirmPassword) {
        $message = 'Password and confirm password do not match.';
    } else {

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $role = 'admin';

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {

            $statement = $conn->prepare(
                'INSERT INTO users (name, email, phone, password, is_verified, is_active, role)
                 VALUES (?, ?, ?, ?, 1, 1, ?)'
            );

            $statement->bind_param(
                'sssss',
                $name,
                $email,
                $phone,
                $passwordHash,
                $role
            );

            $statement->execute();
            $statement->close();

            $_SESSION['admin_create_message'] =
                'Admin account created successfully.';

            header('Location: admin-dashboard.php');
            exit;

        } catch (mysqli_sql_exception $exception) {

            if ((int) $exception->getCode() === 1062) {
                $message = 'Admin email already exists.';
            } else {
                $message = 'Unable to create admin account.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create Admin</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter',sans-serif;
}

body{

    min-height:100vh;

    display:flex;
    justify-content:center;
    align-items:center;

    padding:20px;

    background:
    linear-gradient(
        135deg,
        #f8fafc,
        #eefbf3,
        #ecfeff
    );
}

.container{
    width:100%;
    max-width:450px;
}

.form-box{

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

.form-box::before{
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

.form-box h2{
    text-align:center;

    margin-bottom:30px;

    color:#0f172a;

    font-size:32px;

    font-weight:700;

    line-height:1.2;
}

.brand{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    margin-bottom:24px;
    position:relative;
    z-index:1;
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
    font-size:28px;
    font-weight:700;
    color:#0f172a;
    letter-spacing:-0.4px;
}

.input-box{
    margin-bottom:22px;
}

.input-box input{

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

.input-box input::placeholder{
    color:#94a3b8;
}

.input-box input:focus{

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

.message{

    background:
    rgba(239,68,68,0.08);

    border:
    1px solid rgba(239,68,68,0.15);

    color:#dc2626;

    padding:14px;

    border-radius:14px;

    margin-bottom:18px;

    text-align:center;

    font-size:14px;

    font-weight:500;
}

</style>

</head>

<body>

<div class="container">

    <div class="form-box">

        <div class="brand">
            <div class="brand-badge">
                <img src="/php_invitation_system/assets/Photos/icon-192.png" alt="Drivault logo">
            </div>
            <span>Drivault</span>
        </div>

        <h2>Create Admin Account</h2>

        <?php if ($message !== ''): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="input-box">
                <input
                    type="text"
                    name="name"
                    placeholder="Full Name"
                    required
                >
            </div>

            <div class="input-box">
                <input
                    type="email"
                    name="email"
                    placeholder="Email Address"
                    required
                >
            </div>

            <div class="input-box">
                <input
                    type="text"
                    name="phone"
                    placeholder="Phone Number"
                    required
                >
            </div>

            <div class="input-box">
                <input
                    type="password"
                    name="password"
                    placeholder="Password"
                    required
                >
            </div>

            <div class="input-box">
                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Confirm Password"
                    required
                >
            </div>

            <button type="submit">
                Create Admin
            </button>

        </form>

    </div>

</div>

</body>
</html>
