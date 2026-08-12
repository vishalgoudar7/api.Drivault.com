<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

/*
|--------------------------------------------------------------------------
| Fetch subscription/payment information
|--------------------------------------------------------------------------
*/

function fetchInvoiceSubscription(mysqli $conn, int $subscriptionId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            s.*,
            pl.name AS plan_name,
            pl.quota,
            u.phone AS user_phone
        FROM subscriptions s
        LEFT JOIN plans pl
            ON s.plan_id = pl.id
        LEFT JOIN users u
            ON u.email = s.drivault_email
            OR u.username = s.drivault_display_name
            OR u.name = s.drivault_display_name
        WHERE s.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $subscriptionId);
    $stmt->execute();

    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    $stmt->close();

    return $payment ?: null;
}


/*
|--------------------------------------------------------------------------
| Convert logo to Base64
|--------------------------------------------------------------------------
| Dompdf works more reliably with a local Base64 image.
|--------------------------------------------------------------------------
*/

function getInvoiceLogoSrc(): string
{
    $possiblePaths = [
        __DIR__ . '/../assets/Photos/nav-logo-invoice.jpg',
        __DIR__ . '/../assets/Photos/nav-logo.png',
        __DIR__ . '/../assets/Photos/icon-192.png',
        __DIR__ . '/../assets/Photos/icon-192.jpg',
    ];

    foreach ($possiblePaths as $path) {

        if (!is_file($path)) {
            continue;
        }

        $extension = strtolower(
            pathinfo($path, PATHINFO_EXTENSION)
        );

        if ($extension === 'png' && !extension_loaded('gd')) {
            continue;
        }

        $mimeType = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        $imageData = @file_get_contents($path);

        if ($imageData === false) {
            continue;
        }

        return 'data:' .
            $mimeType .
            ';base64,' .
            base64_encode($imageData);
    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Safe HTML helper
|--------------------------------------------------------------------------
*/

function invoiceEsc($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Format invoice date
|--------------------------------------------------------------------------
*/

function invoiceDate(array $payment): string
{
    $dateValue =
        $payment['paid_at']
        ?? $payment['payment_date']
        ?? $payment['created_at']
        ?? null;

    if (!$dateValue) {
        return date('d-m-Y');
    }

    $timestamp = strtotime((string) $dateValue);

    if ($timestamp === false) {
        return date('d-m-Y');
    }

    return date('d-m-Y', $timestamp);
}


/*
|--------------------------------------------------------------------------
| Build invoice HTML
|--------------------------------------------------------------------------
*/

function invoiceNumberToWords(int $number): string
{
    if ($number === 0) {
        return 'Zero';
    }

    $ones = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];

    $tens = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

    if ($number < 20) {
        return $ones[$number];
    }

    if ($number < 100) {
        return trim($tens[intdiv($number, 10)] . ' ' . $ones[$number % 10]);
    }

    if ($number < 1000) {
        return trim($ones[intdiv($number, 100)] . ' Hundred ' . invoiceNumberToWords($number % 100));
    }

    if ($number < 100000) {
        return trim(invoiceNumberToWords(intdiv($number, 1000)) . ' Thousand ' . invoiceNumberToWords($number % 1000));
    }

    if ($number < 10000000) {
        return trim(invoiceNumberToWords(intdiv($number, 100000)) . ' Lakh ' . invoiceNumberToWords($number % 100000));
    }

    return trim(invoiceNumberToWords(intdiv($number, 10000000)) . ' Crore ' . invoiceNumberToWords($number % 10000000));
}

function invoiceAmountInWords(float $amount): string
{
    $rupees = (int) floor($amount);
    $paise = (int) round(($amount - $rupees) * 100);
    $words = invoiceNumberToWords($rupees) . ' Rupees';

    if ($paise > 0) {
        $words .= ' And ' . invoiceNumberToWords($paise) . ' Paise';
    }

    return $words;
}

function buildOobStyleInvoiceHtml(array $payment): string
{
    $logoSrc = getInvoiceLogoSrc();
    $logoHtml = $logoSrc !== ''
        ? '<img src="' . $logoSrc . '" class="brand-logo" alt="Drivault">'
        : '<div class="brand-logo-fallback">D</div>';

    $customerName = trim((string) ($payment['drivault_display_name'] ?? $payment['name'] ?? 'Customer'));
    $customerEmail = trim((string) ($payment['drivault_email'] ?? $payment['email'] ?? ''));
    $customerPhone = trim((string) ($payment['user_phone'] ?? $payment['phone'] ?? $payment['drivault_phone'] ?? ''));
    $planName = trim((string) ($payment['plan_name'] ?? $payment['name'] ?? 'Drivault Plan'));
    $quota = trim((string) ($payment['quota'] ?? $payment['storage_quota'] ?? ''));
    $billingCycle = ucfirst(trim((string) ($payment['billing_cycle'] ?? 'monthly')));
    $paymentStatus = trim((string) ($payment['payment_status'] ?? $payment['status'] ?? 'Success'));
    $orderId = trim((string) ($payment['razorpay_order_id'] ?? ''));
    $paymentId = trim((string) ($payment['razorpay_payment_id'] ?? ''));
    $subscriptionAction = ucfirst(trim((string) ($payment['subscription_action'] ?? $payment['payment_type'] ?? 'Purchase')));

    $totalAmount = round((float) ($payment['paid_amount'] ?? 0), 2);
    $gstPercent = 18.00;
    $billingCycleLower = strtolower(trim((string) ($payment['billing_cycle'] ?? 'monthly')));
    $savedPlanAmount = $billingCycleLower === 'yearly'
        ? (float) ($payment['yearly_price'] ?? 0)
        : (float) ($payment['monthly_price'] ?? 0);
    $planAmount = round($savedPlanAmount > 0 ? $savedPlanAmount : ($totalAmount / (1 + ($gstPercent / 100))), 2);
    $gstAmount = round($planAmount * ($gstPercent / 100), 2);
    $roundOffAmount = round($totalAmount - ($planAmount + $gstAmount), 2);
    $isPaid = in_array(strtolower($paymentStatus), ['success', 'successful', 'paid', 'active'], true);
    $statusText = $isPaid ? 'PAID' : 'UNPAID';
    $balanceDue = $isPaid ? 0.00 : $totalAmount;
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . (int) ($payment['id'] ?? 0);
    $invoiceDate = invoiceDate($payment);
    $dueDate = $invoiceDate;
    $periodStart = !empty($payment['start_date']) ? date('d-m-Y h:i a', strtotime((string) $payment['start_date'])) : $invoiceDate;
    $periodEnd = !empty($payment['expiry_date']) ? date('d-m-Y h:i a', strtotime((string) $payment['expiry_date'])) : '-';
    $description = $planName . ' | Plan: ' . ($quota !== '' ? $quota : '-') . ' | Billing: ' . $billingCycle . ' | ' . $periodStart . ' - ' . $periodEnd;

    return '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
@page { margin: 35px 35px 35px 35px; }
body {
    margin: 0;
    color: #4d4d4d;
    font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
    font-size: 9.8px;
    line-height: 1.45;
}
.invoice { width: 100%; }
.top-band { height: 162px; position: relative; }
.brand-block { position: absolute; left: 0; top: 0; }
.brand-table { border-collapse: collapse; }
.brand-table td { vertical-align: middle; padding: 0; }
.brand-logo-cell { width: 62px; }
.brand-logo { width: 48px; height: 48px; object-fit: contain; }
.brand-logo-fallback {
    display: inline-block;
    width: 48px;
    height: 48px;
    line-height: 48px;
    text-align: center;
    background: #12b76a;
    color: #fff;
    font-size: 26px;
    font-weight: bold;
}
.brand-name { display: block; font-size: 31px; font-weight: bold; color: #111827; line-height: 31px; }
.status-card {
    position: absolute;
    right: 27px;
    top: 18px;
    width: 116px;
    height: 42px;
    border: 1px solid ' . ($isPaid ? '#12b76a' : '#d40c0c') . ';
    border-radius: 4px;
    text-align: center;
    background: #ffffff;
}
.status-table { width: 100%; height: 42px; border-collapse: collapse; }
.status-table td { text-align: center; vertical-align: middle; padding-top: 3px; }
.status-text { color: ' . ($isPaid ? '#12b76a' : '#d40c0c') . '; font-size: 18px; font-weight: bold; }
.status-card .due-copy { color: #4d4d4d; font-size: 12px; margin-top: 22px; line-height: 1.45; }
.section-line { border-top: 1px solid #e9e6e6; margin: 0 0 22px; }
.two-col { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
.two-col td { width: 50%; vertical-align: top; }
.right-col { padding-left: 145px; }
.section-title { font-size: 10.5px; font-weight: bold; color: #4d4d4d; margin-bottom: 10px; }
.line { margin-bottom: 7px; }
.detail-table { width: auto; border-collapse: collapse; }
.detail-table td { padding: 0 0 10px; }
.detail-label { width: 120px; }
.detail-value { text-align: left; padding-left: 18px; white-space: nowrap; }
.summary-title { font-size: 10.5px; font-weight: bold; margin: 28px 0 12px; }
.summary { width: 100%; border-collapse: collapse; }
.summary th {
    font-weight: bold;
    text-align: left;
    padding: 8px 2px 10px;
    border-bottom: 1px solid #e9e6e6;
}
.summary td { padding: 11px 2px; border-bottom: 1px solid #e9e6e6; vertical-align: top; }
.summary .num { width: 22px; }
.summary .qty { width: 28px; text-align: left; }
.summary .money { width: 58px; text-align: right; white-space: nowrap; }
.totals { width: 245px; margin-left: auto; border-collapse: collapse; margin-top: 16px; }
.totals td { padding: 5px 0; }
.totals .label { text-align: left; }
.totals .amount { text-align: right; white-space: nowrap; }
.totals .strong { font-weight: bold; color: #333; }
.words-title { font-weight: bold; margin-top: 16px; }
.words { margin-top: 8px; }
.bank { margin-top: 28px; }
.bank-title { font-size: 10.5px; font-weight: bold; margin-bottom: 9px; }
.footer-line { border-top: 1px solid #cccccc; margin-top: 12px; }
.note { margin-top: 14px; font-size: 10.5px; }
.declaration-title { font-size: 10.5px; font-weight: bold; margin-top: 28px; margin-bottom: 16px; }
.terms-title { font-size: 10.5px; font-weight: bold; margin-top: 42px; margin-bottom: 22px; }
.terms-table { width: 100%; border-collapse: collapse; }
.terms-table td { vertical-align: top; padding: 0 0 18px; }
.term-no { width: 25px; }
a { color: #000080; text-decoration: underline; }
</style>
</head>
<body>
<div class="invoice">
    <div class="top-band">
        <div class="brand-block">
            <table class="brand-table">
                <tr>
                    <td class="brand-logo-cell">' . $logoHtml . '</td>
                    <td><span class="brand-name">Drivault</span></td>
                </tr>
            </table>
        </div>
        <div class="status-card">
            <table class="status-table">
                <tr>
                    <td>
                        <span class="status-text">' . invoiceEsc($statusText) . '</span>
                    </td>
                </tr>
            </table>
        </div>
        <div class="due-copy" style="position:absolute;right:0;top:74px;width:170px;text-align:center;">The due date for this<br>invoice is ' . invoiceEsc($dueDate) . '<br>12:00AM</div>
    </div>

    <div class="section-line"></div>

    <table class="two-col">
        <tr>
            <td>
                <div class="section-title">From</div>
                <div class="line">Drivault</div>
                <div class="line">Fourth floor, oneness, CTS No 4837/2/2</div>
                <div class="line">1st cross, 1st main sadashiv nagar</div>
                <div class="line">Belagavi, Karnataka, India</div>
                <div class="line">Mob: 6363900800</div>
                <div class="line">Email: support@drivault.com</div>
                <div class="line">GST: 29AADCO5170D1ZI</div>
            </td>
            <td class="right-col">
                <div class="section-title">Invoice Details</div>
                <table class="detail-table">
                    <tr><td class="detail-label">Invoice Number:</td><td class="detail-value">' . invoiceEsc($invoiceNumber) . '</td></tr>
                    <tr><td class="detail-label">Date of issue:</td><td class="detail-value">' . invoiceEsc($invoiceDate) . ' 12:00AM</td></tr>
                    <tr><td class="detail-label">Payment due on:</td><td class="detail-value">' . invoiceEsc($dueDate) . ' 12:00AM</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">Billing Details</div>
    <div class="line">' . invoiceEsc($customerName) . '</div>
    <div class="line">' . invoiceEsc($customerEmail !== '' ? $customerEmail : '-') . '</div>
    <div class="line">Mob: ' . invoiceEsc($customerPhone !== '' ? $customerPhone : '-') . '</div>

    <div class="section-line" style="margin-top:22px;"></div>

    <div class="summary-title">Summary</div>
    <table class="summary">
        <thead>
            <tr>
                <th class="num">#</th>
                <th>Description</th>
                <th class="qty">Qty</th>
                <th class="money">Amount</th>
                <th class="money">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">1</td>
                <td>' . invoiceEsc($description) . '<br>Type: ' . invoiceEsc($subscriptionAction) . '</td>
                <td class="qty">1</td>
                <td class="money">Rs ' . number_format($planAmount, 2) . '</td>
                <td class="money">Rs ' . number_format($planAmount, 2) . '</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="label">Sub Total</td><td class="amount">Rs ' . number_format($planAmount, 2) . '</td></tr>
        <tr><td class="label">CGST(9%) SGST (9%) IGST(18.00%)</td><td class="amount">+ Rs ' . number_format($gstAmount, 2) . '</td></tr>
        <tr><td class="label strong">Total</td><td class="amount strong">Rs ' . number_format($totalAmount, 2) . '</td></tr>
    </table>

    <div class="words-title">Amount in words</div>
    <div class="words">' . invoiceEsc(invoiceAmountInWords($totalAmount)) . '</div>

    <div class="bank">
        <div class="bank-title">Bank Details:</div>
        <div class="line">Bank Name: HDFC Bank Limited</div>
        <div class="line">Company Name: Drivault</div>
        <div class="line">AC NO.: 50200060111824</div>
        <div class="line">Branch: Bauxite Road, Belagavi</div>
        <div class="line">IFSC: HDFC0005640</div>
    </div>

    <div class="footer-line"></div>
    <div class="note">For active subscriptions, the applicable renewal amount will be automatically charged on the scheduled renewal date using the customer&#39;s authorized payment method.</div>

    <div class="declaration-title">Invoice Declaration</div>
    <div>This invoice is issued for cloud storage services provided as per the agreed terms and conditions. Applicable GST has been charged as per Indian tax laws. No physical goods are involved in this transaction.</div>

    <div class="terms-title">Terms &amp; Conditions</div>
    <table class="terms-table">
        <tr><td class="term-no">1.</td><td>The subscription will automatically expire at the end of the applicable service period unless the subscription is successfully renewed or payment is received in accordance with the applicable plan.</td></tr>
        <tr><td class="term-no">2.</td><td>Service shall be available only on advance pre-payment.</td></tr>
        <tr><td class="term-no">3.</td><td>Payment in favour of: Drivault.</td></tr>
        <tr><td class="term-no">4.</td><td>Terms &amp; Conditions at <a href="https://www.drivault.com/">https://www.drivault.com/</a></td></tr>
        <tr><td class="term-no">5.</td><td>Drivault\'s entire liability to the customer concerning performance or non-performance, or in any way related to the services offered, shall not exceed the amount received by Drivault from the customer during the current month only.</td></tr>
        <tr><td class="term-no">6.</td><td>By acknowledging the invoice, the customer agrees to indemnify Drivault against claims arising from any misuse of software or services by the customer.</td></tr>
        <tr><td class="term-no">7.</td><td>GST @ 18% will be charged on all amounts.</td></tr>
    </table>
</div>
</body>
</html>';
}

function buildInvoiceHtml(array $payment): string
{
    return buildOobStyleInvoiceHtml($payment);

    /*
    |--------------------------------------------------------------------------
    | Basic values
    |--------------------------------------------------------------------------
    */

    $logoSrc = getInvoiceLogoSrc();

    $customerName =
        trim((string) (
            $payment['drivault_display_name']
            ?? $payment['name']
            ?? 'Customer'
        ));

    $customerEmail =
        trim((string) (
            $payment['drivault_email']
            ?? $payment['email']
            ?? ''
        ));

    $customerPhone =
        trim((string) (
            $payment['user_phone']
            ?? $payment['phone']
            ?? $payment['drivault_phone']
            ?? ''
        ));

    $planName =
        trim((string) (
            $payment['plan_name']
            ?? $payment['name']
            ?? 'Drivault Plan'
        ));

    $quota =
        trim((string) (
            $payment['quota']
            ?? $payment['storage_quota']
            ?? ''
        ));

    $billingCycle =
        ucfirst(
            trim(
                (string) (
                    $payment['billing_cycle']
                    ?? 'monthly'
                )
            )
        );

    $paymentStatus =
        trim(
            (string) (
                $payment['payment_status']
                ?? $payment['status']
                ?? 'Success'
            )
        );

    $orderId =
        trim(
            (string) (
                $payment['razorpay_order_id']
                ?? ''
            )
        );

    $paymentId =
        trim(
            (string) (
                $payment['razorpay_payment_id']
                ?? ''
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Amount
    |--------------------------------------------------------------------------
    |
    | paid_amount in your current project is the amount including GST.
    | Therefore GST is extracted from the total.
    |
    */

    $totalAmount = round(
        (float) (
            $payment['paid_amount']
            ?? 0
        ),
        2
    );

    $gstPercent = 18.00;

    $planAmount = round(
        $totalAmount / (1 + ($gstPercent / 100)),
        2
    );

    $gstAmount = round(
        $totalAmount - $planAmount,
        2
    );


    /*
    |--------------------------------------------------------------------------
    | Invoice number
    |--------------------------------------------------------------------------
    */

    $invoiceNumber =
        'INV-' .
        date('Ymd') .
        '-' .
        (int) ($payment['id'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Company information
    |--------------------------------------------------------------------------
    |
    | Keep these values here so the invoice layout can be changed without
    | touching payment code.
    |
    */

    $companyName = 'Drivault';

    $companyLegalName =
        'Drivault Cloud Solutions Pvt. Ltd.';

    $companyLocation =
        'Belagavi, Karnataka, India';

    $supportEmail =
        'support@drivault.com';

    $website =
        'www.drivault.com';


    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    */

    $logoHtml = '';

    if ($logoSrc !== '') {

        $logoHtml =
            '<img
                src="' . $logoSrc . '"
                class="logo-image"
                alt="Drivault"
            >';

    } else {

        $logoHtml =
            '<div class="logo-fallback">D</div>';

    }


    /*
    |--------------------------------------------------------------------------
    | Payment status
    |--------------------------------------------------------------------------
    */

    $statusLower = strtolower($paymentStatus);

    if (
        $statusLower === 'success' ||
        $statusLower === 'successful' ||
        $statusLower === 'paid' ||
        $statusLower === 'active'
    ) {

        $statusClass = 'status-success';

        $statusText = 'Paid';

    } else {

        $statusClass = 'status-pending';

        $statusText = ucfirst($paymentStatus);
    }


    /*
    |--------------------------------------------------------------------------
    | Optional values
    |--------------------------------------------------------------------------
    */

    $subscriptionAction =
        trim(
            (string) (
                $payment['subscription_action']
                ?? $payment['payment_type']
                ?? 'Purchase'
            )
        );

    $subscriptionAction =
        ucfirst($subscriptionAction);


    /*
    |--------------------------------------------------------------------------
    | Return invoice
    |--------------------------------------------------------------------------
    */

    return '
<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

@page {
    margin: 30px 32px 35px 32px;
}

body {

    margin: 0;

    padding: 0;

    background: #ffffff;

    color: #1f2937;

    font-family:
        DejaVu Sans,
        Arial,
        Helvetica,
        sans-serif;

    font-size: 11px;

    line-height: 1.45;
}


/*
|--------------------------------------------------------------------------
| Main container
|--------------------------------------------------------------------------
*/

.invoice {

    width: 100%;

    margin: 0 auto;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.header {

    width: 100%;

    padding-bottom: 18px;

    border-bottom: 3px solid #12b76a;

}

.header-table {

    width: 100%;

    border-collapse: collapse;
}

.header-left {

    width: 55%;

    vertical-align: middle;

    text-align: left;
}

.header-right {

    width: 45%;

    vertical-align: middle;

    text-align: right;
}

.brand {

    font-size: 25px;

    font-weight: 700;

    color: #111827;

    vertical-align: middle;
}

.logo-image {

    width: 46px;

    height: 46px;

    object-fit: contain;

    vertical-align: middle;

    margin-right: 10px;
}

.logo-fallback {

    display: inline-block;

    width: 46px;

    height: 46px;

    line-height: 46px;

    text-align: center;

    background: #eaf8f0;

    border: 2px solid #12b76a;

    border-radius: 12px;

    color: #12b76a;

    font-size: 25px;

    font-weight: 700;

    vertical-align: middle;

    margin-right: 10px;
}

.invoice-heading {

    font-size: 23px;

    font-weight: 700;

    color: #12b76a;

    margin: 0 0 4px 0;
}

.invoice-subheading {

    color: #6b7280;

    font-size: 10px;
}


/*
|--------------------------------------------------------------------------
| Invoice information
|--------------------------------------------------------------------------
*/

.invoice-info {

    width: 100%;

    border-collapse: collapse;

    margin-top: 20px;

    margin-bottom: 18px;
}

.invoice-info td {

    width: 50%;

    vertical-align: top;

    padding: 5px 0;
}

.info-label {

    color: #6b7280;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: .4px;

    margin-bottom: 2px;
}

.info-value {

    color: #111827;

    font-size: 10.5px;

    font-weight: 600;
}

.text-right {

    text-align: right;
}


/*
|--------------------------------------------------------------------------
| Bill To / Company box
|--------------------------------------------------------------------------
*/

.customer-box {

    width: 100%;

    border: 1px solid #dce5df;

    background: #f5faf7;

    border-radius: 7px;

    padding: 13px 15px;

    margin-bottom: 20px;
}

.customer-table {

    width: 100%;

    border-collapse: collapse;
}

.customer-title {

    color: #12b76a;

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 8px;
}

.customer-label {

    color: #6b7280;

    font-size: 9px;
}

.customer-value {

    color: #111827;

    font-size: 10.5px;

    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| Item table
|--------------------------------------------------------------------------
*/

.items-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 6px;

    margin-bottom: 15px;
}

.items-table th {

    background: #12b76a;

    color: #ffffff;

    padding: 10px 8px;

    font-size: 9.5px;

    font-weight: 700;

    text-align: left;

    border: 1px solid #12b76a;
}

.items-table td {

    padding: 10px 8px;

    border: 1px solid #dfe5e2;

    color: #374151;

    font-size: 10px;

    background: #ffffff;
}

.items-table td.amount {

    text-align: right;

    font-weight: 700;

}

.items-table td.status-cell {

    text-align: center;

}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {

    display: inline-block;

    padding: 4px 9px;

    border-radius: 20px;

    font-size: 8.5px;

    font-weight: 700;
}

.status-success {

    background: #e9f8ef;

    color: #0f9f5b;
}

.status-pending {

    background: #fff7e6;

    color: #b7791f;
}


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

.summary-wrapper {

    width: 100%;

    margin-top: 8px;

    margin-bottom: 20px;
}

.summary-table {

    width: 48%;

    margin-left: auto;

    border-collapse: collapse;
}

.summary-table td {

    padding: 7px 10px;

    border-bottom: 1px solid #e5e7eb;

    font-size: 10px;
}

.summary-label {

    font-weight: 600;

    color: #374151;
}

.summary-value {

    text-align: right;

    color: #374151;
}

.total-row td {

    background: #eaf8f0;

    color: #111827;

    font-size: 12px;

    font-weight: 700;

    border-bottom: none;

}

.total-value {

    color: #12b76a !important;

    font-size: 13px !important;
}


/*
|--------------------------------------------------------------------------
| Payment information
|--------------------------------------------------------------------------
*/

.payment-box {

    border: 1px solid #e1e7e3;

    border-radius: 7px;

    padding: 12px 15px;

    margin-bottom: 20px;

    background: #ffffff;
}

.payment-title {

    font-size: 12px;

    font-weight: 700;

    color: #111827;

    margin-bottom: 8px;
}

.payment-table {

    width: 100%;

    border-collapse: collapse;
}

.payment-table td {

    padding: 5px 0;

    font-size: 9.5px;

    border-bottom: 1px solid #f0f2f1;
}

.payment-table tr:last-child td {

    border-bottom: none;
}

.payment-label {

    color: #6b7280;

    width: 35%;
}

.payment-value {

    color: #111827;

    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| Contact section
|--------------------------------------------------------------------------
*/

.contact-box {

    width: 100%;

    border-top: 1px solid #dce2de;

    border-bottom: 1px solid #dce2de;

    padding: 14px 0;

    margin-top: 8px;

    margin-bottom: 15px;
}

.contact-table {

    width: 100%;

    border-collapse: collapse;
}

.contact-table td {

    width: 33.33%;

    text-align: center;

    vertical-align: top;

    padding: 5px 10px;

    color: #4b5563;

    font-size: 9px;
}

.contact-table td + td {

    border-left: 1px solid #e0e5e2;
}

.contact-icon {

    color: #12b76a;

    font-size: 17px;

    font-weight: 700;

    margin-bottom: 3px;
}

.contact-title {

    color: #111827;

    font-size: 9px;

    font-weight: 700;

    margin-bottom: 2px;
}


/*
|--------------------------------------------------------------------------
| Declaration
|--------------------------------------------------------------------------
*/

.declaration {

    background: #f5faf7;

    border: 1px solid #dce5df;

    border-radius: 6px;

    padding: 10px 13px;

    margin-bottom: 14px;

    color: #4b5563;

    font-size: 9px;
}

.declaration strong {

    color: #111827;
}


/*
|--------------------------------------------------------------------------
| Terms
|--------------------------------------------------------------------------
*/

.terms {

    margin-top: 8px;

    color: #555;

    font-size: 8.5px;
}

.terms-title {

    color: #111827;

    font-size: 10px;

    font-weight: 700;

    margin-bottom: 6px;
}

.terms-table {

    width: 100%;

    border-collapse: collapse;
}

.terms-table td {

    width: 50%;

    padding: 3px 8px;

    vertical-align: top;

    color: #555;

    font-size: 8.5px;
}


/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

.footer {

    text-align: center;

    color: #9ca3af;

    font-size: 8px;

    margin-top: 16px;

    padding-top: 10px;

    border-top: 1px solid #e5e7eb;
}

</style>

</head>

<body>

<div class="invoice">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="header">

<table class="header-table">

<tr>

<td class="header-left">

    ' . $logoHtml . '

    <span class="brand">
        ' . invoiceEsc($companyName) . '
    </span>

</td>

<td class="header-right">

    <div class="invoice-heading">
        Payment Invoice
    </div>

    <div class="invoice-subheading">
        Computer-generated invoice
    </div>

</td>

</tr>

</table>

</div>


<!-- =========================================================
     INVOICE INFORMATION
========================================================= -->

<table class="invoice-info">

<tr>

<td>

    <div class="info-label">
        Invoice Number
    </div>

    <div class="info-value">
        ' . invoiceEsc($invoiceNumber) . '
    </div>

</td>

<td class="text-right">

    <div class="info-label">
        Invoice Date
    </div>

    <div class="info-value">
        ' . invoiceEsc(invoiceDate($payment)) . '
    </div>

</td>

</tr>

<tr>

<td>

    <div class="info-label">
        Order ID
    </div>

    <div class="info-value">
        ' . invoiceEsc($orderId !== '' ? $orderId : '-') . '
    </div>

</td>

<td class="text-right">

    <div class="info-label">
        Payment ID
    </div>

    <div class="info-value">
        ' . invoiceEsc($paymentId !== '' ? $paymentId : '-') . '
    </div>

</td>

</tr>

</table>


<!-- =========================================================
     BILL TO
========================================================= -->

<div class="customer-box">

<table class="customer-table">

<tr>

<td style="width:50%;">

    <div class="customer-title">
        Bill To
    </div>

    <div class="customer-label">
        Name
    </div>

    <div class="customer-value">
        ' . invoiceEsc($customerName) . '
    </div>

</td>

<td style="width:50%;">

    <div class="customer-label">
        Email
    </div>

    <div class="customer-value">
        ' . invoiceEsc($customerEmail !== '' ? $customerEmail : '-') . '
    </div>

    <br>

    <div class="customer-label">
        Phone
    </div>

    <div class="customer-value">
        ' . invoiceEsc($customerPhone !== '' ? $customerPhone : '-') . '
    </div>

</td>

</tr>

</table>

</div>


<!-- =========================================================
     PLAN DETAILS
========================================================= -->

<table class="items-table">

<thead>

<tr>

<th style="width:24%;">
    Plan
</th>

<th style="width:17%;">
    Storage
</th>

<th style="width:17%;">
    Billing Cycle
</th>

<th style="width:16%;">
    Type
</th>

<th style="width:14%; text-align:right;">
    Amount
</th>

<th style="width:12%; text-align:center;">
    Status
</th>

</tr>

</thead>

<tbody>

<tr>

<td>
    ' . invoiceEsc($planName) . '
</td>

<td>
    ' . invoiceEsc($quota !== '' ? $quota : '-') . '
</td>

<td>
    ' . invoiceEsc($billingCycle) . '
</td>

<td>
    ' . invoiceEsc($subscriptionAction) . '
</td>

<td class="amount">
    ₹' . number_format($totalAmount, 2) . '
</td>

<td class="status-cell">

    <span class="status ' . $statusClass . '">
        ' . invoiceEsc($statusText) . '
    </span>

</td>

</tr>

</tbody>

</table>


<!-- =========================================================
     AMOUNT SUMMARY
========================================================= -->

<div class="summary-wrapper">

<table class="summary-table">

<tr>

<td class="summary-label">
    Plan Amount
</td>

<td class="summary-value">
    ₹' . number_format($planAmount, 2) . '
</td>

</tr>

<tr>

<td class="summary-label">
    GST (' . number_format($gstPercent, 0) . '%)
</td>

<td class="summary-value">
    ₹' . number_format($gstAmount, 2) . '
</td>

</tr>

<tr class="total-row">

<td>
    Total
</td>

<td class="summary-value total-value">
    ₹' . number_format($totalAmount, 2) . '
</td>

</tr>

</table>

</div>


<!-- =========================================================
     PAYMENT DETAILS
========================================================= -->

<div class="payment-box">

<div class="payment-title">
    Payment Details
</div>

<table class="payment-table">

<tr>

<td class="payment-label">
    Payment Method
</td>

<td class="payment-value">
    ' . invoiceEsc(
        $payment['payment_method']
        ?? 'Razorpay'
    ) . '
</td>

</tr>

<tr>

<td class="payment-label">
    Payment Status
</td>

<td class="payment-value">
    ' . invoiceEsc($statusText) . '
</td>

</tr>

<tr>

<td class="payment-label">
    Razorpay Payment ID
</td>

<td class="payment-value">
    ' . invoiceEsc($paymentId !== '' ? $paymentId : '-') . '
</td>

</tr>

<tr>

<td class="payment-label">
    Order ID
</td>

<td class="payment-value">
    ' . invoiceEsc($orderId !== '' ? $orderId : '-') . '
</td>

</tr>

</table>

</div>


<!-- =========================================================
     COMPANY / CONTACT
========================================================= -->

<div class="contact-box">

<table class="contact-table">

<tr>

<td>

    <div class="contact-icon">
        ✉
    </div>

    <div class="contact-title">
        Email
    </div>

    ' . invoiceEsc($supportEmail) . '

</td>

<td>

    <div class="contact-icon">
        🌐
    </div>

    <div class="contact-title">
        Website
    </div>

    ' . invoiceEsc($website) . '

</td>

<td>

    <div class="contact-icon">
        ☎
    </div>

    <div class="contact-title">
        Support
    </div>

    We are here to help

</td>

</tr>

</table>

</div>


<!-- =========================================================
     COMPANY INFORMATION
========================================================= -->

<div style="text-align:center;margin-bottom:12px;">

    <div style="
        font-size:10px;
        font-weight:700;
        color:#111827;
        margin-bottom:2px;
    ">
        ' . invoiceEsc($companyLegalName) . '
    </div>

    <div style="
        font-size:8.5px;
        color:#6b7280;
    ">
        ' . invoiceEsc($companyLocation) . '
    </div>

</div>


<!-- =========================================================
     DECLARATION
========================================================= -->

<div class="declaration">

    <strong>Invoice Declaration:</strong>

    This invoice is issued for Drivault cloud storage services.
    Applicable GST has been charged as per the applicable tax rules.
    This is a computer-generated invoice and does not require a signature.

</div>


<!-- =========================================================
     TERMS & CONDITIONS
========================================================= -->

<div class="terms">

<div class="terms-title">
    Terms &amp; Conditions
</div>

<table class="terms-table">

<tr>

<td>
    • Storage plans are non-refundable once activated.
</td>

<td>
    • Subscription renewal is subject to the selected plan.
</td>

</tr>

<tr>

<td>
    • Services are provided according to the selected subscription period.
</td>

<td>
    • Billing or payment queries should be directed to Drivault Support.
</td>

</tr>

<tr>

<td colspan="2">
    • Continued use of Drivault services is subject to the applicable
      service terms and conditions.
</td>

</tr>

</table>

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<div class="footer">

    Thank you for choosing Drivault.

    <br>

    ' . invoiceEsc($website) . '
    &nbsp; | &nbsp;
    ' . invoiceEsc($supportEmail) . '

</div>


</div>

</body>

</html>
';
}


/*
|--------------------------------------------------------------------------
| Render PDF using Dompdf
|--------------------------------------------------------------------------
*/

function renderInvoicePdf(string $html): string
{
    $options = new Options();

    $options->set(
        'isRemoteEnabled',
        true
    );

    $options->set(
        'isHtml5ParserEnabled',
        true
    );

    $options->set(
        'defaultFont',
        'DejaVu Sans'
    );

    $options->set(
        'chroot',
        realpath(__DIR__ . '/..')
    );

    $dompdf = new Dompdf($options);

    $dompdf->loadHtml(
        $html,
        'UTF-8'
    );

    $dompdf->setPaper(
        'A4',
        'portrait'
    );

    $dompdf->render();

    return $dompdf->output();
}
