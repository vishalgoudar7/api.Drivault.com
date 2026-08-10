<?php
require '../config/db.php';

function pricingJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function fetchDrivaultUserForPricing(string $username): array
{
    $drivaultConfig = require __DIR__ . '/../config/drivault.php';
    $endpoint = trim((string) ($drivaultConfig['endpoint'] ?? 'https://login.drivault.com/ocs/v1.php/cloud/users'));
    $apiUsername = trim((string) ($drivaultConfig['username'] ?? ''));
    $apiPassword = trim((string) ($drivaultConfig['password'] ?? ''));

    if ($endpoint === '' || $apiUsername === '' || $apiPassword === '') {
        throw new RuntimeException('Drivault API is not configured.');
    }

    $curlHandle = curl_init(rtrim($endpoint, '/') . '?search=' . rawurlencode($username));

    curl_setopt_array($curlHandle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $apiUsername . ':' . $apiPassword,
        CURLOPT_HTTPHEADER => [
            'OCS-APIRequest: true',
            'Accept: application/json',
        ],
    ]);

    $responseBody = curl_exec($curlHandle);
    $curlError = curl_error($curlHandle);
    $httpStatus = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
    curl_close($curlHandle);

    if ($responseBody === false) {
        throw new RuntimeException('Unable to connect to Drivault: ' . $curlError);
    }

    if ($httpStatus >= 400) {
        $decoded = json_decode((string) $responseBody, true);
        throw new RuntimeException($decoded['ocs']['meta']['message'] ?? 'Unable to fetch user details.');
    }

    $decoded = json_decode((string) $responseBody, true);
    $users = $decoded['ocs']['data']['users'] ?? [];
    $users = is_array($users) ? array_values($users) : [];
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL) !== false;
    $isVerified = false;

    foreach ($users as $user) {
        $user = trim((string) $user);

        if ($isEmail || strcasecmp($user, $username) === 0) {
            $isVerified = true;
            break;
        }
    }

    if (!$isVerified) {
        throw new RuntimeException('User not found.');
    }

    return [
        'id' => $username,
    ];
}

if (($_GET['action'] ?? '') === 'get_user_details') {
    $username = trim((string) ($_GET['username'] ?? ''));

    if ($username === '') {
        pricingJsonResponse(422, [
            'success' => false,
            'message' => 'Please enter your Drivault username or email.',
        ]);
    }

    if (!preg_match('/^[a-zA-Z0-9._@+\-]{2,100}$/', $username)) {
        pricingJsonResponse(422, [
            'success' => false,
            'message' => 'Please enter a valid username.',
        ]);
    }

    try {
        pricingJsonResponse(200, [
            'success' => true,
            'message' => 'User verified successfully.',
            'username' => $username,
            'user' => fetchDrivaultUserForPricing($username),
        ]);
    } catch (Throwable $exception) {
        pricingJsonResponse(404, [
            'success' => false,
            'message' => $exception->getMessage(),
        ]);
    }
}

