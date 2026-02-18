<?php
require_once 'config.php';
requireAdmin();

$pageTitle = 'จัดการสินค้า';
include 'includes/header.php';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE product_id = $id");
    setAlert('success', 'ลบสินค้าเรียบร้อยแล้ว');
    redirect('products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_code = clean($_POST['product_code']);
    $product_name = clean($_POST['product_name']);
    $category_id  = (int)$_POST['category_id'];
    $brand        = clean($_POST['brand']);
    $unit         = clean($_POST['unit']);
    $min_stock    = (int)$_POST['min_stock'];
    $description  = clean($_POST['description']);

    if (!empty($_POST['product_id'])) {
        $id = (int)$_POST['product_id'];
        $conn->query("UPDATE products SET product_code='$product_code', product_name='$product_name', category_id=$category_id, brand='$brand', unit='$unit', min_stock=$min_stock, description='$description' WHERE product_id=$id");
        setAlert('success', 'แก้ไขสินค้าเรียบร้อยแล้ว');
    } else {
        $conn->query("INSERT INTO products (product_code, product_name, category_id, brand, unit, min_stock, description) VALUES ('$product_code','$product_name',$category_id,'$brand','$unit',$min_stock,'$description')");
        setAlert('success', 'เพิ่มสินค้าเรียบร้อยแล้ว');
    }
    redirect('products.php');
}

$editProduct = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM products WHERE product_id=$id");
    if ($res && $res->num_rows) $editProduct = $res->fetch_assoc();
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

