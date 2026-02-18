<?php
// ===============================
// ป้องกัน Headers already sent
// ต้องเป็นบรรทัดแรกสุด ห้ามมีช่องว่างหรือ BOM ก่อนหน้า
ob_start();

// ===============================
// แสดง Error (ใช้เฉพาะตอนพัฒนา)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===============================
// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// ===============================
// Database Config
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'khemjira_warehouse');

// ===============================
// เชื่อมต่อฐานข้อมูล
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('การเชื่อมต่อล้มเหลว: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ===============================
// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================
// Helper Functions
function redirect($url) {
    header("Location: {$url}");
    exit;
}

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect('dashboard.php');
    }
}

// ===============================
// Alert Functions
function setAlert($type, $message) {
    $_SESSION['alert_type'] = $type;       // success | danger | warning | info
    $_SESSION['alert_message'] = $message;
}

function showAlert() {
    if (!empty($_SESSION['alert_message'])) {
        $type = $_SESSION['alert_type'] ?? 'info';
        $message = htmlspecialchars($_SESSION['alert_message'], ENT_QUOTES, 'UTF-8');

        echo "
        <div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
            {$message}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>
        ";

        unset($_SESSION['alert_type'], $_SESSION['alert_message']);
    }
}

function clean($data)
{
    global $conn;
    return htmlspecialchars(
        trim($conn->real_escape_string($data)),
        ENT_QUOTES,
        'UTF-8'
    );
}
