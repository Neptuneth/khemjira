<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'รับสินค้าเข้า';
include 'includes/header.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receipt_date = $_POST['receipt_date'] ?? date('Y-m-d');
    $supplier_id  = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
    $note         = $_POST['note'] ?? '';
    $user_id      = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO goods_receipt (receipt_date, supplier_id, user_id, note) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $receipt_date, $supplier_id, $user_id, $note);
        $stmt->execute();
        $receipt_id = $stmt->insert_id;

        if (!empty($_POST['products']) && is_array($_POST['products'])) {
            $stmtItem  = $conn->prepare("INSERT INTO goods_receipt_items (receipt_id, product_id, quantity, lot_number, expiry_date) VALUES (?, ?, ?, ?, ?)");
            $stmtStock = $conn->prepare("INSERT INTO inventory (product_id, quantity) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            $stmtMove  = $conn->prepare("INSERT INTO stock_movement (product_id, movement_type, quantity, reference_type, reference_id, note) VALUES (?, 'in', ?, 'receipt', ?, 'รับสินค้าเข้า')");

            foreach ($_POST['products'] as $i => $product_id) {
                if (empty($product_id) || empty($_POST['quantities'][$i])) continue;
                $product_id  = (int)$product_id;
                $quantity    = (int)$_POST['quantities'][$i];
                $lot_number  = $_POST['lot_numbers'][$i] ?? '';
                $expiry_date = !empty($_POST['expiry_dates'][$i]) ? $_POST['expiry_dates'][$i] : null;

                $stmtItem->bind_param("iiiss", $receipt_id, $product_id, $quantity, $lot_number, $expiry_date);
                $stmtItem->execute();
                $stmtStock->bind_param("ii", $product_id, $quantity);
                $stmtStock->execute();
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

$suppliers    = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name");
$productsQ    = $conn->query("SELECT * FROM products ORDER BY product_name");
$productList  = $productsQ->fetch_all(MYSQLI_ASSOC);

$recentReceipts = $conn->query("
    SELECT gr.*, s.supplier_name, u.full_name,
           COUNT(gri.item_id) as item_count,
           SUM(gri.quantity) as total_qty
    FROM goods_receipt gr
    LEFT JOIN suppliers s ON gr.supplier_id = s.supplier_id
    LEFT JOIN users u ON gr.user_id = u.user_id
    LEFT JOIN goods_receipt_items gri ON gr.receipt_id = gri.receipt_id
    GROUP BY gr.receipt_id
    ORDER BY gr.created_at DESC
    LIMIT 8
");
?>

<style>
/* ===== Page Header ===== */
.page-header-custom {
    background: #fff; border-radius: 16px; padding: 22px 26px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; gap: 16px;
}
.page-header-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, #43e97b, #38f9d7);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}

/* ===== Form Card ===== */
.form-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    overflow: hidden; margin-bottom: 22px;
}
.form-card-header {
    padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
    font-weight: 700; font-size: 15px;
    display: flex; align-items: center; gap: 10px;
}
.form-card-header .accent { width: 5px; height: 20px; border-radius: 3px; display: inline-block; }
.form-card-body { padding: 24px; }

/* ===== Form Controls ===== */
.form-control, .form-select {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 10px 14px; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: #43e97b; box-shadow: 0 0 0 4px rgba(67,233,123,0.12);
}
.form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }

/* ===== Item Rows ===== */
.item-row {
    background: #f8fafc; border-radius: 14px; padding: 16px;
    margin-bottom: 12px; border: 2px solid #e2e8f0;
    position: relative; transition: border-color 0.2s, box-shadow 0.2s;
    animation: slideDown 0.25s ease;
}
.item-row:hover { border-color: #a7f3d0; box-shadow: 0 4px 12px rgba(67,233,123,0.08); }
.row-number {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #43e97b, #38f9d7);
    color: #fff; font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.col-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }

/* ===== Remove Button ===== */
.btn-remove {
    width: 36px; height: 36px; border-radius: 50%;
    border: none; background: #fee2e2; color: #ef4444;
    font-size: 14px; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; flex-shrink: 0; cursor: pointer;
}
.btn-remove:hover { background: #ef4444; color: #fff; transform: rotate(90deg); }

/* ===== Add Row Button ===== */
.btn-add-row {
    border: 2px dashed #a7f3d0; background: transparent; color: #059669;
    border-radius: 12px; padding: 10px 20px;
    font-weight: 600; font-size: 14px;
    transition: all 0.2s; width: 100%; margin-top: 4px;
}
.btn-add-row:hover { background: #f0fdf4; border-color: #43e97b; }

/* ===== Summary Box ===== */
.summary-box {
    background: linear-gradient(135deg, #43e97b, #38f9d7);
    border-radius: 14px; padding: 18px 22px; color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 16px;
}
.summary-box .s-num { font-size: 2.2rem; font-weight: 800; line-height: 1; }
.summary-box .s-label { opacity: 0.85; font-size: 13px; }

/* ===== Submit Button ===== */
.btn-submit {
    background: linear-gradient(135deg, #43e97b, #38f9d7);
    border: none; color: #fff; border-radius: 12px;
    padding: 12px 32px; font-weight: 700; font-size: 15px;
    box-shadow: 0 4px 15px rgba(67,233,123,0.4);
    transition: all 0.2s;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(67,233,123,0.45); color: #fff; }

/* ===== Recent Receipts Table ===== */
.rpt-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07); overflow: hidden;
}
.rpt-card-header {
    padding: 16px 22px; border-bottom: 1px solid #f1f5f9;
    font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 10px;
}
.rpt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rpt-table thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    padding: 11px 16px; border-bottom: 2px solid #e2e8f0;
}
.rpt-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.rpt-table tbody tr:hover { background: #f8fafc; }
.rpt-table tbody td { padding: 11px 16px; vertical-align: middle; }
.rpt-table tbody tr:last-child { border-bottom: none; }

.badge-in { background: #e8f5e9; color: #2e7d32; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.empty-state { text-align: center; padding: 40px; color: #94a3b8; }

/* ===== Animations ===== */
.fade-in { opacity: 0; animation: fadeUp 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.10s; }
.fade-in-3 { animation-delay: 0.15s; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

.row-count-badge { background: #eef2ff; color: #667eea; border-radius: 8px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
</style>

<!-- ===== Page Header ===== -->
<div class="page-header-custom fade-in fade-in-1">
    <div class="page-header-icon"><i class="fas fa-truck-loading"></i></div>
    <div>
        <h4 class="mb-0 fw-bold">รับสินค้าเข้าคลัง</h4>
        <p class="text-muted mb-0" style="font-size:13px;">บันทึกการรับสินค้าจากซัพพลายเออร์</p>
    </div>
</div>

<!-- ===== Info Form ===== -->
<div class="form-card fade-in fade-in-2">
    <div class="form-card-header">
        <span class="accent" style="background:linear-gradient(135deg,#43e97b,#38f9d7);"></span>
        ข้อมูลการรับสินค้า
    </div>
    <div class="form-card-body">
        <form method="POST" id="receiveForm">
            <div class="row g-3 mb-0">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar-alt me-1 text-success"></i>วันที่รับสินค้า</label>
                    <input type="date" name="receipt_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label"><i class="fas fa-truck me-1 text-success"></i>ซัพพลายเออร์</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">— ไม่ระบุซัพพลายเออร์ —</option>
                        <?php while ($s = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $s['supplier_id'] ?>"><?= htmlspecialchars($s['supplier_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ===== Items ===== -->
<div class="form-card fade-in fade-in-3">
    <div class="form-card-header">
        <span class="accent" style="background:linear-gradient(135deg,#43e97b,#38f9d7);"></span>
        รายการสินค้าที่รับเข้า
        <span class="ms-auto row-count-badge" id="rowCount">0 รายการ</span>
    </div>
    <div class="form-card-body">

        <!-- Column Headers -->
        <div class="row mb-2 px-1 d-none d-md-flex">
            <div class="col-md-1"></div>
            <div class="col-md-4"><div class="col-label">สินค้า</div></div>
            <div class="col-md-2"><div class="col-label">จำนวน</div></div>
            <div class="col-md-2"><div class="col-label">Lot Number</div></div>
            <div class="col-md-2"><div class="col-label">วันหมดอายุ</div></div>
            <div class="col-md-1"></div>
        </div>

        <div id="itemsContainer">
            <div class="empty-state" id="emptyState">
                <i class="fas fa-box-open fa-2x d-block mb-2"></i>
                ยังไม่มีรายการ กดปุ่มด้านล่างเพื่อเพิ่มสินค้า
            </div>
        </div>

        <button type="button" class="btn-add-row mt-2" onclick="addItem()">
            <i class="fas fa-plus me-2"></i>เพิ่มรายการสินค้า
        </button>

        <!-- Summary -->
        <div id="summaryBox" class="summary-box" style="display:none;">
            <div>
                <div class="s-label"><i class="fas fa-boxes me-1"></i>จำนวนรวมทั้งหมด</div>
                <div class="s-num mt-1"><span id="totalQty">0</span> <small style="font-size:1rem;font-weight:400;">ชิ้น</small></div>
            </div>
            <div class="text-end">
                <div class="s-label">จำนวนสินค้า</div>
                <div class="s-num mt-1"><span id="totalTypes">0</span> <small style="font-size:1rem;font-weight:400;">ประเภท</small></div>
            </div>
        </div>

        <!-- Note -->
        <div class="mt-4">
            <label class="form-label"><i class="fas fa-sticky-note me-1 text-success"></i>หมายเหตุ (ถ้ามี)</label>
            <textarea name="note" form="receiveForm" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" form="receiveForm" class="btn-submit">
                <i class="fas fa-save me-2"></i>บันทึกรับสินค้า
            </button>
        </div>

    </div>
</div>

<!-- ===== Recent Receipts ===== -->
<div class="rpt-card fade-in fade-in-3">
    <div class="rpt-card-header">
        <span style="width:5px;height:20px;border-radius:3px;background:linear-gradient(135deg,#43e97b,#38f9d7);display:inline-block;"></span>
        <i class="fas fa-history text-success"></i>
        รายการรับสินค้าล่าสุด
    </div>
    <?php if ($recentReceipts && $recentReceipts->num_rows > 0): ?>
    <table class="rpt-table">
        <thead>
            <tr>
                <th>วันที่</th>
                <th>ซัพพลายเออร์</th>
                <th>ผู้รับ</th>
                <th class="text-center">รายการ</th>
                <th class="text-center">จำนวนรวม</th>
                <th class="text-center">ดูรายละเอียด</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($r = $recentReceipts->fetch_assoc()): ?>
            <tr>
                <td style="white-space:nowrap;">
                    <i class="fas fa-calendar-alt text-muted me-1" style="font-size:11px;"></i>
                    <?= date('d/m/Y', strtotime($r['receipt_date'])) ?>
                </td>
                <td style="font-size:13px;"><?= htmlspecialchars($r['supplier_name'] ?? '-') ?></td>
                <td style="font-size:13px;color:#64748b;"><?= htmlspecialchars($r['full_name'] ?? '-') ?></td>
                <td class="text-center">
                    <span style="background:#eef2ff;color:#667eea;border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;">
                        <?= $r['item_count'] ?> ประเภท
                    </span>
                </td>
                <td class="text-center">
                    <span class="badge-in">+<?= number_format($r['total_qty'] ?? 0) ?> ชิ้น</span>
                </td>
                <td class="text-center">
                    <a href="receipt_detail.php?id=<?= $r['receipt_id'] ?>"
                       style="background:#eef2ff;color:#667eea;border-radius:8px;padding:5px 12px;font-size:12px;font-weight:700;text-decoration:none;transition:all 0.2s;"
                       onmouseover="this.style.background='#667eea';this.style.color='#fff';"
                       onmouseout="this.style.background='#eef2ff';this.style.color='#667eea';">
                        <i class="fas fa-eye me-1"></i>ดู
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-inbox fa-2x d-block mb-2"></i>ยังไม่มีประวัติการรับสินค้า</div>
    <?php endif; ?>
</div>

<script>
const products = <?= json_encode($productList) ?>;
let rowIndex = 0;

function addItem() {
    document.getElementById('emptyState').style.display = 'none';
    rowIndex++;
    const idx = rowIndex;

    const div = document.createElement('div');
    div.className = 'item-row';
    div.dataset.idx = idx;

    div.innerHTML = `
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="row-number">${idx}</div>
            <div class="flex-grow-1" style="min-width:180px;">
                <div class="col-label">สินค้า</div>
                <select name="products[]" class="form-select" required onchange="calcTotal()">
                    <option value="">— เลือกสินค้า —</option>
                    ${products.map(p => `<option value="${p.product_id}">${p.product_code} - ${p.product_name}</option>`).join('')}
                </select>
            </div>
            <div style="width:110px;">
                <div class="col-label">จำนวน</div>
                <input type="number" name="quantities[]" class="form-control qty"
                       min="1" placeholder="0" required oninput="calcTotal()">
            </div>
            <div style="width:130px;">
                <div class="col-label">Lot Number</div>
                <input type="text" name="lot_numbers[]" class="form-control" placeholder="(ถ้ามี)">
            </div>
            <div style="width:150px;">
                <div class="col-label">วันหมดอายุ</div>
                <input type="date" name="expiry_dates[]" class="form-control">
            </div>
            <div class="mt-auto" style="padding-top:18px;">
                <button type="button" class="btn-remove" onclick="removeRow(this)" title="ลบ">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `;

    document.getElementById('itemsContainer').appendChild(div);
    updateNumbers();
    calcTotal();
}

function removeRow(btn) {
    const row = btn.closest('.item-row');
    row.style.animation = 'slideDown 0.2s ease reverse';
    setTimeout(() => {
        row.remove();
        updateNumbers();
        calcTotal();
        if (document.querySelectorAll('.item-row').length === 0) {
            document.getElementById('emptyState').style.display = '';
        }
    }, 180);
}

function updateNumbers() {
    document.querySelectorAll('.item-row .row-number').forEach((el, i) => {
        el.textContent = i + 1;
    });
    document.getElementById('rowCount').textContent = document.querySelectorAll('.item-row').length + ' รายการ';
}

function calcTotal() {
    let total = 0, types = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const sel = row.querySelector('[name="products[]"]');
        const qty = parseInt(row.querySelector('.qty')?.value || 0);
        if (sel?.value && qty > 0) { total += qty; types++; }
    });
    document.getElementById('totalQty').textContent   = total.toLocaleString('th-TH');
    document.getElementById('totalTypes').textContent = types;
    document.getElementById('summaryBox').style.display = types > 0 ? 'flex' : 'none';
}

addItem();
</script>

<?php include 'includes/footer.php'; ?>