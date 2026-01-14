<?php


require_once 'config.php';
$pageTitle = 'จัดการสินค้า';
include 'includes/header.php';

// ลบสินค้า
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM products WHERE product_id = $product_id");
    setAlert('success', 'ลบสินค้าเรียบร้อยแล้ว');
    redirect('products.php');
}

// เพิ่ม/แก้ไขสินค้า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_code = clean($_POST['product_code']);
    $product_name = clean($_POST['product_name']);
    $category_id = (int)$_POST['category_id'];
    $brand = clean($_POST['brand']);
    $unit = clean($_POST['unit']);
    $min_stock = (int)$_POST['min_stock'];
    $description = clean($_POST['description']);
    
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        // แก้ไข
        $product_id = (int)$_POST['product_id'];
        $sql = "UPDATE products SET 
                product_code = '$product_code',
                product_name = '$product_name',
                category_id = $category_id,
                brand = '$brand',
                unit = '$unit',
                min_stock = $min_stock,
                description = '$description'
                WHERE product_id = $product_id";
        $conn->query($sql);
        setAlert('success', 'แก้ไขสินค้าเรียบร้อยแล้ว');
    } else {
        // เพิ่มใหม่
        $sql = "INSERT INTO products (product_code, product_name, category_id, brand, unit, min_stock, description)
                VALUES ('$product_code', '$product_name', $category_id, '$brand', '$unit', $min_stock, '$description')";
        $conn->query($sql);
        setAlert('success', 'เพิ่มสินค้าเรียบร้อยแล้ว');
    }
    redirect('products.php');
}

// ดึงข้อมูลสินค้าสำหรับแก้ไข
$editProduct = null;
if (isset($_GET['edit'])) {
    $product_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM products WHERE product_id = $product_id");
    $editProduct = $result->fetch_assoc();
}

// ดึงข้อมูลหมวดหมู่
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

// ดึงข้อมูลสินค้าทั้งหมด
$searchTerm = isset($_GET['search']) ? clean($_GET['search']) : '';
$sql = "SELECT p.*, c.category_name, i.quantity, i.location
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN inventory i ON p.product_id = i.product_id
        WHERE p.product_name LIKE '%$searchTerm%' 
        OR p.product_code LIKE '%$searchTerm%'
        OR p.brand LIKE '%$searchTerm%'
        ORDER BY p.created_at DESC";
$products = $conn->query($sql);
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0">
                <i class="fas fa-box text-primary me-2"></i>
                จัดการสินค้า
            </h3>
            <p class="text-muted mb-0">เพิ่ม แก้ไข ลบข้อมูลสินค้า</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="fas fa-plus me-2"></i>เพิ่มสินค้าใหม่
        </button>
    </div>
</div>

<!-- ค้นหา -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="ค้นหาสินค้า (รหัส, ชื่อ, แบรนด์)" value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>ค้นหา
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ตารางสินค้า -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>หมวดหมู่</th>
                        <th>แบรนด์</th>
                        <th class="text-center">สต็อก</th>
                        <th>ตำแหน่ง</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($row = $products->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $row['product_code'] ?></strong></td>
                                <td><?= $row['product_name'] ?></td>
                                <td><span class="badge bg-info"><?= $row['category_name'] ?></span></td>
                                <td><?= $row['brand'] ?></td>
                                <td class="text-center">
                                    <?php
                                    $quantity = $row['quantity'] ?? 0;
                                    $badge = 'bg-success';
                                    if ($quantity == 0) $badge = 'bg-danger';
                                    elseif ($quantity <= $row['min_stock']) $badge = 'bg-warning';
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= number_format($quantity) ?> <?= $row['unit'] ?></span>
                                </td>
                                <td><?= $row['location'] ?? '-' ?></td>
                                <td class="text-center">
                                    <a href="?edit=<?= $row['product_id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#productModal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?= $row['product_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ต้องการลบสินค้านี้?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
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

<!-- Modal เพิ่ม/แก้ไขสินค้า -->
<div class="modal fade" id="productModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $editProduct ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="products.php">
                <div class="modal-body">
                    <?php if ($editProduct): ?>
                        <input type="hidden" name="product_id" value="<?= $editProduct['product_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">รหัสสินค้า <span class="text-danger">*</span></label>
                            <input type="text" name="product_code" class="form-control" required 
                                   value="<?= $editProduct['product_code'] ?? '' ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" required
                                   value="<?= $editProduct['product_name'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หมวดหมู่ <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">เลือกหมวดหมู่</option>
                                <?php 
                                $categories->data_seek(0);
                                while ($cat = $categories->fetch_assoc()): 
                                ?>
                                    <option value="<?= $cat['category_id'] ?>" 
                                        <?= ($editProduct && $editProduct['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        <?= $cat['category_name'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">แบรนด์</label>
                            <input type="text" name="brand" class="form-control"
                                   value="<?= $editProduct['brand'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">หน่วย <span class="text-danger">*</span></label>
                            <input type="text" name="unit" class="form-control" required
                                   value="<?= $editProduct['unit'] ?? 'ชิ้น' ?>" placeholder="เช่น ชิ้น, กล่อง, ขวด">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สต็อกขั้นต่ำ <span class="text-danger">*</span></label>
                            <input type="number" name="min_stock" class="form-control" required
                                   value="<?= $editProduct['min_stock'] ?? 10 ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea name="description" class="form-control" rows="3"><?= $editProduct['description'] ?? '' ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// เปิด Modal อัตโนมัติถ้ามีการแก้ไข
if ($editProduct): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('productModal'));
        myModal.show();
    });
</script>
<?php endif; ?>

<script>
// เพิ่ม Event Listener สำหรับปุ่มเพิ่มสินค้า
document.addEventListener('DOMContentLoaded', function() {
    // ตรวจสอบว่า Bootstrap โหลดหรือยัง
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap JS ไม่โหลด!');
        alert('เกิดข้อผิดพลาด: Bootstrap ไม่โหลด กรุณารีเฟรชหน้าเว็บ');
    } else {
        console.log('Bootstrap JS โหลดสำเร็จ');
    }
});
</script>

<?php include 'includes/footer.php'; ?>