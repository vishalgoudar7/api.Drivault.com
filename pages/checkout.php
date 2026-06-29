<?php

session_start();

require '../config/db.php';

if (!isset($_GET['plan_id'])) {
    die("Plan not found");
}

$planId = (int)$_GET['plan_id'];

$stmt = $conn->prepare(
    "SELECT * FROM plans WHERE id=? AND status=1"
);

$stmt->bind_param("i", $planId);
$stmt->execute();

$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    die("Invalid plan");
}

/*
|--------------------------------------------------------------------------
| Logged in user
|--------------------------------------------------------------------------
*/

$userId = $_SESSION['user_id'] ?? 1;

$stmt = $conn->prepare(
    "SELECT name,email,phone FROM users WHERE id=?"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$price = $plan['monthly_price'];
$gst = round($price * 0.18, 2);
$total = $price + $gst;

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>Checkout - Drivault</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f8fafc;
}

.checkout-card{
    max-width:800px;
    margin:auto;
    background:#fff;
    border-radius:20px;
    padding:30px;
    box-shadow:0 5px 25px rgba(0,0,0,.08);
}

.checkout-header{
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    gap:16px;
    margin-bottom:24px;
}

.checkout-title-row{
    position:relative;
    width:100%;
    min-height:48px;
    display:flex;
    align-items:center;
}

.brand-logo{
    display:flex;
    align-items:center;
    gap:8px;
    color:#111827;
    font-weight:700;
    font-size:22px;
    text-decoration:none;
}

.brand-logo img{
    width:34px;
    height:34px;
    object-fit:contain;
}

.checkout-content{
    padding-top:0;
}

.checkout-title{
    position:absolute;
    left:50%;
    transform:translateX(-50%);
    text-align:center;
    margin:0;
}

.plan-badge{
    background:#38d989;
    color:#fff;
    padding:10px 18px;
    border-radius:10px;
    display:inline-block;
    font-weight:600;
}

.total{
    font-size:34px;
    font-weight:700;
    color:#38d989;
}

.btn-pay{
    background:#38d989;
    border:none;
    padding:14px;
    font-size:18px;
    font-weight:600;
}

.btn-pay:hover{
    background:#2ec477;
}

.btn-pay:disabled{
    background:#38d989;
    border:none;
    opacity:.8;
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

@keyframes paySpin{
    to{
        transform:rotate(360deg);
    }
}

.custom-toast {
    visibility: hidden;
    min-width: 350px;
    max-width: 450px;
    background: #dc3545;
    color: #fff;
    text-align: center;
    border-radius: 8px;
    padding: 16px 24px;
    position: fixed;
    z-index: 99999;

    /* Top center position */
    top: 30px;
    left: 50%;
    transform: translateX(-50%);

    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,.3);

    opacity: 0;
    transition: all .4s ease;
}

.custom-toast.show {
    visibility: visible;
    opacity: 1;
}
/* Mobile Responsive */
@media (max-width: 768px) {

    .container {
        padding-left: 15px;
        padding-right: 15px;
    }

    .checkout-card {
        padding: 20px;
        border-radius: 15px;
    }

    .checkout-header {
        gap: 12px;
    }

    .checkout-title-row {
        flex-direction: column;
        align-items: center;
        gap: 15px;
        min-height: auto;
    }

    .checkout-title{
    position:static;
    transform:none;
    font-size:34px; /* Reduced size */
    font-weight:700;
    margin:10px 0 0;
}

    .brand-logo {
        justify-content: center;
        width: 100%;
        font-size: 24px;
    }

    .brand-logo img {
        width: 30px;
        height: 30px;
    }

    .table td {
        padding: 10px 5px;
        font-size: 14px;
    }

    .plan-badge {
        width: 100%;
        text-align: center;
        padding: 12px;
    }

    .total {
        font-size: 28px;
    }

    .btn-pay {
        width: 100%;
        font-size: 16px;
        padding: 12px;
    }

    .custom-toast {
        min-width: auto;
        width: 90%;
        max-width: 90%;
        font-size: 13px;
        padding: 14px;
    }

    .form-control {
        font-size: 16px;
    }
}
@media (max-width:768px){

    .container{
        padding-left:12px;
        padding-right:12px;
    }

    .checkout-card{
        padding:20px;
        border-radius:20px;
    }

    .checkout-header{
    position:relative;
    text-align:center;
    align-items:center;
    padding-top:10px;
}
    .brand-logo{
        justify-content:center;
        width:100%;
        font-size:32px;
        font-weight:700;
    }

    .brand-logo img{
        width:40px;
        height:40px;
    }

    .checkout-title-row{
        width:100%;
        min-height:auto;
        flex-direction:column;
        gap:12px;
    }

    .checkout-title{
        position:static;
        transform:none;
        font-size:35px;
        font-weight:800;
        margin:0;
    }

    .checkout-title::after{
        content:"Review your selected plan and complete your payment securely.";
        display:block;
        font-size:15px;
        font-weight:400;
        color:#64748b;
        margin-top:12px;
        line-height:1.5;
    }

    .checkout-title-row .btn{
    position:absolute;
    top:-15px;
    left:-5px;
    width:46px;
    height:46px;
    padding:0;
    border-radius:12px;
    font-size:22px;
    display:flex;
    align-items:center;
    justify-content:center;
}

    h5{
        font-size:28px;
        margin-bottom:20px;
        font-weight:700;
    }

    .plan-badge{
        width:100%;
        text-align:center;
        padding:16px;
        font-size:22px;
        border-radius:14px;
    }

    .table td{
        padding:16px 0;
        font-size:17px;
    }

    .table td:last-child{
        text-align:right;
        font-weight:600;
    }

    .total{
        font-size:34px;
        text-align:center;
        margin:20px 0;
    }

    .form-control{
        border-radius:14px;
        padding:14px;
        font-size:16px;
    }

    .btn-pay{
        width:100%;
        padding:16px;
        font-size:18px;
        border-radius:16px;
    }

    .custom-toast{
        width:90%;
        min-width:auto;
        max-width:90%;
    }

    .row .col-md-6:first-child{
        margin-bottom:30px;
    }
}
</style>

