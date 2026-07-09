<?php

session_start();

require '../config/db.php';

function fetchDrivaultUserForCheckout(string $username): array
{
    $drivaultConfig = require __DIR__ . '/../config/drivault.php';
    $endpoint = trim((string) ($drivaultConfig['endpoint'] ?? 'https://login.drivault.com/ocs/v1.php/cloud/users'));
    $apiUsername = trim((string) ($drivaultConfig['username'] ?? ''));
    $apiPassword = trim((string) ($drivaultConfig['password'] ?? ''));

    if ($endpoint === '' || $apiUsername === '' || $apiPassword === '') {
        throw new RuntimeException('Drivault API is not configured.');
    }

    $curlHandle = curl_init(rtrim($endpoint, '/') . '/' . rawurlencode($username));

    curl_setopt_array($curlHandle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'OCS-APIRequest: true',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($apiUsername . ':' . $apiPassword),
        ],
    ]);

    $responseBody = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    $httpStatus = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
    curl_close($curlHandle);

    if ($responseBody === false) {
        throw new RuntimeException('Unable to connect to Drivault: ' . $curlError);
    }

    $decoded = json_decode((string) $responseBody, true);
    $ocsStatusCode = (int) ($decoded['ocs']['meta']['statuscode'] ?? 0);
    $userData = $decoded['ocs']['data'] ?? [];

    if ($httpStatus === 404 || $ocsStatusCode === 404 || !is_array($userData) || empty($userData)) {
        throw new RuntimeException('User not found.');
    }

    if ($httpStatus >= 400 || ($ocsStatusCode !== 0 && $ocsStatusCode !== 100)) {
        throw new RuntimeException($decoded['ocs']['meta']['message'] ?? 'Unable to fetch user details.');
    }

    return [
        'displayname' => (string) ($userData['displayname'] ?? ''),
        'email' => (string) ($userData['email'] ?? ''),
        'id' => (string) ($userData['id'] ?? $username),
        'phone' => (string) ($userData['phone'] ?? ''),
        'quota' => [
            'used' => $userData['quota']['used'] ?? '',
            'total' => $userData['quota']['total'] ?? '',
        ],
    ];
}

function formatBytesToGb($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric($value)) {
        return (string) $value;
    }

    $gb = ((float) $value) / 1024 / 1024 / 1024;
    $decimals = $gb >= 10 ? 0 : 2;
    $formatted = number_format($gb, $decimals);

    return rtrim(rtrim($formatted, '0'), '.') . ' GB';
}

if (!isset($_GET['plan_id'])) {
    die("Plan not found");
}

$planId = (int)$_GET['plan_id'];
$username = trim((string) ($_GET['username'] ?? ''));

if ($username === '') {
    die("Username not found");
}

$stmt = $conn->prepare(
    "SELECT * FROM plans WHERE id=? AND status=1"
);

$stmt->bind_param("i", $planId);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    die("Invalid plan");
}

