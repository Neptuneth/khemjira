<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'Dashboard';
include 'includes/header.php';

// ===============================
// เตรียมค่าเริ่มต้น
$stats = [
    'total_products' => 0,
    'total_stock'    => 0,
    'low_stock'      => 0,
    'out_stock'      => 0
];

// ===============================
// นับจำนวนสินค้าทั้งหมด
$result = $conn->query("SELECT COUNT(*) AS total FROM products");
if ($result) {
    $stats['total_products'] = (int)$result->fetch_assoc()['total'];
}

// ===============================
// นับสต็อกรวม
$result = $conn->query("SELECT SUM(quantity) AS total FROM inventory");
if ($result) {
    $stats['total_stock'] = (int)($result->fetch_assoc()['total'] ?? 0);
}

// ===============================
// สินค้าใกล้หมด
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM inventory i
    JOIN products p ON i.product_id = p.product_id
    WHERE i.quantity <= p.min_stock AND i.quantity > 0
");
if ($result) {
    $stats['low_stock'] = (int)$result->fetch_assoc()['total'];
}

// ===============================
// สินค้าหมด
$result = $conn->query("SELECT COUNT(*) AS total FROM inventory WHERE quantity = 0");
if ($result) {
    $stats['out_stock'] = (int)$result->fetch_assoc()['total'];
}

// ===============================
// สินค้าที่ต้องจัดซื้อ
$lowStockProducts = $conn->query("
    SELECT 
        p.product_code,
        p.product_name,
        i.quantity,
        p.min_stock,
        p.unit
    FROM products p
    JOIN inventory i ON p.product_id = i.product_id
    WHERE i.quantity <= p.min_stock
    ORDER BY i.quantity ASC
    LIMIT 10
");

// ===============================
// สินค้ายอดนิยมในการเบิก (Top 5)
$popularProducts = $conn->query("
    SELECT 
        p.product_name,
        SUM(gii.quantity) AS total_issue
    FROM goods_issue_items gii
    JOIN products p ON gii.product_id = p.product_id
    GROUP BY gii.product_id
    ORDER BY total_issue DESC
    LIMIT 5
");

$chartLabels = [];
$chartData   = [];

if ($popularProducts && $popularProducts->num_rows > 0) {
    while ($row = $popularProducts->fetch_assoc()) {
        $chartLabels[] = $row['product_name'];
        $chartData[]   = (int)$row['total_issue'];
    }
}
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-home text-primary me-2"></i> Dashboard
    </h3>
    <p class="text-muted mb-0">ภาพรวมระบบคลังสินค้า</p>
</div>

<!-- สถิติ -->
<div class="row">
<?php
$cards = [
    ['สินค้าทั้งหมด', $stats['total_products'], 'รายการ', 'fa-box', 'primary'],
    ['สต็อกรวม', $stats['total_stock'], 'ชิ้น', 'fa-cubes', 'success'],
    ['สินค้าใกล้หมด', $stats['low_stock'], 'รายการ', 'fa-exclamation-triangle', 'warning'],
    ['สินค้าหมด', $stats['out_stock'], 'รายการ', 'fa-times-circle', 'danger'],
];
foreach ($cards as $c):
?>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2"><?= $c[0] ?></h6>
                    <h3 class="fw-bold mb-0"><?= number_format($c[1]) ?></h3>
                    <small class="text-muted"><?= $c[2] ?></small>
                </div>
                <div class="icon bg-<?= $c[4] ?>-light">
                    <i class="fas <?= $c[3] ?>"></i>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ตารางแจ้งจัดซื้อ -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
            สินค้าที่ต้องแจ้งจัดซื้อเพิ่ม
        </h5>
    </div>
    <div class="card-body">
        <?php if ($lowStockProducts && $lowStockProducts->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>รหัสสินค้า</th>
                            <th>ชื่อสินค้า</th>
                            <th class="text-center">คงเหลือ</th>
                            <th class="text-center">ขั้นต่ำ</th>
                            <th class="text-center">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $lowStockProducts->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['product_code']) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $row['quantity'] == 0 ? 'bg-danger' : 'bg-warning' ?>">
                                        <?= number_format($row['quantity']) ?> <?= $row['unit'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?= number_format($row['min_stock']) ?> <?= $row['unit'] ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $row['quantity'] == 0 ? 'bg-danger' : 'bg-warning' ?>">
                                        <?= $row['quantity'] == 0 ? 'หมดสต็อก' : 'ใกล้หมด' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-success mb-0">
                สินค้าทุกรายการมีสต็อกเพียงพอ
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- กราฟสินค้ายอดนิยม -->
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar text-primary me-2"></i>
            สินค้ายอดนิยมในการเบิกออก
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($chartLabels)): ?>
            <canvas id="popularChart"></canvas>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                ยังไม่มีข้อมูลการเบิกสินค้า
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if (!empty($chartLabels)): ?>
new Chart(document.getElementById('popularChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'จำนวนที่เบิก',
            data: <?= json_encode($chartData) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
