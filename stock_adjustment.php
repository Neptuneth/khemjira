<?php
require_once 'config.php';
requireLogin();
$pageTitle = 'ปรับแก้สต็อก';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id      = (int)$_POST['product_id'];
    $adjustment_type = $_POST['adjustment_type'];
    $quantity        = (int)$_POST['quantity'];
    $reason          = clean($_POST['reason']);
    $note            = clean($_POST['note']);
    $user_id         = $_SESSION['user_id'];

    $conn->begin_transaction();
    try {
        $result      = $conn->query("SELECT quantity FROM inventory WHERE product_id = $product_id");
        $current     = $result->fetch_assoc();
        $current_qty = $current['quantity'] ?? 0;

        if ($adjustment_type == 'add') {
            $new_qty = $current_qty + $quantity; $movement_type = 'in';
        } else {
            if ($current_qty < $quantity) throw new Exception("สต็อกไม่เพียงพอ (คงเหลือ: $current_qty)");
            $new_qty = $current_qty - $quantity; $movement_type = 'out';
        }

        $conn->query("UPDATE inventory SET quantity = $new_qty WHERE product_id = $product_id");
        $full_note = "ปรับแก้สต็อก: $reason" . (!empty($note) ? " - $note" : "");
        $conn->query("INSERT INTO stock_movement (product_id, movement_type, quantity, reference_type, note)
                      VALUES ($product_id, '$movement_type', $quantity, 'adjustment', '$full_note')");

        $conn->commit();
        setAlert('success', 'ปรับแก้สต็อกเรียบร้อยแล้ว');
        redirect('stock_adjustment.php');
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

$products = $conn->query("
    SELECT p.*, COALESCE(i.quantity,0) as quantity, c.category_name
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_name
");
$productList = $products->fetch_all(MYSQLI_ASSOC);

$history = $conn->query("
    SELECT sm.*, p.product_code, p.product_name, p.unit
    FROM stock_movement sm
    JOIN products p ON sm.product_id = p.product_id
    WHERE sm.reference_type = 'adjustment'
    ORDER BY sm.created_at DESC
    LIMIT 20
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
    background: linear-gradient(135deg, #f6d365, #fda085);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}

/* ===== Cards ===== */
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
    border-color: #f6d365; box-shadow: 0 0 0 4px rgba(246,211,101,0.15);
}
.form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }

/* ===== Type Toggle ===== */
.type-toggle { display: flex; gap: 12px; }
.type-btn {
    flex: 1; padding: 14px; border-radius: 12px; border: 2px solid #e2e8f0;
    background: #fff; cursor: pointer; text-align: center;
    transition: all 0.2s; font-weight: 700; font-size: 14px;
}
.type-btn:hover { border-color: #94a3b8; }
.type-btn.active-add  { border-color: #22c55e; background: #f0fdf4; color: #16a34a; }
.type-btn.active-reduce { border-color: #ef4444; background: #fef2f2; color: #dc2626; }
.type-btn .type-icon { font-size: 24px; display: block; margin-bottom: 6px; }

/* ===== Stock Info Box ===== */
.stock-info {
    border-radius: 12px; padding: 14px 18px;
    border: 2px solid #e2e8f0; margin-top: 10px;
    display: flex; align-items: center; gap: 14px; transition: all 0.3s;
}
.stock-info.has-stock { border-color: #a7f3d0; background: #f0fdf4; }
.stock-info .s-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.stock-num { font-size: 1.5rem; font-weight: 800; line-height: 1; }

/* ===== Preview Box ===== */
.preview-box {
    border-radius: 12px; padding: 16px 20px;
    background: linear-gradient(135deg, #f6d365, #fda085);
    color: #fff; margin-top: 16px; display: none;
}
.preview-box .arrow { font-size: 1.4rem; font-weight: 800; }
.preview-num { font-size: 1.8rem; font-weight: 800; line-height: 1; }

/* ===== Warning Box ===== */
.warning-box {
    background: #fffbeb; border: 2px solid #fde68a; border-radius: 12px;
    padding: 14px 18px; margin-bottom: 20px;
    display: flex; align-items: flex-start; gap: 12px;
}
.warning-box i { color: #f59e0b; margin-top: 2px; flex-shrink: 0; }

/* ===== Buttons ===== */
.btn-submit {
    background: linear-gradient(135deg, #f6d365, #fda085);
    border: none; color: #fff; border-radius: 12px;
    padding: 12px 32px; font-weight: 700; font-size: 15px;
    box-shadow: 0 4px 15px rgba(246,211,101,0.4);
    transition: all 0.2s;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(246,211,101,0.45); color: #fff; }
.btn-cancel {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 12px; padding: 11px 24px; font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.btn-cancel:hover { border-color: #94a3b8; }

/* ===== History Table ===== */
.rpt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rpt-table thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.rpt-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.rpt-table tbody tr:hover { background: #f8fafc; }
.rpt-table tbody td { padding: 11px 16px; vertical-align: middle; }
.rpt-table tbody tr:last-child { border-bottom: none; }

.product-code { background: #eef2ff; color: #667eea; border-radius: 7px; padding: 3px 9px; font-size: 12px; font-weight: 700; font-family: monospace; }
.badge-add    { background: #e8f5e9; color: #2e7d32; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.badge-reduce { background: #ffebee; color: #c62828; border-radius: 8px; padding: 4px 10px; font-size: 12px; font-weight: 700; }
.empty-state  { text-align: center; padding: 40px; color: #94a3b8; }

/* ===== Animations ===== */
.fade-in { opacity: 0; animation: fadeUp 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.10s; }
.fade-in-3 { animation-delay: 0.15s; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
</style>

<!-- ===== Header ===== -->
<div class="page-header-custom fade-in fade-in-1">
    <div class="page-header-icon"><i class="fas fa-sliders-h"></i></div>
    <div>
        <h4 class="mb-0 fw-bold">ปรับแก้สต็อกสินค้า</h4>
        <p class="text-muted mb-0" style="font-size:13px;">ปรับเพิ่ม/ลดสต็อกเมื่อมีความผิดพลาดหรือสินค้าเสียหาย</p>
    </div>
</div>

<?php showAlert(); ?>

<!-- ===== Form ===== -->
<div class="form-card fade-in fade-in-2">
    <div class="form-card-header">
        <span class="accent" style="background:linear-gradient(135deg,#f6d365,#fda085);"></span>
        <i class="fas fa-edit" style="color:#f59e0b;"></i>
        บันทึกการปรับแก้สต็อก
    </div>
    <div class="form-card-body">
        <form method="POST" id="adjustmentForm">
            <input type="hidden" name="adjustment_type" id="adjustmentTypeInput" value="add">

            <!-- ประเภทการปรับแก้ -->
            <div class="mb-4">
                <label class="form-label"><i class="fas fa-exchange-alt me-1 text-warning"></i>ประเภทการปรับแก้</label>
                <div class="type-toggle">
                    <div class="type-btn active-add" id="btnAdd" onclick="setType('add')">
                        <span class="type-icon">➕</span>
                        เพิ่มสต็อก
                    </div>
                    <div class="type-btn" id="btnReduce" onclick="setType('reduce')">
                        <span class="type-icon">➖</span>
                        ลดสต็อก
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- เลือกสินค้า -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-box me-1 text-warning"></i>เลือกสินค้า <span class="text-danger">*</span></label>
                    <select name="product_id" id="productSelect" class="form-select" required onchange="onProductChange()">
                        <option value="">— เลือกสินค้า —</option>
                        <?php foreach ($productList as $p): ?>
                            <option value="<?= $p['product_id'] ?>"
                                    data-stock="<?= $p['quantity'] ?>"
                                    data-unit="<?= htmlspecialchars($p['unit']) ?>"
                                    data-name="<?= htmlspecialchars($p['product_name']) ?>">
                                <?= htmlspecialchars($p['product_code']) ?> - <?= htmlspecialchars($p['product_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Stock Info -->
                    <div class="stock-info" id="stockInfo">
                        <div class="s-icon" style="background:#f1f5f9;color:#94a3b8;">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#94a3b8;font-weight:600;">สต็อกปัจจุบัน</div>
                            <div class="stock-num" id="stockNum" style="color:#94a3b8;">—</div>
                        </div>
                    </div>
                </div>

                <!-- จำนวน -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-sort-numeric-up me-1 text-warning"></i>จำนวนที่ปรับแก้ <span class="text-danger">*</span></label>
                    <input type="number" name="quantity" id="qtyInput" class="form-control" required min="1" placeholder="ระบุจำนวน" oninput="updatePreview()">

                    <!-- Preview -->
                    <div class="preview-box" id="previewBox">
                        <div style="font-size:12px;opacity:0.85;margin-bottom:6px;">สต็อกหลังปรับแก้</div>
                        <div class="d-flex align-items-center gap-3">
                            <div>
                                <div style="font-size:11px;opacity:0.8;">ก่อน</div>
                                <div class="preview-num" id="prevBefore">0</div>
                            </div>
                            <div class="arrow">→</div>
                            <div>
                                <div style="font-size:11px;opacity:0.8;">หลัง</div>
                                <div class="preview-num" id="prevAfter">0</div>
                            </div>
                            <div style="font-size:13px;opacity:0.85;" id="prevUnit"></div>
                        </div>
                    </div>
                </div>

                <!-- เหตุผล -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-clipboard-list me-1 text-warning"></i>เหตุผล <span class="text-danger">*</span></label>
                    <select name="reason" class="form-select" required>
                        <option value="">— เลือกเหตุผล —</option>
                        <option value="นับสต็อกไม่ตรง">นับสต็อกไม่ตรง</option>
                        <option value="สินค้าเสียหาย">สินค้าเสียหาย</option>
                        <option value="สินค้าหาย">สินค้าหาย</option>
                        <option value="สินค้าหมดอายุ">สินค้าหมดอายุ</option>
                        <option value="ได้รับสินค้าเพิ่มเติม">ได้รับสินค้าเพิ่มเติม</option>
                        <option value="อื่นๆ">อื่นๆ</option>
                    </select>
                </div>

                <!-- หมายเหตุ -->
                <div class="col-md-6">
                    <label class="form-label"><i class="fas fa-sticky-note me-1 text-warning"></i>หมายเหตุเพิ่มเติม</label>
                    <textarea name="note" class="form-control" rows="1" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
                </div>
            </div>

            <!-- Warning -->
            <div class="warning-box mt-4">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>คำเตือน:</strong> การปรับแก้สต็อกจะมีผลกับข้อมูลในระบบทันที กรุณาตรวจสอบความถูกต้องก่อนบันทึก
                </div>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i>บันทึกการปรับแก้
                </button>
                <button type="reset" class="btn-cancel" onclick="resetForm()">
                    <i class="fas fa-redo me-1"></i>ล้างข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== History ===== -->
<div class="form-card fade-in fade-in-3">
    <div class="form-card-header">
        <span class="accent" style="background:linear-gradient(135deg,#667eea,#764ba2);"></span>
        <i class="fas fa-history" style="color:#667eea;"></i>
        ประวัติการปรับแก้สต็อก
        <span style="background:#eef2ff;color:#667eea;border-radius:8px;padding:3px 10px;font-size:12px;font-weight:700;margin-left:4px;">20 รายการล่าสุด</span>
    </div>
    <div class="table-responsive">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>วันที่-เวลา</th>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th class="text-center">ประเภท</th>
                    <th class="text-center">จำนวน</th>
                    <th>เหตุผล/หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($history && $history->num_rows > 0): ?>
                <?php while ($row = $history->fetch_assoc()): ?>
                <tr>
                    <td style="white-space:nowrap;font-size:12px;color:#64748b;">
                        <i class="fas fa-clock me-1"></i>
                        <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                    </td>
                    <td><span class="product-code"><?= htmlspecialchars($row['product_code']) ?></span></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="text-center">
                        <?php if ($row['movement_type'] == 'in'): ?>
                            <span class="badge-add"><i class="fas fa-plus me-1"></i>เพิ่ม</span>
                        <?php else: ?>
                            <span class="badge-reduce"><i class="fas fa-minus me-1"></i>ลด</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="font-weight:700;">
                        <?= ($row['movement_type']=='in' ? '+' : '-') . number_format($row['quantity']) ?>
                        <small style="font-weight:400;color:#94a3b8;"><?= htmlspecialchars($row['unit']) ?></small>
                    </td>
                    <td style="font-size:12px;color:#64748b;max-width:220px;">
                        <?= htmlspecialchars($row['note']) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <i class="fas fa-history fa-2x d-block mb-2"></i>ยังไม่มีประวัติการปรับแก้สต็อก
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const productData = <?= json_encode($productList) ?>;
let currentStock = 0;
let currentUnit  = '';
let currentType  = 'add';

function setType(type) {
    currentType = type;
    document.getElementById('adjustmentTypeInput').value = type;
    document.getElementById('btnAdd').className    = 'type-btn' + (type === 'add'    ? ' active-add'    : '');
    document.getElementById('btnReduce').className = 'type-btn' + (type === 'reduce' ? ' active-reduce' : '');
    updatePreview();
}

function onProductChange() {
    const sel  = document.getElementById('productSelect');
    const opt  = sel.options[sel.selectedIndex];
    const info = document.getElementById('stockInfo');
    const num  = document.getElementById('stockNum');

    if (sel.value) {
        currentStock = parseInt(opt.getAttribute('data-stock')) || 0;
        currentUnit  = opt.getAttribute('data-unit') || '';

        let color = '#2e7d32', bg = '#e8f5e9';
        if (currentStock == 0)          { color = '#c62828'; bg = '#ffebee'; }
        else if (currentStock <= 10)    { color = '#f57c00'; bg = '#fff3e0'; }

        info.className = 'stock-info has-stock';
        info.querySelector('.s-icon').style.cssText = `background:${bg};color:${color};width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;`;
        num.style.color = color;
        num.textContent = currentStock.toLocaleString('th-TH') + ' ' + currentUnit;
    } else {
        currentStock = 0; currentUnit = '';
        info.className = 'stock-info';
        num.style.color = '#94a3b8';
        num.textContent = '—';
    }
    updatePreview();
}

function updatePreview() {
    const qty = parseInt(document.getElementById('qtyInput').value) || 0;
    const box = document.getElementById('previewBox');

    if (qty > 0 && document.getElementById('productSelect').value) {
        const after = currentType === 'add' ? currentStock + qty : currentStock - qty;
        document.getElementById('prevBefore').textContent = currentStock.toLocaleString('th-TH');
        document.getElementById('prevAfter').textContent  = after.toLocaleString('th-TH');
        document.getElementById('prevUnit').textContent   = currentUnit;
        box.style.display = 'block';
        box.style.background = currentType === 'add'
            ? 'linear-gradient(135deg,#43e97b,#38f9d7)'
            : 'linear-gradient(135deg,#f093fb,#f5576c)';
    } else {
        box.style.display = 'none';
    }
}

function resetForm() {
    currentStock = 0; currentUnit = '';
    document.getElementById('stockInfo').className = 'stock-info';
    document.getElementById('stockNum').style.color = '#94a3b8';
    document.getElementById('stockNum').textContent = '—';
    document.getElementById('previewBox').style.display = 'none';
    setType('add');
}

document.getElementById('adjustmentForm').addEventListener('submit', function(e) {
    const qty  = parseInt(document.getElementById('qtyInput').value) || 0;
    const type = currentType === 'add' ? 'เพิ่ม' : 'ลด';
    const after = currentType === 'add' ? currentStock + qty : currentStock - qty;
    if (!confirm(`ยืนยัน${type}สต็อก ${qty.toLocaleString()} ${currentUnit}\nสต็อกจะเปลี่ยนเป็น ${after.toLocaleString()} ${currentUnit}`)) {
        e.preventDefault();
    }
});
</script>

<?php include 'includes/footer.php'; ?>