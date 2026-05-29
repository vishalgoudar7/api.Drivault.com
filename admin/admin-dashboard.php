<?php
session_start();

if (
    !isset($_SESSION['admin_role']) ||
    $_SESSION['admin_role'] !== 'admin'
) {
    http_response_code(403);
    exit('Access denied');
}

require __DIR__ . '/../config/db.php';

$adminName = (string) ($_SESSION['admin_name'] ?? 'Admin');
$adminCreateMessage = (string) ($_SESSION['admin_create_message'] ?? '');

if ($adminCreateMessage !== '') {
    unset($_SESSION['admin_create_message']);
}

$statement = $conn->prepare(
    "SELECT name, email, phone, inviter_email, invite_accepted
     FROM users
     WHERE role = 'user'
     ORDER BY id DESC"
);

$statement->execute();
$result = $statement->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);
$statement->close();

$referralRewardsStatement = $conn->prepare(
    "SELECT name, email, inviter_email, invite_accepted
     FROM users
     WHERE role = 'user'
     AND inviter_email IS NOT NULL
     AND inviter_email != ''
     AND invite_accepted = 'yes'
     ORDER BY id DESC"
);

$referralRewardsStatement->execute();
$referralRewardsResult = $referralRewardsStatement->get_result();
$referralRewards = $referralRewardsResult->fetch_all(MYSQLI_ASSOC);
$referralRewardsStatement->close();

$adminsStatement = $conn->prepare(
    "SELECT name, email, phone, is_active, is_verified
     FROM users
     WHERE role = 'admin'
     ORDER BY id DESC"
);

$adminsStatement->execute();
$adminsResult = $adminsStatement->get_result();
$admins = $adminsResult->fetch_all(MYSQLI_ASSOC);
$adminsStatement->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Dashboard</title>

<link
    rel="icon"
    type="image/x-icon"
    href="/php_invitation_system/assets/Photos/favicon.ico"
>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Inter',sans-serif;
}

body{

    background:
    linear-gradient(
        135deg,
        #f8fafc,
        #eefbf3,
        #ecfeff
    );

    min-height:100vh;

    padding:30px;
}

.dashboard-container{
    max-width:1250px;
    margin:auto;
}

.header{

    background:
    rgba(255,255,255,0.92);

    backdrop-filter:blur(12px);

    border:
    1px solid rgba(255,255,255,0.6);

    border-radius:28px;

    padding:35px;

    margin-bottom:28px;

    box-shadow:
    0 10px 40px rgba(15,23,42,0.08);

    position:relative;

    overflow:hidden;
}

.header::before{
    content:'';
    position:absolute;

    width:220px;
    height:220px;

    background:
    rgba(74,222,128,0.12);

    border-radius:50%;

    top:-80px;
    right:-80px;

    filter:blur(25px);
}

.header h2{

    color:#0f172a;

    font-size:34px;

    margin-bottom:10px;

    position:relative;

    z-index:1;
}

.brand{
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:20px;
    position:relative;
    z-index:1;
}

.brand-badge{
    width:56px;
    height:56px;
    border-radius:18px;
    background:#ecfdf5;
    border:1px solid #d1fae5;
    box-shadow:0 10px 24px rgba(74,222,128,0.18);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:9px;
}

.brand-badge img{
    width:100%;
    height:100%;
    object-fit:contain;
}

.brand span{
    font-size:28px;
    font-weight:700;
    color:#0f172a;
    letter-spacing:-0.4px;
}

.header p{

    color:#64748b;

    font-size:15px;

    position:relative;

    z-index:1;
}

.nav-links{

    margin-top:24px;

    position:relative;

    z-index:1;
}

.nav-links a,
.nav-links button{

    display:inline-block;

    margin-right:12px;

    padding:13px 22px;

    border-radius:16px;

    background:
    linear-gradient(
        135deg,
        #4ade80,
        #22c55e
    );

    color:white;

    text-decoration:none;

    font-weight:600;

    font-size:15px;

    border:none;

    cursor:pointer;

    transition:0.3s;

    box-shadow:
    0 10px 20px rgba(74,222,128,0.22);
}

.nav-links a:hover,
.nav-links button:hover{

    transform:translateY(-2px);

    box-shadow:
    0 14px 28px rgba(74,222,128,0.34);
}

.table-container{

    background:
    rgba(255,255,255,0.92);

    backdrop-filter:blur(12px);

    border:
    1px solid rgba(255,255,255,0.6);

    border-radius:28px;

    padding:30px;

    box-shadow:
    0 10px 40px rgba(15,23,42,0.08);

    overflow-x:auto;
}

.table-container h3{

    color:#0f172a;

    font-size:26px;

    margin-bottom:24px;
}

table{
    width:100%;
    border-collapse:collapse;
    overflow:hidden;
}

table thead{

    background:
    linear-gradient(
        135deg,
        #4ade80,
        #22c55e
    );

    color:white;
}

table th{

    padding:18px;

    text-align:left;

    font-size:14px;

    font-weight:600;
}

table td{

    padding:18px;

    border-bottom:
    1px solid #e2e8f0;

    color:#334155;

    font-size:14px;
}

table tbody tr{

    transition:0.3s;
}

table tbody tr:hover{

    background:
    rgba(74,222,128,0.06);
}

.status-yes{

    background:
    rgba(34,197,94,0.12);

    color:#16a34a;

    padding:8px 14px;

    border-radius:999px;

    font-size:13px;

    font-weight:600;

    display:inline-block;
}