</head>
<body>

<div class="container py-3 py-md-5">

<div class="checkout-card">

<div class="checkout-header">

<a href="pricing.php" class="brand-logo" aria-label="Drivault">
    <img src="../assets/Photos/icon-192.png" alt="">
    <span>Drivault</span>
</a>

<div class="checkout-title-row">

<h2 class="fw-bold checkout-title">
    Checkout
</h2>

<a href="pricing.php"
   class="btn btn-light shadow-sm">
   ←
</a>

</div>

</div>

<div class="checkout-content">

<div class="row"><div class="row">
<div class="col-md-6">

<h5>Selected Plan</h5>

<div class="plan-badge mb-3">
    <?= htmlspecialchars($plan['name']) ?>
</div>

<table class="table">

<tr>
<td>Storage</td>
<td><strong><?= $plan['quota'] ?></strong></td>
</tr>

<tr>
<td>Price</td>
<td>₹<?= number_format($price,2) ?></td>
</tr>

<tr>
<td>GST (18%)</td>
<td>₹<?= number_format($gst,2) ?></td>
</tr>

<tr>
<td>Total</td>
<td class="fw-bold">
₹<?= number_format($total,2) ?>
</td>
</tr>

</table>

</div>

<div class="col-md-6">

<h5>Personal Details</h5>

<div class="mb-3">
<label class="form-label">
    Name <span class="text-danger">*</span>
</label>
<input type="text"
       class="form-control"
       id="name"
         placeholder="Enter your full name"
       name="checkout_name_blank"
       value=""
       autocomplete="new-password"
       data-lpignore="true"
       data-form-type="other"
       required>

<div id="nameError" class="text-danger small mt-1"></div>
</div>

<div class="mb-3">
<label class="form-label">
    Email <span class="text-danger">*</span>
</label>
<input type="email"
       class="form-control"
       id="email"
       name="checkout_email_blank"
       value=""
       placeholder="Enter your email address"
       autocomplete="new-password"
       data-lpignore="true"
       data-form-type="other"
       required>

<div id="emailError" class="text-danger small mt-1"></div>
</div>

<div class="mb-3">
<label class="form-label">
    Mobile Number <span class="text-danger">*</span>
</label>
<input type="text"
       class="form-control"
       id="phone"
       name="checkout_phone_blank"
       value=""
       maxlength="10"
       autocomplete="new-password"
       data-lpignore="true"
       data-form-type="other"
       placeholder="Enter your mobile number"
       oninput="this.value=this.value.replace(/[^0-9]/g,'')"
       required>

<div id="phoneError" class="text-danger small mt-1"></div>


</div>

</div>

<hr>

<div class="text-center">

<div class="total mb-3">
₹<?= number_format($total,2) ?>
</div>

<button
    class="btn btn-pay btn-success w-100"
    id="payBtn">
Proceed to Pay</button>
<p class="text-muted mt-3">
    🔒 Secure payments powered by Razorpay
</p>

</div>

</div>

</div>

</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>

