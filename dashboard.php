<?php


require_once 'config.php';
$pageTitle = 'Dashboard';
include 'includes/header.php';

// ดึงข้อมูลสถิติ
$stats = [
    'total_products' => 0,
    'total_stock' => 0,
    'low_stock' => 0,
    'out_stock' => 0
];

// นับจำนวนสินค้าทั้งหมด
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $result->fetch_assoc()['total'];

// นับสต็อกรวม
$result = $conn->query("SELECT SUM(quantity) as total FROM inventory");
$row = $result->fetch_assoc();
$stats['total_stock'] = $row['total'] ?? 0;

// สินค้าใกล้หมด (quantity <= min_stock)
$result = $conn->query("
    SELECT COUNT(*) as total 
    FROM inventory i 
    JOIN products p ON i.product_id = p.product_id 
    WHERE i.quantity <= p.min_stock AND i.quantity > 0
");
$stats['low_stock'] = $result->fetch_assoc()['total'];

// สินค้าหมด
$result = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE quantity = 0");
$stats['out_stock'] = $result->fetch_assoc()['total'];

// ดึงรายการสินค้าที่ต้องเร่งสั่ง
$lowStockProducts = $conn->query("
    SELECT p.product_code, p.product_name, i.quantity, p.min_stock, p.unit
    FROM products p
    JOIN inventory i ON p.product_id = i.product_id
    WHERE i.quantity <= p.min_stock
    ORDER BY i.quantity ASC
    LIMIT 10
");
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-home text-primary me-2"></i>
        Dashboard
    </h3>
    <p class="text-muted mb-0">ภาพรวมระบบคลังสินค้า</p>
</div>

<!-- สถิติ -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สินค้าทั้งหมด</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($stats['total_products']) ?></h3>
                    <small class="text-muted">รายการ</small>
                </div>
                <div class="icon bg-primary-light">
                    <i class="fas fa-box"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สต็อกรวม</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($stats['total_stock']) ?></h3>
                    <small class="text-muted">ชิ้น</small>
                </div>
                <div class="icon bg-success-light">
                    <i class="fas fa-cubes"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สินค้าใกล้หมด</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($stats['low_stock']) ?></h3>
                    <small class="text-muted">รายการ</small>
                </div>
                <div class="icon bg-warning-light">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สินค้าหมด</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($stats['out_stock']) ?></h3>
                    <small class="text-muted">รายการ</small>
                </div>
                <div class="icon bg-danger-light">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ตารางสินค้าที่ต้องแจ้งจัดซื้อ -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
            สินค้าที่ต้องแจ้งจัดซื้อเพิ่ม
        </h5>
    </div>
    <div class="card-body">
        <?php if ($lowStockProducts->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>รหัสสินค้า</th>
                            <th>ชื่อสินค้า</th>
                            <th class="text-center">สต็อกคงเหลือ</th>
                            <th class="text-center">สต็อกขั้นต่ำ</th>
                            <th class="text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $lowStockProducts->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['product_code'] ?></td>
                                <td><?= $row['product_name'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $row['quantity'] == 0 ? 'bg-danger' : 'bg-warning' ?>">
                                        <?= number_format($row['quantity']) ?> <?= $row['unit'] ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= number_format($row['min_stock']) ?> <?= $row['unit'] ?></td>
                                <td class="text-center">
                                    <?php if ($row['quantity'] == 0): ?>
                                        <span class="badge bg-danger">หมดสต็อก</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">ใกล้หมด</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-0">
                <i class="fas fa-check-circle me-2"></i>
                สินค้าทุกรายการมีสต็อกเพียงพอ
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>