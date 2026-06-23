<?php
require '../config/db.php';

$planId = (int)($_GET['plan_id'] ?? 0);
$orderId = trim($_GET['order_id'] ?? '');
$reason = trim($_GET['reason'] ?? 'Payment could not be completed. Please try again.');

$subscription = null;

if ($orderId !== '') {
    $stmt = $conn->prepare("SELECT * FROM subscriptions WHERE razorpay_order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
}

if ($subscription) {
    $planId = (int)$subscription['plan_id'];
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Failed - Drivault</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{
    background:#f8fafc;
    font-family:Arial,sans-serif;
}
.result-card{
    max-width:760px;
    margin:auto;
    background:#fff;
    border-radius:20px;
    padding:34px;
    box-shadow:0 5px 25px rgba(0,0,0,.08);
}
.status-icon{
    width:74px;
    height:74px;
    border-radius:50%;
    background:#fff1f2;
    color:#dc3545;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:38px;
    margin:0 auto 18px;
}
.plan-badge{
    background:#fff1f2;
    color:#dc3545;
    padding:10px 18px;
    border-radius:10px;
    display:inline-block;
    font-weight:600;
}
.btn-success{
    background:#38d989;
    border-color:#38d989;
}
.btn-success:hover{
    background:#2ec477;
    border-color:#2ec477;
}
</style>
</head>
<body>
<div class="container py-5">
    <div class="result-card">
        <div class="text-center">
            <div class="status-icon">
                <i class="bi bi-x-lg"></i>
            </div>
            <h2 class="fw-bold mb-2">Payment Failed</h2>
            <p class="text-muted mb-4"><?= htmlspecialchars($reason) ?></p>
        </div>

        <?php if ($plan): ?>
            <div class="text-center mb-4">
                <div class="plan-badge"><?= htmlspecialchars($plan['name']) ?></div>
            </div>

            <table class="table">
                <tr>
                    <td>Selected Storage</td>
                    <td class="text-end fw-semibold"><?= htmlspecialchars($plan['quota']) ?></td>
                </tr>
                <tr>
                    <td>Billing</td>
                    <td class="text-end text-capitalize"><?= htmlspecialchars($billingCycle) ?></td>
                </tr>
                <tr>
                    <td>Amount</td>
                    <td class="text-end fw-bold">Rs <?= number_format((float)$amount, 2) ?></td>
                </tr>
                <?php if ($orderId !== ''): ?>
                    <tr>
                        <td>Order ID</td>
                        <td class="text-end small"><?= htmlspecialchars($orderId) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">
                The selected package details could not be loaded. Please choose a package again.
            </div>
        <?php endif; ?>

        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
            <?php if ($planId > 0): ?>
                <a href="checkout.php?plan_id=<?= $planId ?>" class="btn btn-success px-4">Retry Payment</a>
            <?php endif; ?>
            <a href="pricing.php" class="btn btn-outline-secondary px-4">Choose Another Package</a>
        </div>
    </div>
</div>
</body>
</html>
