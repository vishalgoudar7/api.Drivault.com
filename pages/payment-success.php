<?php
require '../config/db.php';

$paymentId = trim($_GET['payment_id'] ?? '');
$orderId = trim($_GET['order_id'] ?? '');
$subscriptionId = (int)($_GET['subscription_id'] ?? 0);
$planId = (int)($_GET['plan_id'] ?? 0);

$subscription = null;

if ($subscriptionId > 0) {
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->bind_param("i", $subscriptionId);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
}

if (!$subscription && $orderId !== '') {
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE razorpay_order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
}

if ($subscription) {
    $planId = (int)$subscription['plan_id'];
    $orderId = $orderId ?: ($subscription['razorpay_order_id'] ?? '');
    $paymentId = $paymentId ?: ($subscription['razorpay_payment_id'] ?? '');
}

$plan = null;

if ($planId > 0) {
    $stmt = $conn->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->bind_param("i", $planId);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
}

$amount = $subscription['amount'] ?? ($plan['monthly_price'] ?? 0);
$billingCycle = $subscription['billing_cycle'] ?? 'monthly';
$invoiceUrl = $subscription
    ? '../payment/generate-invoice.php?payment_id=' . urlencode((string) $subscription['id'])
    : '#';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Successful - Drivault</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{
    margin:0;
    background:#f8fafc;
    color:#0f172a;
    font-family:Arial,sans-serif;
}

.brand{
    display:flex;
    align-items:center;
    gap:10px;
    color:#0f172a;
    font-size:24px;
    font-weight:700;
    text-decoration:none;
    margin-bottom:0;
}

.brand img{
    width:30px;
    height:30px;
    border-radius:7px;
}

.success-page{
    min-height:100vh;
    padding:10px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}

.success-card{
    width:100%;
    max-width:1000px;
    margin:0 auto;
    background:rgba(255,255,255,.74);
    border:1px solid #e5e7eb;
    border-radius:12px;
    padding:12px 32px 14px;
    box-shadow:0 20px 55px rgba(15,23,42,.08);
}

.status-icon{
    width:74px;
    height:74px;
    border-radius:50%;
    background:#e8fff2;
    color:#38d989;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:38px;
    margin:6px auto 16px;
}

.success-title{
    color:#38d989;
    font-size:30px;
    line-height:1.15;
    font-weight:700;
    text-align:center;
    margin:0 0 8px;
}

.success-subtitle{
    color:#64748b;
    font-size:15px;
    text-align:center;
    margin:0 0 24px;
}

.plan-pill{
    background:#38d989;
    color:#fff;
    border-radius:12px;
    padding:10px 22px;
    display:inline-flex;
    font-size:17px;
    font-weight:700;
    box-shadow:0 10px 18px rgba(56,217,137,.18);
}

.success-divider{
    border:0;
    border-top:1px solid #e5e7eb;
    margin:26px 0 18px;
}

.success-card .text-center{
    margin-top:0;
}

.detail-row{
    display:grid;
    grid-template-columns:44px minmax(120px,1fr) minmax(0,1fr);
    align-items:center;
    gap:16px;
    padding:10px 8px;
    border-bottom:1px solid #e5e7eb;
}

.detail-row:last-child{
    border-bottom:none;
}

.detail-icon{
    width:40px;
    height:40px;
    border-radius:50%;
    background:#e8fff2;
    color:#38d989;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
}

.detail-label{
    color:#64748b;
    font-size:15px;
    font-weight:600;
}

.detail-value{
    color:#0f172a;
    font-size:15px;
    font-weight:700;
    text-align:right;
    overflow-wrap:anywhere;
}

.detail-value.success-value{
    color:#38d989;
}

.action-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin-top:26px;
    max-width:560px;
    margin-left:auto;
    margin-right:auto;
}

.success-action{
    min-height:44px;
    border-radius:8px;
    font-size:16px;
    font-weight:600;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    transition:transform .2s ease, box-shadow .2s ease, background .2s ease;
}

.success-action:hover{
    transform:translateY(-1px);
}

.action-primary{
    color:#fff;
    background:#38d989;
    border:1px solid #38d989;
    box-shadow:0 12px 24px rgba(56,217,137,.22);
}

.action-primary:hover{
    color:#fff;
    background:#2ec477;
}

.action-secondary{
    color:#64748b;
    background:#fff;
    border:1px solid #94a3b8;
}

.action-secondary:hover{
    color:#0f172a;
    border-color:#64748b;
}

