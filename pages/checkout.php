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
</style>

</head>
<body>

<div class="container py-5">

<div class="checkout-card">

<a href="pricing.php"
   class="btn btn-light mb-4">
   ← Back
</a>

<h2 class="fw-bold mb-4">
    Checkout
</h2>

<div class="row">

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

<h5>User Details</h5>

<div class="mb-3">
<label>Name</label>
<input type="text"
       class="form-control"
       id="name"
       value="<?= htmlspecialchars($user['name'] ?? '') ?>"
       autocomplete="off"
       required>

<div id="nameError" class="text-danger small mt-1"></div>
</div>

<div class="mb-3">
<label>Email</label>
<input type="email"
       class="form-control"
       id="email"
       value="<?= htmlspecialchars($user['email'] ?? '') ?>"
       autocomplete="off"
       required>

<div id="emailError" class="text-danger small mt-1"></div>
</div>

<div class="mb-3">
<label>Phone</label>
<input type="text"
       class="form-control"
       id="phone"
       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
       maxlength="10"
       autocomplete="off"
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
    Pay with Razorpay
</button>

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

setLoading('Creating order...');

    let data;

    try {
        let response = await fetch(
            '../api/create-order.php',
            {
                method:'POST',
                headers:{
                    'Content-Type':
                    'application/x-www-form-urlencoded'
                },
               body:new URLSearchParams({
    user_id:'<?= $userId ?>',
    plan_id:'<?= $planId ?>',
    billing_cycle:'monthly',
    name: document.getElementById('name').value,
    email: document.getElementById('email').value,
    phone: document.getElementById('phone').value
})
            }
        );

        data = await response.json();
    } catch(error) {
        resetButton();
        console.error(error);
        alert('Something went wrong while creating the order.');
        return;
    }

    if(!data.success){
        resetButton();
        alert(data.message);
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

</script>

</body>
</html>
