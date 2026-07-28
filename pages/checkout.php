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

function formatBytesToGb(mixed $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric($value)) {
        return (string) $value;
    }

    $decimals = $gb >= 10 ? 0 : 2;

if ($decimals === 0) {
    return number_format($gb, 0) . ' GB';
}

return rtrim(rtrim(number_format($gb, 2), '0'), '.') . ' GB';
}

function formatStorageAmount(mixed $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if (!is_numeric($value)) {
        return (string)$value;
    }

    $gb = (float)$value / (1024 * 1024 * 1024);

    if ($gb >= 1024) {
        $tb = $gb / 1024;
        return round($tb, 1) . ' TB';
    }

    if ($gb >= 10) {
        return round($gb) . ' GB';
    }

    return round($gb, 2) . ' GB';
}

function formatAvailableStorage(mixed $used, mixed $total): string
{
    if (!is_numeric($used) || !is_numeric($total)) {
        return '-';
    }

    $available = max(0, (float) $total - (float) $used);

    return formatStorageAmount($available);
}

function formatRoundedPrice(mixed $amount): string
{
    return number_format((int) ceil((float) $amount), 2);
}
function maskUsername(string $username): string
{
    $length = strlen($username);

    if ($length <= 3) {
        return $username;
    }

    return str_repeat('*', $length - 3) . substr($username, -3);
}

function maskEmail(string $email): string
{
    if (empty($email) || strpos($email, '@') === false) {
        return $email;
    }

    [$name, $domain] = explode('@', $email, 2);

    if (strlen($name) <= 3) {
        $maskedName = str_repeat('*', strlen($name));
    } else {
        $maskedName = str_repeat('*', strlen($name) - 3) . substr($name, -3);
    }

    return $maskedName . '@' . $domain;
}
if (!isset($_GET['plan_id'])) {
    die("Plan not found");
}

$planId = (int)$_GET['plan_id'];
$username = trim((string) ($_GET['username'] ?? ''));
$billingCycle = strtolower(trim((string) ($_GET['billing_cycle'] ?? 'monthly')));
$mode = $_GET['mode'] ?? 'new';
$subscriptionId = (int)($_GET['subscription_id'] ?? 0);


if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
    $billingCycle = 'monthly';
}

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
/*
|--------------------------------------------------------------------------
| Store Original Drivault Quota
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Get User's Current Quota from Drivault
|--------------------------------------------------------------------------
*/

$currentQuotaBytes = (int)($verifiedUser['quota']['total'] ?? 0);

$currentQuotaGb = round($currentQuotaBytes / (1024 * 1024 * 1024));

/*
|--------------------------------------------------------------------------
| Always use the user's current total quota before purchase
|--------------------------------------------------------------------------
*/

// New user
if ($mode == 'new') {

    $_SESSION['free_quota'] = $currentQuotaGb;
}

