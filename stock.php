<?php
require_once 'config.php';
requireLogin();
$pageTitle = 'สต็อกคงเหลือ';
include 'includes/header.php';

$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$statusFilter   = isset($_GET['status'])   ? $_GET['status']         : 'all';
$searchTerm     = isset($_GET['search'])   ? clean($_GET['search'])  : '';

$sql = "
    SELECT p.*, c.category_name, COALESCE(i.quantity,0) AS quantity
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE 1=1
";
if (!empty($searchTerm))    $sql .= " AND (p.product_name LIKE '%$searchTerm%' OR p.product_code LIKE '%$searchTerm%' OR p.brand LIKE '%$searchTerm%')";
if ($categoryFilter > 0)    $sql .= " AND p.category_id = $categoryFilter";
if ($statusFilter == 'low') $sql .= " AND i.quantity <= p.min_stock AND i.quantity > 0";
elseif ($statusFilter == 'out')    $sql .= " AND i.quantity = 0";
elseif ($statusFilter == 'normal') $sql .= " AND i.quantity > p.min_stock";
$sql .= " ORDER BY p.product_name";

$products   = $conn->query($sql);
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

$statsQuery = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN COALESCE(i.quantity,0) > p.min_stock THEN 1 ELSE 0 END) as normal,
        SUM(CASE WHEN COALESCE(i.quantity,0) <= p.min_stock AND COALESCE(i.quantity,0) > 0 THEN 1 ELSE 0 END) as low,
        SUM(CASE WHEN COALESCE(i.quantity,0) = 0 THEN 1 ELSE 0 END) as out_stock
    FROM products p LEFT JOIN inventory i ON p.product_id = i.product_id
");
$stats = $statsQuery ? $statsQuery->fetch_assoc() : ['total'=>0,'normal'=>0,'low'=>0,'out_stock'=>0];
?>

