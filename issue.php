<?php


require_once 'config.php';
$pageTitle = 'เบิกสินค้าออก';
include 'includes/header.php';

// บันทึกการเบิกสินค้า
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $issue_date = clean($_POST['issue_date']);
    $purpose = clean($_POST['purpose']);
    $note = clean($_POST['note']);
    $user_id = $_SESSION['user_id'];
    
    // เริ่ม Transaction
    $conn->begin_transaction();
    
    try {
        // ตรวจสอบสต็อกก่อน
        $hasError = false;
        $errorMessages = [];
        
        foreach ($_POST['products'] as $index => $product_id) {
            if (!empty($product_id) && !empty($_POST['quantities'][$index])) {
                $product_id = (int)$product_id;
                $quantity = (int)$_POST['quantities'][$index];
                
                // ตรวจสอบสต็อก
                $result = $conn->query("SELECT i.quantity, p.product_name 
                                       FROM inventory i 
                                       JOIN products p ON i.product_id = p.product_id
                                       WHERE i.product_id = $product_id");
                $stock = $result->fetch_assoc();
                
                if ($stock['quantity'] < $quantity) {
                    $hasError = true;
                    $errorMessages[] = "สินค้า {$stock['product_name']} มีสต็อกไม่เพียงพอ (คงเหลือ {$stock['quantity']})";
                }
            }
        }
        
        if ($hasError) {
            throw new Exception(implode('<br>', $errorMessages));
        }
        
        // บันทึกหัวใบเบิกสินค้า
        $sql = "INSERT INTO goods_issue (issue_date, user_id, purpose, note)
                VALUES ('$issue_date', $user_id, '$purpose', '$note')";
        $conn->query($sql);
        $issue_id = $conn->insert_id;
        
        // บันทึกรายการสินค้า
        foreach ($_POST['products'] as $index => $product_id) {
            if (!empty($product_id) && !empty($_POST['quantities'][$index])) {
                $product_id = (int)$product_id;
                $quantity = (int)$_POST['quantities'][$index];
                
                // บันทึกรายการ
                $sql = "INSERT INTO goods_issue_items (issue_id, product_id, quantity)
                        VALUES ($issue_id, $product_id, $quantity)";
                $conn->query($sql);
                
                // ลดสต็อก
                $sql = "UPDATE inventory SET quantity = quantity - $quantity WHERE product_id = $product_id";
                $conn->query($sql);
                
                // บันทึกประวัติการเคลื่อนไหว
                $sql = "INSERT INTO stock_movement (product_id, movement_type, quantity, reference_type, reference_id, note)
                        VALUES ($product_id, 'out', $quantity, 'issue', $issue_id, 'เบิกสินค้าออก')";
                $conn->query($sql);
            }
        }
        
        $conn->commit();
        setAlert('success', 'บันทึกการเบิกสินค้าเรียบร้อยแล้ว');
        redirect('issue.php');
        
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ดึงข้อมูลสินค้าพร้อมสต็อก
$products = $conn->query("
    SELECT p.*, i.quantity
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    ORDER BY p.product_name
");

// ดึงประวัติการเบิกสินค้า (10 รายการล่าสุด)
$recentIssues = $conn->query("
    SELECT gi.*, u.full_name,
           COUNT(gii.item_id) as item_count
    FROM goods_issue gi
    LEFT JOIN users u ON gi.user_id = u.user_id
    LEFT JOIN goods_issue_items gii ON gi.issue_id = gii.issue_id
    GROUP BY gi.issue_id
    ORDER BY gi.created_at DESC
    LIMIT 10
");
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-dolly text-primary me-2"></i>
        เบิกสินค้าออกจากคลัง
    </h3>
    <p class="text-muted mb-0">บันทึกการเบิกสินค้าออกจากคลัง</p>
</div>

<!-- ฟอร์มเบิกสินค้า -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">บันทึกเบิกสินค้าออก</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="issueForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">วันที่เบิกสินค้า <span class="text-danger">*</span></label>
                    <input type="date" name="issue_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">วัตถุประสงค์ <span class="text-danger">*</span></label>
                    <input type="text" name="purpose" class="form-control" required placeholder="เช่น ขายหน้าร้าน, โอนสาขา">
                </div>
            </div>
            
            <h5 class="mt-4 mb-3">รายการสินค้า</h5>
            <div id="itemsContainer">
                <div class="row mb-2 item-row">
                    <div class="col-md-6">
                        <label class="form-label">สินค้า</label>
                        <select name="products[]" class="form-select product-select" required onchange="updateStock(this)">
                            <option value="">เลือกสินค้า</option>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <option value="<?= $product['product_id'] ?>" data-stock="<?= $product['quantity'] ?>">
                                    <?= $product['product_code'] ?> - <?= $product['product_name'] ?> 
                                    (คงเหลือ: <?= number_format($product['quantity']) ?> <?= $product['unit'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">จำนวน</label>
                        <input type="number" name="quantities[]" class="form-control" required min="1">
                        <small class="text-muted stock-info"></small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                            <i class="fas fa-trash"></i> ลบ
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
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check me-2"></i>บันทึกเบิกสินค้า
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fas fa-redo me-2"></i>ล้างข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ประวัติการเบิกสินค้า -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">ประวัติการเบิกสินค้า (10 รายการล่าสุด)</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>เลขที่</th>
                        <th>วันที่</th>
                        <th>วัตถุประสงค์</th>
                        <th>จำนวนรายการ</th>
                        <th>ผู้บันทึก</th>
                        <th class="text-center">ดูรายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recentIssues->num_rows > 0): ?>
                        <?php while ($issue = $recentIssues->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= str_pad($issue['issue_id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($issue['issue_date'])) ?></td>
                                <td><?= $issue['purpose'] ?></td>
                                <td><span class="badge bg-info"><?= $issue['item_count'] ?> รายการ</span></td>
                                <td><?= $issue['full_name'] ?></td>
                                <td class="text-center">
                                    <a href="issue_detail.php?id=<?= $issue['issue_id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ยังไม่มีประวัติการเบิกสินค้า</td>
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
        <option value="<?= $product['product_id'] ?>" data-stock="<?= $product['quantity'] ?>">
            <?= $product['product_code'] ?> - <?= $product['product_name'] ?> 
            (คงเหลือ: <?= number_format($product['quantity']) ?> <?= $product['unit'] ?>)
        </option>
    <?php endwhile; ?>
`;

function updateStock(select) {
    const row = select.closest('.item-row');
    const stockInfo = row.querySelector('.stock-info');
    const quantityInput = row.querySelector('input[name="quantities[]"]');
    const selectedOption = select.options[select.selectedIndex];
    const stock = selectedOption.getAttribute('data-stock');
    
    if (stock) {
        stockInfo.textContent = `คงเหลือ: ${stock} หน่วย`;
        quantityInput.max = stock;
    } else {
        stockInfo.textContent = '';
        quantityInput.max = '';
    }
}

function addItem() {
    const container = document.getElementById('itemsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-2 item-row';
    newRow.innerHTML = `
        <div class="col-md-6">
            <select name="products[]" class="form-select product-select" required onchange="updateStock(this)">
                ${productOptions}
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="quantities[]" class="form-control" required min="1">
            <small class="text-muted stock-info"></small>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-danger w-100" onclick="removeItem(this)">
                <i class="fas fa-trash"></i> ลบ
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

// Validate form ก่อน submit
document.getElementById('issueForm').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.item-row');
    let hasError = false;
    
    rows.forEach(row => {
        const select = row.querySelector('select[name="products[]"]');
        const quantity = parseInt(row.querySelector('input[name="quantities[]"]').value);
        const stock = parseInt(select.options[select.selectedIndex].getAttribute('data-stock'));
        
        if (quantity > stock) {
            hasError = true;
            alert('จำนวนเบิกเกินสต็อกคงเหลือ กรุณาตรวจสอบ');
        }
    });
    
    if (hasError) {
        e.preventDefault();
    }
});
</script>

<?php include 'includes/footer.php'; ?>