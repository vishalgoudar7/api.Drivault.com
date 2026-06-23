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
    background:#e8fff2;
    color:#38d989;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:38px;
    margin:0 auto 18px;
}
.plan-badge{
    background:#38d989;
    color:#fff;
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
                <i class="bi bi-check-lg"></i>
            </div>
            <h2 class="fw-bold mb-2">Payment Successful</h2>
            <p class="text-muted mb-4">Your storage package has been added to your Drivault account.</p>
        </div>

        <?php if ($plan): ?>
            <div class="text-center mb-4">
                <div class="plan-badge"><?= htmlspecialchars($plan['name']) ?></div>
            </div>

            <table class="table">
                <tr>
                    <td>Storage</td>
                    <td class="text-end fw-semibold"><?= htmlspecialchars($plan['quota']) ?></td>
                </tr>
                <tr>
                    <td>Billing</td>
                    <td class="text-end text-capitalize"><?= htmlspecialchars($billingCycle) ?></td>
                </tr>
                <tr>
                    <td>Amount Paid</td>
                    <td class="text-end fw-bold">Rs <?= number_format((float)$amount, 2) ?></td>
                </tr>
                <?php if ($paymentId !== ''): ?>
                    <tr>
                        <td>Payment ID</td>
                        <td class="text-end small"><?= htmlspecialchars($paymentId) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">
                Payment completed, but the selected package details could not be loaded.
            </div>
        <?php endif; ?>

        <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
            <a href="pricing.php" class="btn btn-success px-4">View Packages</a>
            <?php if ($planId > 0): ?>
                <a href="checkout.php?plan_id=<?= $planId ?>" class="btn btn-outline-secondary px-4">Buy Again</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
