<?php


require_once 'config.php';
$pageTitle = 'สต็อกคงเหลือ';
include 'includes/header.php';

// ตัวกรอง
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? clean($_GET['search']) : '';

// สร้าง SQL Query
$sql = "SELECT p.*, c.category_name, i.quantity, i.location
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN inventory i ON p.product_id = i.product_id
        WHERE 1=1";

// ค้นหา
if (!empty($searchTerm)) {
    $sql .= " AND (p.product_name LIKE '%$searchTerm%' 
              OR p.product_code LIKE '%$searchTerm%' 
              OR p.brand LIKE '%$searchTerm%')";
}

// กรองตามหมวดหมู่
if ($categoryFilter > 0) {
    $sql .= " AND p.category_id = $categoryFilter";
}

// กรองตามสถานะ
if ($statusFilter == 'low') {
    $sql .= " AND i.quantity <= p.min_stock AND i.quantity > 0";
} elseif ($statusFilter == 'out') {
    $sql .= " AND i.quantity = 0";
} elseif ($statusFilter == 'normal') {
    $sql .= " AND i.quantity > p.min_stock";
}

$sql .= " ORDER BY p.product_name";
$products = $conn->query($sql);

// ดึงหมวดหมู่สำหรับ Filter
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// สถิติสรุป
$statsQuery = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN COALESCE(i.quantity, 0) > p.min_stock THEN 1 ELSE 0 END) as normal,
        SUM(CASE WHEN COALESCE(i.quantity, 0) <= p.min_stock AND COALESCE(i.quantity, 0) > 0 THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN COALESCE(i.quantity, 0) = 0 THEN 1 ELSE 0 END) as out_stock
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
");

// ตรวจสอบว่า query สำเร็จหรือไม่
if (!$statsQuery) {
    die("Error in stats query: " . $conn->error);
}

$stats = $statsQuery->fetch_assoc();

// ถ้าไม่มีข้อมูล ให้ใช้ค่าเริ่มต้น
if (!$stats) {
    $stats = [
        'total' => 0,
        'normal' => 0,
        'low' => 0,
        'out_stock' => 0
    ];
}
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-warehouse text-primary me-2"></i>
        สต็อกคงเหลือ
    </h3>
    <p class="text-muted mb-0">ตรวจสอบสต็อกสินค้าคงเหลือในคลัง</p>
</div>

<!-- สถิติสรุป -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สินค้าทั้งหมด</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($stats['total']) ?></h3>
                </div>
                <div class="icon bg-primary-light">
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สต็อกปกติ</h6>
                    <h3 class="fw-bold mb-0 text-success"><?= number_format($stats['normal']) ?></h3>
                </div>
                <div class="icon bg-success-light">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">สต็อกใกล้หมด</h6>
                    <h3 class="fw-bold mb-0 text-warning"><?= number_format($stats['low']) ?></h3>
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
                    <h3 class="fw-bold mb-0 text-danger"><?= number_format($stats['out_stock']) ?></h3>
                </div>
                <div class="icon bg-danger-light">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ค้นหาและกรอง -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="ค้นหาสินค้า..." value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="0">ทุกหมวดหมู่</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['category_id'] ?>" <?= $categoryFilter == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= $cat['category_name'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="all" <?= $statusFilter == 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
                    <option value="normal" <?= $statusFilter == 'normal' ? 'selected' : '' ?>>สต็อกปกติ</option>
                    <option value="low" <?= $statusFilter == 'low' ? 'selected' : '' ?>>สต็อกใกล้หมด</option>
                    <option value="out" <?= $statusFilter == 'out' ? 'selected' : '' ?>>สินค้าหมด</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>ค้นหา
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ตารางสต็อก -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">รายการสินค้าคงเหลือ</h5>
        <button class="btn btn-success btn-sm" onclick="exportToExcel()">
            <i class="fas fa-file-excel me-2"></i>Export Excel
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="stockTable">
                <thead>
                    <tr>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>หมวดหมู่</th>
                        <th>แบรนด์</th>
                        <th class="text-center">สต็อกคงเหลือ</th>
                        <th class="text-center">สต็อกขั้นต่ำ</th>
                        <th>ตำแหน่ง</th>
                        <th class="text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($row = $products->fetch_assoc()): 
                            $quantity = $row['quantity'] ?? 0;
                            $status = 'normal';
                            $statusText = 'ปกติ';
                            $statusBadge = 'bg-success';
                            
                            if ($quantity == 0) {
                                $status = 'out';
                                $statusText = 'หมดสต็อก';
                                $statusBadge = 'bg-danger';
                            } elseif ($quantity <= $row['min_stock']) {
                                $status = 'low';
                                $statusText = 'ใกล้หมด';
                                $statusBadge = 'bg-warning';
                            }
                        ?>
                            <tr class="status-<?= $status ?>">
                                <td><strong><?= $row['product_code'] ?></strong></td>
                                <td><?= $row['product_name'] ?></td>
                                <td><span class="badge bg-info"><?= $row['category_name'] ?></span></td>
                                <td><?= $row['brand'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $statusBadge ?>">
                                        <?= number_format($quantity) ?> <?= $row['unit'] ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= number_format($row['min_stock']) ?> <?= $row['unit'] ?></td>
                                <td><?= $row['location'] ?? '-' ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $statusBadge ?>"><?= $statusText ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                ไม่พบข้อมูลสินค้า
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    // ฟังก์ชันง่ายๆ ส่งออก HTML Table เป็น Excel
    let table = document.getElementById('stockTable');
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    downloadLink.href = url;
    downloadLink.download = 'stock_report_' + new Date().toISOString().slice(0,10) + '.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<style>
.status-out {
    background-color: #ffebee;
}
.status-low {
    background-color: #fff3e0;
}
</style>

<?php include 'includes/footer.php'; ?>