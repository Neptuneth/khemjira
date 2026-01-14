<?php


require_once 'config.php';
$pageTitle = 'ปรับแก้สต็อก';
include 'includes/header.php';

// บันทึกการปรับแก้สต็อก
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = (int)$_POST['product_id'];
    $adjustment_type = $_POST['adjustment_type']; // add or reduce
    $quantity = (int)$_POST['quantity'];
    $reason = clean($_POST['reason']);
    $note = clean($_POST['note']);
    $user_id = $_SESSION['user_id'];
    
    $conn->begin_transaction();
    
    try {
        // ตรวจสอบสต็อกปัจจุบัน
        $result = $conn->query("SELECT quantity FROM inventory WHERE product_id = $product_id");
        $current = $result->fetch_assoc();
        $current_qty = $current['quantity'] ?? 0;
        
        // คำนวณสต็อกใหม่
        if ($adjustment_type == 'add') {
            $new_qty = $current_qty + $quantity;
            $movement_type = 'in';
        } else {
            if ($current_qty < $quantity) {
                throw new Exception("สต็อกไม่เพียงพอ (คงเหลือ: $current_qty)");
            }
            $new_qty = $current_qty - $quantity;
            $movement_type = 'out';
        }
        
        // อัพเดทสต็อก
        $conn->query("UPDATE inventory SET quantity = $new_qty WHERE product_id = $product_id");
        
        // บันทึกประวัติ
        $full_note = "ปรับแก้สต็อก: $reason" . (!empty($note) ? " - $note" : "");
        $conn->query("
            INSERT INTO stock_movement (product_id, movement_type, quantity, reference_type, note)
            VALUES ($product_id, '$movement_type', $quantity, 'adjustment', '$full_note')
        ");
        
        $conn->commit();
        setAlert('success', 'ปรับแก้สต็อกเรียบร้อยแล้ว');
        redirect('stock_adjustment.php');
        
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ดึงข้อมูลสินค้า
$products = $conn->query("
    SELECT p.*, i.quantity, c.category_name
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_name
");

// ดึงประวัติการปรับแก้ล่าสุด (20 รายการ)
$history = $conn->query("
    SELECT sm.*, p.product_code, p.product_name, p.unit, u.full_name
    FROM stock_movement sm
    JOIN products p ON sm.product_id = p.product_id
    LEFT JOIN users u ON sm.created_at = sm.created_at
    WHERE sm.reference_type = 'adjustment'
    ORDER BY sm.created_at DESC
    LIMIT 20
");
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-edit text-primary me-2"></i>
        ปรับแก้สต็อกสินค้า
    </h3>
    <p class="text-muted mb-0">ปรับเพิ่ม/ลดสต็อกเมื่อมีความผิดพลาดหรือสินค้าเสียหาย</p>
</div>

<!-- ฟอร์มปรับแก้สต็อก -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>บันทึกการปรับแก้สต็อก</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="adjustmentForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">เลือกสินค้า <span class="text-danger">*</span></label>
                    <select name="product_id" id="productSelect" class="form-select" required onchange="showCurrentStock()">
                        <option value="">เลือกสินค้า</option>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <option value="<?= $product['product_id'] ?>" data-stock="<?= $product['quantity'] ?? 0 ?>" data-unit="<?= $product['unit'] ?>">
                                <?= $product['product_code'] ?> - <?= $product['product_name'] ?> 
                                (คงเหลือ: <?= number_format($product['quantity'] ?? 0) ?> <?= $product['unit'] ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div id="currentStock" class="mt-2"></div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">ประเภทการปรับแก้ <span class="text-danger">*</span></label>
                    <select name="adjustment_type" class="form-select" required>
                        <option value="add">เพิ่มสต็อก (+)</option>
                        <option value="reduce">ลดสต็อก (-)</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">จำนวน <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" class="form-control" required min="1" placeholder="ระบุจำนวนที่ต้องการปรับแก้">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">เหตุผล <span class="text-danger">*</span></label>
                    <select name="reason" class="form-select" required>
                        <option value="">เลือกเหตุผล</option>
                        <option value="นับสต็อกไม่ตรง">นับสต็อกไม่ตรง</option>
                        <option value="สินค้าเสียหาย">สินค้าเสียหาย</option>
                        <option value="สินค้าหาย">สินค้าหาย</option>
                        <option value="สินค้าหมดอายุ">สินค้าหมดอายุ</option>
                        <option value="ได้รับสินค้าเพิ่มเติม">ได้รับสินค้าเพิ่มเติม</option>
                        <option value="อื่นๆ">อื่นๆ</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">หมายเหตุเพิ่มเติม</label>
                <textarea name="note" class="form-control" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>คำเตือน:</strong> การปรับแก้สต็อกจะมีผลกับข้อมูลในระบบทันที กรุณาตรวจสอบความถูกต้องก่อนบันทึก
            </div>
            
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-save me-2"></i>บันทึกการปรับแก้
            </button>
            <button type="reset" class="btn btn-secondary">
                <i class="fas fa-redo me-2"></i>ล้างข้อมูล
            </button>
        </form>
    </div>
</div>

<!-- ประวัติการปรับแก้ -->
<div class="card mt-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i>ประวัติการปรับแก้สต็อก (20 รายการล่าสุด)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>วันที่-เวลา</th>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th class="text-center">ประเภท</th>
                        <th class="text-center">จำนวน</th>
                        <th>เหตุผล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($history->num_rows > 0): ?>
                        <?php while ($row = $history->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                                </td>
                                <td><strong><?= $row['product_code'] ?></strong></td>
                                <td><?= $row['product_name'] ?></td>
                                <td class="text-center">
                                    <?php if ($row['movement_type'] == 'in'): ?>
                                        <span class="badge bg-success"><i class="fas fa-plus me-1"></i>เพิ่ม</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-minus me-1"></i>ลด</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <strong><?= number_format($row['quantity']) ?></strong> <?= $row['unit'] ?>
                                </td>
                                <td>
                                    <small><?= $row['note'] ?></small>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ยังไม่มีประวัติการปรับแก้สต็อก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showCurrentStock() {
    const select = document.getElementById('productSelect');
    const option = select.options[select.selectedIndex];
    const stock = option.getAttribute('data-stock');
    const unit = option.getAttribute('data-unit');
    const div = document.getElementById('currentStock');
    
    if (stock !== null && select.value) {
        div.innerHTML = `
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                <strong>สต็อกปัจจุบัน:</strong> ${parseInt(stock).toLocaleString()} ${unit}
            </div>
        `;
    } else {
        div.innerHTML = '';
    }
}

// Confirm before submit
document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
    if (!confirm('ยืนยันการปรับแก้สต็อก?')) {
        e.preventDefault();
    }
});
</script>

<?php include 'includes/footer.php'; ?>