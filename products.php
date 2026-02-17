<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'จัดการสินค้า';
include 'includes/header.php';

/* ===============================
   ลบสินค้า
================================ */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE product_id = $id");
    setAlert('success', 'ลบสินค้าเรียบร้อยแล้ว');
    redirect('products.php');
}

/* ===============================
   เพิ่ม / แก้ไขสินค้า
================================ */
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
        $sql = "
            UPDATE products SET
                product_code='$product_code',
                product_name='$product_name',
                category_id=$category_id,
                brand='$brand',
                unit='$unit',
                min_stock=$min_stock,
                description='$description'
            WHERE product_id=$id
        ";
        $conn->query($sql);
        setAlert('success', 'แก้ไขสินค้าเรียบร้อยแล้ว');
    } else {
        $sql = "
            INSERT INTO products
            (product_code, product_name, category_id, brand, unit, min_stock, description)
            VALUES
            ('$product_code','$product_name',$category_id,'$brand','$unit',$min_stock,'$description')
        ";
        $conn->query($sql);
        setAlert('success', 'เพิ่มสินค้าเรียบร้อยแล้ว');
    }

    redirect('products.php');
}

/* ===============================
   ดึงข้อมูลสำหรับแก้ไข
================================ */
$editProduct = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM products WHERE product_id=$id");
    if ($res && $res->num_rows) {
        $editProduct = $res->fetch_assoc();
    }
}

/* ===============================
   หมวดหมู่
================================ */
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

/* ===============================
   ค้นหา + สินค้า
================================ */
$search = isset($_GET['search']) ? clean($_GET['search']) : '';

$sql = "
    SELECT 
        p.*, 
        c.category_name, 
        COALESCE(i.quantity,0) AS quantity
    FROM products p
    LEFT JOIN categories c ON p.category_id=c.category_id
    LEFT JOIN inventory i ON p.product_id=i.product_id
    WHERE p.product_name LIKE '%$search%'
       OR p.product_code LIKE '%$search%'
       OR p.brand LIKE '%$search%'
    ORDER BY p.created_at DESC
";

$products = $conn->query($sql);
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h3><i class="fas fa-box text-primary me-2"></i>จัดการสินค้า</h3>
        <p class="text-muted mb-0">เพิ่ม / แก้ไข / ลบสินค้า</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
        <i class="fas fa-plus me-2"></i>เพิ่มสินค้า
    </button>
</div>

<!-- ค้นหา -->
<div class="card">
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control"
                       placeholder="ค้นหา..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">ค้นหา</button>
            </div>
        </form>
    </div>
</div>

<!-- ตารางสินค้า -->
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>สินค้า</th>
                    <th>หมวดหมู่</th>
                    <th class="text-center">สต็อก</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($products && $products->num_rows): ?>
                <?php while ($p = $products->fetch_assoc()): 
                    $badge = 'bg-success';
                    if ($p['quantity'] == 0) $badge='bg-danger';
                    elseif ($p['quantity'] <= $p['min_stock']) $badge='bg-warning';
                ?>
                <tr>
                    <td><?= $p['product_code'] ?></td>
                    <td><?= $p['product_name'] ?></td>
                    <td><span class="badge bg-info"><?= $p['category_name'] ?></span></td>
                    <td class="text-center">
                        <span class="badge <?= $badge ?>">
                            <?= $p['quantity'] ?> <?= $p['unit'] ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="?edit=<?= $p['product_id'] ?>" class="btn btn-sm btn-warning"
                           data-bs-toggle="modal" data-bs-target="#productModal">
                           <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete=<?= $p['product_id'] ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('ลบสินค้านี้?')">
                           <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">ไม่มีสินค้า</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5><?= $editProduct ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?= $editProduct['product_id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>รหัสสินค้า</label>
                        <input name="product_code" class="form-control" required
                               value="<?= $editProduct['product_code'] ?? '' ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>ชื่อสินค้า</label>
                        <input name="product_name" class="form-control" required
                               value="<?= $editProduct['product_name'] ?? '' ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>หมวดหมู่</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">เลือกหมวดหมู่</option>
                            <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
                                <option value="<?= $c['category_id'] ?>"
                                    <?= ($editProduct && $editProduct['category_id']==$c['category_id'])?'selected':'' ?>>
                                    <?= $c['category_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>แบรนด์</label>
                        <input name="brand" class="form-control"
                               value="<?= $editProduct['brand'] ?? '' ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>หน่วย</label>
                        <input name="unit" class="form-control" required
                               value="<?= $editProduct['unit'] ?? 'ชิ้น' ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>สต็อกขั้นต่ำ</label>
                        <input name="min_stock" type="number" class="form-control" required
                               value="<?= $editProduct['min_stock'] ?? 10 ?>">
                    </div>
                </div>

                <label>รายละเอียด</label>
                <textarea name="description" class="form-control"><?= $editProduct['description'] ?? '' ?></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<?php if ($editProduct): ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    new bootstrap.Modal(document.getElementById('productModal')).show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