<style>
@media print {
    #sidebar, .no-print { display: none !important; }
    #content { padding: 0 !important; }
    .stock-card { box-shadow: none !important; border: 1px solid #ddd !important; }
}

/* ===== Page Header ===== */
.page-header-custom {
    background: #fff; border-radius: 16px; padding: 22px 26px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.page-header-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}

/* ===== Stat Cards ===== */
.stat-card {
    background: #fff; border-radius: 16px; padding: 20px 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer; position: relative; overflow: hidden;
    border: 2px solid transparent;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.11); }
.stat-card.active { border-color: currentColor; }
.stat-card .blob {
    position: absolute; right: -18px; top: -18px;
    width: 80px; height: 80px; border-radius: 50%; opacity: 0.1;
}
.stat-card .icon {
    width: 46px; height: 46px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.stat-num { font-size: 1.8rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: 12px; color: #94a3b8; font-weight: 600; margin-bottom: 6px; }

/* ===== Filter Card ===== */
.filter-card {
    background: #fff; border-radius: 16px; padding: 18px 22px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
}
.filter-card .form-control, .filter-card .form-select {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 9px 14px; font-size: 14px; transition: border-color 0.2s;
}
.filter-card .form-control:focus, .filter-card .form-select:focus {
    border-color: #4facfe; box-shadow: 0 0 0 4px rgba(79,172,254,0.1);
}
.form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }
.btn-search {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    border: none; color: #fff; border-radius: 10px;
    padding: 10px 22px; font-weight: 700; font-size: 14px; transition: all 0.2s;
}
.btn-search:hover { transform: translateY(-1px); color: #fff; box-shadow: 0 6px 16px rgba(79,172,254,0.4); }
.btn-reset {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 10px; padding: 9px 18px; font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.btn-reset:hover { border-color: #4facfe; color: #4facfe; }

/* ===== Table Card ===== */
.stock-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07); overflow: hidden;
}
.stock-card-header {
    padding: 16px 22px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
}
.stock-card-header .title { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 10px; }

/* ===== Table ===== */
.stock-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.stock-table thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.stock-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.stock-table tbody tr:hover { background: #f8fafc; }
.stock-table tbody td { padding: 12px 16px; vertical-align: middle; }
.stock-table tbody tr:last-child { border-bottom: none; }

/* ===== Stock Bar ===== */
.stock-bar-wrap { height: 5px; background: #e2e8f0; border-radius: 99px; margin-top: 6px; min-width: 80px; }
.stock-bar-fill { height: 100%; border-radius: 99px; transition: width 0.4s ease; }

/* ===== Badges ===== */
.product-code { background: #eef2ff; color: #667eea; border-radius: 7px; padding: 3px 9px; font-size: 12px; font-weight: 700; font-family: monospace; }
.cat-badge { background: #f0f4ff; color: #4338ca; border-radius: 8px; padding: 3px 9px; font-size: 12px; font-weight: 600; }
.status-ok    { background: #e8f5e9; color: #2e7d32; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.status-low   { background: #fff3e0; color: #f57c00; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.status-out   { background: #ffebee; color: #c62828; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.qty-ok  { font-weight: 800; color: #2e7d32; }
.qty-low { font-weight: 800; color: #f57c00; }
.qty-out { font-weight: 800; color: #c62828; }
.count-badge { background: #eef2ff; color: #667eea; border-radius: 8px; padding: 3px 10px; font-size: 12px; font-weight: 700; }

.btn-print {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 10px; padding: 7px 16px; font-size: 13px; font-weight: 600; transition: all 0.2s;
}
.btn-print:hover { border-color: #4facfe; color: #4facfe; }

/* ===== Empty State ===== */
.empty-state { text-align: center; padding: 48px; color: #94a3b8; }

/* ===== Animations ===== */
.fade-in { opacity: 0; animation: fadeUp 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.10s; }
.fade-in-3 { animation-delay: 0.15s; }
.fade-in-4 { animation-delay: 0.20s; }
.fade-in-5 { animation-delay: 0.25s; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
</style>

<!-- ===== Header ===== -->
<div class="page-header-custom fade-in fade-in-1 no-print">
    <div class="d-flex align-items-center gap-3">
        <div class="page-header-icon"><i class="fas fa-warehouse"></i></div>
        <div>
            <h4 class="mb-0 fw-bold">สต็อกคงเหลือ</h4>
            <p class="text-muted mb-0" style="font-size:13px;">ตรวจสอบสต็อกสินค้าคงเหลือในคลัง</p>
        </div>
    </div>
    <button onclick="window.print()" class="btn-print no-print">
        <i class="fas fa-print me-1"></i> พิมพ์
    </button>
</div>

<!-- ===== Stat Cards (คลิกกรองได้) ===== -->
<div class="row g-3 mb-4">
<?php
$statCards = [
    ['all',    'สินค้าทั้งหมด',   $stats['total'],     '#4facfe','#00f2fe','#e0f4ff','#0369a1', 'fa-boxes'],
    ['normal', 'สต็อกปกติ',       $stats['normal'],    '#43e97b','#38f9d7','#e8f5e9','#2e7d32', 'fa-check-circle'],
    ['low',    'ใกล้หมด',         $stats['low'],       '#f6d365','#fda085','#fff3e0','#f57c00', 'fa-exclamation-triangle'],
    ['out',    'หมดสต็อก',        $stats['out_stock'], '#f093fb','#f5576c','#ffebee','#c62828', 'fa-times-circle'],
];
foreach ($statCards as $i => [$sval, $slabel, $snum, $c1, $c2, $bg, $tc, $icon]):
?>
<div class="col-md-3 col-6 fade-in fade-in-<?= $i+1 ?>">
    <a href="?status=<?= $sval ?>&search=<?= urlencode($searchTerm) ?>&category=<?= $categoryFilter ?>" style="text-decoration:none;">
        <div class="stat-card <?= $statusFilter === $sval ? 'active' : '' ?>" style="color:<?= $tc ?>;">
            <div class="blob" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);"></div>
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label"><?= $slabel ?></div>
                    <div class="stat-num" style="color:<?= $tc ?>;"><?= number_format($snum) ?></div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">รายการ</div>
                </div>
                <div class="icon" style="background:<?= $bg ?>;color:<?= $tc ?>;">
                    <i class="fas <?= $icon ?>"></i>
                </div>
            </div>
        </div>
    </a>
</div>
<?php endforeach; ?>
</div>

<!-- ===== Filter ===== -->
<div class="filter-card fade-in fade-in-3 no-print">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div class="col-md-4">
            <label class="form-label"><i class="fas fa-search me-1 text-primary"></i>ค้นหาสินค้า</label>
            <input type="text" name="search" class="form-control"
                   placeholder="ชื่อสินค้า / รหัส / แบรนด์..."
                   value="<?= htmlspecialchars($searchTerm) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><i class="fas fa-tags me-1 text-primary"></i>หมวดหมู่</label>
            <select name="category" class="form-select">
                <option value="0">ทุกหมวดหมู่</option>
                <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= $categoryFilter == $cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn-search flex-grow-1"><i class="fas fa-search me-1"></i>ค้นหา</button>
            <a href="stock.php" class="btn-reset"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<!-- ===== Table ===== -->
<div class="stock-card fade-in fade-in-4">
    <div class="stock-card-header">
        <div class="title">
            <span style="width:5px;height:20px;border-radius:3px;background:linear-gradient(135deg,#4facfe,#00f2fe);display:inline-block;"></span>
            รายการสต็อกสินค้า
            <?php if ($products): ?>
                <span class="count-badge"><?= $products->num_rows ?> รายการ</span>
            <?php endif; ?>
            <?php if ($statusFilter !== 'all'): ?>
                <span style="font-size:12px;color:#94a3b8;">
                    — กรอง:
                    <?= ['normal'=>'สต็อกปกติ','low'=>'ใกล้หมด','out'=>'หมดสต็อก'][$statusFilter] ?? '' ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="stock-table" id="stockTable">
            <thead>
                <tr>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th>แบรนด์</th>
                    <th class="text-center">สต็อกคงเหลือ</th>
                    <th class="text-center">ขั้นต่ำ</th>
                    <th class="text-center">สถานะ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($products && $products->num_rows > 0): ?>
                <?php while ($row = $products->fetch_assoc()):
                    $qty    = (int)$row['quantity'];
                    $minQty = (int)$row['min_stock'];
                    $pct    = $minQty > 0 ? min(($qty / max($minQty, 1)) * 100, 100) : 100;

                    if ($qty == 0) {
                        $statusClass = 'status-out'; $statusText = 'หมดสต็อก';
                        $qtyClass = 'qty-out'; $barColor = '#ef4444'; $icon = 'fa-times-circle';
                    } elseif ($qty <= $minQty) {
                        $statusClass = 'status-low'; $statusText = 'ใกล้หมด';
                        $qtyClass = 'qty-low'; $barColor = '#f59e0b'; $icon = 'fa-exclamation-triangle';
                    } else {
                        $statusClass = 'status-ok'; $statusText = 'ปกติ';
                        $qtyClass = 'qty-ok'; $barColor = '#22c55e'; $icon = 'fa-check-circle';
                    }
                ?>
                <tr>
                    <td><span class="product-code"><?= htmlspecialchars($row['product_code']) ?></span></td>
                    <td>
                        <div style="font-weight:600;color:#1e293b;"><?= htmlspecialchars($row['product_name']) ?></div>
                    </td>
                    <td><span class="cat-badge"><?= htmlspecialchars($row['category_name'] ?? '-') ?></span></td>
                    <td style="font-size:13px;color:#64748b;"><?= htmlspecialchars($row['brand'] ?: '-') ?></td>
                    <td class="text-center">
                        <div class="<?= $qtyClass ?>" style="font-size:15px;">
                            <?= number_format($qty) ?> <small style="font-size:11px;font-weight:500;"><?= htmlspecialchars($row['unit']) ?></small>
                        </div>
                        <div class="stock-bar-wrap">
                            <div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                        </div>
                    </td>
                    <td class="text-center" style="color:#94a3b8;font-size:13px;">
                        <?= number_format($minQty) ?> <?= htmlspecialchars($row['unit']) ?>
                    </td>
                    <td class="text-center">
                        <span class="<?= $statusClass ?>">
                            <i class="fas <?= $icon ?> me-1"></i><?= $statusText ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-box-open fa-3x d-block mb-3"></i>
                            <?= $searchTerm ? 'ไม่พบสินค้าที่ค้นหา "'.htmlspecialchars($searchTerm).'"' : 'ไม่พบข้อมูลสินค้า' ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>