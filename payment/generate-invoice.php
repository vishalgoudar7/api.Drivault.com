<?php

session_start();

require '../config/db.php';
require '../vendor/autoload.php';
$logoPath = realpath(__DIR__ . '/../assets/Photos/icon-192.png');

if (!$logoPath) {
    die('Logo file not found');
}

use Dompdf\Dompdf;
use Dompdf\Options;

/*
|--------------------------------------------------------------------------
| Check Payment ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET['payment_id'])) {
    die("Payment ID missing");
}

$paymentId = (int) $_GET['payment_id'];

/*
|--------------------------------------------------------------------------
| Fetch Subscription + Plan Details
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT s.*, pl.name AS plan_name, pl.quota
    FROM subscriptions s
    JOIN plans pl ON s.plan_id = pl.id
    WHERE s.id = ?
");

$stmt->bind_param("i", $paymentId);
$stmt->execute();

$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die("Invoice not found");
}

/*
|--------------------------------------------------------------------------
| Invoice Number
|--------------------------------------------------------------------------
*/

$invoiceNumber = 'INV-' . date('Ymd') . '-' . $payment['id'];

/*
|--------------------------------------------------------------------------
| Invoice HTML
|--------------------------------------------------------------------------
*/

$html = '

<!DOCTYPE html>
<html>
<head>
<style>

body{
    font-family: DejaVu Sans, sans-serif;
    color:#333;
    font-size:14px;
}

.header{
    text-align:center;
    margin-bottom:20px;
}

.logo{
    color:#38d989;
    font-size:30px;
    font-weight:bold;
}

.invoice-title{
    font-size:22px;
    margin-top:5px;
}

.info-table{
    width:100%;
    margin-bottom:20px;
}

.info-table td{
    padding:5px 0;
}

.customer-box{
    background:#f8f9fa;
    padding:15px;
    border-radius:8px;
    margin-bottom:20px;
}

.items-table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}

.items-table th{
    background:#38d989;
    color:#fff;
}

.items-table th,
.items-table td{
    border:1px solid #ddd;
    padding:12px;
    text-align:center;
}

.total-section{
    margin-top:20px;
    text-align:right;
    font-size:18px;
    font-weight:bold;
}

.footer{
    text-align:center;
    margin-top:40px;
    color:#777;
    font-size:13px;
}

</style>
</head>

<body>

<div class="header">

<table width="100%">
<tr>

<td width="20%" align="left">
    <img src="file://'.$logoPath.'"
         width="70">
</td>

<td width="80%" align="center">
    <div class="logo">DRIVAULT</div>
    <div class="invoice-title">
        Payment Invoice
    </div>
</td>

</tr>
</table>

</div>

<hr>

<table class="info-table">
<tr>
<td>
<b>Invoice No:</b> '.$invoiceNumber.'<br>
<b>Date:</b> '.date('d-m-Y').'
</td>

<td align="right">
<b>Order ID:</b><br>
'.$payment['razorpay_order_id'].'<br><br>

<b>Payment ID:</b><br>
'.$payment['razorpay_payment_id'].'
</td>
</tr>
</table>

<div class="customer-box">

<h3>Customer Details</h3>

<b>Name:</b> '.htmlspecialchars($payment['customer_name']).'<br><br>

<b>Email:</b> '.htmlspecialchars($payment['customer_email']).'<br><br>

<b>Phone:</b> '.htmlspecialchars($payment['customer_phone']).'

</div>

<table class="items-table">

<tr>
<th>Plan</th>
<th>Storage</th>
<th>Billing Cycle</th>
<th>Amount</th>
<th>Status</th>
</tr>

<tr>
<td>'.$payment['plan_name'].'</td>
<td>'.$payment['quota'].'</td>
<td>'.ucfirst($payment['billing_cycle']).'</td>
<td>₹'.number_format($payment['amount'],2).'</td>
<td>'.ucfirst($payment['status']).'</td>
</tr>

</table>

<div class="total-section">
Grand Total : ₹'.number_format($payment['amount'],2).'
</div>

<div class="footer">

<hr>

Thank you for choosing Drivault.<br>
This is a computer-generated invoice and does not require a signature.

</div>

</body>
</html>

';

/*
|--------------------------------------------------------------------------
| Generate PDF
|--------------------------------------------------------------------------
*/

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

/*
|--------------------------------------------------------------------------
| Clear Output Buffer
|--------------------------------------------------------------------------
*/

if (ob_get_length()) {
    ob_end_clean();
}

/*
|--------------------------------------------------------------------------
| Download PDF
|--------------------------------------------------------------------------
*/

$dompdf->stream(
    'Drivault-Invoice-'.$payment['id'].'.pdf',
    ['Attachment' => true]
);

exit;