try {
    $verifiedUser = fetchDrivaultUserForCheckout($username);
} catch (Throwable $exception) {
    die("Unable to verify Drivault user: " . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$price = $plan['monthly_price'];
$gst = round($price * 0.18, 2);
$total = $price + $gst;

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Checkout - Drivault</title>

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:
        radial-gradient(circle at top left, rgba(56,217,137,.08), transparent 34%),
        linear-gradient(180deg,#f8fafc 0%,#ffffff 100%);
    color:#0f172a;
    font-family:Arial,sans-serif;
}

.checkout-page{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:10px !important;
}

.checkout-shell{
    width:100%;
    max-width:1000px;
    margin:0 auto;
    padding:12px 32px 14px;
    border:1px solid #e5e7eb;
    border-radius:12px;
    background:rgba(255,255,255,.74);
    box-shadow:0 20px 55px rgba(15,23,42,.08);
}

.checkout-topbar{
    display:flex;
    align-items:center;
    min-height:26px;
}

.back-link{
    color:#38d989;
    font-size:15px;
    font-weight:700;
    text-decoration:none;
    display:flex;
    align-items:center;
    gap:10px;
    transition:color .2s ease, transform .2s ease;
}

.back-link:hover{
    color:#2ec477;
    transform:translateX(-2px);
}

.checkout-hero{
    text-align:center;
    margin:-2px auto 18px;
}

.checkout-hero-title{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    margin-bottom:4px;
}

.hero-icon{
    color:#38d989;
    font-size:30px;
    line-height:1;
}

.checkout-hero h1{
    margin:0;
    font-size:30px;
    line-height:1.1;
    font-weight:700;
    color:#0f172a;
    letter-spacing:0;
}

.checkout-hero p{
    margin:0;
    color:#64748b;
    font-size:15px;
}

.checkout-grid{
    display:grid;
    grid-template-columns:minmax(0,1fr) minmax(0,1fr);
    gap:24px;
    align-items:stretch;
}

.checkout-card-panel{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    padding:16px 20px;
    box-shadow:0 12px 30px rgba(15,23,42,.07);
    height:100%;
}

.panel-heading{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:14px;
}

.panel-heading-icon,
.detail-icon,
.summary-icon,
.feature-icon{
    width:50px;
    height:50px;
    border-radius:15px;
    background:#e8fff2;
    color:#38d989;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
    flex-shrink:0;
}

.detail-icon{
    width:36px;
    height:36px;
    border-radius:12px;
    font-size:18px;
}

.panel-heading h2{
    margin:0;
    font-size:22px;
    line-height:1.15;
    font-weight:700;
    color:#0f172a;
}

.profile-block{
    display:flex;
    align-items:center;
    gap:16px;
    padding:0 0 14px;
    border-bottom:1px solid #e5e7eb;
    margin-bottom:10px;
}

.profile-avatar{
    width:66px;
    height:66px;
    border-radius:50%;
    background:linear-gradient(135deg,#38d989,#2ec477);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:38px;
    font-weight:800;
    box-shadow:0 12px 28px rgba(56,217,137,.28);
    flex-shrink:0;
}

.profile-name{
    color:#0f172a;
    font-size:18px;
    font-weight:700;
    margin-bottom:4px;
    overflow-wrap:anywhere;
}

.profile-status{
    color:#64748b;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:10px;
}

.profile-status i{
    color:#38d989;
}

.detail-list{
    display:flex;
    flex-direction:column;
}

.detail-row{
    display:grid;
    grid-template-columns:36px minmax(110px,1fr) minmax(0,1.35fr);
    gap:12px;
    align-items:center;
    padding:8px 0;
    border-bottom:1px solid #e5e7eb;
}

.detail-row:last-child{
    border-bottom:none;
}

.detail-label{
    color:#64748b;
    font-size:15px;
    font-weight:600;
}

.detail-value{
    color:#0f172a;
    font-size:15px;
    font-weight:500;
    text-align:right;
    overflow-wrap:anywhere;
}

.plan-preview{
    display:flex;
    align-items:center;
    gap:14px;
    padding:12px 16px;
    border:1px solid #38d989;
    border-radius:14px;
    background:linear-gradient(135deg,rgba(56,217,137,.08),rgba(255,255,255,.92));
    margin-bottom:14px;
}

.summary-icon{
    width:48px;
    height:48px;
    border-radius:50%;
    font-size:26px;
}

.plan-name{
    color:#0f172a;
    font-size:18px;
    font-weight:700;
    margin-bottom:4px;
}

.plan-storage{
    color:#64748b;
    font-size:15px;
    font-weight:500;
}

.order-lines{
    margin-bottom:8px;
}

.order-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:16px;
    padding:7px 0;
    color:#64748b;
    font-size:15px;
}

.order-value{
    color:#0f172a;
    font-weight:700;
    white-space:nowrap;
}

.order-divider{
    border:0;
    border-top:1px dashed #cbd5e1;
    margin:8px 0 12px;
}

.total-box{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    background:linear-gradient(135deg,#f0fff6,#f8fffb);
    border:1px solid #e0f5e9;
    border-radius:12px;
    padding:10px 14px;
    margin-bottom:12px;
}

.total-label{
    color:#38d989;
    font-size:16px;
    font-weight:700;
}

.total{
    color:#38d989;
    font-size:26px;
    font-weight:700;
    line-height:1;
    white-space:nowrap;
}

.secure-list{
    background:linear-gradient(135deg,#f7fffb,#f8fafc);
    border-radius:12px;
    padding:9px 14px;
    margin-bottom:12px;
}

.checkout-card-panel{
    min-height:0;
}

.secure-item{
    display:flex;
    align-items:center;
    gap:12px;
    color:#64748b;
    font-size:14px;
    padding:2px 0;
}

.secure-item .feature-icon{
    width:auto;
    height:auto;
    border-radius:0;
    background:transparent;
    color:#38d989;
    font-size:18px;
}

.btn-pay{
    background:#38d989;
    border:none;
    min-height:48px;
    padding:11px;
    font-size:17px;
    font-weight:700;
    border-radius:12px;
    transition:transform .2s ease, box-shadow .2s ease, background .2s ease;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:14px;
}

.btn-pay:hover{
    background:#2ec477;
    transform:translateY(-1px);
    box-shadow:0 16px 30px rgba(56,217,137,.28);
}

.btn-pay:disabled{
    background:#38d989;
    border:none;
    opacity:.8;
}

.checkout-action{
    max-width:560px;
    margin:28px auto 0;
}

.pay-spinner{
    width:1rem;
    height:1rem;
    border:2px solid rgba(255,255,255,.45);
    border-top-color:#fff;
    border-radius:50%;
    display:inline-block;
    margin-right:8px;
    vertical-align:-2px;
    animation:paySpin .8s linear infinite;
}

.payment-note{
    color:#64748b;
    font-size:13px;
    text-align:center;
    margin:8px 0 0;
}

.checkout-footer-note{
    color:#64748b;
    text-align:center;
    font-size:13px;
    margin-top:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:12px;
}

.checkout-footer-note i{
    color:#94a3b8;
}

@keyframes paySpin{
    to{
        transform:rotate(360deg);
    }
}

.custom-toast {
    visibility: hidden;
    min-width: 350px;
    max-width: 450px;
    background: #ef4444;
    color: #fff;
    border: 1px solid #dc2626;
    border-radius: 14px;
    padding: 16px 24px;
    position: fixed;
    z-index: 99999;
    top: 30px;
    left: 50%;
    transform: translate(-50%, -14px);
    font-size: 15px;
    font-weight: 500;
    box-shadow: 0 12px 30px rgba(56,217,137,.25);
    opacity: 0;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    line-height:1.35;
    transition: opacity .3s ease, transform .3s ease, visibility .3s ease;
}

.custom-toast.show {
    visibility: visible;
    opacity: 1;
    transform: translate(-50%, 0);
}

.custom-toast i {
    color:#fff;
    font-size:18px;
    flex-shrink:0;
}

@media (max-width: 991px){
    .checkout-grid{
        grid-template-columns:1fr;
        gap:24px;
    }

    .checkout-shell{
        padding:20px;
    }
}

@media (max-height: 760px) and (min-width: 992px){
    .checkout-footer-note{
        display:none;
    }
}

@media (max-width: 768px){
    .checkout-page{
        padding-left:0 !important;
        padding-right:0 !important;
    }

    .checkout-shell{
        border-radius:0;
        border-left:0;
        border-right:0;
        padding:18px 14px 26px;
    }

    .checkout-topbar{
        margin-bottom:18px;
    }

    .checkout-hero{
        margin-bottom:28px;
    }

    .checkout-hero-title{
        gap:12px;
    }

    .hero-icon{
        font-size:34px;
    }

    .checkout-hero h1{
        font-size:34px;
    }

    .checkout-hero p{
        font-size:17px;
    }

    .checkout-card-panel{
        padding:22px 18px;
        border-radius:18px;
    }

    .panel-heading h2{
        font-size:24px;
    }

    .profile-block{
        align-items:flex-start;
        gap:16px;
    }

    .profile-avatar{
        width:82px;
        height:82px;
        font-size:44px;
    }

    .profile-name{
        font-size:21px;
    }

    .profile-status{
        font-size:16px;
    }

    .detail-row{
        grid-template-columns:46px minmax(0,1fr);
        gap:14px;
    }

    .detail-icon{
        width:46px;
        height:46px;
    }

    .detail-label{
        font-size:16px;
    }

    .detail-value{
        grid-column:2;
        text-align:left;
        font-size:16px;
        margin-top:-10px;
    }

    .plan-preview{
        padding:18px;
        align-items:flex-start;
    }

    .summary-icon{
        width:58px;
        height:58px;
        font-size:30px;
    }

    .order-row{
        font-size:17px;
    }

    .total-box{
        flex-direction:column;
        align-items:flex-start;
        gap:8px;
    }

    .total{
        font-size:34px;
    }

    .btn-pay{
        min-height:60px;
        font-size:20px;
    }

    .checkout-action{
        max-width:100%;
        margin-top:24px;
    }

    .custom-toast{
        width:90%;
        min-width:auto;
        max-width:90%;
    }

    .row .col-md-6:first-child{
        margin-bottom:30px;
    }
}
</style>

</head>
<body>

<div class="checkout-page px-3 py-3">
<div class="checkout-shell">

<div class="checkout-topbar">
<a href="pricing.php" class="back-link">
    <i class="bi bi-arrow-left"></i>
    <span>Back to Plans</span>
</a>
</div>

<header class="checkout-hero">
    <div class="checkout-hero-title">
        <i class="bi bi-shield-check hero-icon"></i>
        <h1>Checkout</h1>
    </div>
    <p>Complete your payment and upgrade your storage</p>
</header>

<div class="checkout-grid">

<section class="checkout-card-panel">
    <div class="panel-heading">
        <div class="panel-heading-icon">
            <i class="bi bi-person"></i>
        </div>
        <h2>User Details</h2>
    </div>

    <div class="profile-block">
        <div class="profile-avatar">
            <?= htmlspecialchars(strtoupper(substr($verifiedUser['displayname'] ?: $verifiedUser['id'] ?: 'U', 0, 1)), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div>
            <div class="profile-name">
                <?= htmlspecialchars($verifiedUser['displayname'] ?: '-') ?>
            </div>
            <div class="profile-status">
                <span>Premium Account</span>
                <i class="bi bi-patch-check-fill"></i>
            </div>
        </div>
    </div>

    <div class="detail-list">
        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-envelope"></i></div>
            <div class="detail-label">Email Address</div>
            <div class="detail-value"><?= htmlspecialchars($verifiedUser['email'] ?: '-') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-person-badge"></i></div>
            <div class="detail-label">User ID</div>
            <div class="detail-value"><?= htmlspecialchars($verifiedUser['id'] ?: '-') ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-pie-chart"></i></div>
            <div class="detail-label">Current Storage Used</div>
            <div class="detail-value">
                <?= htmlspecialchars(formatBytesToGb($verifiedUser['quota']['used'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-database"></i></div>
            <div class="detail-label">Total Storage</div>
            <div class="detail-value">
                <?= htmlspecialchars(formatBytesToGb($verifiedUser['quota']['total'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
</section>

<section class="checkout-card-panel">
    <div class="panel-heading">
        <div class="panel-heading-icon">
            <i class="bi bi-credit-card"></i>
        </div>
        <h2>Order Summary</h2>
    </div>

    <div class="plan-preview">
        <div class="summary-icon">
            <i class="bi bi-box-seam"></i>
        </div>
        <div>
            <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
            <div class="plan-storage"><?= htmlspecialchars((string)$plan['quota']) ?> Storage</div>
        </div>
    </div>

    <div class="order-lines">
        <div class="order-row">
            <span>Monthly Price</span>
            <span class="order-value">&#8377;<?= number_format((float)$price,2) ?></span>
        </div>

        <div class="order-row">
            <span>GST (18%)</span>
            <span class="order-value">&#8377;<?= number_format((float)$gst,2) ?></span>
        </div>
    </div>

    <hr class="order-divider">

    <div class="total-box">
        <span class="total-label">Total Amount</span>
        <span class="total">&#8377;<?= number_format((float)$total,2) ?></span>
    </div>

    <div class="secure-list">
        <div class="secure-item">
            <i class="bi bi-shield-check feature-icon"></i>
            <span>Secure Payment</span>
        </div>
        <div class="secure-item">
            <i class="bi bi-shield-check feature-icon"></i>
            <span>Instant Storage Upgrade</span>
        </div>
        <div class="secure-item">
            <i class="bi bi-shield-check feature-icon"></i>
            <span>Powered by Razorpay</span>
        </div>
    </div>

</section>

</div>

<div class="checkout-action">
    <button
        class="btn btn-pay btn-success w-100"
        id="payBtn">
        <i class="bi bi-lock-fill"></i>
        <span>Proceed to Pay</span>
    </button>

    <p class="payment-note">
        You will be redirected to Razorpay to complete your payment securely.
    </p>
</div>

<div class="checkout-footer-note">
    <i class="bi bi-lock"></i>
    <span>Your payment information is secure and encrypted</span>
</div>

</div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>

const payBtn = document.getElementById('payBtn');
const verifiedUser = <?= json_encode($verifiedUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const payButtonText = '<i class="bi bi-lock-fill"></i><span>Proceed to Pay</span>';

function showToast(message, type = 'error') {

    const toast = document.getElementById('toast');
    const isSuccess = type === 'success';
    const icon = isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
    const escapedMessage = String(message)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    toast.innerHTML = '<i class="bi ' + icon + '"></i><span>' + escapedMessage + '</span>';
    toast.style.background = isSuccess ? '#38d989' : '#ef4444';
    toast.style.borderColor = isSuccess ? '#2ec477' : '#dc2626';

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function displayValue(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
}

function setPayLoading(text) {
    payBtn.innerHTML = '<span class="pay-spinner"></span>' + text;
    payBtn.disabled = true;
}

function resetPayButton() {
    payBtn.innerHTML = payButtonText;
    payBtn.disabled = false;
}

payBtn.addEventListener('click', async function() {
    const customerName = displayValue(verifiedUser.displayname);
    const customerEmail = displayValue(verifiedUser.email);
    const customerUserId = displayValue(verifiedUser.id);
    const customerPhone = verifiedUser.phone ? String(verifiedUser.phone) : '';
    const storageAccountId = customerUserId;

    setPayLoading('Creating order...');

    let data;

    try {
        let response = await fetch(
            '../api/create-order.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    user_id: customerUserId,
                    plan_id: '<?= $planId ?>',
                    billing_cycle: 'monthly',
                    name: customerName,
                    email: customerEmail,
                    phone: customerPhone || storageAccountId
                })
            }
        );

        data = await response.json();

    } catch (error) {

        resetPayButton();
        console.error(error);
        showToast('Something went wrong while creating the order.');
        return;
    }

    if(!data.success){
        resetPayButton();

        showToast(data.message);

        return;
    }

    let options = {

        modal: {
            ondismiss: function() {
                resetPayButton();
            }
        },

        key:data.key,

        amount:data.amount,

        currency:"INR",

        name:"Drivault",

        description:
        "Storage Upgrade",

        order_id:data.order_id,

        prefill:{
            name: customerName,
            email: customerEmail,
            contact: customerPhone
        },

        handler:function(payment){

            setPayLoading('Verifying payment...');

            fetch(
                '../api/payment-success.php',
                {
                    method:'POST',
                    headers:{
                        'Content-Type':
                        'application/x-www-form-urlencoded'
                    },
                    body:new URLSearchParams({
                        razorpay_order_id:
                        payment.razorpay_order_id,

                        razorpay_payment_id:
                        payment.razorpay_payment_id,

                        razorpay_signature:
                        payment.razorpay_signature,

                        name: customerName,
                        email: customerEmail,
                        phone: customerPhone || storageAccountId
                    })
                }
            )
            .then(r=>r.json())
            .then(res=>{
                if(res.success){
                    window.location =
                        "payment-success.php?payment_id=" +
                        encodeURIComponent(res.payment_id) +
                        "&order_id=" +
                        encodeURIComponent(res.order_id) +
                        "&subscription_id=" +
                        encodeURIComponent(res.subscription_id) +
                        "&plan_id=<?= $planId ?>";
                    return;
                }

                window.location =
                    "payment-failed.php?plan_id=<?= $planId ?>" +
                    "&order_id=" +
                    encodeURIComponent(payment.razorpay_order_id || data.order_id) +
                    "&reason=" +
                    encodeURIComponent(res.message || "Payment verification failed");
            })
            .catch(()=>{
                window.location =
                    "payment-failed.php?plan_id=<?= $planId ?>&reason=" +
                    encodeURIComponent("Unable to verify payment. Please try again.");
            });

        }

    };

    let rzp = new Razorpay(options);

    rzp.on('payment.failed', function(response){
        let message = response.error && response.error.description
            ? response.error.description
            : "Payment failed. Please try again.";

        window.location =
            "payment-failed.php?plan_id=<?= $planId ?>" +
            "&order_id=" +
            encodeURIComponent(data.order_id) +
            "&reason=" +
            encodeURIComponent(message);
    });

    setPayLoading('Complete payment in Razorpay...');

    rzp.open();

});

</script>
<div id="toast" class="custom-toast"></div>
</body>
</html>