$result = $conn->query("
    SELECT *
    FROM plans
    WHERE status = 1
    ORDER BY monthly_price ASC
");

$plans = $result->fetch_all(MYSQLI_ASSOC);

function formatRoundedPrice(mixed $amount): string
{
    return number_format((int) ceil((float) $amount), 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Drivault Storage Plans</title>
<link rel="icon" type="image/x-icon" href="../assets/Photos/favicon.ico">
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f8fafc;
    font-family:Arial,sans-serif;
}

.drivault-navbar{
    position:sticky;
    top:0;
    z-index:1030;
    background:#fff;
    min-height:72px;
    padding:0 48px;
    border-bottom:1px solid #edf1f5;
    box-shadow:0 2px 14px rgba(15,23,42,.06);
}

.drivault-navbar .container-fluid{
    padding:0;
    min-height:72px;
    display:flex;
    align-items:center;
    justify-content:flex-start;
}

.drivault-navbar .navbar-brand{
    display:inline-flex;
    align-items:center;
    gap:12px;
    margin:0;
    padding:0;
}

.drivault-navbar .navbar-brand img{
    width:32px;
    height:32px;
    object-fit:contain;
    flex-shrink:0;
}

.drivault-navbar .navbar-brand strong{
    color:#0f172a;
    font-size:1.5rem;
    font-weight:700;
    line-height:2rem;
}

.drivault-navbar .navbar-toggler,
.drivault-navbar .navbar-collapse{
    display:none;
}

.old-price{
    color:#999;
    text-decoration:line-through;
    font-size:14px;
    margin:0 8px 0 0;
}

.save-badge{
    display:inline-block;
    margin-top:10px;
    background:#e8fff2;
    color:#38d989;
    padding:7px 11px;
    border-radius:8px;
    font-weight:600;
    font-size:12px;
    line-height:1.25;
}

.section-title{
    font-size:30px;
    font-weight:700;
    color:#0f172a;
    line-height:1.2;
}

.section-subtitle{
    color:#64748b;
    font-size:20px;
}
.feature-box{
    background:#f4fcf8;
    border:1px solid #e4f5eb;
    border-radius:15px;
    padding:25px;
}

.icon-box{
    width:48px;
    height:48px;
    background:#e8fff2;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.icon-box i{
    color:#38d989;
    font-size:24px;
}


.popular{
    border-color:#38d989;
    box-shadow:0 16px 36px rgba(56,217,137,.15);
}

.popular-badge{
    position:absolute;
    top:-12px;
    left:50%;
    transform:translateX(-50%);
    background:#38d989;
    color:#fff;
    padding:8px 20px;
    border-radius:999px;
    font-size:14px;
    font-weight:600;
    white-space:nowrap;
    z-index:10;
    box-shadow:0 8px 18px rgba(56,217,137,.25);
}

.storage-icon{
    width:82px;
    height:82px;
    background:#e8fff2;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 22px;
    font-size:35px;
}

.storage{
    font-size:24px;
    font-weight:700;
    color:#0f172a;
    margin-top:0;
    margin-bottom:8px;
}

.plan-name-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    align-self:center;
    max-width:100%;
    margin:0 auto 20px;
    padding:8px 18px;
    border-radius:8px;
    background:#e8fff2;
    color:#07983f;
    font-size:15px;
    font-weight:700;
    line-height:1.2;
    font-family:Arial,sans-serif;
}

.pricing-card .text-muted{
    margin-bottom:20px;
    color:#64748b !important;
}

.pricing-card hr{
    width:100%;
    margin:0 0 24px;
    border-color:#e5e7eb;
    opacity:1;
}

.price{
    color:#0f172a;
    font-size:30px;
    font-weight:700;
    line-height:1;
    white-space:nowrap;
    margin:0;
}

.price small{
    font-size:14px;
    color:#64748b;
    font-weight:600;
    white-space:nowrap;
}

.pricing-wrapper{margin-bottom:20px}
.monthly-price,.yearly-price{padding:0 2px;transition:opacity .2s ease}
.price-divider{display:flex;align-items:center;gap:10px;margin:12px 0;color:#64748b;font-size:11px;font-weight:700}
.price-divider::before,.price-divider::after{content:"";height:1px;background:#e5e7eb;flex:1}
.yearly-price-line{display:flex;align-items:baseline;justify-content:center;flex-wrap:wrap;row-gap:5px}
.monthly-price .price,
.yearly-price .price{
    color:#0f172a !important;
}

.monthly-price.is-selected .price,
.yearly-price.is-selected .price{
    color:#08b957 !important;
}

.discount-badge{
    position:absolute;
    top:17px;
    right:14px;
    padding:5px 9px;
    border-radius:7px;
    background:#e7fbed;
    color:#07983f;
    font-size:11px;
    font-weight:700;
    line-height:1;
}

.btn-plan,
.btn-verify{
    border:2px solid #38d989;
    color:#38d989;
    border-radius:12px;
    min-height:52px;
    padding:12px;
    width:100%;
    font-weight:600;
    display:flex;
    align-items:center;
    justify-content:center;
    transition:all .25s ease;
}

.btn-plan.opening{
    background:#38d989 !important;
    border-color:#38d989 !important;
    color:#fff !important;
    pointer-events:none;
}

.btn-plan.opening .spinner-border{
    color:#fff;
}

/* Loading state for Choose Plan button */
.btn-plan.loading{
    background:#38d989 !important;
    border-color:#38d989 !important;
    color:#fff !important;
    pointer-events:none;
}

.btn-plan.loading:hover{
    background:#38d989 !important;
    border-color:#38d989 !important;
    color:#fff !important;
}

.btn-plan.loading .spinner-border{
    color:#fff;
}
.btn-verify,
.btn-plan:hover{
    background:#38d989;
    color:#fff;
}

.btn-verify:hover{
    background:#2ec477;
    border-color:#2ec477;
    color:#fff;
}

.btn-popular{
    background:#38d989;
    color:#fff;
}
.pricing-card{
    background:#fff;
    border-radius:20px;
    padding:26px 20px;

    border:2px solid transparent;

    box-shadow:0 10px 25px rgba(0,0,0,.08);

    position:relative;

    transition:all .3s ease;

    display:flex;
    flex-direction:column;

    height:100%;
    min-height:520px;
}

.pricing-card.popular{
    border-color:#38d989;
    box-shadow:0 10px 25px rgba(0,0,0,.08), 0 0 0 1px rgba(56,217,137,.18);
}

.pricing-card:hover{
    border-color:#38d989;
    transform:translateY(-8px);
    box-shadow:0 16px 36px rgba(56,217,137,.18);
}
.popular .btn-plan{
    background:#38d989;
    color:#fff;
    border:2px solid #38d989;
}
.pricing-card:hover .btn-plan{
    background:#38d989;
    color:#fff;
    border-color:#38d989;
}
.pricing-card:hover .storage-icon{
    background:#dff8ea;
}

.check-icon{
    color:#38d989;
    font-size:18px;
    margin-right:12px;
    flex-shrink:0;
    width:auto;
    height:auto;
    background:none;
    border-radius:0;
    display:inline-block;
}
.feature-list li{
    display:flex;
    align-items:flex-start;
    margin-bottom:18px;
    color:#64748b;
    font-size:15px;
    line-height:1.45;
}

.list-unstyled li{
    margin-bottom:12px;
    color:#64748b;
    font-size:15px;
}
.pricing-card-inner{
    display:flex;
    flex-direction:column;
    height:100%;
    text-align:center;
}

.feature-list{
    flex:1;
    min-height:142px;
    margin-bottom:24px;
    margin-top:0 !important;
}

.pricing-card .btn-plan{
    margin-top:auto;
}

.pricing-grid{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:25px;
    align-items:stretch;
}

.pricing-grid > div{min-width:0}

.modal-backdrop.show{
    opacity:.68;
    background:#111827;
}

#verifyPlanModal .modal-dialog{
    max-width:640px;
}

.verify-modal-content{
    border:1px solid rgba(226,232,240,.9);
    border-radius:22px;
    box-shadow:0 24px 70px rgba(15,23,42,.32);
    overflow:hidden;
}

.verify-modal-header{
    border-bottom:none;
    padding:16px 22px 0;
    justify-content:flex-end;
}

.verify-modal-header .btn-close{
    width:24px;
    height:24px;
    margin:0;
    opacity:.68;
    transform:scale(1.15);
}

.verify-modal-body{
    padding:0 52px 28px;
}

.verify-hero{
    text-align:center;
}

.verify-logo-halo{
    width:auto;
    height:auto;
    margin:-6px auto 8px;
    border-radius:0;
    background:transparent;
    display:flex;
    align-items:center;
    justify-content:center;
    position:relative;
}

.verify-logo-halo::before,
.verify-logo-halo::after{
    content:none;
}

.verify-logo{
    width:54px;
    height:54px;
    border-radius:0;
    object-fit:contain;
    box-shadow:none;
    position:relative;
    z-index:1;
}

.verify-modal-title{
    color:#0f172a;
    font-size:28px;
    font-weight:800;
    line-height:1.15;
    margin:0 0 12px;
}

.verify-modal-title span{
    color:#12b76a;
}

.verify-subtitle{
    max-width:460px;
    margin:0 auto 24px;
    color:#64748b;
    font-size:17px;
    line-height:1.45;
}

.verify-form-label{
    color:#111827;
    font-size:17px;
    font-weight:800;
    margin-bottom:10px;
}

.verify-input-wrap{
    position:relative;
}

.verify-input-icon{
    position:absolute;
    left:20px;
    top:50%;
    transform:translateY(-50%);
    color:#12b76a;
    font-size:22px;
    pointer-events:none;
}

.verify-input{
    border:1px solid #cbd5e1;
    border-radius:10px;
    min-height:58px;
    padding:14px 18px 14px 58px;
    color:#0f172a;
    font-size:17px;
    box-shadow:none;
}

.verify-input:focus{
    border-color:#12b76a;
    box-shadow:0 0 0 4px rgba(18,183,106,.12);
}

.verify-input::placeholder{
    color:#94a3b8;
}

.verify-trust-strip{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:0;
    margin:20px 0 22px;
    border-radius:12px;
    background:linear-gradient(90deg,#f3fbf7,#eef9f3);
    overflow:hidden;
}

.verify-trust-item{
    display:flex;
    align-items:center;
    gap:12px;
    min-height:72px;
    padding:14px 18px;
    color:#475569;
    font-size:14px;
    font-weight:700;
    line-height:1.35;
}

.verify-trust-item + .verify-trust-item{
    border-left:1px solid #dbe7df;
}

.verify-trust-item i{
    color:#12b76a;
    font-size:25px;
    flex:0 0 auto;
}

.verify-modal-footer{
    border-top:none;
    padding:0;
    display:block;
}

.btn-verify-modal{
    background:transparent;
    border:2px solid #38d989;
    color:#38d989;
    border-radius:12px;
    min-height:52px;
    padding:12px;
    width:100%;
    font-size:16px;
    font-weight:600;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:none;
    transition:all .25s ease;
}

.btn-verify-modal:hover{
    background:#38d989;
    border-color:#38d989;
    color:#fff;
}

.btn-verify-modal.summary-action{
    background:#38d989;
    border-color:#38d989;
    color:#fff;
}

.btn-verify-modal.summary-action:hover{
    background:#2ec477;
    border-color:#2ec477;
    color:#fff;
}

.btn-verify-modal:disabled{
    opacity:.78;
    pointer-events:none;
}

.verify-help{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    margin:20px 0 0;
    color:#64748b;
    font-size:14px;
    text-align:center;
}

.verify-help i{
    color:#64748b;
    font-size:18px;
}

.verify-help span{
    color:#12b76a;
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

.billing-switch{
    width:380px;
    max-width:100%;
    margin:auto;
    background:rgba(255,255,255,.96);
    border:1px solid #dfe5ec;
    border-radius:999px;
    padding:6px;
    display:flex;
    gap:4px;
    box-shadow:0 10px 24px rgba(15,23,42,.09);
}

.billing-option{
    flex:1;
    text-align:center;
    padding:7px 14px 8px;
    border:0;
    border-radius:999px;
    cursor:pointer;
    transition:background-color .25s ease, color .25s ease, box-shadow .25s ease, transform .25s ease;
    font-size:17px;
    font-weight:700;
    line-height:1.1;
    color:#0f172a;
    user-select:none;
}

.billing-option small{
    display:block;
    margin-top:4px;
    color:#64748b;
    font-size:13px;
    font-weight:400;
    line-height:1;
}

.billing-option.active{
    background:#40e095;
    color:#fff;
    box-shadow:0 7px 16px rgba(64,224,149,.28);
}

.billing-option.active small{
    color:rgba(255,255,255,.78);
}

.billing-option:focus-visible{
    outline:3px solid rgba(64,224,149,.28);
    outline-offset:2px;
}

.billing-option:active{
    transform:scale(.985);
}

@media (max-width: 992px){
    .section-title{
        font-size:40px;
    }

    .section-subtitle{
        font-size:18px;
    }

    .pricing-card{
        margin-bottom:20px;
    }

    .pricing-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

}

@media (min-width:993px) and (max-width:1199px){
    .pricing-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}

@media (max-width:576px){
    .billing-switch{
        width:100%;
    }

    .billing-option{
        padding-inline:8px;
        font-size:16px;
    }

    .popular-badge{
        font-size:11px;
        padding:6px 12px;
        top:-10px;
    }

    .pricing-grid{
        grid-template-columns:1fr;
    }

    .pricing-card{
        min-height:auto;
    }
}
@media (max-width:768px){
    .pricing-card{
        width:100%;
        margin:0;
    }

    .section-title{
        font-size:32px;
    }

    .price{
        font-size:30px;
    }

    .feature-list li{
        font-size:14px;
    }

    #verifyPlanModal .modal-dialog{
        max-width:calc(100% - 24px);
        margin:12px auto;
    }

    .verify-modal-content{
        border-radius:22px;
    }

    .verify-modal-header{
        padding:22px 22px 0;
    }

    .verify-modal-body{
        padding:0 22px 26px;
    }

    .verify-logo-halo{
        width:auto;
        height:auto;
    }

    .verify-logo{
        width:48px;
        height:48px;
        border-radius:0;
    }

    .verify-modal-title{
        font-size:24px;
    }

    .verify-subtitle{
        font-size:16px;
    }

    .verify-form-label{
        font-size:17px;
    }

    .verify-input{
        min-height:62px;
        font-size:16px;
        padding-left:58px;
    }

    .verify-input-icon{
        left:20px;
        font-size:22px;
    }

    .verify-trust-strip{
        grid-template-columns:1fr;
    }

    .verify-trust-item{
        min-height:64px;
        padding:14px 18px;
    }

    .verify-trust-item + .verify-trust-item{
        border-left:none;
        border-top:1px solid #dbe7df;
    }

    .btn-verify-modal{
        min-height:56px;
        font-size:17px;
    }
}

@media (min-width:1200px){
    .pricing-grid{
        grid-template-columns:repeat(5,minmax(0,1fr));
    }
}

@media (max-width: 768px){
    .section-title{
        font-size:30px;
        line-height:1.3;
    }

    .section-subtitle{
        font-size:16px;
        padding:0 10px;
    }

    .storage{
        font-size:22px;
    }

    .price{
        font-size:30px;
    }

    .feature-box .row > div{
        margin-bottom:20px;
    }

    .navbar-brand strong{
        font-size:24px !important;
    }

    .popular-badge{
        font-size:12px;
        padding:6px 15px;
    }

}

@media (max-width: 576px){
    .container{
        padding-left:15px;
        padding-right:15px;
    }

    .drivault-navbar{
        min-height:64px;
        padding:0 20px;
    }

    .drivault-navbar .container-fluid{
        min-height:64px;
        align-items:center;
        justify-content:flex-start;
        flex-direction:row;
        flex-wrap:nowrap;
    }

    .drivault-navbar .navbar-brand{
        flex-direction:row;
        text-align:left;
        min-width:0;
        margin-right:0;
    }

    .drivault-navbar .navbar-brand img{
        width:28px;
        height:28px;
        margin-right:0 !important;
        flex-shrink:0;
    }

    .drivault-navbar .navbar-brand strong{
        font-size:24px !important;
    }

    .pricing-card{
        padding:20px;
    }

    .storage-icon{
        width:72px;
        height:72px;
    }

    .price{
        font-size:28px;
    }

    .btn-plan{
        font-size:15px;
    }

    .feature-box{
        padding:20px 15px;
    }

    .d-flex.align-items-center{
        flex-direction:column;
        text-align:center;
    }

    .ms-3{
        margin-left:0 !important;
        margin-top:10px;
    }
}
</style>

</head>
<body>
<nav class="navbar drivault-navbar">
    <div class="container-fluid">

        <div class="navbar-brand d-flex align-items-center">
            <img src="../assets/Photos/nav-logo.png"
                 alt="Drivault logo"
                 width="38"
                 height="38">
            <strong>Drivault</strong>
        </div>

    </div>
</nav>

<div class="container-fluid px-4 py-3">

    <div class="text-center mb-3">
        <h1 class="section-title">
            Upgrade Your Storage
        </h1>

        <p class="section-subtitle">
             Choose a storage plan and verify your account before checkout.
        </p>
        <div class="billing-switch mt-4 mb-5" role="group" aria-label="Billing period">

    <button type="button" id="monthlyBtn" class="billing-option active" aria-pressed="true">
        Monthly
        <small>Pay as you go</small>
    </button>

    <button type="button" id="yearlyBtn" class="billing-option" aria-pressed="false">
        Yearly
        <small>Save 20%</small>
    </button>

</div>
    </div>

    <div class="pricing-grid">

        <?php foreach($plans as $plan): ?>

       <div>
            <div class="pricing-card <?= ($plan['id'] == 3) ? 'popular' : '' ?>">

              <?php if($plan['id'] == 3): ?>
                    <div class="popular-badge">
                        ★ MOST POPULAR
                    </div>
                <?php endif; ?>

                <div class="discount-badge">
                    <?= (int) $plan['discount_percent']; ?>% OFF
                </div>

                <div class="pricing-card-inner">

                    <div class="storage-icon">
    <svg width="40" height="40" fill="#38d989"
         xmlns="http://www.w3.org/2000/svg">
        <path d="M20 3C11 3 4 6 4 10v20c0 4 7 7 16 7s16-3 16-7V10c0-4-7-7-16-7zm0 4c7 0 12 2 12 3s-5 3-12 3-12-2-12-3 5-3 12-3zm0 10c7 0 12-2 12-3v5c0 1-5 3-12 3s-12-2-12-3v-5c0 1 5 3 12 3zm0 10c7 0 12-2 12-3v5c0 1-5 3-12 3s-12-2-12-3v-5c0 1 5 3 12 3z"/>
    </svg>
</div>

                    <div class="plan-name-badge">
                        <?= htmlspecialchars((string) $plan['name']); ?>
                    </div>

                    <div class="storage">
                        <?= htmlspecialchars((string) $plan['quota']); ?>
                    </div>

                    <p class="text-muted">More Storage</p>

                    <hr>

                    <div class="pricing-wrapper">

    <div class="monthly-price is-selected">
        <div class="price">
            ₹<?= formatRoundedPrice($plan['monthly_price']); ?>
            <small>/month</small>
        </div>
    </div>

    <div class="price-divider"><span>OR</span></div>

    <div class="yearly-price">
        <div class="yearly-price-line">
            <span class="old-price">₹<?= formatRoundedPrice($plan['monthly_price'] * 12); ?></span>
            <div class="price">
                ₹<?= formatRoundedPrice($plan['yearly_price']); ?>
                <small>/year</small>
            </div>
        </div>

        <div class="save-badge">
            <i class="bi bi-tag"></i>
            You Save ₹<?= formatRoundedPrice($plan['save_amount']); ?>
            (<?= (int) $plan['discount_percent']; ?>%)
        </div>

    </div>

</div>

                    <ul class="list-unstyled mt-4 feature-list">
    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
       Adds <?= htmlspecialchars((string) $plan['quota']); ?> to account
    </li>

    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
        Works across all devices
    </li>

    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
        Secure cloud storage
    </li>

    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
        24/7 Support
    </li>
</ul>

 <a href="#"
  class="btn btn-plan"
   data-plan-id="<?= (int) $plan['id'] ?>"
   onclick="showLoading(event,this)">
    <span class="btn-plan-label">Choose Plan</span>
</a>

                </div>
            </div>
        </div>

        <?php endforeach; ?>

    </div>

    <div class="feature-box mt-5">
    <div class="row">

        <div class="col-12 col-md-4 mb-3 mb-md-0">
            <div class="d-flex align-items-center">
                <div class="icon-box">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div class="ms-3">
                    <h6 class="mb-1 fw-bold">Secure & Private</h6>
                    <small class="text-muted">
                        Your data is encrypted and safe
                    </small>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 mb-3 mb-md-0">
            <div class="d-flex align-items-center">
                <div class="icon-box">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>

                <div class="ms-3">
                    <h6 class="mb-1 fw-bold">Instant Upgrade</h6>
                    <small class="text-muted">
                        Get more space in seconds
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="d-flex align-items-center">
                <div class="icon-box">
                    <i class="bi bi-arrow-repeat"></i>
                </div>

                <div class="ms-3">
                    <h6 class="mb-1 fw-bold">Seamless Sync</h6>
                    <small class="text-muted">
                        Access your files from anywhere
                    </small>
                </div>
            </div>
        </div>

    </div>

</div>

</div>
<div class="modal fade" id="verifyPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content verify-modal-content">
            <div class="modal-header verify-modal-header">
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body verify-modal-body">
                <div class="verify-hero">
                    <div class="verify-logo-halo">
                        <img src="../assets/Photos/icon-192.png" alt="Drivault" class="verify-logo">
                    </div>
                    <h5 class="modal-title verify-modal-title">
                        Verify Your <span>Drivault</span> Account
                    </h5>
                    <p class="verify-subtitle">
                        Enter your Drivault Account Name or email to verify your account before purchasing additional cloud storage.
                    </p>
                </div>

                <label class="verify-form-label" for="modalUsername">Enter your Drivault Account Name or Email</label>
                <div class="verify-input-wrap">
                    <i class="bi bi-person verify-input-icon"></i>
                    <input type="text"
                           class="form-control verify-input"
                           id="modalUsername"
                           autocomplete="off"
                           placeholder="Enter your Drivault Account Name or Email">
                </div>
                <div id="modalUsernameError" class="text-danger small mt-2"></div>

                <div class="verify-trust-strip">
                    <div class="verify-trust-item">
                        <i class="bi bi-shield-lock"></i>
                        <span>Secure verification</span>
                    </div>
                    <div class="verify-trust-item">
                        <i class="bi bi-lock"></i>
                        <span>No password required</span>
                    </div>
                    <div class="verify-trust-item">
                        <i class="bi bi-lightning-charge"></i>
                        <span>Takes only a few seconds</span>
                    </div>
                </div>

                <div class="modal-footer verify-modal-footer">
                    <button type="button"
                            class="btn btn-verify-modal"
                            id="continueVerifyBtn">
                        <span>Verify &amp; Continue</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
<div id="toast" class="custom-toast"></div>
<script>
const verifyPlanModalElement = document.getElementById('verifyPlanModal');
const verifyModalBody = verifyPlanModalElement.querySelector('.verify-modal-body');
const initialVerifyModalBodyHtml = verifyModalBody.innerHTML;
let modalUsernameInput = document.getElementById('modalUsername');
let modalUsernameError = document.getElementById('modalUsernameError');
let continueVerifyBtn = document.getElementById('continueVerifyBtn');

let verifyPlanModal = null;
let selectedPlanId = '';
let selectedPlanButton = null;
let selectedBillingCycle = 'monthly';
let isVerifying = false;
let planPopupTimer = null;
let verifiedCheckoutUsername = '';
let selectedSubscriptionId = '';

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

function getVerifyPlanModal() {
    if (!verifyPlanModal) {
        verifyPlanModal = new bootstrap.Modal(verifyPlanModalElement);
    }

    return verifyPlanModal;
}

function refreshVerifyModalElements() {
    modalUsernameInput = document.getElementById('modalUsername');
    modalUsernameError = document.getElementById('modalUsernameError');
    continueVerifyBtn = document.getElementById('continueVerifyBtn');
}

function resetVerifyModal() {
    verifyModalBody.innerHTML = initialVerifyModalBodyHtml;
    refreshVerifyModalElements();
    modalUsernameInput.value = '';
    modalUsernameInput.disabled = false;
    modalUsernameError.innerText = '';
    verifiedCheckoutUsername = '';
    selectedSubscriptionId = '';
    setContinueLoading(false);
}

function setContinueLoading(isLoading, text = 'Verifying...') {
    continueVerifyBtn.disabled = isLoading;
    continueVerifyBtn.innerHTML = isLoading
        ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + text
        : '<span>Verify &amp; Continue</span>';
}

function setCheckoutLoading(btn) {
    btn.classList.add('loading');
    btn.disabled = true;
    btn.style.pointerEvents = 'none';
    btn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2"
              role="status"
              aria-hidden="true"></span>
        Processing...
    `;
}

function setPlanOpening(btn, isLoading) {
    if (!btn) {
        return;
    }

    const label = btn.querySelector('.btn-plan-label');

    btn.classList.toggle('opening', isLoading);
    btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    btn.style.pointerEvents = isLoading ? 'none' : '';

    if (label) {
        label.innerHTML = isLoading
            ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Opening...'
            : 'Choose Plan';
    }
}

function resetPlanButtons() {
    document.querySelectorAll('.btn-plan.opening').forEach((button) => {
        setPlanOpening(button, false);
    });
}

function escapeHtml(value) {
    return String(displayValue(value))
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getActionConfig(action) {
    const configs = {
        purchase: {
            icon: 'bi-check-circle-fill',
            label: 'Purchase Now',
            heading: 'No active subscription found.',
            message: 'You are purchasing storage for the first time.',
            recommended: 'Purchase',
            disabled: false
        },
        renew: {
            icon: 'bi-arrow-repeat',
            label: 'Renew Subscription',
            heading: 'You already have this storage plan.',
            message: 'Purchasing this plan again will renew your subscription.',
            recommended: 'Renew',
            disabled: false
        },
        upgrade: {
            icon: 'bi-arrow-up-circle-fill',
            label: 'Upgrade Storage',
            heading: 'Upgrade Storage',
            message: 'Your storage will be upgraded',
            recommended: 'Upgrade',
            disabled: false
        },
        downgrade: {
            icon: 'bi-arrow-down-circle-fill',
            label: 'Continue with Downgrade',
            heading: 'Downgrade Selected',
            message: 'Your selected plan is smaller than your current plan. Downgrading will reduce your available storage after your current subscription ends.',
            recommended: 'Downgrade',
            disabled: false
        }
    };

    return configs[action] || configs.purchase;
}

function buildCheckoutUrl(action) {
    const params = new URLSearchParams({
        plan_id: selectedPlanId,
        username: verifiedCheckoutUsername,
        billing_cycle: selectedBillingCycle,
        mode: action
    });

    if (action === 'renew' && selectedSubscriptionId) {
        params.set('subscription_id', selectedSubscriptionId);
    }

    return 'checkout.php?' + params.toString();
}

function continueToCheckout(action) {
    if (selectedPlanButton) {
        setCheckoutLoading(selectedPlanButton);
    }

    continueVerifyBtn.disabled = true;
    continueVerifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
    window.location.href = buildCheckoutUrl(action);
}

function showSubscriptionSummary(data) {
    const action = data.action || 'purchase';
    const config = getActionConfig(action);
    const currentPlan = data.current_plan || 'None';
    const expiry = data.expiry_date_label || data.expiry_date || '-';
    const downgradeDisabled = action === 'downgrade' && data.downgrade_supported === false;
    const buttonDisabled = config.disabled || downgradeDisabled;
    const actionMessage = downgradeDisabled
        ? 'Downgrades are available only after your current subscription expires.'
        : config.message;

    selectedSubscriptionId = data.subscription_id || '';

    verifyModalBody.innerHTML = `
        <div class="verify-hero">
            <div class="verify-logo-halo">
                <img src="../assets/Photos/icon-192.png" alt="Drivault" class="verify-logo">
            </div>
            <h5 class="modal-title verify-modal-title">
                Account <span>Verified</span>
            </h5>
        </div>

        <div class="card border-success-subtle bg-light-subtle mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span class="text-muted fw-semibold">Account Name or email</span>
                    <span class="fw-bold text-end">${escapeHtml(data.username)}</span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span class="text-muted fw-semibold">Current Plan</span>
                    <span class="fw-bold text-end">${escapeHtml(currentPlan)}</span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span class="text-muted fw-semibold">Selected Plan</span>
                    <span class="fw-bold text-success text-end">${escapeHtml(data.selected_plan)}</span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                    <span class="text-muted fw-semibold">Expiry Date</span>
                    <span class="fw-bold text-end">${escapeHtml(expiry)}</span>
                </div>
                <div class="d-flex justify-content-between gap-3 py-2">
                    <span class="text-muted fw-semibold">Recommended Action</span>
                    <span class="fw-bold text-success text-end">${escapeHtml(config.recommended)}</span>
                </div>
            </div>
        </div>

        <div class="text-center mb-3">
            <h6 class="fw-bold mb-2">${escapeHtml(config.heading)}</h6>
            <p class="text-muted mb-0">${escapeHtml(actionMessage)}</p>
        </div>

        <div class="modal-footer verify-modal-footer">
            <button type="button"
                    class="btn btn-verify-modal summary-action"
                    id="continueVerifyBtn"
                    data-checkout-action="${escapeHtml(action)}"
                    ${buttonDisabled ? 'disabled' : ''}>
                <i class="bi ${config.icon} me-2"></i>
                <span>${escapeHtml(config.label)}</span>
            </button>
        </div>
    `;

    refreshVerifyModalElements();
}

async function verifySelectedPlan() {
    if (isVerifying) {
        return;
    }

    const username = modalUsernameInput.value.trim();

    modalUsernameError.innerText = '';

    if (username === '') {
        modalUsernameError.innerText = 'Please enter your Drivault Account Name or email.';
        modalUsernameInput.focus();
        return;
    }

    isVerifying = true;
    setContinueLoading(true);
    let timeoutId = null;
    let summaryShown = false;

    try {
        const controller = new AbortController();
        timeoutId = setTimeout(() => controller.abort(), 8000);
        const response = await fetch(
            'pricing.php?action=get_user_details&username=' + encodeURIComponent(username),
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                },
                signal: controller.signal
            }
        );
        clearTimeout(timeoutId);
        timeoutId = null;

        const data = await response.json();

        if (!data.success) {
            modalUsernameError.innerText = data.message || 'User verification failed.';
            return;
        }

        const verifiedUsername = data.username || username;
        verifiedCheckoutUsername = verifiedUsername;
        setContinueLoading(true, 'Checking subscription...');
        timeoutId = setTimeout(() => controller.abort(), 8000);

        const subscriptionResponse = await fetch(
            '../api/check-subscription.php',
            {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    username: verifiedUsername,
                    selected_plan_id: selectedPlanId,
                    billing_cycle: selectedBillingCycle
                }),
                signal: controller.signal
            }
        );
        clearTimeout(timeoutId);
        timeoutId = null;

        const subscriptionData = await subscriptionResponse.json();

        if (!subscriptionData.success) {
            modalUsernameError.innerText = subscriptionData.message || 'Unable to check subscription.';
            return;
        }

        modalUsernameInput.disabled = true;
        showSubscriptionSummary(subscriptionData);
        summaryShown = true;

    } catch (error) {
        console.error(error);
        modalUsernameError.innerText = error.name === 'AbortError'
            ? 'Verification is taking too long. Please try again.'
            : 'Unable to verify user.';
    } finally {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }
        isVerifying = false;
        if (!summaryShown && continueVerifyBtn) {
            setContinueLoading(false);
        }
    }
}

verifyPlanModalElement.addEventListener('click', function(event) {
    const button = event.target.closest('#continueVerifyBtn');

    if (!button) {
        return;
    }

    const checkoutAction = button.getAttribute('data-checkout-action');

    if (checkoutAction) {
        continueToCheckout(checkoutAction);
        return;
    }

    verifySelectedPlan();
});

verifyPlanModalElement.addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();

        if (event.target && event.target.id === 'modalUsername') {
            verifySelectedPlan();
        }
    }
});

verifyPlanModalElement.addEventListener('hidden.bs.modal', function() {
    if (!isVerifying) {
        resetVerifyModal();
        resetPlanButtons();
    }
});

function showLoading(event, btn) {
    event.preventDefault();

    if (btn.classList.contains('loading') || btn.classList.contains('opening')) {
        return;
    }

    selectedPlanId = btn.getAttribute('data-plan-id') || '';
    selectedPlanButton = btn;

    resetVerifyModal();
    resetPlanButtons();
    setPlanOpening(btn, true);

    if (planPopupTimer) {
        clearTimeout(planPopupTimer);
    }

    planPopupTimer = setTimeout(() => {
        getVerifyPlanModal().show();
        setPlanOpening(btn, false);
        modalUsernameInput.focus();
    }, 550);
}
const monthlyBtn=document.getElementById("monthlyBtn");

const yearlyBtn=document.getElementById("yearlyBtn");

monthlyBtn.onclick=function(){
    selectedBillingCycle = "monthly";

    monthlyBtn.classList.add("active");
    yearlyBtn.classList.remove("active");
    monthlyBtn.setAttribute("aria-pressed","true");
    yearlyBtn.setAttribute("aria-pressed","false");

    document.querySelector(".pricing-grid").classList.remove("yearly-selected");
    document.querySelectorAll(".monthly-price").forEach(e=>e.classList.add("is-selected"));
    document.querySelectorAll(".yearly-price").forEach(e=>e.classList.remove("is-selected"));

}

yearlyBtn.onclick=function(){
    selectedBillingCycle = "yearly";

    yearlyBtn.classList.add("active");
    monthlyBtn.classList.remove("active");
    yearlyBtn.setAttribute("aria-pressed","true");
    monthlyBtn.setAttribute("aria-pressed","false");

    document.querySelector(".pricing-grid").classList.add("yearly-selected");
    document.querySelectorAll(".monthly-price").forEach(e=>e.classList.remove("is-selected"));
    document.querySelectorAll(".yearly-price").forEach(e=>e.classList.add("is-selected"));

}
</script>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</html>
