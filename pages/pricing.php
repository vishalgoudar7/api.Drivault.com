<?php
require '../config/db.php';

$result = $conn->query("
    SELECT *
    FROM plans
    WHERE status = 1
    ORDER BY monthly_price ASC
");

$plans = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Drivault Storage Plans</title>
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background:#f8fafc;
    font-family:Arial,sans-serif;
}

.section-title{
    font-size:30px;
    font-weight:700;
    color:#0f172a;
    line-height:1.2;
}

.section-subtitle{
    color:#64748b;
    font-size:20px;
}
.feature-box{
    background:#f4fcf8;
    border:1px solid #e4f5eb;
    border-radius:15px;
    padding:25px;
}

.icon-box{
    width:48px;
    height:48px;
    background:#e8fff2;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.icon-box i{
    color:#38d989;
    font-size:24px;
}

.pricing-card{
    background:#fff;
    border-radius:20px;
    padding:30px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
    height:100%;
    position:relative;
    transition:.3s;
}

.pricing-card:hover{
    transform:translateY(-5px);
}

.popular{
    border:2px solid #38d989;
}

.popular-badge{
    position:absolute;
    top:-12px;
    left:50%;
    transform:translateX(-50%);
    background:#38d989;
    color:#fff;
    padding:8px 18px;
    border-radius:12px;
    font-size:14px;
    font-weight:600;
    white-space:nowrap;
    z-index:10;
}

.storage-icon{
    width:75px;
    height:75px;
    background:#e8fff2;
    border-radius:15px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
    font-size:35px;
}

.storage{
    font-size:24px;
    font-weight:700;
    color:#0f172a;
    margin-top:20px;
}

.price{
    color:#38d989;
    font-size:48px;
    font-weight:700;
}

.price small{
    font-size:20px;
    color:#64748b;
}

.btn-plan{
    border:2px solid #38d989;
    color:#38d989;
    border-radius:10px;
    padding:12px;
    width:100%;
    font-weight:600;
}

.btn-plan:hover{
    background:#38d989;
    color:#fff;
}

.btn-popular{
    background:#38d989;
    color:#fff;
}
.pricing-card{
    border:2px solid transparent;
    transition:all 0.3s ease;
}

.pricing-card:hover{
    border:2px solid #38d989;
    transform:translateY(-8px);
    box-shadow:0 12px 30px rgba(56,217,137,0.15);
}
.pricing-card:hover .btn-plan{
    background:#38d989;
    color:#fff;
}
.pricing-card:hover .storage-icon{
    background:#dff8ea;
}

.check-icon{
    color:#38d989;
    font-size:18px;
    margin-right:12px;
    flex-shrink:0;

    width:auto;
    height:auto;
    background:none;
    border-radius:0;
    display:inline-block;
}
.feature-list li{
    display:flex;
    align-items:center;
    margin-bottom:20px; !important
    color:#64748b;
    font-size:15px;
}

.list-unstyled li{
    margin-bottom:12px;
    color:#64748b;
    font-size:15px;
}
.pricing-card{
    background:#fff;
    border-radius:20px;
    padding:30px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
    position:relative;
    transition:.3s;
    display:flex;
    flex-direction:column;
    height:100%;
}

.pricing-card .btn-plan{
    margin-top:auto;
}
/* Tablet */
@media (max-width: 992px){

    .section-title{
        font-size:40px;
    }

    .section-subtitle{
        font-size:18px;
    }

    .pricing-card{
        margin-bottom:20px;
    }
}

@media (max-width:576px){

    .popular-badge{
        font-size:11px;
        padding:6px 12px;
        top:-10px;
    }

    .pricing-grid{
        grid-template-columns:1fr;
    }

    .pricing-card{
        min-height:auto;
    }
}
@media (max-width:768px){

    .pricing-card{
        max-width:420px;
        margin:0 auto;
    }

    .section-title{
        font-size:32px;
    }

    .price{
        font-size:42px;
    }

    .feature-list li{
        font-size:14px;
    }
}

@media (min-width:1200px){

    .col-xl-2{
        width:20%;
    }
}
/* Mobile */
@media (max-width: 768px){

    .section-title{
        font-size:30px;
        line-height:1.3;
    }

    .section-subtitle{
        font-size:16px;
        padding:0 10px;
    }

    .storage{
        font-size:22px;
    }

    .price{
        font-size:40px;
    }

    .feature-box .row > div{
        margin-bottom:20px;
    }

    .navbar-brand strong{
        font-size:24px !important;
    }

    .popular-badge{
        font-size:12px;
        padding:6px 15px;
    }
}

/* Small Mobile */
@media (max-width: 576px){

    .container{
        padding-left:15px;
        padding-right:15px;
    }

    .pricing-card{
        padding:20px;
    }

    .storage-icon{
        width:65px;
        height:65px;
    }

    .price{
        font-size:36px;
    }

    .btn-plan{
        font-size:15px;
    }

    .feature-box{
        padding:20px 15px;
    }

    .d-flex.align-items-center{
        flex-direction:column;
        text-align:center;
    }

    .ms-3{
        margin-left:0 !important;
        margin-top:10px;
    }
}
.row.pricing-row{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    gap:20px;
}

