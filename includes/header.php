<?php
// ===============================
// โหลด config ก่อนทุกอย่าง
require_once __DIR__ . '/../config.php';

// ===============================
// บังคับ Login
requireLogin();

// ===============================
// กัน session key หาย
$_SESSION['full_name'] = $_SESSION['full_name'] ?? 'ผู้ใช้งาน';
$_SESSION['role']      = $_SESSION['role'] ?? 'staff';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'ระบบคลังสินค้า') ?> - เขมจิรา บิวตี้ช็อป</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
        }

        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ================= Sidebar ================= */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 15px rgba(0,0,0,0.08);
        }

        .sidebar-header {
            padding: 22px;
            background: rgba(0,0,0,0.15);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 700;
        }

        .components {
            flex: 1;
            padding-top: 10px;
            overflow-y: auto;
        }

        .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 22px;
            margin: 6px 12px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.25s ease;
        }

        .nav-link i {
            width: 24px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.22);
            color: #fff;
            transform: translateX(4px);
        }

        .user-info {
            background: rgba(255,255,255,0.12);
            padding: 15px;
            border-radius: 12px;
            margin: 15px;
        }

        /* ================= Content ================= */
        #content {
            width: 100%;
            padding: 25px;
        }

        .page-header {
            background: #fff;
            padding: 22px;
            border-radius: 14px;
            margin-bottom: 22px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 22px;
        }

        /* ================= Stat Card ================= */
        .stat-card {
            padding: 22px;
            border-radius: 14px;
            background: #fff;
        }

        .stat-card .icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .menu-title {
    margin: 18px 16px 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(255,255,255,0.55);
        }


        .bg-primary-light { background: #e3f2fd; color: #1976d2; }
        .bg-success-light { background: #e8f5e9; color: #388e3c; }
        .bg-warning-light { background: #fff3e0; color: #f57c00; }
        .bg-danger-light  { background: #ffebee; color: #d32f2f; }
        .bg-info-light    { background: #e1f5fe; color: #0277bd; }
    </style>
</head>
<body>

<div class="wrapper">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div id="content">
        <?php showAlert(); ?>