document.getElementById('payBtn')
.addEventListener('click', async function() {

    const btn = this;
    const defaultText = 'Pay with Razorpay';

    function setLoading(text) {
        btn.innerHTML = '<span class="pay-spinner"></span>' + text;
        btn.disabled = true;
    }

    function resetButton() {
        btn.innerHTML = defaultText;
        btn.disabled = false;
    }

    let customerName = document.getElementById("name").value.trim();
let customerEmail = document.getElementById("email").value.trim();
let customerPhone = document.getElementById("phone").value.trim();

document.getElementById("nameError").innerText = "";
document.getElementById("emailError").innerText = "";
document.getElementById("phoneError").innerText = "";

/* Validation */

if (customerName === '') {
    document.getElementById("nameError").innerText =
        "Please enter your name";
    return;
}

if (!/^[A-Za-z ]+$/.test(customerName)) {
    document.getElementById("nameError").innerText =
        "Name should contain only letters";
    return;
}

if (customerName.length < 3) {
    document.getElementById("nameError").innerText =
        "Name must be at least 3 characters";
    return;
}

const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

if (!emailRegex.test(customerEmail)) {
    document.getElementById("emailError").innerText =
        "Please enter a valid email address";
    return;
}

const phoneRegex = /^[6-9]\d{9}$/;

if (!phoneRegex.test(customerPhone)) {
    document.getElementById("phoneError").innerText =
        "Please enter a valid 10-digit mobile number";
    return;
}

/* Verify Drivault account before creating order */

setLoading('Verifying account...');

try {

    let verifyResponse = await fetch(
        '../api/verify-drivault-user.php',
        {
            method: 'POST',
            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                email: customerEmail,
                phone: customerPhone
            })
        }
    );

    let verifyData = await verifyResponse.json();

    if (!verifyData.success) {
    resetButton();

    showToast(
        verifyData.message ||
        'You do not have a Drivault account. Please create an account first.'
    );

    return;
}

} catch (error) {
    resetButton();
    console.error(error);

    showToast('Unable to verify Drivault account.');

    return;
}

setLoading('Creating order...');

let data;

try {

    let response = await fetch(
        '../api/create-order.php',
        {
            method: 'POST',
            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                user_id: '<?= $userId ?>',
                plan_id: '<?= $planId ?>',
                billing_cycle: 'monthly',
                name: customerName,
                email: customerEmail,
                phone: customerPhone
            })
        }
    );

    data = await response.json();

} catch (error) {

    resetButton();
    console.error(error);
    alert('Something went wrong while creating the order.');
    return;
}

    if(!data.success){
    resetButton();

    showToast(data.message);

    return;
}

    // let customerName =
    // document.getElementById("name").value;

    // let customerEmail =
    // document.getElementById("email").value;

    // let customerPhone =
    // document.getElementById("phone").value;

    let options = {

        modal: {
            ondismiss: function() {
                resetButton();
            }
        },

        key:data.key,

        amount:data.amount,

        currency:"INR",

        name:"Drivault",

        description:
        "Storage Upgrade",

        order_id:data.order_id,

        prefill:{
            name: customerName,
            email: customerEmail,
            contact: customerPhone
        },

        handler:function(payment){

            setLoading('Verifying payment...');

            fetch(
                '../api/payment-success.php',
                {
                    method:'POST',
                    headers:{
                        'Content-Type':
                        'application/x-www-form-urlencoded'
                    },
                    body:new URLSearchParams({
                        razorpay_order_id:
                        payment.razorpay_order_id,

                        razorpay_payment_id:
                        payment.razorpay_payment_id,

                        razorpay_signature:
                        payment.razorpay_signature,

                        name: customerName,
                        email: customerEmail,
                        phone: customerPhone
                    })
                }
            )
            .then(r=>r.json())
            .then(res=>{
                if(res.success){
                    window.location =
                        "payment-success.php?payment_id=" +
                        encodeURIComponent(res.payment_id) +
                        "&order_id=" +
                        encodeURIComponent(res.order_id) +
                        "&subscription_id=" +
                        encodeURIComponent(res.subscription_id) +
                        "&plan_id=<?= $planId ?>";
                    return;
                }

                window.location =
                    "payment-failed.php?plan_id=<?= $planId ?>" +
                    "&order_id=" +
                    encodeURIComponent(payment.razorpay_order_id || data.order_id) +
                    "&reason=" +
                    encodeURIComponent(res.message || "Payment verification failed");
            })
            .catch(()=>{
                window.location =
                    "payment-failed.php?plan_id=<?= $planId ?>&reason=" +
                    encodeURIComponent("Unable to verify payment. Please try again.");
            });

        }

    };

    let rzp = new Razorpay(options);

    rzp.on('payment.failed', function(response){
        let message = response.error && response.error.description
            ? response.error.description
            : "Payment failed. Please try again.";

        window.location =
            "payment-failed.php?plan_id=<?= $planId ?>" +
            "&order_id=" +
            encodeURIComponent(data.order_id) +
            "&reason=" +
            encodeURIComponent(message);
    });

    setLoading('Complete payment in Razorpay...');

    rzp.open();

});
function showToast(message, type = 'error') {

    const toast = document.getElementById('toast');

    toast.innerText = message;

    if (type === 'success') {
        toast.style.background = '#28a745';
    } else {
        toast.style.background = '#dc3545';
    }

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
document.getElementById('payBtn')

function showToast(message, type = 'error') {

    const toast = document.getElementById('toast');

    toast.innerText = message;

    toast.style.background =
        type === 'success' ? '#28a745' : '#dc3545';

    toast.classList.add('show');

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

</script>
<div id="toast" class="custom-toast"></div>
</body>
</html>
