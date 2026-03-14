<?php
session_start(); 
unset($_SESSION['login']);
unset($_SESSION['stdid']);
session_destroy(); // destroy session
header("location:index.php"); 
exit;
?>
