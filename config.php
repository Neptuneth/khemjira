<?php


// ตั้งค่าการแสดงข้อผิดพลาด (ใช้ตอนพัฒนา)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตั้งค่า Timezone เป็นเวลาประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
define('DB_HOST', 'localhost');           // ชื่อ Host (ปกติคือ localhost)
define('DB_USER', 'root');                // Username (XAMPP ใช้ root)
define('DB_PASS', '');                    // Password (XAMPP ปล่อยว่าง)
define('DB_NAME', 'khemjira_warehouse');  // ชื่อฐานข้อมูล

// สร้างการเชื่อมต่อ
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("การเชื่อมต่อล้มเหลว: " . $conn->connect_error);
}

// ตั้งค่า Character Set เป็น UTF-8 เพื่อรองรับภาษาไทย
$conn->set_charset("utf8mb4");

// เริ่ม Session (สำหรับเก็บข้อมูล Login)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ฟังก์ชันช่วยเหลือ
function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

// ฟังก์ชันป้องกัน SQL Injection
function clean($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// ฟังก์ชันแสดงข้อความแจ้งเตือน
function setAlert($type, $message) {
    $_SESSION['alert_type'] = $type;  // success, danger, warning, info
    $_SESSION['alert_message'] = $message;
}

function showAlert() {
    if (isset($_SESSION['alert_message'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
        unset($_SESSION['alert_message']);
        unset($_SESSION['alert_type']);
    }
}
?>