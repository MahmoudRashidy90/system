<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: /system/dashboard.php");
    exit;
} else {
    header("Location: /system/login.php");
    exit;
}
?>
