<?php


require_once 'config.php';

// ลบข้อมูล Session ทั้งหมด
session_destroy();

// Redirect กลับไปหน้า Login
setAlert('success', 'ออกจากระบบเรียบร้อยแล้ว');
redirect('login.php');
?>