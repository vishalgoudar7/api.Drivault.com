<?php
session_start();

$token = (string) ($_GET['token'] ?? ($_SESSION['verified_invite_token'] ?? ''));

$accountCreatedDetails = $_SESSION['account_created_details'] ?? null;

$mailConfig = require __DIR__ . '/../config/mail.php';

$googlePlayLink = (string) ($mailConfig['google_play_link'] ?? 'https://play.google.com/store');

$appStoreLink = (string) ($mailConfig['app_store_link'] ?? 'https://www.apple.com/app-store/');

$googlePlayImagePath = __DIR__ . '/../assets/Photos/googlePlay.png';

if ($accountCreatedDetails !== null) {
    unset($_SESSION['account_created_details']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Set Password</title>

<link rel="icon" type="image/x-icon" href="/assets/Photos/favicon.ico">

<link rel="preconnect" href="https://fonts.googleapis.com">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
    max-width:500px;
}

.card{
    background:#ffffff;
    border-radius:24px;
    padding:40px;
    box-shadow:0 4px 20px rgba(0,0,0,0.05);
}

.logo{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:25px;
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
    font-size:24px;
    color:#0f172a;
    font-weight:700;
}

.title{
    font-size:30px;
    font-weight:700;
    color:#0f172a;
    margin-bottom:8px;
}

.title span{
    color:#4ade80;
}

.subtitle{
    color:#64748b;
    margin-bottom:30px;
    font-size:15px;
}

.form-group{
    margin-bottom:22px;
    position:relative;
}

label{
    display:block;
    margin-bottom:8px;
    color:#0f172a;
    font-weight:500;
}

input{
    width:100%;
    padding:14px 50px 14px 16px;
    border:1px solid #e2e8f0;
    border-radius:14px;
    outline:none;
    font-size:15px;
    transition:0.3s;
}

input:focus{
    border-color:#4ade80;
    box-shadow:0 0 0 4px rgba(74,222,128,0.15);
}

.input-error{
    border-color:#ef4444 !important;
}

.password-toggle{
    position:absolute;
    right:16px;
    top:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    width:24px;
    height:24px;
    padding:0;
    border:none;
    background:transparent;
    cursor:pointer;
    color:#64748b;
}

.password-toggle:hover{
    color:#0f172a;
}

.password-toggle:focus{
    outline:none;
    color:#0f172a;
}

.password-toggle svg{
    width:20px;
    height:20px;
    stroke:currentColor;
}

.password-rules{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:18px;
    margin-bottom:24px;
}

.password-rules.is-hidden{
    display:none;
}

.password-rules h3{
    font-size:16px;
    margin-bottom:12px;
    color:#0f172a;
}

.password-rules ul{
    margin:0;
    padding-left:18px;
}

.password-rules li{
    color:#64748b;
    margin-bottom:8px;
    font-size:14px;
}

.password-rules li:last-child{
    margin-bottom:0;
}

.password-strength{
    margin-top:10px;
    font-size:14px;
    font-weight:600;
}

.match-message{
    margin-top:10px;
    font-size:14px;
    font-weight:600;
}

.loader{
    width:22px;
    height:22px;
    border:3px solid rgba(255,255,255,0.3);
    border-top-color:#ffffff;
    border-radius:50%;
    animation:spin 1s linear infinite;
    display:none;
    margin:auto;
}

@keyframes spin{
    to{
        transform:rotate(360deg);
    }
}

button{
    width:100%;
    padding:15px;
    border:none;
    border-radius:14px;
    background:#4ade80;
    color:white;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    transition:0.3s;
}

button:hover{
    background:#22c55e;
}

button:disabled{
    opacity:0.7;
    cursor:not-allowed;
}

.success-box{
    background:#dcfce7;
    border:1px solid #86efac;
    border-radius:18px;
    padding:22px;
    margin-bottom:24px;
    text-align:center;
}

.success-icon{
    font-size:50px;
    margin-bottom:14px;
}

.success-box h3{
    color:#15803d;
    margin-bottom:16px;
}

.success-box p{
    margin-bottom:10px;
    color:#166534;
}

.download-section{
    margin-top:24px;
    text-align:center;
}

.download-section h3{
    margin-bottom:18px;
    color:#0f172a;
}

.download-links a{
    display:inline-block;
    margin:10px;
}

.download-links img{
    width:180px;
    max-width:100%;
}

.app-store-btn{
    display:inline-block;
    padding:14px 24px;
    background:#0f172a;
    color:white;
    border-radius:14px;
    text-decoration:none;
    font-weight:500;
}

.footer{
    text-align:center;
    margin-top:24px;
    color:#94a3b8;
    font-size:14px;
}

.toast{
    position:fixed;
    bottom:30px;
    left:50%;
    transform:translateX(-50%);
    color:#ffffff;
    padding:14px 22px;
    border-radius:12px;
    font-size:14px;
    font-weight:600;
    box-shadow:0 8px 20px rgba(0,0,0,0.15);
    z-index:9999;
    opacity:0;
    transition:0.3s;
}

</style>

</head>

<body>

<div class="container">

    <div class="card">

        <div class="logo">

            <div class="logo-box">
                <img src="/assets/Photos/icon-192.png" alt="Drivault logo">
            </div>

            <h1>Drivault</h1>

        </div>

        <?php if (!is_array($accountCreatedDetails)) { ?>

        <h2 class="title">
            Create <span>Password</span>
        </h2>

        <p class="subtitle">
            Set a secure password to complete your account setup.
        </p>

        <form
            id="set-password-form"
            action="../api/set_password.php"
            method="POST"
        >

            <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    placeholder="Enter password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    onclick="togglePassword('password', this)"
                    aria-label="Show password"
                    title="Show password"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        <circle cx="12" cy="12" r="3" stroke-width="1.8"></circle>
                    </svg>
                </button>

                <div
                    class="password-strength"
                    id="passwordStrength"
                ></div>

            </div>

            <div class="form-group">

                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    id="confirm_password"
                    type="password"
                    name="confirm_password"
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    required
                >

                <button
                    type="button"
                    class="password-toggle"
                    onclick="togglePassword('confirm_password', this)"
                    aria-label="Show password"
                    title="Show password"
                >
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                        <circle cx="12" cy="12" r="3" stroke-width="1.8"></circle>
                    </svg>
                </button>

                <div
                    class="match-message"
                    id="matchMessage"
                ></div>

            </div>

            <div class="password-rules is-hidden" id="passwordRules">

                <h3>Password Requirements</h3>

                <p>• Minimum 8 characters</p>
                <p>• At least 1 uppercase letter</p>
                <p>• At least 1 lowercase letter</p>
                <p>• At least 1 number</p>
                <p>• At least 1 special character</p>

            </div>

            <button
                id="createAccountBtn"
                type="submit"
            >
                Create Account
            </button>

            <div
                class="loader"
                id="loader"
            ></div>

        </form>

        <?php } ?>

        <?php if (is_array($accountCreatedDetails)) { ?>

        <div class="success-box">

            <div class="success-icon">
                ✅
            </div>

            <h3>
                Account Created Successfully
            </h3>
        
        </div>

        <div class="download-section">

            <h3>
                Download App
            </h3>

            <div class="download-links">

                <?php if (is_file($googlePlayImagePath)) { ?>

                <a
                    href="<?php echo htmlspecialchars($googlePlayLink, ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >

                    <img
                        src="/assets/Photos/googlePlay.png"
                        alt="Get it on Google Play"
                    >

                </a>

                <?php } else { ?>

                <a
                    class="app-store-btn"
                    href="<?php echo htmlspecialchars($googlePlayLink, ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Download on Google Play
                </a>

                <?php } ?>

                <br><br>

                <a
                    class="app-store-btn"
                    href="<?php echo htmlspecialchars($appStoreLink, ENT_QUOTES, 'UTF-8'); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Download on App Store
                </a>

            </div>

        </div>

        <?php } ?>

        <div class="footer">
            © 2026 Drivault
        </div>

    </div>

</div>

<script>

function showToast(message, type = 'success') {

    const toast = document.createElement('div');

    toast.className = 'toast';

    toast.innerText = message;

    if(type === 'error') {

        toast.style.background = '#ef4444';

    } else {

        toast.style.background = '#22c55e';
    }

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '1';
    }, 100);

    setTimeout(() => {

        toast.style.opacity = '0';

        setTimeout(() => {
            toast.remove();
        }, 300);

    }, 3000);
}

