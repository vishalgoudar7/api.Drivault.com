<?php
session_start();

$error = "";
$user = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $search = trim($_POST['search']);

    if ($search != "") {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://login.drivault.com/ocs/v1.php/cloud/users/" . urlencode($search),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "OCS-APIRequest: true",
                "Accept: application/json",
                "Authorization: Basic YWRtaW46a3VSc2VmLWdvYm5vOC1nYW5rdXg="
            ]
        ]);

        $response = curl_exec($curl);

if (curl_errno($curl)) {
    die("Curl Error: " . curl_error($curl));
}

$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

echo "<pre>";
echo "HTTP Status: " . $httpCode . "\n";
echo $response;
echo "</pre>";

$data = json_decode($response, true);

        if (
            isset($data['ocs']['meta']['status']) &&
            $data['ocs']['meta']['status'] == "ok"
        ) {

            $user = $data['ocs']['data'];

        } else {

            $error = "User not found.";

        }

    } else {

        $error = "Please enter Email or Username.";

    }

}
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">

<meta name="viewport" content="width=device-width,initial-scale=1">

<title>User Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f5f8fb;
}

.card{

    max-width:700px;

    margin:auto;

    margin-top:50px;

    border:none;

    border-radius:20px;

    box-shadow:0 10px 30px rgba(0,0,0,.08);

}

.logo{

    font-size:34px;

    font-weight:bold;

    color:#38d989;

}

.btn-green{

    background:#38d989;

    color:#fff;

}

.btn-green:hover{

    background:#2bc977;

    color:#fff;

}

.info{

    background:#f8fafc;

    border-radius:10px;

    padding:12px;

    margin-bottom:15px;

}

.info label{

    color:#888;

    font-size:13px;

}

.info h6{

    margin-top:5px;

    font-weight:600;

}

</style>

</head>

<body>

<div class="container">

<div class="card">

<div class="card-body p-5">

<div class="text-center mb-4">

<div class="logo">

Drivault

</div>

<h3 class="mt-3">

User Details

</h3>

<p class="text-muted">

Enter Email or Username

</p>

</div>

<form method="POST">

<div class="mb-3">

<input

type="text"

name="search"

class="form-control form-control-lg"

placeholder="Enter Email or Username"

value="<?= htmlspecialchars($_POST['search'] ?? '') ?>"

required>

</div>

<button

class="btn btn-green w-100 btn-lg">

Get Details

</button>

</form>

<?php if($error!=""): ?>

<div class="alert alert-danger mt-4">

<?= $error ?>

</div>

<?php endif; ?>

<?php if($user): ?>

<hr class="my-4">

<h4 class="mb-4">

Account Information

</h4>

<div class="info">

<label>Name</label>

<h6><?= htmlspecialchars($user['displayname']) ?></h6>

</div>

<div class="info">

<label>User ID</label>

<h6><?= htmlspecialchars($user['id']) ?></h6>

</div>

<div class="info">

<label>Email</label>

<h6><?= htmlspecialchars($user['email']) ?></h6>

</div>

<div class="info">

<label>Phone</label>

<h6><?= htmlspecialchars($user['phone'] ?: '-') ?></h6>

</div>

<div class="info">

<label>Language</label>

<h6><?= htmlspecialchars($user['language']) ?></h6>

</div>

<div class="info">

<label>Backend</label>

<h6><?= htmlspecialchars($user['backend']) ?></h6>

</div>

<?php

$total = $user['quota']['total'];

$used = $user['quota']['used'];

$free = $total - $used;

?>

<div class="info">

<label>Storage Used</label>

<h6><?= round($used/1024/1024,2) ?> MB</h6>

</div>

<div class="info">

<label>Available Storage</label>

<h6><?= round($free/1024/1024/1024,2) ?> GB</h6>

</div>

<div class="info">

<label>Total Storage</label>

<h6><?= round($total/1024/1024/1024,2) ?> GB</h6>

</div>

<a

href="pricing.php?user=<?= urlencode($user['id']) ?>"

class="btn btn-green btn-lg w-100 mt-4">

View Storage Plans →

</a>

<?php endif; ?>

</div>

</div>

</div>

</body>

</html>