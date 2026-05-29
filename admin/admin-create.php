<?php
session_start();

if (
    !isset($_SESSION['admin_role']) ||
    $_SESSION['admin_role'] !== 'admin'
) {
    http_response_code(403);
    exit('Only admins can create a new admin account.');
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

<title>Create Admin Account</title>

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

/* Green Glow */

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

.form-group{
    margin-bottom:22px;
}

.form-group label{

    display:block;

    margin-bottom:10px;

    color:#0f172a;

    font-weight:600;

    font-size:14px;
}

.form-group input{

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

.form-group input::placeholder{
    color:#94a3b8;
}

.form-group input:focus{

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

.login-link{

    margin-top:24px;

    text-align:center;
}

.login-link a{

    color:#22c55e;

    text-decoration:none;

    font-weight:600;

    transition:0.3s;
}

.login-link a:hover{

    color:#16a34a;

    text-decoration:underline;
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

        <form action="admin_create.php" method="POST">

            <div class="form-group">
                <label for="name">Name</label>

                <input
                    id="name"
                    type="text"
                    name="name"
                    placeholder="Enter Full Name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    placeholder="Enter Email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>

                <input
                    id="phone"
                    type="text"
                    name="phone"
                    placeholder="Enter Phone Number"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Enter Password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    id="confirm_password"
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

        <div class="login-link">
            <a href="admin-dashboard.php">
                Back to Dashboard
            </a>
        </div>

    </div>

</div>

</body>
</html>
