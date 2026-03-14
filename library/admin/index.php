<?php
session_start();

if (!empty($_SESSION['alogin'])) {
header('location:dashboard.php');
exit;
}

header('location:../adminlogin.php');
exit;
