<?php

require_once 'config.php';
$pageTitle = 'จัดการหมวดหมู่สินค้า';
include 'includes/header.php';

// ลบหมวดหมู่
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    // ตรวจสอบว่ามีสินค้าในหมวดหมู่นี้หรือไม่
    $check = $conn->query("SELECT COUNT(*) as count FROM products WHERE category_id = $category_id");
    $count = $check->fetch_assoc()['count'];
    
    if ($count > 0) {
        setAlert('danger', "ไม่สามารถลบได้ เนื่องจากมีสินค้า $count รายการในหมวดหมู่นี้");
    } else {
        $conn->query("DELETE FROM categories WHERE category_id = $category_id");
        setAlert('success', 'ลบหมวดหมู่เรียบร้อยแล้ว');
    }
    redirect('categories.php');
}

// เพิ่ม/แก้ไขหมวดหมู่
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = clean($_POST['category_name']);
    $description = clean($_POST['description']);
    
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        // แก้ไข
        $category_id = (int)$_POST['category_id'];
        $sql = "UPDATE categories SET 
                category_name = '$category_name',
                description = '$description'
                WHERE category_id = $category_id";
        $conn->query($sql);
        setAlert('success', 'แก้ไขหมวดหมู่เรียบร้อยแล้ว');
    } else {
        // เพิ่มใหม่
        $sql = "INSERT INTO categories (category_name, description)
                VALUES ('$category_name', '$description')";
        $conn->query($sql);
        setAlert('success', 'เพิ่มหมวดหมู่เรียบร้อยแล้ว');
    }
    redirect('categories.php');
}

// ดึงข้อมูลหมวดหมู่สำหรับแก้ไข
$editCategory = null;
if (isset($_GET['edit'])) {
    $category_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM categories WHERE category_id = $category_id");
    $editCategory = $result->fetch_assoc();
}

// ดึงข้อมูลหมวดหมู่พร้อมนับสินค้า
$categories = $conn->query("
    SELECT c.*, COUNT(p.product_id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name
");
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0">
                <i class="fas fa-tags text-primary me-2"></i>
                จัดการหมวดหมู่สินค้า
            </h3>
            <p class="text-muted mb-0">จัดกลุ่มสินค้าตามประเภท</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="fas fa-plus me-2"></i>เพิ่มหมวดหมู่ใหม่
        </button>
    </div>
</div>

<!-- การ์ดหมวดหมู่ -->
<div class="row">
    <?php if ($categories->num_rows > 0): ?>
        <?php 
        $colors = ['primary', 'success', 'danger', 'warning', 'info', 'secondary'];
        $index = 0;
        while ($row = $categories->fetch_assoc()): 
            $color = $colors[$index % count($colors)];
            $index++;
        ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">
                                    <i class="fas fa-tag text-<?= $color ?> me-2"></i>
                                    <?= $row['category_name'] ?>
                                </h5>
                                <p class="text-muted mb-0 small"><?= $row['description'] ?: 'ไม่มีคำอธิบาย' ?></p>
                            </div>
                            <span class="badge bg-<?= $color ?>"><?= $row['product_count'] ?> รายการ</span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="?edit=<?= $row['category_id'] ?>" class="btn btn-sm btn-warning flex-fill" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                <i class="fas fa-edit"></i> แก้ไข
                            </a>
                            <?php if ($row['product_count'] == 0): ?>
                                <a href="?delete=<?= $row['category_id'] ?>" class="btn btn-sm btn-danger flex-fill" onclick="return confirm('ต้องการลบหมวดหมู่นี้?')">
                                    <i class="fas fa-trash"></i> ลบ
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary flex-fill" disabled title="มีสินค้าในหมวดหมู่">
                                    <i class="fas fa-lock"></i> ลบไม่ได้
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-tags fa-4x text-muted mb-3"></i>
                    <h5>ยังไม่มีหมวดหมู่สินค้า</h5>
                    <p class="text-muted">เริ่มต้นโดยการเพิ่มหมวดหมู่ใหม่</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="fas fa-plus me-2"></i>เพิ่มหมวดหมู่แรก
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ตารางแสดงรายละเอียด -->
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">รายการหมวดหมู่ทั้งหมด</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อหมวดหมู่</th>
                        <th>คำอธิบาย</th>
                        <th class="text-center">จำนวนสินค้า</th>
                        <th>วันที่สร้าง</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $categories->data_seek(0);
                    if ($categories->num_rows > 0): 
                    ?>
                        <?php while ($row = $categories->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['category_id'] ?></strong></td>
                                <td><i class="fas fa-tag me-2"></i><?= $row['category_name'] ?></td>
                                <td><?= $row['description'] ?: '-' ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $row['product_count'] ?> รายการ</span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($row['created_at'])) ?></td>
                                <td class="text-center">
                                    <a href="?edit=<?= $row['category_id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($row['product_count'] == 0): ?>
                                        <a href="?delete=<?= $row['category_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ต้องการลบหมวดหมู่นี้?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="มีสินค้าในหมวดหมู่">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ไม่พบข้อมูลหมวดหมู่</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขหมวดหมู่ -->
<div class="modal fade" id="categoryModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $editCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <div class="modal-body">
                    <?php if ($editCategory): ?>
                        <input type="hidden" name="category_id" value="<?= $editCategory['category_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" required 
                               value="<?= $editCategory['category_name'] ?? '' ?>"
                               placeholder="เช่น เครื่องสำอาง, ผลิตภัณฑ์ดูแลผิว">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea name="description" class="form-control" rows="3" 
                                  placeholder="ระบุรายละเอียดหมวดหมู่ (ไม่บังคับ)"><?= $editCategory['description'] ?? '' ?></textarea>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>หมวดหมู่จะช่วยจัดกลุ่มสินค้าให้ค้นหาและจัดการได้ง่ายขึ้น</small>
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
if ($editCategory): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('categoryModal'));
        myModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>