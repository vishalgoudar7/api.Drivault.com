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
<!-- <input type="text"
       class="form-control"
       value="<?= htmlspecialchars($user['name']) ?>"
       name="name"> -->
       <input type="text"
       class="form-control"
       id="name"
       value="<?= htmlspecialchars($user['name']) ?>">
</div>

<div class="mb-3">
<label>Email</label>
<!-- <input type="text"
       class="form-control"
       value="<?= htmlspecialchars($user['email']) ?>"
       name="email"> -->
       <input type="text"
       class="form-control"
       id="email"
       value="<?= htmlspecialchars($user['email']) ?>">
</div>

<div class="mb-3">
<label>Phone</label>
<!-- <input type="text"
       class="form-control"
       value="<?= htmlspecialchars($user['phone']) ?>"
       name="phone"> -->
       <input type="text"
       class="form-control"
       id="phone"
       value="<?= htmlspecialchars($user['phone']) ?>">
</div>

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
.addEventListener('click', async () => {

console.log("Sending Request");

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
    billing_cycle:'monthly'
})
        }
    );
    console.log(response);

let data = await response.json();
console.log("Order Created:", data);
console.log(data);

if(!data.success){
    alert(data.message);
    return;
}

  if(!data.success){
    alert(data.message);
    return;
}

let customerName =
document.getElementById("name").value;

let customerEmail =
document.getElementById("email").value;

let customerPhone =
document.getElementById("phone").value;
    let options = {

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
                    // body:new URLSearchParams({
                    //     razorpay_order_id:
                    //     payment.razorpay_order_id,

                    //     razorpay_payment_id:
                    //     payment.razorpay_payment_id,

                    //     razorpay_signature:
                    //     payment.razorpay_signature
                    // })
                }
            )
           .then(r=>r.text())
           .then(res=>{
    alert(res);
})
//             .then(res=>{

//                 if(res.success){

//                     alert(
//                      "Payment Successful! Storage upgraded."
//                     );

//                   window.location =
// "success.php?payment_id=" +
// res.payment_id;

//                 }else{

//                     alert(res.message);

//                 }

//             });

        }

    };

    let rzp = new Razorpay(options);

    rzp.open();

});

</script>

</body>
</html>