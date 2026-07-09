<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

function fetchInvoiceSubscription(mysqli $conn, int $subscriptionId): ?array
{
    $stmt = $conn->prepare("
        SELECT s.*, pl.name AS plan_name, pl.quota
        FROM subscriptions s
        JOIN plans pl ON s.plan_id = pl.id
        WHERE s.id = ?
    ");

    $stmt->bind_param("i", $subscriptionId);
    $stmt->execute();

    $payment = $stmt->get_result()->fetch_assoc();

    return $payment ?: null;
}

function getInvoiceLogoSrc(): string
{
    $logoPath = realpath(__DIR__ . '/../assets/Photos/icon-192.jpg');

    if (!$logoPath) {
        return '';
    }

    $logoData = base64_encode((string) file_get_contents($logoPath));

    return 'data:image/jpeg;base64,' . $logoData;
}

function buildInvoiceHtml(array $payment): string
{
    $logoSrc = getInvoiceLogoSrc();
    $planAmount = ((float) $payment['paid_amount']) / 1.18;
    $gstAmount = ((float) $payment['paid_amount']) - $planAmount;
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . $payment['id'];

    $logoImage = $logoSrc !== ''
        ? '<img src="' . $logoSrc . '" width="90">'
        : '';

    return '

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
    font-size:11px;
    border-top:1px solid #ddd;
    padding-top:15px;
}
    .items-table tr:nth-child(even){
    background:#f8fafc;
}

.customer-box{
    padding:8px;
    margin-bottom:5px;
}

.items-table td,
.items-table th{
    padding:8px;
}

.invoice-footer{
    margin-top:30px;
    text-align:center;
    font-size:11px;
    color:#555;
}

.contact-table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:15px;
}

.contact-table td{
    width:33%;
    text-align:center;
    padding:10px;
    vertical-align:top;
}

.contact-title{
    font-weight:bold;
    color:#0b8f4d;
    font-size:13px;
    margin-top:5px;
}

.divider{
    border-top:1px solid #dcdcdc;
    margin:10px 0;
}

.terms-title{
    color:#0b8f4d;
    font-weight:bold;
    font-size:13px;
    text-align:center;
    margin-bottom:10px;
}

.terms-table{
    width:100%;
    font-size:11px;
    color:#555;
}

.terms-table td{
    padding:3px 10px;
    text-align:center;
}

</style>
</head>

<body>

<div class="header">

<table width="100%">
<tr>
<td width="20%" align="left">
    '.$logoImage.'
</td>

<td width="80%" align="center">

    <div class="logo">Drivault</div>

    <div class="invoice-title">
        Payment Invoice
    </div>

</td>

</tr>
</table>
<br>


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
'.htmlspecialchars((string) $payment['razorpay_order_id'], ENT_QUOTES, 'UTF-8').'<br><br>

<b>Payment ID:</b><br>
'.htmlspecialchars((string) $payment['razorpay_payment_id'], ENT_QUOTES, 'UTF-8').'

</td>
</tr>
</table>

<div class="customer-box">

<h3>Bill To</h3>

<b>Name:</b> '.htmlspecialchars((string) $payment['drivault_display_name'], ENT_QUOTES, 'UTF-8').'<br><br>

<b>Email:</b> '.htmlspecialchars((string) $payment['drivault_email'], ENT_QUOTES, 'UTF-8').'<br><br>

<b>Phone:</b> '.htmlspecialchars((string) $payment['drivault_phone'], ENT_QUOTES, 'UTF-8').'

<br>
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
<td>'.htmlspecialchars((string) $payment['plan_name'], ENT_QUOTES, 'UTF-8').'</td>
<td>'.htmlspecialchars((string) $payment['quota'], ENT_QUOTES, 'UTF-8').'</td>
<td>'.htmlspecialchars(ucfirst((string) $payment['billing_cycle']), ENT_QUOTES, 'UTF-8').'</td>
<td>₹'.number_format((float) $payment['paid_amount'],2).'</td>
<td>'.htmlspecialchars(ucfirst((string) $payment['status']), ENT_QUOTES, 'UTF-8').'</td>
</tr>

</table>
<table width="100%" style="margin-top:10px;">
<tr>
<td width="50%" valign="top">
<table width="100%" cellpadding="6" cellspacing="0"
style="border-collapse: collapse;">
<tr>
<td><b>Plan Amount</b></td>
<td align="right">
₹'.number_format($planAmount,2).'
</td>
</tr>

<tr>
<td><b>GST (18%)</b></td>
<td align="right">
₹'.number_format($gstAmount,2).'
</td>
</tr>

<tr style="background:#f3f4f6;">
<td><b>Total</b></td>
<td align="right">
<b>₹'.number_format((float) $payment['paid_amount'],2).'</b>
</td>
</tr>

</table>

</td>

</tr>

</table>
<div class="invoice-footer">

    <table class="contact-table">

        <tr>

            <td>
                <div style="font-size:20px;">✉</div>
                <div class="contact-title">Email</div>
                support@drivault.com
            </td>

            <td style="border-left:1px solid #ddd;border-right:1px solid #ddd;">
                <div style="font-size:20px;">🌐</div>
                <div class="contact-title">Website</div>
                www.drivault.com
            </td>

            <td>
                <div style="font-size:20px;">☎</div>
                <div class="contact-title">Support</div>
                We are here to help
            </td>

        </tr>

    </table>

    <div class="divider"></div>

    <p>
        This is a computer-generated invoice and does not require a signature.
    </p>

    <div class="divider"></div>

    <div class="terms-title">
        Terms &amp; Conditions
    </div>

    <table class="terms-table">

        <tr>
            <td>• Storage plans are non-refundable once activated.</td>
            <td>• Subscription renewal is subject to plan availability.</td>
        </tr>

        <tr>
            <td colspan="2">
                • For billing or payment queries, please contact Drivault Support.
            </td>
        </tr>

    </table>

</div>
</body>
</html>

';
}

function renderInvoicePdf(string $html): string
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('chroot', realpath(__DIR__ . '/..'));

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
