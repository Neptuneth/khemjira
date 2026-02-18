<?php
require_once 'config.php';
requireLogin();
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
        foreach ($_POST['products'] as $i => $product_id) {
            $product_id = (int)$product_id;
            $qty = (int)$_POST['quantities'][$i];
            $q = $conn->query("SELECT quantity FROM inventory WHERE product_id = $product_id");
            $stock = $q->fetch_assoc();
            if ($qty > $stock['quantity']) throw new Exception('มีสินค้าบางรายการเบิกเกินสต็อก');
        }

        $conn->query("INSERT INTO goods_issue (issue_date, user_id, purpose, note) VALUES ('$issue_date', $user_id, '$purpose', '$note')");
        $issue_id = $conn->insert_id;

        foreach ($_POST['products'] as $i => $product_id) {
            $product_id = (int)$product_id;
            $qty = (int)$_POST['quantities'][$i];
            $conn->query("INSERT INTO goods_issue_items (issue_id, product_id, quantity) VALUES ($issue_id, $product_id, $qty)");
            $conn->query("UPDATE inventory SET quantity = quantity - $qty WHERE product_id = $product_id");
            $conn->query("INSERT INTO stock_movement (product_id, movement_type, quantity, reference_type, reference_id, note) VALUES ($product_id,'out',$qty,'issue',$issue_id,'เบิกสินค้า')");
        }

        $conn->commit();
        redirect("issue_detail.php?id=$issue_id");
    } catch (Exception $e) {
        $conn->rollback();
        setAlert('danger', $e->getMessage());
    }
}

$products = $conn->query("
    SELECT p.product_id, p.product_code, p.product_name, p.unit,
           IFNULL(i.quantity,0) AS stock
    FROM products p
    LEFT JOIN inventory i ON p.product_id = i.product_id
    ORDER BY p.product_name
");
?>

<style>
.page-header-custom {
    background: #fff;
    border-radius: 16px;
    padding: 22px 26px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; gap: 16px;
}
.page-header-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg, #f093fb, #f5576c);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}

/* ===== Form Card ===== */
.form-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    overflow: hidden;
    margin-bottom: 22px;
}
.form-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid #f1f5f9;
    font-weight: 700; font-size: 15px;
    display: flex; align-items: center; gap: 10px;
}
.form-card-body { padding: 24px; }

/* ===== Form controls ===== */
.form-control, .form-select {
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    padding: 10px 14px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102,126,234,0.12);
}
.form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }

