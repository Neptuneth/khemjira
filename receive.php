<?php


require_once 'config.php';
$pageTitle = 'รับสินค้าเข้า';
include 'includes/header.php';

// บันทึกการรับสินค้า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $receipt_date = clean($_POST['receipt_date']);
    $supplier_id = (int)$_POST['supplier_id'];
    $note = clean($_POST['note']);
    $user_id = $_SESSION['user_id'];
    
    // เริ่ม Transaction
    $conn->begin_transaction();
    
    try {
        // บันทึกหัวใบรับสินค้า
        $sql = "INSERT INTO goods_receipt (receipt_date, supplier_id, user_id, note)
                VALUES ('$receipt_date', $supplier_id, $user_id, '$note')";
        $conn->query($sql);
        $receipt_id = $conn->insert_id;
        
        // บันทึกรายการสินค้า
        foreach ($_POST['products'] as $index => $product_id) {
            if (!empty($product_id) && !empty($_POST['quantities'][$index])) {
                $product_id = (int)$product_id;
                $quantity = (int)$_POST['quantities'][$index];
                $lot_number = clean($_POST['lot_numbers'][$index] ?? '');
                $expiry_date = !empty($_POST['expiry_dates'][$index]) ? clean($_POST['expiry_dates'][$index]) : null;
                
                // บันทึกรายการ
                $sql = "INSERT INTO goods_receipt_items (receipt_id, product_id, quantity, lot_number, expiry_date)
                        VALUES ($receipt_id, $product_id, $quantity, '$lot_number', " . ($expiry_date ? "'$expiry_date'" : "NULL") . ")";
                $conn->query($sql);
                
                // อัพเดทสต็อก
                $sql = "UPDATE inventory SET quantity = quantity + $quantity WHERE product_id = $product_id";
                $conn->query($sql);
                
                // บันทึกประวัติการเคลื่อนไหว
                $sql = "INSERT INTO stock_movement (product_id, movement_type, quantity, reference_type, reference_id, note)
                        VALUES ($product_id, 'in', $quantity, 'receipt', $receipt_id, 'รับสินค้าเข้า')";
                $conn->query($sql);
            }
        }
        
        $conn->commit();
        setAlert('success', 'บันทึกการรับสินค้าเรียบร้อยแล้ว');
        redirect('receive.php');
        
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ดึงข้อมูลซัพพลายเออร์
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");

// ดึงข้อมูลสินค้า
$products = $conn->query("SELECT * FROM products ORDER BY product_name");

// ดึงประวัติการรับสินค้า (10 รายการล่าสุด)
$recentReceipts = $conn->query("
    SELECT gr.*, s.supplier_name, u.full_name,
           COUNT(gri.item_id) as item_count
    FROM goods_receipt gr
    LEFT JOIN suppliers s ON gr.supplier_id = s.supplier_id
    LEFT JOIN users u ON gr.user_id = u.user_id
    LEFT JOIN goods_receipt_items gri ON gr.receipt_id = gri.receipt_id
    GROUP BY gr.receipt_id
    ORDER BY gr.created_at DESC
    LIMIT 10
");
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-truck-loading text-primary me-2"></i>
        รับสินค้าเข้าคลัง
    </h3>
    <p class="text-muted mb-0">บันทึกการรับสินค้าจากซัพพลายเออร์</p>
</div>

<!-- ฟอร์มรับสินค้า -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">บันทึกรับสินค้าเข้า</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="receiveForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">วันที่รับสินค้า <span class="text-danger">*</span></label>
                    <input type="date" name="receipt_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">ซัพพลายเออร์</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">ไม่ระบุ</option>
                        <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $supplier['supplier_id'] ?>"><?= $supplier['supplier_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <h5 class="mt-4 mb-3">รายการสินค้า</h5>
            <div id="itemsContainer">
                <div class="row mb-2 item-row">
                    <div class="col-md-4">
                        <label class="form-label">สินค้า</label>
                        <select name="products[]" class="form-select" required>
                            <option value="">เลือกสินค้า</option>
                            <?php 
                            $products->data_seek(0);
                            while ($product = $products->fetch_assoc()): 
                            ?>
                                <option value="<?= $product['product_id'] ?>">
                                    <?= $product['product_code'] ?> - <?= $product['product_name'] ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">จำนวน</label>
                        <input type="number" name="quantities[]" class="form-control" required min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">เลขล็อต</label>
                        <input type="text" name="lot_numbers[]" class="form-control" placeholder="LOT-XXX">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">วันหมดอายุ</label>
                        <input type="date" name="expiry_dates[]" class="form-control">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary" onclick="addItem()">
                <i class="fas fa-plus me-2"></i>เพิ่มรายการ
            </button>
            
            <div class="mt-4">
                <label class="form-label">หมายเหตุ</label>
                <textarea name="note" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i>บันทึกรับสินค้า
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-redo me-2"></i>ล้างข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ประวัติการรับสินค้า -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">ประวัติการรับสินค้า (10 รายการล่าสุด)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>เลขที่</th>
                        <th>วันที่</th>
                        <th>ซัพพลายเออร์</th>
                        <th>จำนวนรายการ</th>
                        <th>ผู้บันทึก</th>
                        <th class="text-center">ดูรายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentReceipts->num_rows > 0): ?>
                        <?php while ($receipt = $recentReceipts->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= str_pad($receipt['receipt_id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($receipt['receipt_date'])) ?></td>
                                <td><?= $receipt['supplier_name'] ?? '-' ?></td>
                                <td><span class="badge bg-info"><?= $receipt['item_count'] ?> รายการ</span></td>
                                <td><?= $receipt['full_name'] ?></td>
                                <td class="text-center">
                                    <a href="receipt_detail.php?id=<?= $receipt['receipt_id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ยังไม่มีประวัติการรับสินค้า</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ตัวแปรเก็บ HTML ของสินค้า
const productOptions = `
    <option value="">เลือกสินค้า</option>
    <?php 
    $products->data_seek(0);
    while ($product = $products->fetch_assoc()): 
    ?>
        <option value="<?= $product['product_id'] ?>">
            <?= $product['product_code'] ?> - <?= $product['product_name'] ?>
        </option>
    <?php endwhile; ?>
`;

function addItem() {
    const container = document.getElementById('itemsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 item-row';
    newRow.innerHTML = `
        <div class="col-md-4">
            <select name="products[]" class="form-select" required>${productOptions}</select>
        </div>
        <div class="col-md-2">
            <input type="number" name="quantities[]" class="form-control" required min="1">
        </div>
        <div class="col-md-2">
            <input type="text" name="lot_numbers[]" class="form-control" placeholder="LOT-XXX">
        </div>
        <div class="col-md-3">
            <input type="date" name="expiry_dates[]" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newRow);
}

function removeItem(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
        btn.closest('.item-row').remove();
    } else {
        alert('ต้องมีอย่างน้อย 1 รายการ');
    }
}
</script>

<?php include 'includes/footer.php'; ?>