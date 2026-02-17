<?php
require_once 'config.php';
$pageTitle = 'จัดการหมวดหมู่สินค้า';
include 'includes/header.php';

/* ===============================
   ลบหมวดหมู่
================================ */
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];

    $check = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE category_id = $category_id");
    $count = $check ? (int)$check->fetch_assoc()['cnt'] : 0;

    if ($count > 0) {
        setAlert('danger', "ไม่สามารถลบได้ เนื่องจากมีสินค้า $count รายการในหมวดหมู่นี้");
    } else {
        $conn->query("DELETE FROM categories WHERE category_id = $category_id");
        setAlert('success', 'ลบหมวดหมู่เรียบร้อยแล้ว');
    }
    redirect('categories.php');
}

/* ===============================
   เพิ่ม / แก้ไขหมวดหมู่
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = clean($_POST['category_name']);
    $description   = clean($_POST['description']);

    if (!empty($_POST['category_id'])) {
        $category_id = (int)$_POST['category_id'];
        $conn->query("
            UPDATE categories 
            SET category_name = '$category_name',
                description = '$description'
            WHERE category_id = $category_id
        ");
        setAlert('success', 'แก้ไขหมวดหมู่เรียบร้อยแล้ว');
    } else {
        $conn->query("
            INSERT INTO categories (category_name, description)
            VALUES ('$category_name', '$description')
        ");
        setAlert('success', 'เพิ่มหมวดหมู่เรียบร้อยแล้ว');
    }
    redirect('categories.php');
}

/* ===============================
   ดึงข้อมูลสำหรับแก้ไข
================================ */
$editCategory = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM categories WHERE category_id = $id");
    if ($res && $res->num_rows) {
        $editCategory = $res->fetch_assoc();
    }
}

/* ===============================
   ดึงหมวดหมู่ทั้งหมด (เก็บเป็น array)
================================ */
$sql = "
    SELECT c.*, COUNT(p.product_id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name
";
$result = $conn->query($sql);
$categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h3 class="mb-0"><i class="fas fa-tags text-primary me-2"></i>จัดการหมวดหมู่สินค้า</h3>
        <p class="text-muted mb-0">จัดกลุ่มสินค้า</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus me-2"></i>เพิ่มหมวดหมู่
    </button>
</div>

<!-- การ์ดหมวดหมู่ -->
<div class="row">
<?php if (count($categories)): 
    $colors = ['primary','success','danger','warning','info','secondary'];
    $i = 0;
    foreach ($categories as $row): 
        $color = $colors[$i++ % count($colors)];
?>
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>
                    <i class="fas fa-tag text-<?= $color ?> me-2"></i>
                    <?= $row['category_name'] ?>
                </h5>
                <p class="text-muted"><?= $row['description'] ?: 'ไม่มีคำอธิบาย' ?></p>
                <span class="badge bg-<?= $color ?> mb-3"><?= $row['product_count'] ?> รายการ</span>

                <div class="d-flex gap-2">
                    <a href="?edit=<?= $row['category_id'] ?>" class="btn btn-sm btn-warning flex-fill"
                       data-bs-toggle="modal" data-bs-target="#categoryModal">
                        แก้ไข
                    </a>

                    <?php if ($row['product_count'] == 0): ?>
                        <a href="?delete=<?= $row['category_id'] ?>" class="btn btn-sm btn-danger flex-fill"
                           onclick="return confirm('ต้องการลบหมวดหมู่นี้?')">
                            ลบ
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary flex-fill" disabled>ลบไม่ได้</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; else: ?>
    <div class="col-12 text-center text-muted py-5">
        <i class="fas fa-tags fa-3x mb-3"></i>
        <p>ยังไม่มีหมวดหมู่</p>
    </div>
<?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= $editCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?= $editCategory['category_id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label>ชื่อหมวดหมู่</label>
                    <input type="text" name="category_name" class="form-control" required
                           value="<?= $editCategory['category_name'] ?? '' ?>">
                </div>

                <div class="mb-3">
                    <label>คำอธิบาย</label>
                    <textarea name="description" class="form-control"><?= $editCategory['description'] ?? '' ?></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- หมวดหมู่สินค้า -->
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-tags text-info me-2"></i>
            หมวดหมู่สินค้าทั้งหมด
        </h5>
    </div>
    <div class="card-body">
        <?php if (count($categories) > 0): ?>
            <div class="row">
                <?php foreach ($categories as $cat): ?>
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-3 h-100">
                            <h6 class="mb-1">
                                <i class="fas fa-tag me-1 text-secondary"></i>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </h6>
                            <small class="text-muted">
                                <?= number_format($cat['product_count']) ?> รายการ
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-muted text-center">
                ยังไม่มีหมวดหมู่สินค้า
            </div>
        <?php endif; ?>
    </div>
</div>



<?php if ($editCategory): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
