<?php


require_once 'config.php';

// ถ้า Login แล้ว ไปหน้า Dashboard
// ถ้ายัง ไปหน้า Login
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
?>