// Upgrade or Renew
else {

    $stmt = $conn->prepare("
        SELECT free_quota
        FROM subscriptions
        WHERE user_id = ?
        AND status='active'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $subscription = $stmt->get_result()->fetch_assoc();

    if ($subscription) {
        $_SESSION['free_quota'] = $subscription['free_quota'];
    } else {
        $_SESSION['free_quota'] = $currentQuotaGb;
    }
}

$_SESSION['current_quota'] = $_SESSION['free_quota'];

$_SESSION['drivault_username'] = $verifiedUser['id'];


$_SESSION['selected_plan_id'] = $planId;
$_SESSION['billing_cycle'] = $billingCycle;
$_SESSION['checkout_mode'] = $mode;

$price = $billingCycle === 'yearly'
    ? (float) $plan['yearly_price']
    : (float) $plan['monthly_price'];
$billingLabel = $billingCycle === 'yearly' ? 'Yearly Price' : 'Monthly Price';
$gst = round($price * 0.18, 2);
$total = ceil($price + $gst);

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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

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

.payment-processing-overlay{
    position:fixed;
    inset:0;
    z-index:9999;
    background:rgba(248,250,252,.92);
    backdrop-filter:blur(5px);
    display:none;
    align-items:center;
    justify-content:center;
    padding:24px;
}

.payment-processing-overlay.show{
    display:flex;
}

.payment-processing-card{
    width:100%;
    max-width:420px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 24px 70px rgba(15,23,42,.18);
    padding:34px 30px;
    text-align:center;
}

.payment-processing-icon{
    width:68px;
    height:68px;
    border-radius:50%;
    background:#e8fff2;
    color:#38d989;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 18px;
    font-size:30px;
}

.payment-processing-icon .pay-spinner{
    width:28px;
    height:28px;
    margin:0;
    border-color:rgba(56,217,137,.24);
    border-top-color:#38d989;
}

.payment-processing-card h2{
    margin:0 0 8px;
    color:#0f172a;
    font-size:24px;
    font-weight:800;
}

.payment-processing-card p{
    margin:0;
    color:#64748b;
    font-size:15px;
    line-height:1.5;
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

.payment-method-modal{
    font-family:Inter, Arial, sans-serif;
}

.payment-method-modal .modal-dialog{
    max-width:920px;
    width:95%;
    margin-left:auto;
    margin-right:auto;
}

.payment-method-modal .modal-content{
    background:#ffffff;
    border:1px solid #e5e7eb;
    border-radius:18px;
    box-shadow:0 20px 60px rgba(0,0,0,.08);
    overflow:hidden;
    max-height:calc(100vh - 32px);
    animation:paymentModalIn .25s ease forwards;
}

.payment-method-modal .modal-header{
    display:flex;
    align-items:flex-start;
    gap:16px;
    padding:24px 28px;
    border-bottom:1px solid #e5e7eb;
}

.payment-modal-icon{
    width:48px;
    height:48px;
    border-radius:14px;
    background:#dcfce7;
    color:#16a34a;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:26px;
    flex-shrink:0;
}

.payment-method-modal .modal-title{
    color:#111827;
    font-size:30px;
    line-height:1.15;
    font-weight:800;
    margin:0 0 4px;
}

.payment-modal-subtitle{
    color:#6b7280;
    font-size:16px;
    line-height:1.5;
    margin:0;
}

.payment-method-modal .modal-body{
    padding:24px 28px;
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:24px;
    overflow:auto;
}

.payment-option-card{
    display:block;
    border:1px solid #e5e7eb;
    border-radius:18px;
    background:#ffffff;
    padding:20px;
    cursor:pointer;
    transition:transform .25s ease, border-color .25s ease, background .25s ease, box-shadow .25s ease;
}

.payment-option-card:hover{
    transform:translateY(-2px);
}

.payment-option-card.is-selected{
    border:2px solid #16a34a;
    background:#f0fdf4;
    box-shadow:0 10px 30px rgba(22,163,74,.12);
}

.payment-option-top{
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    gap:12px;
    align-items:flex-start;
}

.payment-option-radio{
    width:20px;
    height:20px;
    margin-top:4px;
    border-color:#d1d5db;
}

.payment-option-radio:checked{
    background-color:#16a34a;
    border-color:#16a34a;
}

.payment-option-radio:focus{
    box-shadow:0 0 0 .2rem rgba(22,163,74,.18);
}

.payment-option-heading{
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:10px;
    color:#111827;
    font-size:21px;
    line-height:1.25;
    font-weight:700;
    margin-bottom:8px;
}

.payment-option-badge{
    display:inline-flex;
    align-items:center;
    min-height:26px;
    padding:4px 10px;
    border-radius:999px;
    background:#dcfce7;
    color:#16a34a;
    font-size:14px;
    font-weight:700;
}

.payment-option-description{
    color:#6b7280;
    font-size:15px;
    line-height:1.5;
    margin:0;
}

.payment-option-info{
    margin-top:16px;
    border-radius:14px;
    padding:14px 16px;
    display:grid;
    gap:8px;
    font-size:14px;
    line-height:1.35;
}

.payment-option-info span{
    display:flex;
    align-items:center;
    gap:10px;
}

.payment-option-info-green{
    background:#dcfce7;
    color:#166534;
}

.payment-option-info-muted{
    background:#f9fafb;
    color:#6b7280;
}

.payment-option-info i{
    color:#16a34a;
    font-size:16px;
    flex-shrink:0;
}

.payment-method-modal .modal-footer{
    padding:0 28px 24px;
    border-top:0;
    display:grid;
    grid-template-columns:max-content max-content;
    justify-content:end;
    gap:12px;
}

.payment-modal-btn{
    min-height:48px;
    border-radius:12px;
    padding:0 22px;
    font-size:16px;
    font-weight:700;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:10px;
}

.payment-modal-cancel{
    background:#ffffff;
    color:#374151;
    border:1px solid #d1d5db;
}

.payment-modal-cancel:hover{
    background:#f9fafb;
    color:#111827;
    border-color:#9ca3af;
}

.payment-modal-continue{
    background:#16a34a;
    color:#ffffff;
    border:1px solid #16a34a;
}

.payment-modal-continue:hover{
    background:#15803d;
    color:#ffffff;
    border-color:#15803d;
}

.payment-modal-btn .pay-spinner{
    margin-right:0;
    border-color:rgba(22,163,74,.24);
    border-top-color:#16a34a;
}

.payment-modal-btn:disabled{
    opacity:1;
}

.payment-modal-spinner{
    width:18px;
    height:18px;
    border:2px solid rgba(22,163,74,.25);
    border-top-color:#16a34a;
    border-radius:50%;
    display:inline-block;
    flex-shrink:0;
    animation:paySpin .8s linear infinite;
}

@keyframes paymentModalIn{
    from{
        opacity:0;
        transform:scale(.95);
    }
    to{
        opacity:1;
        transform:scale(1);
    }
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

    .payment-method-modal .modal-dialog{
        width:calc(100% - 16px);
        margin:.25rem auto;
    }

    .payment-method-modal .modal-content{
        max-height:calc(100vh - 8px);
    }

    .payment-method-modal .modal-header{
        gap:10px;
        padding:12px 14px;
    }

    .payment-method-modal .modal-body{
        display:flex;
        flex-direction:row;
        gap:8px;
        padding:12px 14px;
        overflow:visible;
    }

    .payment-method-modal .modal-footer{
        padding:0 14px 12px;
    }

    .payment-method-modal .modal-title{
        font-size:20px;
    }

    .payment-modal-subtitle{
        font-size:12px;
        line-height:1.25;
    }

    .payment-modal-icon{
        width:38px;
        height:38px;
        border-radius:12px;
        font-size:20px;
    }

    .payment-option-card{
        flex:1 1 0;
        min-width:0;
        padding:9px;
        border-radius:12px;
    }

    .payment-option-top{
        grid-template-columns:1fr;
        gap:6px;
    }

    .payment-option-radio{
        width:16px;
        height:16px;
        margin-top:0;
    }

    .payment-option-heading{
        align-items:flex-start;
        flex-direction:column;
        gap:4px;
        font-size:14px;
        margin-bottom:4px;
    }

    .payment-option-badge{
        min-height:18px;
        padding:2px 6px;
        font-size:10px;
    }

    .payment-option-description{
        font-size:11px;
        line-height:1.25;
    }

    .payment-option-info{
        margin-top:7px;
        padding:7px;
        gap:5px;
        font-size:10px;
        line-height:1.2;
        border-radius:10px;
    }

    .payment-option-info span{
        align-items:flex-start;
        gap:4px;
    }

    .payment-option-info i{
        font-size:12px;
    }

    .payment-method-modal .modal-footer{
        grid-template-columns:1fr 1fr;
        align-items:center;
    }

    .payment-modal-btn{
        width:100%;
        min-height:40px;
        font-size:14px;
        padding:0 12px;
    }
}

@media (max-width: 430px){
    .payment-method-modal .modal-dialog{
        width:calc(100% - 8px);
    }

    .payment-method-modal .modal-header{
        padding:10px;
    }

    .payment-method-modal .modal-body{
        gap:6px;
        padding:10px;
    }

    .payment-option-card{
        padding:8px;
    }

    .payment-option-heading{
        font-size:13px;
    }

    .payment-option-description,
    .payment-option-info{
        font-size:9px;
    }

    .payment-method-modal .modal-footer{
        padding:0 10px 10px;
        gap:6px;
    }

    .payment-modal-btn{
        min-height:38px;
        font-size:13px;
        padding:0 8px;
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
        <h1>
            <?= $mode == 'renew'
                ? 'Renew Subscription'
                : ($mode == 'upgrade'
                    ? 'Upgrade Storage'
                    : 'Checkout')
            ?>
        </h1>
    </div>

    <p>
        <?= $mode == 'renew'
            ? 'Renew your Drivault subscription'
            : ($mode == 'upgrade'
                ? 'Upgrade your storage plan'
                : 'Complete your payment and upgrade your storage')
        ?>
    </p>
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
            <div class="detail-value"><?= htmlspecialchars(maskEmail($verifiedUser['email'] ?: '-')) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-person-badge"></i></div>
            <div class="detail-label">User ID</div>
            <div class="detail-value"><?= htmlspecialchars(maskUsername($verifiedUser['id'] ?: '-')) ?></div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-pie-chart"></i></div>
            <div class="detail-label">Storage Used</div>
            <div class="detail-value">
                <?= htmlspecialchars(formatStorageAmount($verifiedUser['quota']['used'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                of
                <?= htmlspecialchars(formatStorageAmount($verifiedUser['quota']['total'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <div class="detail-row">
            <div class="detail-icon"><i class="bi bi-database"></i></div>
            <div class="detail-label">Available Storage</div>
            <div class="detail-value">
                <?= htmlspecialchars(formatAvailableStorage($verifiedUser['quota']['used'] ?? '', $verifiedUser['quota']['total'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
            <span><?= htmlspecialchars($billingLabel, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="order-value">&#8377;<?= formatRoundedPrice($price) ?></span>
        </div>

        <div class="order-row">
            <span>GST (18%)</span>
            <span class="order-value">&#8377;<?= formatRoundedPrice($gst) ?></span>
        </div>
    </div>

    <hr class="order-divider">

    <div class="total-box">
        <span class="total-label">Total Amount</span>
        <span class="total">&#8377;<?= formatRoundedPrice($total) ?></span>
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
<div class="modal fade payment-method-modal" id="paymentModeModal" tabindex="-1" aria-labelledby="paymentModeModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <div class="payment-modal-icon">
                    <i class="bi bi-credit-card"></i>
                </div>
                <div>
                    <h5 class="modal-title" id="paymentModeModalTitle">
                        Choose Payment Method
                    </h5>
                    <p class="payment-modal-subtitle">
                        Select how you'd like your subscription to renew.
                    </p>
                </div>
            </div>

            <div class="modal-body">

                <label class="payment-option-card is-selected" tabindex="0">
                    <div class="payment-option-top">
                        <input
                            class="form-check-input payment-option-radio"
                            type="radio"
                            name="payment_mode"
                            value="auto"
                            checked>

                        <div>
                            <div class="payment-option-heading">
                                <span>Auto Pay</span>
                                <span class="payment-option-badge">Recommended</span>
                            </div>

                            <p class="payment-option-description">
                                Automatically renew every billing cycle.
                            </p>

                            <div class="payment-option-info payment-option-info-green">
                                <span><i class="bi bi-check-lg"></i>Hassle-free experience</span>
                                <span><i class="bi bi-check-lg"></i>Never miss a renewal</span>
                                <span><i class="bi bi-check-lg"></i>Auto billing each cycle</span>
                            </div>
                        </div>
                    </div>
                </label>

                <label class="payment-option-card" tabindex="0">
                    <div class="payment-option-top">
                        <input
                            class="form-check-input payment-option-radio"
                            type="radio"
                            name="payment_mode"
                            value="manual">

                        <div>
                            <div class="payment-option-heading">
                                <span>Monthly Pay</span>
                            </div>

                            <p class="payment-option-description">
                                Renew manually whenever required.
                            </p>

                            <div class="payment-option-info payment-option-info-muted">
                                <span><i class="bi bi-check-lg"></i>Renewal reminder emails before your plan expires</span>
                                <span><i class="bi bi-check-lg"></i>Renew only when you choose</span>
                                <span><i class="bi bi-check-lg"></i>Service may be interrupted until your payment is completed</span>
                            </div>
                        </div>
                    </div>
                </label>


            </div>

            <div class="modal-footer">

                <button
                    class="btn payment-modal-btn payment-modal-cancel"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    class="btn payment-modal-btn payment-modal-continue"
                    id="continuePayment">

                    <span>Continue</span>
                    <i class="bi bi-arrow-right"></i>

                </button>

            </div>

        </div>
    </div>
</div>

<div class="checkout-action">
    <button
    class="btn btn-pay btn-success w-100"
    id="payBtn">
    <i class="bi bi-lock-fill"></i>
    <span>
        <?= $mode == 'renew'
            ? 'Renew Now'
            : ($mode == 'upgrade'
                ? 'Upgrade Now'
                : 'Proceed to Pay')
        ?>
    </span>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>

const payBtn = document.getElementById('payBtn');
const continuePaymentBtn = document.getElementById('continuePayment');
const verifiedUser = <?= json_encode($verifiedUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
console.log("Verified User:", verifiedUser);
console.log("Quota:", verifiedUser.quota);
console.log("Used:", verifiedUser.quota.used);
console.log("Total:", verifiedUser.quota.total);
const payButtonText = payBtn.innerHTML;
const continuePaymentButtonText = continuePaymentBtn.innerHTML;
// ===== START MANUAL/AUTO PAYMENT UPDATE =====
let paymentMode = "auto";
// ===== END MANUAL/AUTO PAYMENT UPDATE =====

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
    continuePaymentBtn.innerHTML = '<span class="payment-modal-spinner"></span><span>Processing</span>';
    continuePaymentBtn.disabled = true;
}

function resetPayButton() {
    payBtn.innerHTML = payButtonText;
    payBtn.disabled = false;
    continuePaymentBtn.innerHTML = continuePaymentButtonText;
    continuePaymentBtn.disabled = false;
}

function showPaymentProcessing(title, text, isComplete = false) {
    const overlay = document.getElementById('paymentProcessingOverlay');
    const icon = document.getElementById('paymentProcessingIcon');
    const titleEl = document.getElementById('paymentProcessingTitle');
    const textEl = document.getElementById('paymentProcessingText');

    if (!overlay || !icon || !titleEl || !textEl) {
        return;
    }

    titleEl.textContent = title;
    textEl.textContent = text;
    icon.innerHTML = isComplete
        ? '<i class="bi bi-check-lg"></i>'
        : '<span class="pay-spinner"></span>';
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
}

function redirectAfterProcessing(url) {
    showPaymentProcessing(
        'Payment verified',
        'Redirecting you to the confirmation page...',
        true
    );

    setTimeout(() => {
        window.location = url;
    }, 500);
}

// ===== START PAYMENT MODAL UI UPDATE =====
document.querySelectorAll('.payment-option-card').forEach((card) => {
    const radio = card.querySelector('input[name="payment_mode"]');

    function selectPaymentCard() {
        radio.checked = true;

        document.querySelectorAll('.payment-option-card').forEach((option) => {
            option.classList.toggle(
                'is-selected',
                option.querySelector('input[name="payment_mode"]').checked
            );
        });
    }

    card.addEventListener('click', selectPaymentCard);

    card.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            selectPaymentCard();
        }
    });

    radio.addEventListener('change', selectPaymentCard);
});
// ===== END PAYMENT MODAL UI UPDATE =====

// ===== START MANUAL/AUTO PAYMENT UPDATE =====
payBtn.addEventListener("click", function () {

    const modal = new bootstrap.Modal(
        document.getElementById("paymentModeModal")
    );

    modal.show();

});
continuePaymentBtn.addEventListener("click", function () {

    paymentMode =
        document.querySelector(
            'input[name="payment_mode"]:checked'
        ).value;

    startPayment();

});

async function startPayment() {
    const customerName = displayValue(verifiedUser.displayname);
    const customerEmail = displayValue(verifiedUser.email);
    const customerUserId = displayValue(verifiedUser.id);
    const customerPhone = verifiedUser.phone ? String(verifiedUser.phone) : '';
    const storageAccountId = customerUserId;
    const apiUrl =
        paymentMode === "manual"
            ? "../api/create-order.php"
            : "../api/create-subscription.php";

    setPayLoading('Creating order...');

    let data;

    try {
        let response = await fetch(
            apiUrl,
            {
                method: 'POST',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
    user_id: customerUserId,
    plan_id: '<?= $planId ?>',
    billing_cycle: '<?= $billingCycle ?>',
    // ===== START RENEWAL MODE UPDATE =====
    renewal_mode: paymentMode,
    // ===== END RENEWAL MODE UPDATE =====
    mode: '<?= $mode ?>',
    subscription_id: '<?= $subscriptionId ?>',
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

        currency:"INR",

        name:"Drivault",

        description:
        "Storage Upgrade",

        prefill:{
            name: customerName,
            email: customerEmail,
            contact: customerPhone
        },

        handler:function(payment){

            setPayLoading('Verifying payment...');
            showPaymentProcessing(
                'Verifying payment',
                'Please wait while we confirm your payment and prepare your subscription.'
            );

            const paymentSuccessParams = new URLSearchParams({
                razorpay_payment_id: payment.razorpay_payment_id,
                razorpay_signature: payment.razorpay_signature,

                mode: '<?= $mode ?>',
                subscription_id: data.subscription_id || '<?= $subscriptionId ?>',

                name: customerName,
                email: customerEmail,
                phone: customerPhone || storageAccountId
            });

            if (paymentMode === "manual") {
                paymentSuccessParams.set(
                    'razorpay_order_id',
                    payment.razorpay_order_id || data.order_id
                );
            } else {
                paymentSuccessParams.set(
                    'razorpay_subscription_id',
                    payment.razorpay_subscription_id || data.razorpay_subscription_id
                );
            }

            console.log('Payment verification request:', {
                paymentMode: paymentMode,
                params: Object.fromEntries(paymentSuccessParams.entries())
            });

            fetch(
                '../api/payment-success.php',
                {
                    method:'POST',
                    headers:{
                        'Content-Type':
                        'application/x-www-form-urlencoded'
                    },
                   body: paymentSuccessParams
                }
            )
            .then(async (r) => {
                const responseText = await r.text();

                console.log('Payment verification response:', {
                    status: r.status,
                    ok: r.ok,
                    body: responseText
                });

                try {
                    return JSON.parse(responseText);
                } catch (error) {
                    console.error('Payment verification JSON parse failed:', error);
                    throw error;
                }
            })
           .then(res=>{
    if(res.success){

       let successUrl =
            "payment-success.php?payment_id=" +
            encodeURIComponent(res.payment_id) +
            "&subscription_id=" +
            encodeURIComponent(res.subscription_id) +
            "&mode=<?= $mode ?>" +
            "&plan_id=<?= $planId ?>";

        if (paymentMode === "manual") {
            successUrl +=
                "&order_id=" +
                encodeURIComponent(res.order_id || data.order_id || '');
        } else {
            successUrl +=
                "&razorpay_subscription_id=" +
                encodeURIComponent(
                    res.razorpay_subscription_id ||
                    data.razorpay_subscription_id ||
                    ''
                );
        }

        redirectAfterProcessing(successUrl);
        return;
    }

    let failedUrl =
        "payment-failed.php?plan_id=<?= $planId ?>" +
        "&subscription_id=" +
        encodeURIComponent(data.subscription_id || '<?= $subscriptionId ?>');

    if (paymentMode === "manual") {
        failedUrl +=
            "&order_id=" +
            encodeURIComponent(
                payment.razorpay_order_id ||
                data.order_id ||
                ''
            );
    } else {
        failedUrl +=
            "&razorpay_subscription_id=" +
            encodeURIComponent(
                payment.razorpay_subscription_id ||
                data.razorpay_subscription_id ||
                ''
            );
    }

    window.location =
        failedUrl +
        "&reason=" +
        encodeURIComponent(res.message || "Payment verification failed");
})
            .catch((error)=>{
                console.error('Payment verification request failed:', error);

                window.location =
                    "payment-failed.php?plan_id=<?= $planId ?>&reason=" +
                    encodeURIComponent("Unable to verify payment. Please try again.");
            });

        }

    };

    if (paymentMode === "manual") {
        options.amount = data.amount;
        options.order_id = data.order_id;
    } else {
        options.subscription_id = data.razorpay_subscription_id;
    }

    let rzp = new Razorpay(options);

    rzp.on('payment.failed', function(response){
        let message = response.error && response.error.description
            ? response.error.description
            : "Payment failed. Please try again.";

        let failedUrl =
            "payment-failed.php?plan_id=<?= $planId ?>" +
            "&subscription_id=" +
            encodeURIComponent(data.subscription_id || '<?= $subscriptionId ?>');

        if (paymentMode === "manual") {
            failedUrl +=
                "&order_id=" +
                encodeURIComponent(data.order_id || '');
        } else {
            failedUrl +=
                "&razorpay_subscription_id=" +
                encodeURIComponent(data.razorpay_subscription_id || '');
        }

        window.location =
            failedUrl +
            "&reason=" +
            encodeURIComponent(message);
    });

    setPayLoading('Complete payment in Razorpay...');

    bootstrap.Modal
        .getInstance(document.getElementById("paymentModeModal"))
        .hide();

    continuePaymentBtn.innerHTML = continuePaymentButtonText;
    continuePaymentBtn.disabled = false;

    rzp.open();

}
// ===== END MANUAL/AUTO PAYMENT UPDATE =====

</script>
<div id="paymentProcessingOverlay" class="payment-processing-overlay" aria-live="polite" aria-hidden="true">
    <div class="payment-processing-card">
        <div class="payment-processing-icon" id="paymentProcessingIcon">
            <span class="pay-spinner"></span>
        </div>
        <h2 id="paymentProcessingTitle">Verifying payment</h2>
        <p id="paymentProcessingText">
            Please wait while we confirm your payment and prepare your subscription.
        </p>
    </div>
</div>
<div id="toast" class="custom-toast"></div>
</body>
</html>