.status-no{

    background:
    rgba(239,68,68,0.10);

    color:#dc2626;

    padding:8px 14px;

    border-radius:999px;

    font-size:13px;

    font-weight:600;

    display:inline-block;
}

.no-data{

    text-align:center;

    padding:30px;

    color:#64748b;
}

.is-hidden{
    display:none;
}

.message{
    background:#dcfce7;
    border:1px solid #86efac;
    color:#166534;
    padding:14px 18px;
    border-radius:16px;
    margin-bottom:20px;
    font-size:14px;
    font-weight:600;
}

@media(max-width:768px){

    body{
        padding:18px;
    }

    .header,
    .table-container{
        padding:22px;
    }

    .header h2{
        font-size:28px;
    }

    table th,
    table td{
        padding:14px;
    }
}

</style>

</head>

<body>

<div class="dashboard-container">

    <div class="header">

        <div class="brand">
            <div class="brand-badge">
                <img src="/php_invitation_system/assets/Photos/icon-192.png" alt="Drivault logo">
            </div>
            <span>Drivault</span>
        </div>

        <h2>Admin Dashboard</h2>

        <p>
            Welcome,
            <?php
            echo htmlspecialchars(
                $adminName,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </p>

        <div class="nav-links">

            <button id="toggle-admins-button" type="button" aria-expanded="false">
                Admins List
            </button>

            <a href="admin-create.php">
                Create Admin
            </a>

            <a href="admin_logout.php">
                Logout
            </a>

        </div>

    </div>

    <div class="table-container">

        <?php if ($adminCreateMessage !== '') { ?>
        <div class="message">
            <?php echo htmlspecialchars($adminCreateMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php } ?>

        <h3>Invited Users</h3>

        <table>

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone Number</th>
                    <th>Inviter Email</th>
                    <th>Invite Accepted</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($users === []) { ?>

                <tr>
                    <td colspan="5" class="no-data">
                        No invited users found.
                    </td>
                </tr>

            <?php } else { ?>

                <?php foreach ($users as $user) { ?>

                <tr>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($user['name'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($user['email'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($user['phone'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($user['inviter_email'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>

                        <?php
                        $status = strtolower(
                            (string) ($user['invite_accepted'] ?? 'no')
                        );
                        ?>

                        <span class="
                            <?php
                            echo $status === 'yes'
                                ? 'status-yes'
                                : 'status-no';
                            ?>
                        ">

                        <?php
                        echo htmlspecialchars(
                            ucfirst($status),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                        </span>

                    </td>

                </tr>

                <?php } ?>

            <?php } ?>

            </tbody>

        </table>

    </div>

    <div class="table-container" style="margin-top:30px;">

        <h3>Referral Rewards</h3>

        <table>

            <thead>

                <tr>
                    <th>Inviter Email</th>
                    <th>Invited User</th>
                    <th>Invited User Email</th>
                    <th>Referral Status</th>
                    <th>Reward</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($referralRewards === []) { ?>

                <tr>
                    <td colspan="5" class="no-data">
                        No successful referral rewards found.
                    </td>
                </tr>

            <?php } else { ?>

                <?php foreach ($referralRewards as $reward) { ?>

                <?php
                $referralStatus = 'yes';
                $rewardEarned = '100GB';
                ?>

                <tr>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($reward['inviter_email'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($reward['name'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($reward['email'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <span class="<?php echo $referralStatus === 'yes' ? 'status-yes' : 'status-no'; ?>">
                            <?php echo htmlspecialchars(ucfirst($referralStatus), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>

                    <td>
                        <span class="<?php echo $referralStatus === 'yes' ? 'status-yes' : 'status-no'; ?>">
                            <?php echo htmlspecialchars($rewardEarned, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>

                </tr>

                <?php } ?>

            <?php } ?>

            </tbody>

        </table>

    </div>

    <div id="admins-table-section" class="table-container is-hidden" style="margin-top:30px;">

        <h3>Admins List</h3>

        <table>

            <thead>

                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone Number</th>
                    <th>Active</th>
                    <th>Verified</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($admins === []) { ?>

                <tr>
                    <td colspan="5" class="no-data">
                        No admin accounts found.
                    </td>
                </tr>

            <?php } else { ?>

                <?php foreach ($admins as $admin) { ?>

                <tr>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($admin['name'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($admin['email'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <?php
                        echo htmlspecialchars(
                            (string) ($admin['phone'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </td>

                    <td>
                        <span class="<?php echo ((int) ($admin['is_active'] ?? 0) === 1) ? 'status-yes' : 'status-no'; ?>">
                            <?php echo ((int) ($admin['is_active'] ?? 0) === 1) ? 'Yes' : 'No'; ?>
                        </span>
                    </td>

                    <td>
                        <span class="<?php echo ((int) ($admin['is_verified'] ?? 0) === 1) ? 'status-yes' : 'status-no'; ?>">
                            <?php echo ((int) ($admin['is_verified'] ?? 0) === 1) ? 'Yes' : 'No'; ?>
                        </span>
                    </td>

                </tr>

                <?php } ?>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>

<script>
const toggleAdminsButton = document.getElementById('toggle-admins-button');
const adminsTableSection = document.getElementById('admins-table-section');

if (toggleAdminsButton && adminsTableSection) {
    toggleAdminsButton.addEventListener('click', () => {
        const isHidden = adminsTableSection.classList.toggle('is-hidden');
        toggleAdminsButton.setAttribute('aria-expanded', isHidden ? 'false' : 'true');

        if (!isHidden) {
            adminsTableSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}
</script>

</body>
</html>
