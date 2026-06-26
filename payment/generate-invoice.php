<?php

session_start();

require '../config/db.php';
require '../vendor/autoload.php';
require __DIR__ . '/invoice-helper.php';

/*
|--------------------------------------------------------------------------
| Check Subscription ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET['payment_id'])) {
    die("Payment ID missing");
}

$subscriptionId = (int) $_GET['payment_id'];
$payment = fetchInvoiceSubscription($conn, $subscriptionId);

if (!$payment) {
    die("Invoice not found");
}

/*
|--------------------------------------------------------------------------
| Generate PDF
|--------------------------------------------------------------------------
*/

$html = buildInvoiceHtml($payment);
$pdf = renderInvoicePdf($html);

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

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Drivault-Invoice-' . $payment['id'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));

echo $pdf;
exit;
