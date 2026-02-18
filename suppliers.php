<?php
require_once 'config.php';
requireAdmin();
$pageTitle = 'จัดการซัพพลายเออร์';
include 'includes/header.php';

if (isset($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM suppliers WHERE supplier_id = $supplier_id");
    setAlert('success', 'ลบซัพพลายเออร์เรียบร้อยแล้ว');
    redirect('suppliers.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_name  = clean($_POST['supplier_name']);
    $contact_person = clean($_POST['contact_person']);
    $phone          = clean($_POST['phone']);
    $email          = clean($_POST['email']);
    $address        = clean($_POST['address']);

    if (!empty($_POST['supplier_id'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        $conn->query("UPDATE suppliers SET supplier_name='$supplier_name', contact_person='$contact_person', phone='$phone', email='$email', address='$address' WHERE supplier_id=$supplier_id");
        setAlert('success', 'แก้ไขซัพพลายเออร์เรียบร้อยแล้ว');
    } else {
        $conn->query("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES ('$supplier_name','$contact_person','$phone','$email','$address')");
        setAlert('success', 'เพิ่มซัพพลายเออร์เรียบร้อยแล้ว');
    }
    redirect('suppliers.php');
}

$editSupplier = null;
if (isset($_GET['edit'])) {
    $supplier_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM suppliers WHERE supplier_id=$supplier_id");
    $editSupplier = $result->fetch_assoc();
}

$suppliers = $conn->query("
    SELECT s.*, COUNT(gr.receipt_id) as receipt_count
    FROM suppliers s
    LEFT JOIN goods_receipt gr ON s.supplier_id = gr.supplier_id
    GROUP BY s.supplier_id
    ORDER BY s.supplier_name
");
$supplierList = $suppliers->fetch_all(MYSQLI_ASSOC);
$totalSuppliers = count($supplierList);
$totalReceipts  = array_sum(array_column($supplierList, 'receipt_count'));
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
    background: linear-gradient(135deg, #a18cd1, #fbc2eb);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0;
}
.btn-add {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 12px;
    padding: 10px 22px; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
    transition: all 0.2s; cursor: pointer;
}
.btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.45); color: #fff; }

/* ===== Summary Bar ===== */
.summary-bar {
    background: #fff; border-radius: 16px; padding: 16px 24px;
    margin-bottom: 22px; box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; gap: 28px; flex-wrap: wrap;
}
.summary-item { display: flex; align-items: center; gap: 12px; }
.summary-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
.summary-num   { font-size: 1.5rem; font-weight: 800; color: #1e293b; line-height: 1; }
.summary-label { font-size: 12px; color: #94a3b8; font-weight: 600; }
.summary-divider { width: 1px; height: 38px; background: #e2e8f0; }

/* ===== Table Card ===== */
.table-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 22px;
}
.table-card-header {
    padding: 16px 22px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    font-weight: 700; font-size: 15px;
}
.table-card-header .title { display: flex; align-items: center; gap: 10px; }

/* ===== Table ===== */
.sup-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sup-table thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    padding: 12px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap;
}
.sup-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.sup-table tbody tr:hover { background: #f8fafc; }
.sup-table tbody td { padding: 13px 16px; vertical-align: middle; }
.sup-table tbody tr:last-child { border-bottom: none; }

/* ===== Supplier Name Cell ===== */
.sup-avatar {
    width: 40px; height: 40px; border-radius: 11px;
    background: linear-gradient(135deg, #a18cd1, #fbc2eb);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: #fff; font-weight: 800; flex-shrink: 0;
}
.sup-name   { font-weight: 700; color: #1e293b; }
.sup-addr   { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.contact-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: #f1f5f9; color: #475569; border-radius: 8px;
    padding: 3px 10px; font-size: 12px; text-decoration: none;
    transition: all 0.2s;
}
.contact-chip:hover { background: #667eea; color: #fff; }

/* ===== Receipt Badge ===== */
.receipt-badge {
    background: #eef2ff; color: #667eea;
    border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px;
}
.receipt-badge.zero { background: #f1f5f9; color: #94a3b8; }

/* ===== Action Buttons ===== */
.btn-edit {
    width: 34px; height: 34px; border-radius: 9px;
    background: #fff8e1; color: #f59e0b; border: none;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; transition: all 0.2s; cursor: pointer; text-decoration: none;
}
.btn-edit:hover   { background: #f59e0b; color: #fff; transform: scale(1.1); }
.btn-delete {
    width: 34px; height: 34px; border-radius: 9px;
    background: #ffebee; color: #ef4444; border: none;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; transition: all 0.2s; cursor: pointer; text-decoration: none;
}
.btn-delete:hover { background: #ef4444; color: #fff; transform: scale(1.1); }

/* ===== Empty State ===== */
.empty-state { text-align: center; padding: 56px; color: #94a3b8; }

/* ===== Modal ===== */
.modal-content { border: none; border-radius: 18px; overflow: hidden; }
.modal-header {
    background: linear-gradient(135deg, #a18cd1, #fbc2eb);
    color: #fff; padding: 20px 24px; border: none;
}
.modal-header .btn-close { filter: invert(1) brightness(2); }
.modal-title { font-weight: 800; font-size: 17px; }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; }
.modal .form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }
.modal .form-control, .modal .form-select {
    border-radius: 10px; border: 2px solid #e2e8f0;
    padding: 10px 14px; font-size: 14px; transition: border-color 0.2s;
}
.modal .form-control:focus {
    border-color: #a18cd1; box-shadow: 0 0 0 4px rgba(161,140,209,0.12);
}
.btn-save {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 10px;
    padding: 10px 28px; font-weight: 700; font-size: 14px;
    box-shadow: 0 4px 12px rgba(102,126,234,0.35); transition: all 0.2s;
}
.btn-save:hover { transform: translateY(-1px); color: #fff; }
.btn-cancel {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 10px; padding: 9px 20px; font-weight: 600; font-size: 14px; transition: all 0.2s;
}
.btn-cancel:hover { border-color: #94a3b8; }

/* ===== Animations ===== */
.fade-in   { opacity: 0; animation: fadeUp 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.10s; }
.fade-in-3 { animation-delay: 0.15s; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

.count-badge { background: #eef2ff; color: #667eea; border-radius: 8px; padding: 3px 10px; font-size: 12px; font-weight: 700; }
</style>

<!-- ===== Header ===== -->
<div class="page-header-custom fade-in fade-in-1">
    <div class="d-flex align-items-center gap-3">
        <div class="page-header-icon"><i class="fas fa-truck"></i></div>
        <div>
            <h4 class="mb-0 fw-bold">จัดการซัพพลายเออร์</h4>
            <p class="text-muted mb-0" style="font-size:13px;">ข้อมูลผู้จัดจำหน่ายและติดต่อสินค้า</p>
        </div>
    </div>
    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#supplierModal">
        <i class="fas fa-plus me-2"></i>เพิ่มซัพพลายเออร์
    </button>
</div>

<!-- ===== Summary Bar ===== -->
<div class="summary-bar fade-in fade-in-2">
    <div class="summary-item">
        <div class="summary-icon" style="background:#eef2ff;color:#667eea;">
            <i class="fas fa-truck"></i>
        </div>
        <div>
            <div class="summary-num"><?= $totalSuppliers ?></div>
            <div class="summary-label">ซัพพลายเออร์ทั้งหมด</div>
        </div>
    </div>
    <div class="summary-divider"></div>
    <div class="summary-item">
        <div class="summary-icon" style="background:#e8f5e9;color:#2e7d32;">
            <i class="fas fa-boxes"></i>
        </div>
        <div>
            <div class="summary-num"><?= number_format($totalReceipts) ?></div>
            <div class="summary-label">ครั้งที่รับสินค้ารวม</div>
        </div>
    </div>
    <div class="summary-divider"></div>
    <div class="summary-item">
        <div class="summary-icon" style="background:#fff3e0;color:#f57c00;">
            <i class="fas fa-star"></i>
        </div>
        <div>
            <div class="summary-num">
                <?php
                $top = array_reduce($supplierList, fn($carry, $s) => (!$carry || $s['receipt_count'] > $carry['receipt_count']) ? $s : $carry, null);
                echo $top ? htmlspecialchars(mb_strimwidth($top['supplier_name'], 0, 16, '…')) : '-';
                ?>
            </div>
            <div class="summary-label">ซัพพลายเออร์ที่ใช้บ่อยที่สุด</div>
        </div>
    </div>
</div>

<!-- ===== Table ===== -->
<div class="table-card fade-in fade-in-3">
    <div class="table-card-header">
        <div class="title">
            <span style="width:5px;height:20px;border-radius:3px;background:linear-gradient(135deg,#a18cd1,#fbc2eb);display:inline-block;"></span>
            รายชื่อซัพพลายเออร์ทั้งหมด
            <span class="count-badge"><?= $totalSuppliers ?> ราย</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="sup-table">
            <thead>
                <tr>
                    <th>บริษัท / ร้านค้า</th>
                    <th>ผู้ติดต่อ</th>
                    <th>ช่องทางติดต่อ</th>
                    <th>ที่อยู่</th>
                    <th class="text-center">รับสินค้า</th>
                    <th class="text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($supplierList) > 0): ?>
                <?php foreach ($supplierList as $row):
                    $initial = mb_strtoupper(mb_substr($row['supplier_name'], 0, 1, 'UTF-8'), 'UTF-8');
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="sup-avatar"><?= $initial ?></div>
                            <div>
                                <div class="sup-name"><?= htmlspecialchars($row['supplier_name']) ?></div>
                                <div class="sup-addr">#<?= $row['supplier_id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($row['contact_person']): ?>
                            <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($row['contact_person']) ?></div>
                        <?php else: ?>
                            <span style="color:#cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex flex-column gap-1">
                            <?php if ($row['phone']): ?>
                                <a href="tel:<?= $row['phone'] ?>" class="contact-chip">
                                    <i class="fas fa-phone" style="font-size:10px;"></i>
                                    <?= htmlspecialchars($row['phone']) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($row['email']): ?>
                                <a href="mailto:<?= $row['email'] ?>" class="contact-chip">
                                    <i class="fas fa-envelope" style="font-size:10px;"></i>
                                    <?= htmlspecialchars($row['email']) ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!$row['phone'] && !$row['email']): ?>
                                <span style="color:#cbd5e1;font-size:13px;">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="max-width:180px;">
                        <?php if ($row['address']): ?>
                            <div style="font-size:12px;color:#64748b;line-height:1.4;">
                                <i class="fas fa-map-marker-alt me-1" style="color:#a18cd1;"></i>
                                <?= htmlspecialchars(mb_strimwidth($row['address'], 0, 60, '…')) ?>
                            </div>
                        <?php else: ?>
                            <span style="color:#cbd5e1;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="receipt-badge <?= $row['receipt_count'] == 0 ? 'zero' : '' ?>">
                            <i class="fas fa-boxes" style="font-size:10px;"></i>
                            <?= number_format($row['receipt_count']) ?> ครั้ง
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="?edit=<?= $row['supplier_id'] ?>" class="btn-edit"
                           data-bs-toggle="modal" data-bs-target="#supplierModal" title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete=<?= $row['supplier_id'] ?>" class="btn-delete"
                           onclick="return confirm('ยืนยันการลบ: <?= htmlspecialchars($row['supplier_name']) ?>?')" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <i class="fas fa-truck fa-3x d-block mb-3"></i>
                        <div style="font-size:16px;font-weight:600;color:#64748b;">ยังไม่มีซัพพลายเออร์</div>
                        <div style="font-size:13px;margin-top:6px;">กดปุ่ม "เพิ่มซัพพลายเออร์" เพื่อเริ่มต้น</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== Modal ===== -->
<div class="modal fade" id="supplierModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas <?= $editSupplier ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
                    <?= $editSupplier ? 'แก้ไขซัพพลายเออร์' : 'เพิ่มซัพพลายเออร์ใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if ($editSupplier): ?>
                        <input type="hidden" name="supplier_id" value="<?= $editSupplier['supplier_id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-building me-1" style="color:#a18cd1;"></i>ชื่อบริษัท / ร้านค้า <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" required
                               value="<?= htmlspecialchars($editSupplier['supplier_name'] ?? '') ?>"
                               placeholder="บริษัท ABC จำกัด">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-user me-1" style="color:#a18cd1;"></i>ชื่อผู้ติดต่อ</label>
                            <input type="text" name="contact_person" class="form-control"
                                   value="<?= htmlspecialchars($editSupplier['contact_person'] ?? '') ?>"
                                   placeholder="คุณสมชาย">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fas fa-phone me-1" style="color:#a18cd1;"></i>เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($editSupplier['phone'] ?? '') ?>"
                                   placeholder="02-123-4567">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-envelope me-1" style="color:#a18cd1;"></i>อีเมล</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($editSupplier['email'] ?? '') ?>"
                                   placeholder="contact@supplier.com">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label"><i class="fas fa-map-marker-alt me-1" style="color:#a18cd1;"></i>ที่อยู่</label>
                            <textarea name="address" class="form-control" rows="3"
                                      placeholder="ที่อยู่สำหรับจัดส่งเอกสาร"><?= htmlspecialchars($editSupplier['address'] ?? '') ?></textarea>
                        </div>
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
</div>

<?php if ($editSupplier): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>