/* ===== Item Rows ===== */
.item-row {
    background: #f8fafc;
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 12px;
    border: 2px solid #e2e8f0;
    position: relative;
    transition: border-color 0.2s, box-shadow 0.2s;
    animation: slideDown 0.25s ease;
}
.item-row:hover { border-color: #c7d2fe; box-shadow: 0 4px 12px rgba(102,126,234,0.08); }
.item-row.has-error { border-color: #fca5a5; background: #fff5f5; }

.row-number {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff; font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

/* ===== Stock Bar ===== */
.stock-bar-wrap {
    height: 6px; background: #e2e8f0; border-radius: 99px;
    margin-top: 8px; overflow: hidden; display: none;
}
.stock-bar-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, #43e97b, #38f9d7);
    transition: width 0.4s ease, background 0.3s;
}

/* ===== Summary ===== */
.summary-box {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 14px; padding: 18px 22px; color: #fff;
    display: flex; align-items: center; justify-content: space-between;
}
.summary-box .s-num { font-size: 2rem; font-weight: 800; line-height: 1; }
.summary-box .s-label { opacity: 0.85; font-size: 13px; }
.summary-products { font-size: 13px; opacity: 0.9; }

/* ===== Buttons ===== */
.btn-add-row {
    border: 2px dashed #c7d2fe;
    background: transparent; color: #667eea;
    border-radius: 12px; padding: 10px 20px;
    font-weight: 600; font-size: 14px;
    transition: all 0.2s; width: 100%;
}
.btn-add-row:hover { background: #eef2ff; border-color: #667eea; }

.btn-remove {
    width: 36px; height: 36px; border-radius: 50%;
    border: none; background: #fee2e2; color: #ef4444;
    font-size: 14px; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s; flex-shrink: 0; cursor: pointer;
}
.btn-remove:hover { background: #ef4444; color: #fff; transform: rotate(90deg); }

.btn-submit {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 12px;
    padding: 12px 32px; font-weight: 700; font-size: 15px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    transition: all 0.2s;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.45); color: #fff; }
.btn-submit:disabled { opacity: 0.6; transform: none; }

.empty-state {
    text-align: center; padding: 32px;
    color: #94a3b8;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ===== Fade in ===== */
.fade-in { opacity: 0; animation: fadeSlideIn 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.1s; }
.fade-in-3 { animation-delay: 0.15s; }
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Page Header -->
<div class="page-header-custom fade-in fade-in-1">
    <div class="page-header-icon"><i class="fas fa-dolly"></i></div>
    <div>
        <h4 class="mb-0 fw-bold">เบิกสินค้าออก</h4>
        <p class="text-muted mb-0" style="font-size:13px;">บันทึกการเบิกสินค้าออกจากคลังสินค้า</p>
    </div>
</div>

<!-- Info Form -->
<div class="form-card fade-in fade-in-2">
    <div class="form-card-header">
        <span style="width:5px;height:20px;border-radius:3px;background:linear-gradient(135deg,#667eea,#764ba2);display:inline-block;"></span>
        ข้อมูลการเบิก
    </div>
    <div class="form-card-body">
        <form method="post" id="issueForm">
            <div class="row g-3 mb-2">
                <div class="col-md-3">
                    <label class="form-label"><i class="fas fa-calendar-alt me-1 text-primary"></i>วันที่เบิก</label>
                    <input type="date" name="issue_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label"><i class="fas fa-clipboard me-1 text-primary"></i>วัตถุประสงค์</label>
                    <input type="text" name="purpose" class="form-control" required placeholder="ระบุวัตถุประสงค์การเบิก...">
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label"><i class="fas fa-sticky-note me-1 text-primary"></i>หมายเหตุ (ถ้ามี)</label>
                <textarea name="note" class="form-control" rows="2" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
            </div>
        </form>
    </div>
</div>

<!-- Items -->
<div class="form-card fade-in fade-in-3">
    <div class="form-card-header">
        <span style="width:5px;height:20px;border-radius:3px;background:linear-gradient(135deg,#f093fb,#f5576c);display:inline-block;"></span>
        รายการสินค้าที่เบิก
        <span class="ms-auto badge rounded-pill" style="background:#eef2ff;color:#667eea;font-size:12px;" id="rowCount">0 รายการ</span>
    </div>
    <div class="form-card-body">

        <div id="items">
            <div class="empty-state" id="emptyState">
                <i class="fas fa-box-open fa-2x mb-2 d-block"></i>
                ยังไม่มีรายการ กดปุ่มด้านล่างเพื่อเพิ่มสินค้า
            </div>
        </div>

        <button type="button" class="btn-add-row mt-2" onclick="addRow()">
            <i class="fas fa-plus me-2"></i>เพิ่มสินค้า
        </button>

        <!-- Summary -->
        <div class="summary-box mt-4" id="summaryBox" style="display:none;">
            <div>
                <div class="s-label"><i class="fas fa-boxes me-1"></i>จำนวนรวมทั้งหมด</div>
                <div class="s-num mt-1"><span id="totalQty">0</span> <small style="font-size:1rem;font-weight:400;">ชิ้น</small></div>
            </div>
            <div class="text-end">
                <div class="s-label">จำนวนสินค้า</div>
                <div class="s-num mt-1"><span id="totalProducts">0</span> <small style="font-size:1rem;font-weight:400;">ประเภท</small></div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" form="issueForm" class="btn-submit" id="submitBtn">
                <i class="fas fa-save me-2"></i>บันทึกเบิกสินค้า
            </button>
        </div>

    </div>
</div>

<script>
const products = <?= json_encode($products->fetch_all(MYSQLI_ASSOC)) ?>;
let rowIndex = 0;

function addRow() {
    document.getElementById('emptyState').style.display = 'none';
    rowIndex++;
    const idx = rowIndex;

    const row = document.createElement('div');
    row.className = 'item-row';
    row.dataset.idx = idx;

    row.innerHTML = `
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="row-number">${idx}</div>
            <div class="flex-grow-1" style="min-width:200px;">
                <label class="form-label mb-1">สินค้า</label>
                <select class="form-select product" name="products[]" onchange="onProductChange(this)" required>
                    <option value="">— เลือกสินค้า —</option>
                    ${products.map(p =>
                        `<option value="${p.product_id}" data-stock="${p.stock}" data-unit="${p.unit}">
                            ${p.product_code} - ${p.product_name}
                        </option>`
                    ).join('')}
                </select>
            </div>
            <div style="width:160px;">
                <label class="form-label mb-1">จำนวน</label>
                <input type="number" name="quantities[]" class="form-control qty"
                       min="1" oninput="calcTotal()" placeholder="0" required>
                <div class="stock-bar-wrap" id="bar-${idx}">
                    <div class="stock-bar-fill" id="fill-${idx}" style="width:0%"></div>
                </div>
            </div>
            <div style="width:120px;">
                <label class="form-label mb-1">คงเหลือ</label>
                <div class="form-control bg-light text-center fw-bold stock-display" id="sd-${idx}" style="color:#94a3b8;">—</div>
            </div>
            <div class="mt-auto pt-1">
                <button type="button" class="btn-remove" onclick="removeRow(this)" title="ลบ">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <small class="text-danger stock-warning mt-1 d-block" id="warn-${idx}"></small>
    `;

    document.getElementById('items').appendChild(row);
    syncProducts();
    updateRowNumbers();
    calcTotal();
}

function removeRow(btn) {
    const row = btn.closest('.item-row');
    row.style.animation = 'slideDown 0.2s ease reverse';
    setTimeout(() => {
        row.remove();
        syncProducts();
        updateRowNumbers();
        calcTotal();
        if (document.querySelectorAll('.item-row').length === 0) {
            document.getElementById('emptyState').style.display = '';
        }
    }, 180);
}

function updateRowNumbers() {
    document.querySelectorAll('.item-row .row-number').forEach((el, i) => {
        el.textContent = i + 1;
    });
    document.getElementById('rowCount').textContent = document.querySelectorAll('.item-row').length + ' รายการ';
}

function onProductChange(select) {
    const row = select.closest('.item-row');
    const idx = row.dataset.idx;
    const opt = select.selectedOptions[0];
    const stock = parseInt(opt?.dataset?.stock ?? 0);
    const unit  = opt?.dataset?.unit ?? '';
    const sd = document.getElementById('sd-' + idx);
    const bar = document.getElementById('bar-' + idx);

    if (select.value) {
        sd.textContent = stock + ' ' + unit;
        sd.style.color = stock === 0 ? '#ef4444' : stock <= 5 ? '#f59e0b' : '#22c55e';
        bar.style.display = 'block';
    } else {
        sd.textContent = '—';
        sd.style.color = '#94a3b8';
        bar.style.display = 'none';
    }
    syncProducts();
    calcTotal();
}

function syncProducts() {
    const selected = [...document.querySelectorAll('.product')]
        .map(s => s.value).filter(v => v);
    document.querySelectorAll('.product').forEach(sel => {
        [...sel.options].forEach(opt => {
            opt.disabled = selected.includes(opt.value) && opt.value !== sel.value;
        });
    });
}

function calcTotal() {
    let total = 0;
    let hasError = false;
    let productCount = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const qty    = row.querySelector('.qty');
        const select = row.querySelector('.product');
        const idx    = row.dataset.idx;
        const warn   = document.getElementById('warn-' + idx);
        const fill   = document.getElementById('fill-' + idx);

        if (!select.value) return;

        const stock = parseInt(select.selectedOptions[0]?.dataset?.stock ?? 0);
        const val   = parseInt(qty.value || 0);

        // Update stock bar
        if (fill && val > 0) {
            const pct = Math.min((val / stock) * 100, 100);
            fill.style.width = pct + '%';
            fill.style.background = pct >= 100
                ? 'linear-gradient(90deg,#ef4444,#f97316)'
                : pct >= 70
                    ? 'linear-gradient(90deg,#f59e0b,#fbbf24)'
                    : 'linear-gradient(90deg,#43e97b,#38f9d7)';
        }

        if (val > stock) {
            warn.textContent = '⚠️ เบิกเกินสต็อก (' + stock + ' คงเหลือ)';
            qty.classList.add('is-invalid');
            row.classList.add('has-error');
            hasError = true;
        } else {
            warn.textContent = '';
            qty.classList.remove('is-invalid');
            row.classList.remove('has-error');
            if (val > 0) { total += val; productCount++; }
        }
    });

    document.getElementById('totalQty').textContent = total.toLocaleString('th-TH');
    document.getElementById('totalProducts').textContent = productCount;
    document.getElementById('submitBtn').disabled = hasError;

    const summaryBox = document.getElementById('summaryBox');
    summaryBox.style.display = productCount > 0 ? 'flex' : 'none';
}

// เริ่มต้น 1 แถว
addRow();
</script>

<?php include 'includes/footer.php'; ?>