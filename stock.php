<?php

require_once 'config.php';
$pageTitle = 'สต็อกคงเหลือ';
include 'includes/header.php';

// ตัวกรอง
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchTerm = isset($_GET['search']) ? clean($_GET['search']) : '';

// สร้าง SQL Query
$sql = "SELECT 
            p.*, 
            c.category_name, 
            COALESCE(i.quantity,0) AS quantity
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

$stats = $statsQuery ? $statsQuery->fetch_assoc() : [
    'total' => 0,
    'normal' => 0,
    'low' => 0,
    'out_stock' => 0
];
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
<?php
$cards = [
    ['สินค้าทั้งหมด',$stats['total'],'primary','fa-boxes'],
    ['สต็อกปกติ',$stats['normal'],'success','fa-check-circle'],
    ['สต็อกใกล้หมด',$stats['low'],'warning','fa-exclamation-triangle'],
    ['สินค้าหมด',$stats['out_stock'],'danger','fa-times-circle']
];
foreach ($cards as $c):
?>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2"><?= $c[0] ?></h6>
                    <h3 class="fw-bold mb-0 text-<?= $c[2] ?>"><?= number_format($c[1]) ?></h3>
                </div>
                <div class="icon bg-<?= $c[2] ?>-light">
                    <i class="fas <?= $c[3] ?>"></i>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ค้นหาและกรอง -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control"
                       placeholder="ค้นหาสินค้า..." value="<?= htmlspecialchars($searchTerm) ?>">
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
                    <option value="all">ทุกสถานะ</option>
                    <option value="normal">สต็อกปกติ</option>
                    <option value="low">สต็อกใกล้หมด</option>
                    <option value="out">สินค้าหมด</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>ค้นหา
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ตาราง -->
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover" id="stockTable">
            <thead>
                <tr>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th>แบรนด์</th>
                    <th class="text-center">สต็อกคงเหลือ</th>
                    <th class="text-center">สต็อกขั้นต่ำ</th>
                    <th class="text-center">สถานะ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($products && $products->num_rows): ?>
                <?php while ($row = $products->fetch_assoc()):
                    $qty = $row['quantity'];
                    $badge = 'bg-success';
                    $text = 'ปกติ';
                    if ($qty == 0) { $badge='bg-danger'; $text='หมดสต็อก'; }
                    elseif ($qty <= $row['min_stock']) { $badge='bg-warning'; $text='ใกล้หมด'; }
                ?>
                <tr>
                    <td><strong><?= $row['product_code'] ?></strong></td>
                    <td><?= $row['product_name'] ?></td>
                    <td><span class="badge bg-info"><?= $row['category_name'] ?></span></td>
                    <td><?= $row['brand'] ?></td>
                    <td class="text-center">
                        <span class="badge <?= $badge ?>">
                            <?= number_format($qty) ?> <?= $row['unit'] ?>
                        </span>
                    </td>
                    <td class="text-center"><?= number_format($row['min_stock']) ?> <?= $row['unit'] ?></td>
                    <td class="text-center">
                        <span class="badge <?= $badge ?>"><?= $text ?></span>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">ไม่พบข้อมูลสินค้า</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