.pricing-row .col-xl{
    flex:1;
    min-width:250px;
    max-width:300px;
}

.pricing-card{
    background:#fff;
    border-radius:20px;
    padding:25px;
    box-shadow:0 5px 20px rgba(0,0,0,.08);
    position:relative;
    transition:.3s;
    display:flex;
    flex-direction:column;
}
.pricing-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:25px;
}

.pricing-card{
    height:100%;
}
</style>

</head>
<body>
    <nav class="navbar navbar-expand-lg bg-white shadow-sm">
    <div class="container">

        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="/New%20folder/assets/Photos/icon-192.png"
                 alt="Drivault"
                 width="40"
                 height="40"
                 class="me-2">
            <strong style="font-size:30px;color:#0f172a;">Drivault</strong>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- <ul class="navbar-nav ms-auto">

                <li class="nav-item">
                    <a class="nav-link" href="#">Features</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#">How It Works</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link active text-success" href="#">
                        Pricing
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#">FAQ</a>
                </li>

                <li class="nav-item ms-3">
                    <a href="#"
                       class="btn btn-success px-4">
                       Request Invite
                    </a>
                </li>

            </ul> -->
        </div>

    </div>
</nav>

<div class="container-fluid px-4 py-5">

    <div class="text-center mb-5">
        <h1 class="section-title">
            Flexible Storage Plans for Every Need
        </h1>

        <p class="section-subtitle">
             Store, protect, and access your files securely. Upgrade your storage whenever you need more space.
        </p>
    </div>

    <div class="pricing-grid">

        <?php foreach($plans as $plan): ?>

       <div>
            <div class="pricing-card <?= ($plan['id'] == 3) ? 'popular' : '' ?>">

              <?php if($plan['id'] == 3): ?>
                    <div class="popular-badge">
                        ⭐ MOST POPULAR
                    </div>
                <?php endif; ?>

                <div class="text-center">

                    <div class="storage-icon">
    <svg width="40" height="40" fill="#38d989"
         xmlns="http://www.w3.org/2000/svg">
        <path d="M20 3C11 3 4 6 4 10v20c0 4 7 7 16 7s16-3 16-7V10c0-4-7-7-16-7zm0 4c7 0 12 2 12 3s-5 3-12 3-12-2-12-3 5-3 12-3zm0 10c7 0 12-2 12-3v5c0 1-5 3-12 3s-12-2-12-3v-5c0 1 5 3 12 3zm0 10c7 0 12-2 12-3v5c0 1-5 3-12 3s-12-2-12-3v-5c0 1 5 3 12 3z"/>
    </svg>
</div>

                    <div class="storage">
                        <?= $plan['quota']; ?>
                    </div>

                    <p class="text-muted">More Storage</p>

                    <hr>

                    <div class="price">
                        ₹<?= $plan['monthly_price']; ?>
                        <small>/month</small>
                    </div>

                    <ul class="list-unstyled mt-4 feature-list">
    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
       Adds <?= $plan['quota']; ?> to account
    </li>

    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
        Works across all devices
    </li>

    <li>
        <span class="check-icon">
            <i class="bi bi-check"></i>
        </span>
        Secure cloud storage
    </li>
</ul>

                    <!-- <button class="btn btn-plan <?= $plan['popular'] ? 'btn-popular' : '' ?>">
                        Choose Plan
                    </button> -->
  <a href="checkout.php?plan_id=<?= $plan['id'] ?>"
   class="btn btn-plan <?= $plan['id']==4 ? 'btn-popular' : '' ?>">
    Choose Plan
</a>

                </div>
            </div>
        </div>

        <?php endforeach; ?>

    </div>

    <div class="feature-box mt-5">
    <div class="row">

        <div class="col-12 col-md-4 mb-3 mb-md-0">
            <div class="d-flex align-items-center">
                <div class="icon-box">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div class="ms-3">
                    <h6 class="mb-1 fw-bold">Secure & Private</h6>
                    <small class="text-muted">
                        Your data is encrypted and safe
                    </small>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 mb-3 mb-md-0">
            <div class="d-flex align-items-center">
                <div class="icon-box">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>

                <div class="ms-3">
                    <h6 class="mb-1 fw-bold">Instant Upgrade</h6>
                    <small class="text-muted">
                        Get more space in seconds
                    </small>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="d-flex align-items-center">
                <div class="icon-box">
                    <i class="bi bi-arrow-repeat"></i>
                </div>

                <div class="ms-3">
                    <h6 class="mb-1 fw-bold">Seamless Sync</h6>
                    <small class="text-muted">
                        Access your files from anywhere
                    </small>
                </div>
            </div>
        </div>

    </div>
    
</div>

</div>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</html>