<?php
$role      = $_SESSION['role'] ?? null;
$fullName = $_SESSION['full_name'] ?? 'ไม่ทราบชื่อ';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav id="sidebar">

    <!-- ================= Header ================= -->
    <div class="sidebar-header text-center">
        <h4 class="fw-bold mb-1">
            <i class="fas fa-store me-2"></i>เขมจิรา
        </h4>
        <small class="text-white-50">บิวตี้ช็อป</small>
    </div>

    <!-- ================= Menu ================= -->
    <ul class="list-unstyled components px-2">

        <li>
            <a href="dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> หน้าหลัก
            </a>
        </li>

        <li class="menu-title">จัดการสินค้า</li>

        <li>
            <a href="products.php" class="nav-link <?= $currentPage === 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i> จัดการสินค้า
            </a>
        </li>
        <li>
            <a href="categories.php" class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i> หมวดหมู่สินค้า
            </a>
        </li>

        <li class="menu-title">คลังสินค้า</li>

        <li>
            <a href="receive.php" class="nav-link <?= $currentPage === 'receive.php' ? 'active' : '' ?>">
                <i class="fas fa-truck-loading"></i> รับสินค้าเข้า
            </a>
        </li>
        <li>
            <a href="issue.php" class="nav-link <?= $currentPage === 'issue.php' ? 'active' : '' ?>">
                <i class="fas fa-dolly"></i> เบิกสินค้าออก
            </a>
        </li>
        <li>
            <a href="stock.php" class="nav-link <?= $currentPage === 'stock.php' ? 'active' : '' ?>">
                <i class="fas fa-warehouse"></i> สต็อกคงเหลือ
            </a>
        </li>
        <li>
            <a href="stock_adjustment.php" class="nav-link <?= $currentPage === 'stock_adjustment.php' ? 'active' : '' ?>">
                <i class="fas fa-edit"></i> ปรับแก้สต็อก
            </a>
        </li>

        <li class="menu-title">รายงานและอื่นๆ</li>

        <li>
            <a href="reports.php" class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i> รายงาน
            </a>
        </li>
        <li>
            <a href="suppliers.php" class="nav-link <?= $currentPage === 'suppliers.php' ? 'active' : '' ?>">
                <i class="fas fa-truck"></i> ซัพพลายเออร์
            </a>
        </li>

        <?php if ($role === 'admin'): ?>
            <li class="menu-title">ตั้งค่าระบบ</li>
            <li>
                <a href="users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> จัดการผู้ใช้งาน
                </a>
            </li>
        <?php endif; ?>

    </ul>

    <!-- ================= User ================= -->
    <div class="user-info">
        <div class="d-flex align-items-center mb-2">
            <div class="bg-white rounded-circle p-2 me-2">
                <i class="fas fa-user text-primary"></i>
            </div>
            <div>
                <small class="d-block fw-semibold text-truncate" style="max-width:150px;">
                    <?= htmlspecialchars($fullName) ?>
                </small>
                <small class="text-white-50">
                    <?= $role === 'admin' ? 'Admin' : 'Staff' ?>
                </small>
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
