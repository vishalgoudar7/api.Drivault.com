<?php
session_start();

$adminCreateMessage = $_SESSION['admin_create_message'] ?? '';

if ($adminCreateMessage !== '') {
    unset($_SESSION['admin_create_message']);
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
    font-family:'Segoe UI',sans-serif;
}

body{
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;

    /* DriveVault Dark Theme */
    background:
    linear-gradient(
        135deg,
        #0f172a,
        #111827,
        #1e293b
    );

    overflow:hidden;
}

/* Background Glow Effect */

body::before{
    content:'';
    position:absolute;
    width:500px;
    height:500px;
    background:#2563eb;
    border-radius:50%;
    top:-150px;
    left:-150px;
    opacity:0.15;
    filter:blur(120px);
}

body::after{
    content:'';
    position:absolute;
    width:400px;
    height:400px;
    background:#06b6d4;
    border-radius:50%;
    bottom:-120px;
    right:-120px;
    opacity:0.12;
    filter:blur(120px);
}

.container{
    width:100%;
    max-width:420px;
    padding:20px;
    position:relative;
    z-index:1;
}

.login-box{
    background:rgba(17,24,39,0.95);

    border:1px solid rgba(255,255,255,0.08);

    backdrop-filter:blur(10px);

    padding:40px;

    border-radius:22px;

    box-shadow:
    0 10px 40px rgba(0,0,0,0.4);

    color:white;
}

.brand{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
    margin-bottom:24px;
}

.brand-badge{
    width:52px;
    height:52px;
    border-radius:16px;
    background:rgba(236,253,245,0.96);
    border:1px solid rgba(209,250,229,0.9);
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
    color:#ffffff;
    letter-spacing:-0.4px;
}

.login-box h2{
    text-align:center;
    margin-bottom:30px;
    font-size:30px;
    font-weight:700;
    color:#ffffff;
}

.message{
    background:rgba(34,197,94,0.15);
    color:#4ade80;
    padding:14px;
    border-radius:10px;
    margin-bottom:20px;
    text-align:center;
    border:1px solid rgba(74,222,128,0.3);
}

.form-group{
    margin-bottom:20px;
}

.form-group label{
    display:block;
    margin-bottom:8px;
    color:#cbd5e1;
    font-size:14px;
    font-weight:500;
}

.form-group input{
    width:100%;
    padding:15px;

    background:#0f172a;

    border:1px solid #334155;

    border-radius:12px;

    color:white;

    font-size:15px;

    transition:0.3s;
}

.form-group input::placeholder{
    color:#64748b;
}

.form-group input:focus{

    border-color:#2563eb;

    box-shadow:
    0 0 0 4px rgba(37,99,235,0.2);

    outline:none;
}

button{
    width:100%;
    padding:15px;

    border:none;

    border-radius:12px;

    background:
    linear-gradient(
        135deg,
        #2563eb,
        #06b6d4
    );

    color:white;

    font-size:16px;

    font-weight:600;

    cursor:pointer;

    transition:0.3s;
}

button:hover{
    transform:translateY(-2px);

    box-shadow:
    0 10px 20px rgba(37,99,235,0.35);
}

.create-link{
    margin-top:22px;
    text-align:center;
}

.create-link a{
    color:#38bdf8;
    text-decoration:none;
    font-weight:500;
    transition:0.3s;
}

.create-link a:hover{
    color:#7dd3fc;
    text-decoration:underline;
}

</style>

</head>

<body>

<div class="container">

    <div class="login-box">

        <div class="brand">
            <div class="brand-badge">
                <img src="/php_invitation_system/assets/Photos/icon-192.png" alt="Drivault logo">
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

        <form action="admin_login.php" method="POST">

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
