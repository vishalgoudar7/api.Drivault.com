<?php

require '../config/db.php';

$subscriptions = [];
$error = '';

$query = "
    SELECT
        id,
        drivault_display_name,
        drivault_email,
        plan_name,
        storage_quota,
        billing_cycle,
        paid_amount,
        razorpay_payment_id,
        payment_status,
        status,
        created_at
    FROM subscriptions
    WHERE payment_status = 'Success'
    ORDER BY id DESC
    LIMIT 50
";

$result = $conn->query($query);

if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
} else {
    $error = $conn->error;
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Invoice Download</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f8fb;
            color: #111827;
        }

        .page-shell {
            max-width: 1180px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .table-wrap {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        .btn-download {
            background: #12b76a;
            border-color: #12b76a;
            font-weight: 700;
        }

        .btn-download:hover {
            background: #0f9f5d;
            border-color: #0f9f5d;
        }
    </style>
</head>
<body>
<main class="page-shell">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Test Invoice Download</h1>
            <p class="text-muted mb-0">Download invoice PDFs for successful subscription payments.</p>
        </div>
        <a href="pricing.php" class="btn btn-outline-secondary">Back to Pricing</a>
    </div>

    <form class="row g-2 mb-4" method="get" action="../payment/generate-invoice.php">
        <div class="col-12 col-md-4">
            <input
                type="number"
                min="1"
                name="payment_id"
                class="form-control"
                placeholder="Enter subscription ID"
                required>
        </div>
        <div class="col-12 col-md-auto">
            <button class="btn btn-success btn-download" type="submit">Download Invoice</button>
        </div>
    </form>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <?= e($error); ?>
        </div>
    <?php elseif (!$subscriptions): ?>
        <div class="alert alert-warning">
            No successful subscriptions found.
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Billing</th>
                        <th>Amount</th>
                        <th>Payment ID</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subscriptions as $subscription): ?>
                        <tr>
                            <td><?= e($subscription['id']); ?></td>
                            <td><?= e($subscription['drivault_display_name'] ?: '-'); ?></td>
                            <td><?= e($subscription['drivault_email'] ?: '-'); ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($subscription['plan_name'] ?: '-'); ?></div>
                                <small class="text-muted"><?= e($subscription['storage_quota'] ?: '-'); ?></small>
                            </td>
                            <td><?= e(ucfirst((string) $subscription['billing_cycle'])); ?></td>
                            <td>Rs <?= e(number_format((float) $subscription['paid_amount'], 2)); ?></td>
                            <td><?= e($subscription['razorpay_payment_id'] ?: '-'); ?></td>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-success btn-download"
                                    href="../payment/generate-invoice.php?payment_id=<?= urlencode((string) $subscription['id']); ?>">
                                    Download
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
