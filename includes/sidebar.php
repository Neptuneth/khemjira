<?php
// เมนูด้านข้าง
// บันทึกที่: C:/xampp/htdocs/warehouse/includes/sidebar.php

// ดึงชื่อไฟล์ปัจจุบัน เพื่อไฮไลท์เมนู
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-store me-2"></i>เขมจิรา</h3>
        <p class="mb-0">บิวตี้ช็อป</p>
    </div>

    <ul class="list-unstyled components">
        <li>
            <a href="dashboard.php" class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> หน้าหลัก
            </a>
        </li>
        
        <li style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <small class="text-white-50 px-3">จัดการสินค้า</small>
        </li>
        <li>
            <a href="products.php" class="nav-link <?= $currentPage == 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i> จัดการสินค้า
            </a>
        </li>
        <li>
            <a href="categories.php" class="nav-link <?= $currentPage == 'categories.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i> หมวดหมู่สินค้า
            </a>
        </li>
        
        <li style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <small class="text-white-50 px-3">คลังสินค้า</small>
        </li>
        <li>
            <a href="receive.php" class="nav-link <?= $currentPage == 'receive.php' ? 'active' : '' ?>">
                <i class="fas fa-truck-loading"></i> รับสินค้าเข้า
            </a>
        </li>
        <li>
            <a href="issue.php" class="nav-link <?= $currentPage == 'issue.php' ? 'active' : '' ?>">
                <i class="fas fa-dolly"></i> เบิกสินค้าออก
            </a>
        </li>
        <li>
            <a href="stock.php" class="nav-link <?= $currentPage == 'stock.php' ? 'active' : '' ?>">
                <i class="fas fa-warehouse"></i> สต็อกคงเหลือ
            </a>
        </li>
        <li>
            <a href="stock_adjustment.php" class="nav-link <?= $currentPage == 'stock_adjustment.php' ? 'active' : '' ?>">
                <i class="fas fa-edit"></i> ปรับแก้สต็อก
            </a>
        </li>
        
        <li style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <small class="text-white-50 px-3">รายงานและอื่นๆ</small>
        </li>
        <li>
            <a href="reports.php" class="nav-link <?= $currentPage == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i> รายงาน
            </a>
        </li>
        <li>
            <a href="suppliers.php" class="nav-link <?= $currentPage == 'suppliers.php' ? 'active' : '' ?>">
                <i class="fas fa-truck"></i> ซัพพลายเออร์
            </a>
        </li>
        
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <li style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.1);">
            <small class="text-white-50 px-3">ตั้งค่าระบบ</small>
        </li>
        <li>
            <a href="users.php" class="nav-link <?= $currentPage == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> จัดการผู้ใช้งาน
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="user-info">
        <div class="d-flex align-items-center mb-2">
            <div class="bg-white rounded-circle p-2 me-2">
                <i class="fas fa-user text-primary"></i>
            </div>
            <div class="flex-grow-1">
                <div class="text-truncate" style="max-width: 150px;">
                    <small class="d-block"><?= $_SESSION['full_name'] ?></small>
                </div>
                <small class="text-white-50"><?= $_SESSION['role'] == 'admin' ? 'Admin' : 'Staff' ?></small>
            </div>
        </div>
        <div class="d-grid gap-1">
            <a href="profile.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-user-circle me-1"></i> โปรไฟล์
            </a>
            <a href="logout.php" class="btn btn-sm btn-light">
                <i class="fas fa-sign-out-alt me-1"></i> ออกจากระบบ
            </a>
        </div>
    </div>
</nav>