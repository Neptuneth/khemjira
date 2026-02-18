<?php
require_once 'config.php';
requireAdmin();
$pageTitle = 'จัดการหมวดหมู่สินค้า';
include 'includes/header.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = clean($_POST['category_name']);
    $description   = clean($_POST['description']);
    if (!empty($_POST['category_id'])) {
        $category_id = (int)$_POST['category_id'];
        $conn->query("UPDATE categories SET category_name='$category_name', description='$description' WHERE category_id=$category_id");
        setAlert('success', 'แก้ไขหมวดหมู่เรียบร้อยแล้ว');
    } else {
        $conn->query("INSERT INTO categories (category_name, description) VALUES ('$category_name','$description')");
        setAlert('success', 'เพิ่มหมวดหมู่เรียบร้อยแล้ว');
    }
    redirect('categories.php');
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM categories WHERE category_id=$id");
    if ($res && $res->num_rows) $editCategory = $res->fetch_assoc();
}

$result = $conn->query("
    SELECT c.*, COUNT(p.product_id) AS product_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name
");
$categories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// กำหนดชุดสี gradient สำหรับการ์ด
$gradients = [
    ['#667eea','#764ba2'],
    ['#43e97b','#38f9d7'],
    ['#f093fb','#f5576c'],
    ['#4facfe','#00f2fe'],
    ['#f6d365','#fda085'],
    ['#a18cd1','#fbc2eb'],
    ['#fccb90','#d57eeb'],
    ['#a1c4fd','#c2e9fb'],
];
?>

<style>
/* ===== Page Header ===== */
.page-header-custom {
    background: #fff; border-radius: 16px; padding: 22px 26px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.page-header-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, #f6d365, #fda085);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}
.btn-add {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 12px;
    padding: 10px 22px; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    transition: all 0.2s; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
}
.btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.45); color: #fff; }

/* ===== Summary Bar ===== */
.summary-bar {
    background: #fff; border-radius: 16px; padding: 16px 24px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
}
.summary-item { display: flex; align-items: center; gap: 10px; }
.summary-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.summary-num { font-size: 1.4rem; font-weight: 800; color: #1e293b; line-height: 1; }
.summary-label { font-size: 12px; color: #94a3b8; font-weight: 600; }
.summary-divider { width: 1px; height: 36px; background: #e2e8f0; }

/* ===== Category Cards ===== */
.cat-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    overflow: hidden; height: 100%;
    transition: transform 0.25s, box-shadow 0.25s;
    animation: fadeUp 0.4s ease both;
}
.cat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,0.13); }
.cat-card-top {
    padding: 24px 22px 18px;
    position: relative; overflow: hidden;
}
.cat-card-top .bg-blob {
    position: absolute; right: -20px; top: -20px;
    width: 100px; height: 100px; border-radius: 50%; opacity: 0.15;
}
.cat-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; margin-bottom: 14px;
}
.cat-name { font-weight: 800; font-size: 16px; color: #1e293b; margin-bottom: 4px; }
.cat-desc { font-size: 13px; color: #94a3b8; }
.cat-count-badge {
    display: inline-flex; align-items: center; gap: 6px;
    border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700;
    margin-top: 10px;
}
.cat-card-footer {
    padding: 14px 18px; border-top: 1px solid #f1f5f9;
    display: flex; gap: 8px;
}

/* ===== Buttons ===== */
.btn-edit-cat {
    flex: 1; border-radius: 9px; border: 2px solid #f59e0b;
    background: #fff; color: #f59e0b; font-weight: 700; font-size: 13px;
    padding: 8px; transition: all 0.2s; cursor: pointer; text-align: center; text-decoration: none;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-edit-cat:hover { background: #f59e0b; color: #fff; }
.btn-del-cat {
    flex: 1; border-radius: 9px; border: 2px solid #ef4444;
    background: #fff; color: #ef4444; font-weight: 700; font-size: 13px;
    padding: 8px; transition: all 0.2s; cursor: pointer; text-align: center; text-decoration: none;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.btn-del-cat:hover { background: #ef4444; color: #fff; }
.btn-disabled-cat {
    flex: 1; border-radius: 9px; border: 2px solid #e2e8f0;
    background: #f8fafc; color: #cbd5e1; font-weight: 700; font-size: 13px;
    padding: 8px; cursor: not-allowed; text-align: center;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}

/* ===== Empty State ===== */
.empty-state {
    text-align: center; padding: 60px; color: #94a3b8;
    background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
}

/* ===== Modal ===== */
.modal-content { border: none; border-radius: 18px; overflow: hidden; }
.modal-header {
    background: linear-gradient(135deg, #f6d365, #fda085);
    color: #fff; padding: 20px 24px; border: none;
}
.modal-header .btn-close { filter: invert(1) brightness(2); }
.modal-title { font-weight: 800; font-size: 17px; }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; }
.modal .form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }
.modal .form-control {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 10px 14px; font-size: 14px; transition: border-color 0.2s;
}
.modal .form-control:focus { border-color: #f6d365; box-shadow: 0 0 0 4px rgba(246,211,101,0.15); }
.btn-save {
    background: linear-gradient(135deg, #f6d365, #fda085);
    border: none; color: #fff; border-radius: 10px;
    padding: 10px 28px; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 12px rgba(246,211,101,0.4); transition: all 0.2s;
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
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<!-- ===== Page Header ===== -->
<div class="page-header-custom fade-in fade-in-1">
    <div class="d-flex align-items-center gap-3">
        <div class="page-header-icon"><i class="fas fa-tags"></i></div>
        <div>
            <h4 class="mb-0 fw-bold">จัดการหมวดหมู่สินค้า</h4>
            <p class="text-muted mb-0" style="font-size:13px;">จัดกลุ่มสินค้าในระบบคลังสินค้า</p>
        </div>
    </div>
    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus"></i> เพิ่มหมวดหมู่
    </button>
</div>

<!-- ===== Summary Bar ===== -->
<div class="summary-bar fade-in fade-in-2">
    <div class="summary-item">
        <div class="summary-icon" style="background:#eef2ff;color:#667eea;">
            <i class="fas fa-layer-group"></i>
        </div>
        <div>
            <div class="summary-num"><?= count($categories) ?></div>
            <div class="summary-label">หมวดหมู่ทั้งหมด</div>
        </div>
    </div>
    <div class="summary-divider"></div>
    <div class="summary-item">
        <div class="summary-icon" style="background:#e8f5e9;color:#2e7d32;">
            <i class="fas fa-box"></i>
        </div>
        <div>
            <div class="summary-num"><?= array_sum(array_column($categories, 'product_count')) ?></div>
            <div class="summary-label">สินค้าทั้งหมด</div>
        </div>
    </div>
    <div class="summary-divider"></div>
    <div class="summary-item">
        <div class="summary-icon" style="background:#fff3e0;color:#f57c00;">
            <i class="fas fa-tags"></i>
        </div>
        <div>
            <div class="summary-num"><?= count(array_filter($categories, fn($c) => $c['product_count'] == 0)) ?></div>
            <div class="summary-label">หมวดหมู่ว่าง</div>
        </div>
    </div>
</div>

<!-- ===== Category Cards ===== -->
<?php if (count($categories)): ?>
<div class="row g-3">
    <?php foreach ($categories as $idx => $row):
        $g = $gradients[$idx % count($gradients)];
        $c1 = $g[0]; $c2 = $g[1];
        $delay = ($idx % 6) * 60;
    ?>
    <div class="col-md-4 col-sm-6">
        <div class="cat-card" style="animation-delay: <?= $delay ?>ms;">
            <div class="cat-card-top">
                <div class="bg-blob" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);"></div>
                <div class="cat-icon" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);">
                    <i class="fas fa-tag"></i>
                </div>
                <div class="cat-name"><?= htmlspecialchars($row['category_name']) ?></div>
                <div class="cat-desc"><?= htmlspecialchars($row['description'] ?: 'ไม่มีคำอธิบาย') ?></div>
                <div class="cat-count-badge" style="background:<?= $c1 ?>22;color:<?= $c1 ?>;">
                    <i class="fas fa-box"></i>
                    <?= number_format($row['product_count']) ?> รายการ
                </div>
            </div>
            <div class="cat-card-footer">
                <a href="?edit=<?= $row['category_id'] ?>" class="btn-edit-cat"
                   data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-edit"></i> แก้ไข
                </a>
                <?php if ($row['product_count'] == 0): ?>
                    <a href="?delete=<?= $row['category_id'] ?>" class="btn-del-cat"
                       onclick="return confirm('ยืนยันการลบหมวดหมู่: <?= htmlspecialchars($row['category_name']) ?>?')">
                        <i class="fas fa-trash"></i> ลบ
                    </a>
                <?php else: ?>
                    <div class="btn-disabled-cat" title="มีสินค้าอยู่ในหมวดหมู่นี้">
                        <i class="fas fa-lock"></i> ลบไม่ได้
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="empty-state fade-in fade-in-2">
    <i class="fas fa-tags fa-3x d-block mb-3"></i>
    <div style="font-size:16px;font-weight:600;color:#64748b;">ยังไม่มีหมวดหมู่สินค้า</div>
    <div style="font-size:13px;margin-top:8px;">กดปุ่ม "เพิ่มหมวดหมู่" เพื่อเริ่มต้น</div>
</div>
<?php endif; ?>

<!-- ===== Modal ===== -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas <?= $editCategory ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $editCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่ใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($editCategory): ?>
                    <input type="hidden" name="category_id" value="<?= $editCategory['category_id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label"><i class="fas fa-tag me-1" style="color:#f6d365;"></i>ชื่อหมวดหมู่</label>
                    <input type="text" name="category_name" class="form-control" required
                           placeholder="เช่น ครีมบำรุงผิว, ลิปสติก..."
                           value="<?= htmlspecialchars($editCategory['category_name'] ?? '') ?>">
                </div>
                <div class="mb-0">
                    <label class="form-label"><i class="fas fa-sticky-note me-1" style="color:#f6d365;"></i>คำอธิบาย</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="คำอธิบายหมวดหมู่ (ถ้ามี)"><?= htmlspecialchars($editCategory['description'] ?? '') ?></textarea>
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

<?php if ($editCategory): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>