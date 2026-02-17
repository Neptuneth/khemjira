<?php
// หน้าเบิกสินค้าออก
require_once 'config.php';
$pageTitle = 'เบิกสินค้าออก';
include 'includes/header.php';

// ================== บันทึกการเบิก ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_date = clean($_POST['issue_date']);
    $purpose    = clean($_POST['purpose']);
    $note       = clean($_POST['note']);
    $user_id    = $_SESSION['user_id'];

    $conn->begin_transaction();

    try {
        // ตรวจ stock ซ้ำอีกครั้ง (กัน JS bypass)
        foreach ($_POST['products'] as $i => $product_id) {
            $product_id = (int)$product_id;
            $qty = (int)$_POST['quantities'][$i];

            $q = $conn->query("
                SELECT quantity 
                FROM inventory 
                WHERE product_id = $product_id
            ");
            $stock = $q->fetch_assoc();

            if ($qty > $stock['quantity']) {
                throw new Exception('มีสินค้าบางรายการเบิกเกินสต็อก');
            }
        }

        // บันทึกหัวใบเบิก
        $conn->query("
            INSERT INTO goods_issue (issue_date, user_id, purpose, note)
            VALUES ('$issue_date', $user_id, '$purpose', '$note')
        ");
        $issue_id = $conn->insert_id;

        // บันทึกรายการ + ตัดสต็อก
        foreach ($_POST['products'] as $i => $product_id) {
            $product_id = (int)$product_id;
            $qty = (int)$_POST['quantities'][$i];

            $conn->query("
                INSERT INTO goods_issue_items (issue_id, product_id, quantity)
                VALUES ($issue_id, $product_id, $qty)
            ");

            $conn->query("
                UPDATE inventory 
                SET quantity = quantity - $qty 
                WHERE product_id = $product_id
            ");

            $conn->query("
                INSERT INTO stock_movement
                (product_id, movement_type, quantity, reference_type, reference_id, note)
                VALUES ($product_id,'out',$qty,'issue',$issue_id,'เบิกสินค้า')
            ");
        }

        $conn->commit();
        redirect("issue_detail.php?id=$issue_id");
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('danger', $e->getMessage());
    }
}

// ================== ดึงสินค้า ==================
$products = $conn->query("
    SELECT p.product_id, p.product_code, p.product_name, p.unit,
           IFNULL(i.quantity,0) AS stock
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    ORDER BY p.product_name
");
?>

<div class="page-header">
    <h3><i class="fas fa-dolly text-primary me-2"></i>เบิกสินค้าออก</h3>
    <p class="text-muted mb-0">บันทึกการเบิกสินค้าออกจากคลัง</p>
</div>

<div class="card">
    <div class="card-body">
        <form method="post" id="issueForm">

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">วันที่เบิก</label>
                    <input type="date" name="issue_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">วัตถุประสงค์</label>
                    <input type="text" name="purpose" class="form-control" required>
                </div>
            </div>

            <h5 class="mb-3">รายการสินค้า</h5>

            <div id="items"></div>

            <button type="button" class="btn btn-secondary mt-2" onclick="addRow()">
                <i class="fas fa-plus"></i> เพิ่มสินค้า
            </button>

            <div class="alert alert-info mt-3">
                📊 จำนวนรวมทั้งหมด: <strong><span id="totalQty">0</span></strong> ชิ้น
            </div>

            <div class="mb-3">
                <label class="form-label">หมายเหตุ</label>
                <textarea name="note" class="form-control"></textarea>
            </div>

            <button class="btn btn-primary">
                <i class="fas fa-save me-1"></i>บันทึกเบิกสินค้า
            </button>

        </form>
    </div>
</div>

<script>
const products = <?= json_encode($products->fetch_all(MYSQLI_ASSOC)) ?>;

function addRow() {
    const row = document.createElement('div');
    row.className = 'row mb-2 item-row';

    row.innerHTML = `
        <div class="col-md-6">
            <select class="form-select product" name="products[]" onchange="syncProducts()" required>
                <option value="">เลือกสินค้า</option>
                ${products.map(p =>
                    `<option value="${p.product_id}" data-stock="${p.stock}">
                        ${p.product_code} - ${p.product_name} (คงเหลือ ${p.stock} ${p.unit})
                    </option>`
                ).join('')}
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="quantities[]" class="form-control qty"
                   min="1" oninput="calcTotal()" required>
            <small class="text-danger stock-warning"></small>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-danger w-100" onclick="this.closest('.item-row').remove();syncProducts();calcTotal();">
                ลบ
            </button>
        </div>
    `;
    document.getElementById('items').appendChild(row);
}

// ปิดการเลือกสินค้าซ้ำ
function syncProducts() {
    const selected = [...document.querySelectorAll('.product')]
        .map(s => s.value)
        .filter(v => v);

    document.querySelectorAll('.product').forEach(select => {
        [...select.options].forEach(opt => {
            opt.disabled = selected.includes(opt.value) && opt.value !== select.value;
        });
    });
    calcTotal();
}

// คำนวณ + แจ้งเตือนเกิน stock
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = row.querySelector('.qty');
        const select = row.querySelector('.product');
        const warn = row.querySelector('.stock-warning');

        if (!select.value) return;

        const stock = parseInt(select.selectedOptions[0].dataset.stock);
        const val = parseInt(qty.value || 0);

        if (val > stock) {
            warn.textContent = '❌ เบิกเกินสต็อก';
            qty.classList.add('is-invalid');
        } else {
            warn.textContent = '';
            qty.classList.remove('is-invalid');
            total += val;
        }
    });
    document.getElementById('totalQty').innerText = total;
}

// เริ่มต้น 1 แถว
addRow();
</script>

<?php include 'includes/footer.php'; ?>