.action-download{
    color:#0d6efd;
    background:#fff;
    border:1px solid #0d6efd;
}

.action-download:hover{
    color:#fff;
    background:#0d6efd;
}

.secure-note{
    color:#64748b;
    font-size:13px;
    font-weight:600;
    text-align:center;
    margin:12px 0 0;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:18px;
}

.secure-note i{
    color:#64748b;
}

.secure-note a{
    color:#0d6efd;
    text-decoration:none;
}

@media (max-width: 991px){
    .success-card{
        padding:20px;
    }

    .success-title{
        font-size:34px;
    }

    .success-subtitle{
        font-size:17px;
    }

    .detail-row{
        grid-template-columns:44px minmax(0,1fr);
        gap:14px;
        padding:10px 0;
    }

    .detail-value{
        grid-column:2;
        text-align:left;
        font-size:16px;
        margin-top:-8px;
    }

    .detail-label{
        font-size:16px;
    }

    .action-grid{
        grid-template-columns:1fr;
        gap:14px;
    }
}

@media (max-width: 576px){
    .brand{
        font-size:24px;
    }

    .brand img{
        width:42px;
        height:42px;
    }

    .success-page{
        padding:0;
    }

    .success-card{
        border-radius:0;
        border-left:0;
        border-right:0;
        min-height:100vh;
    }

    .status-icon{
        width:86px;
        height:86px;
        font-size:48px;
    }

    .plan-pill{
        width:100%;
        justify-content:center;
        font-size:19px;
    }
}
</style>
</head>
<body>
<main class="success-page">
    <section class="success-card">
        <a href="pricing.php" class="brand">
            <img src="../assets/Photos/icon-192.png" alt="Drivault">
            <span>Drivault</span>
        </a>

        <div class="status-icon">
            <i class="bi bi-check-lg"></i>
        </div>

        <h1 class="success-title">Payment Successful!</h1>
        <p class="success-subtitle">Thank you! Your storage package has been added to your Drivault account.</p>

        <?php if ($plan): ?>
            <div class="text-center">
                <span class="plan-pill"><?= htmlspecialchars($plan['name']) ?></span>
            </div>

            <hr class="success-divider">

            <div class="detail-row">
                <div class="detail-icon"><i class="bi bi-cloud-upload"></i></div>
                <div class="detail-label">Storage</div>
                <div class="detail-value success-value"><?= htmlspecialchars((string) $plan['quota']) ?></div>
            </div>

            <div class="detail-row">
                <div class="detail-icon"><i class="bi bi-calendar3"></i></div>
                <div class="detail-label">Billing</div>
                <div class="detail-value text-capitalize"><?= htmlspecialchars((string) $billingCycle) ?></div>
            </div>

            <div class="detail-row">
                <div class="detail-icon"><i class="bi bi-currency-rupee"></i></div>
                <div class="detail-label">Amount Paid</div>
                <div class="detail-value success-value">Rs <?= number_format((float)$amount, 2) ?></div>
            </div>

            <?php if ($paymentId !== ''): ?>
                <div class="detail-row">
                    <div class="detail-icon"><i class="bi bi-wallet2"></i></div>
                    <div class="detail-label">Payment ID</div>
                    <div class="detail-value"><?= htmlspecialchars($paymentId) ?></div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning mt-4">
                Payment completed, but the selected package details could not be loaded.
            </div>
        <?php endif; ?>

        <div class="action-grid">
            <a href="pricing.php" class="success-action action-primary">
                <i class="bi bi-box-seam"></i>
                <span>View Packages</span>
            </a>

            <a href="pricing.php" class="success-action action-secondary">
                <i class="bi bi-house-door"></i>
                <span>Go to Dashboard</span>
            </a>

            <?php if ($subscription): ?>
                <a href="<?= htmlspecialchars($invoiceUrl) ?>" class="success-action action-download">
                    <i class="bi bi-download"></i>
                    <span>Download Invoice</span>
                </a>
            <?php else: ?>
                <a href="#" class="success-action action-download disabled" aria-disabled="true">
                    <i class="bi bi-download"></i>
                    <span>Download Invoice</span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <div class="secure-note">
        <span><i class="bi bi-lock-fill"></i> Secure Payment</span>
        <span>&bull;</span>
        <span>Powered by <a href="https://razorpay.com" target="_blank" rel="noopener">Razorpay</a></span>
    </div>
</main>
</body>
</html>