function togglePassword(inputId, element) {

    const input = document.getElementById(inputId);

    const showIcon = `
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            <circle cx="12" cy="12" r="3" stroke-width="1.8"></circle>
        </svg>
    `;

    const hideIcon = `
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M3 3l18 18" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M10.6 5.1A11.7 11.7 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M6.7 6.7C3.9 8.5 2 12 2 12a17.8 17.8 0 0 0 10 7 9.7 9.7 0 0 0 4-.8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
            <path d="M9.9 9.9A3 3 0 0 0 14.1 14.1" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
    `;

    if(input.type === 'password') {

        input.type = 'text';

        element.innerHTML = hideIcon;
        element.setAttribute('aria-label', 'Hide password');
        element.setAttribute('title', 'Hide password');

    } else {

        input.type = 'password';

        element.innerHTML = showIcon;
        element.setAttribute('aria-label', 'Show password');
        element.setAttribute('title', 'Show password');
    }
}

const passwordInput = document.getElementById('password');

const confirmPasswordInput = document.getElementById('confirm_password');

const passwordStrength = document.getElementById('passwordStrength');

const matchMessage = document.getElementById('matchMessage');

const passwordRules = document.getElementById('passwordRules');

const form = document.getElementById('set-password-form');

const loader = document.getElementById('loader');

