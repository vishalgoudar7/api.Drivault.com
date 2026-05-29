<?php
declare(strict_types=1);

session_start();

unset($_SESSION['admin_user_id'], $_SESSION['admin_name'], $_SESSION['admin_email'], $_SESSION['admin_role']);

header('Location: admin-login.php');
exit;
