<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'รับสินค้าเข้า';
include 'includes/header.php';

// ให้ mysqli โยน exception (สำคัญมาก)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// =======================
// บันทึกการรับสินค้า
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $receipt_date = $_POST['receipt_date'] ?? date('Y-m-d');
    $supplier_id  = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $note         = $_POST['note'] ?? '';
    $user_id      = $_SESSION['user_id'];

    $conn->begin_transaction();

    try {
        // ---------- บันทึกหัวใบรับสินค้า ----------
        $stmt = $conn->prepare("
            INSERT INTO goods_receipt (receipt_date, supplier_id, user_id, note)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("siis", $receipt_date, $supplier_id, $user_id, $note);
        $stmt->execute();

        $receipt_id = $stmt->insert_id;

        // ---------- บันทึกรายการสินค้า ----------
        if (!empty($_POST['products']) && is_array($_POST['products'])) {

            $stmtItem = $conn->prepare("
                INSERT INTO goods_receipt_items
                (receipt_id, product_id, quantity, lot_number, expiry_date)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmtStock = $conn->prepare("
                INSERT INTO inventory (product_id, quantity)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");

            $stmtMove = $conn->prepare("
                INSERT INTO stock_movement
                (product_id, movement_type, quantity, reference_type, reference_id, note)
                VALUES (?, 'in', ?, 'receipt', ?, 'รับสินค้าเข้า')
            ");

            foreach ($_POST['products'] as $i => $product_id) {

                if (empty($product_id) || empty($_POST['quantities'][$i])) {
                    continue;
                }

                $product_id  = (int)$product_id;
                $quantity    = (int)$_POST['quantities'][$i];
                $lot_number  = $_POST['lot_numbers'][$i] ?? '';
                $expiry_date = !empty($_POST['expiry_dates'][$i])
                                ? $_POST['expiry_dates'][$i]
                                : null;

                // รายการรับ
                $stmtItem->bind_param(
                    "iiiss",
                    $receipt_id,
                    $product_id,
                    $quantity,
                    $lot_number,
                    $expiry_date
                );
                $stmtItem->execute();

                // อัปเดต / เพิ่ม stock
                $stmtStock->bind_param("ii", $product_id, $quantity);
                $stmtStock->execute();

                // บันทึก movement
                $stmtMove->bind_param("iii", $product_id, $quantity, $receipt_id);
                $stmtMove->execute();
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

// =======================
// ดึงข้อมูลสำหรับแสดงผล
// =======================
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");
$productsQ = $conn->query("SELECT * FROM products ORDER BY product_name");
$productList = $productsQ->fetch_all(MYSQLI_ASSOC);

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

<!-- ======================= HTML ======================= -->

<div class="page-header mb-4">
    <h3><i class="fas fa-truck-loading text-primary me-2"></i>รับสินค้าเข้าคลัง</h3>
    <p class="text-muted">บันทึกการรับสินค้าจากซัพพลายเออร์</p>
</div>

<?php showAlert(); ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" id="receiveForm">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">วันที่รับสินค้า</label>
                    <input type="date" name="receipt_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">ซัพพลายเออร์</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">ไม่ระบุ</option>
                        <?php while ($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['supplier_id'] ?>"><?= $s['supplier_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <h5 class="mt-3">รายการสินค้า</h5>
            <div id="itemsContainer"></div>

            <button type="button" class="btn btn-secondary mt-2" onclick="addItem()">+ เพิ่มรายการ</button>

            <div class="mt-4">
                <label class="form-label">หมายเหตุ</label>
                <textarea name="note" class="form-control"></textarea>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-success">บันทึกรับสินค้า</button>
            </div>
        </form>
    </div>
</div>

<script>
const products = <?= json_encode($productList) ?>;

function productOptions() {
    return products.map(p =>
        `<option value="${p.product_id}">
            ${p.product_code} - ${p.product_name}
        </option>`
    ).join('');
}

function addItem() {
    const div = document.createElement('div');
    div.className = 'row mb-2 item-row';
    div.innerHTML = `
        <div class="col-md-4">
            <select name="products[]" class="form-select" required>
                <option value="">เลือกสินค้า</option>
                ${productOptions()}
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" name="quantities[]" class="form-control" min="1" required>
        </div>
        <div class="col-md-2">
            <input type="text" name="lot_numbers[]" class="form-control">
        </div>
        <div class="col-md-3">
            <input type="date" name="expiry_dates[]" class="form-control">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger" onclick="this.closest('.item-row').remove()">×</button>
        </div>
    `;
    document.getElementById('itemsContainer').appendChild(div);
}

addItem();
</script>

<?php include 'includes/footer.php'; ?>