const createAccountBtn = document.getElementById('createAccountBtn');

function checkPasswordStrength(password) {
    passwordStrength.innerHTML = '';
}

function isPasswordValid(password) {
    return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{10,}$/.test(password);
}

function togglePasswordRules(showRules) {

    if(!passwordRules) {
        return;
    }

    passwordRules.classList.toggle('is-hidden', !showRules);
}

if(passwordRules) {
    passwordRules.innerHTML = `
        <h3>Password Requirements</h3>
        <ul>
            <li>Minimum 10 characters</li>
            <li>At least 1 uppercase letter</li>
            <li>At least 1 lowercase letter</li>
            <li>At least 1 number</li>
            <li>At least 1 special character</li>
        </ul>
    `;
}

passwordInput.addEventListener('input', () => {

    checkPasswordStrength(passwordInput.value);

    if(passwordInput.value === '' || isPasswordValid(passwordInput.value)) {
        togglePasswordRules(false);
        passwordInput.classList.remove('input-error');
    }

    validatePasswordMatch();
});

//confirmPasswordInput.addEventListener('input', validatePasswordMatch);

function validatePasswordMatch() {

    if(confirmPasswordInput.value === '') {

        matchMessage.innerHTML = '';

        confirmPasswordInput.classList.remove('input-error');

        return;
    }

    if(passwordInput.value !== confirmPasswordInput.value) {

        matchMessage.innerHTML = 'Passwords Do Not Match';

        matchMessage.style.color = '#ef4444';

        confirmPasswordInput.classList.add('input-error');

    } else {

        matchMessage.innerHTML = '';

        confirmPasswordInput.classList.remove('input-error');
    }
}

form?.addEventListener('submit', function(event) {

    const password = passwordInput.value;

    const confirmPassword = confirmPasswordInput.value;

    if(!isPasswordValid(password)) {

        event.preventDefault();

        togglePasswordRules(true);
        passwordInput.classList.add('input-error');

        showToast(
    'Password must be at least 10 characters and contain uppercase, lowercase, number and special character',
    'error'
);

        return;
    }

    if(password !== confirmPassword) {

    event.preventDefault();

    matchMessage.innerHTML = 'Passwords Do Not Match';
    matchMessage.style.color = '#ef4444';
    confirmPasswordInput.classList.add('input-error');

    return;
}

    createAccountBtn.disabled = true;

    createAccountBtn.style.display = 'none';

    loader.style.display = 'block';

    showToast('Creating Account...');

});

</script>

</body>

</html><?php
