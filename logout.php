<?php
require_once 'auth.php';

// خروج کاربر
logout();

// هدایت به صفحه ورود
header('Location: login.php');
exit();
?>