$products = $conn->query("
    SELECT p.*, c.category_name, COALESCE(i.quantity,0) AS quantity
    FROM products p
    LEFT JOIN categories c ON p.category_id=c.category_id
    LEFT JOIN inventory i ON p.product_id=i.product_id
    WHERE p.product_name LIKE '%$search%' OR p.product_code LIKE '%$search%' OR p.brand LIKE '%$search%'
    ORDER BY p.created_at DESC
");

$totalProducts = $products ? $products->num_rows : 0;
?>

<style>
@media print {
    #sidebar, .no-print { display: none !important; }
    #content { padding: 0 !important; }
}

/* ===== Page Header ===== */
.page-header-custom {
    background: #fff; border-radius: 16px; padding: 22px 26px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.page-header-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}
.btn-add {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 12px;
    padding: 10px 22px; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    transition: all 0.2s; cursor: pointer;
}
.btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.45); color: #fff; }

/* ===== Search Card ===== */
.search-card {
    background: #fff; border-radius: 16px; padding: 18px 22px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
}
.search-card .form-control {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 10px 16px; font-size: 14px; transition: border-color 0.2s;
}
.search-card .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,0.1); }
.btn-search {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 10px;
    padding: 10px 22px; font-weight: 700; font-size: 14px; transition: all 0.2s;
}
.btn-search:hover { transform: translateY(-1px); color: #fff; }
.btn-clear {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 10px; padding: 9px 18px; font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.btn-clear:hover { border-color: #667eea; color: #667eea; }

/* ===== Table Card ===== */
.table-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    overflow: hidden; margin-bottom: 22px;
}
.table-card-header {
    padding: 16px 22px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
}
.table-card-header .title { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 10px; }
.count-badge {
    background: #eef2ff; color: #667eea;
    border-radius: 8px; padding: 3px 10px; font-size: 12px; font-weight: 700;
}

/* ===== Table ===== */
.prod-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.prod-table thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.prod-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.prod-table tbody tr:hover { background: #f8fafc; }
.prod-table tbody td { padding: 12px 16px; color: #334155; vertical-align: middle; }
.prod-table tbody tr:last-child { border-bottom: none; }

/* ===== Product Code ===== */
.product-code {
    background: #eef2ff; color: #667eea;
    border-radius: 7px; padding: 3px 10px;
    font-size: 12px; font-weight: 700; font-family: monospace;
}
.product-name { font-weight: 600; color: #1e293b; }
.product-brand { font-size: 12px; color: #94a3b8; margin-top: 2px; }

/* ===== Category Badge ===== */
.cat-badge {
    background: #f0f4ff; color: #4338ca;
    border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 600;
}

/* ===== Stock Badge ===== */
.stock-ok      { background: #e8f5e9; color: #2e7d32; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.stock-low     { background: #fff3e0; color: #f57c00; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.stock-empty   { background: #ffebee; color: #c62828; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }

/* ===== Action Buttons ===== */
.btn-edit {
    width: 34px; height: 34px; border-radius: 9px;
    background: #fff8e1; color: #f59e0b; border: none;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; transition: all 0.2s; cursor: pointer;
}
.btn-edit:hover { background: #f59e0b; color: #fff; transform: scale(1.1); }
.btn-del {
    width: 34px; height: 34px; border-radius: 9px;
    background: #ffebee; color: #ef4444; border: none;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; transition: all 0.2s; cursor: pointer;
}
.btn-del:hover { background: #ef4444; color: #fff; transform: scale(1.1); }

/* ===== Empty State ===== */
.empty-state { text-align: center; padding: 48px; color: #94a3b8; }

/* ===== Modal ===== */
.modal-content { border: none; border-radius: 18px; overflow: hidden; }
.modal-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff; padding: 20px 24px; border: none;
}
.modal-header .btn-close { filter: invert(1) brightness(2); }
.modal-title { font-weight: 800; font-size: 17px; }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; }
.modal .form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }
.modal .form-control, .modal .form-select {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 10px 14px; font-size: 14px; transition: border-color 0.2s;
}
.modal .form-control:focus, .modal .form-select:focus {
    border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
}
.btn-save {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 10px;
    padding: 10px 28px; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 12px rgba(102,126,234,0.35); transition: all 0.2s;
}
.btn-save:hover { transform: translateY(-1px); color: #fff; }
.btn-cancel {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 10px; padding: 9px 20px; font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.btn-cancel:hover { border-color: #94a3b8; }

/* ===== Animations ===== */
.fade-in { opacity: 0; animation: fadeUp 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.10s; }
.fade-in-3 { animation-delay: 0.15s; }
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.form-label { font-weight: 600; font-size: 13px; }
</style>

<!-- ===== Page Header ===== -->
<div class="page-header-custom fade-in fade-in-1 no-print">
    <div class="d-flex align-items-center gap-3">
        <div class="page-header-icon"><i class="fas fa-box"></i></div>
        <div>
            <h4 class="mb-0 fw-bold">จัดการสินค้า</h4>
            <p class="text-muted mb-0" style="font-size:13px;">เพิ่ม / แก้ไข / ลบข้อมูลสินค้าในระบบ</p>
        </div>
    </div>
    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="fas fa-plus me-2"></i>เพิ่มสินค้า
    </button>
</div>

<!-- ===== Search ===== -->
<div class="search-card fade-in fade-in-2 no-print">
    <form class="row g-3 align-items-end" method="GET">
        <div class="col-md-9">
            <label class="form-label"><i class="fas fa-search me-1 text-primary"></i>ค้นหาสินค้า</label>
            <input type="text" name="search" class="form-control"
                   placeholder="ค้นหาด้วย ชื่อสินค้า / รหัส / แบรนด์..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn-search flex-grow-1"><i class="fas fa-search me-1"></i>ค้นหา</button>
            <?php if ($search): ?>
                <a href="products.php" class="btn-clear"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ===== Table ===== -->
<div class="table-card fade-in fade-in-3">
    <div class="table-card-header">
        <div class="title">
            <span style="width:5px;height:20px;border-radius:3px;background:linear-gradient(135deg,#667eea,#764ba2);display:inline-block;"></span>
            รายการสินค้าทั้งหมด
            <span class="count-badge"><?= $totalProducts ?> รายการ<?= $search ? ' (ผลค้นหา)' : '' ?></span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="prod-table">
            <thead>
                <tr>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th class="text-center">สต็อกคงเหลือ</th>
                    <th class="text-center">สต็อกขั้นต่ำ</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($products && $products->num_rows): ?>
                <?php while ($p = $products->fetch_assoc()):
                    if ($p['quantity'] == 0) $stockClass = 'stock-empty';
                    elseif ($p['quantity'] <= $p['min_stock']) $stockClass = 'stock-low';
                    else $stockClass = 'stock-ok';
                ?>
                <tr>
                    <td><span class="product-code"><?= htmlspecialchars($p['product_code']) ?></span></td>
                    <td>
                        <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                        <?php if ($p['brand']): ?>
                            <div class="product-brand"><i class="fas fa-tag me-1"></i><?= htmlspecialchars($p['brand']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="cat-badge"><?= htmlspecialchars($p['category_name'] ?? '-') ?></span></td>
                    <td class="text-center">
                        <span class="<?= $stockClass ?>">
                            <?php if ($p['quantity'] == 0): ?>
                                <i class="fas fa-times-circle me-1"></i>
                            <?php elseif ($p['quantity'] <= $p['min_stock']): ?>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                            <?php else: ?>
                                <i class="fas fa-check-circle me-1"></i>
                            <?php endif; ?>
                            <?= number_format($p['quantity']) ?> <?= htmlspecialchars($p['unit']) ?>
                        </span>
                    </td>
                    <td class="text-center" style="color:#94a3b8;font-size:13px;">
                        <?= number_format($p['min_stock']) ?> <?= htmlspecialchars($p['unit']) ?>
                    </td>
                    <td class="text-center">
                        <a href="?edit=<?= $p['product_id'] ?>" class="btn-edit"
                           data-bs-toggle="modal" data-bs-target="#productModal" title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete=<?= $p['product_id'] ?>" class="btn-del"
                           onclick="return confirm('ยืนยันการลบสินค้า: <?= htmlspecialchars($p['product_name']) ?>?')" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-box-open fa-3x d-block mb-3"></i>
                            <?= $search ? 'ไม่พบสินค้าที่ค้นหา "' . htmlspecialchars($search) . '"' : 'ยังไม่มีสินค้าในระบบ' ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== Modal ===== -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas <?= $editProduct ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $editProduct ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?= $editProduct['product_id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-barcode me-1 text-primary"></i>รหัสสินค้า</label>
                        <input name="product_code" class="form-control" required placeholder="เช่น PRD-001"
                               value="<?= htmlspecialchars($editProduct['product_code'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-box me-1 text-primary"></i>ชื่อสินค้า</label>
                        <input name="product_name" class="form-control" required placeholder="ชื่อสินค้า"
                               value="<?= htmlspecialchars($editProduct['product_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-tags me-1 text-primary"></i>หมวดหมู่</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">— เลือกหมวดหมู่ —</option>
                            <?php $categories->data_seek(0); while ($c = $categories->fetch_assoc()): ?>
                                <option value="<?= $c['category_id'] ?>"
                                    <?= ($editProduct && $editProduct['category_id'] == $c['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-trademark me-1 text-primary"></i>แบรนด์</label>
                        <input name="brand" class="form-control" placeholder="ชื่อแบรนด์ (ถ้ามี)"
                               value="<?= htmlspecialchars($editProduct['brand'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-ruler me-1 text-primary"></i>หน่วย</label>
                        <input name="unit" class="form-control" required placeholder="เช่น ชิ้น, กล่อง, โหล"
                               value="<?= htmlspecialchars($editProduct['unit'] ?? 'ชิ้น') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="fas fa-exclamation-triangle me-1 text-primary"></i>สต็อกขั้นต่ำ</label>
                        <input name="min_stock" type="number" class="form-control" required min="0" placeholder="0"
                               value="<?= htmlspecialchars($editProduct['min_stock'] ?? 10) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label"><i class="fas fa-sticky-note me-1 text-primary"></i>รายละเอียด</label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="รายละเอียดสินค้าเพิ่มเติม (ถ้ามี)"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>ยกเลิก
                </button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save me-2"></i>บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($editProduct): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('productModal')).show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>