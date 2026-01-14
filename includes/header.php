<?php

// ตรวจสอบว่า Login หรือยัง
requireLogin();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle : 'ระบบคลังสินค้า' ?> - เขมจิรา บิวตี้ช็อป</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
        }
        .wrapper {
            display: flex;
            width: 100%;
            align-items: stretch;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        .components {
            flex: 1;
            overflow-y: auto;
        }
        .user-info {
            padding: 15px;
            background: rgba(0,0,0,0.2);
            margin-top: auto;
        }
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
        }
        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.1);
        }
        .sidebar-header h3 {
            margin: 0;
            font-weight: 700;
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 5px 10px;
            transition: all 0.3s;
        }
        .nav-link:hover,
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .nav-link i {
            width: 25px;
        }
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            padding: 20px;
            border-radius: 12px;
            background: white;
        }
        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .bg-primary-light { background: #e3f2fd; color: #1976d2; }
        .bg-success-light { background: #e8f5e9; color: #388e3c; }
        .bg-warning-light { background: #fff3e0; color: #f57c00; }
        .bg-danger-light { background: #ffebee; color: #d32f2f; }
        .bg-info-light { background: #e1f5fe; color: #0277bd; }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 10px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div id="content">
            <?php showAlert(); ?>