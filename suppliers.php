<?php

require_once 'config.php';
$pageTitle = 'จัดการซัพพลายเออร์';
include 'includes/header.php';

// ลบซัพพลายเออร์
if (isset($_GET['delete'])) {
    $supplier_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM suppliers WHERE supplier_id = $supplier_id");
    setAlert('success', 'ลบซัพพลายเออร์เรียบร้อยแล้ว');
    redirect('suppliers.php');
}

// เพิ่ม/แก้ไขซัพพลายเออร์
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $supplier_name = clean($_POST['supplier_name']);
    $contact_person = clean($_POST['contact_person']);
    $phone = clean($_POST['phone']);
    $email = clean($_POST['email']);
    $address = clean($_POST['address']);
    
    if (isset($_POST['supplier_id']) && !empty($_POST['supplier_id'])) {
        // แก้ไข
        $supplier_id = (int)$_POST['supplier_id'];
        $sql = "UPDATE suppliers SET 
                supplier_name = '$supplier_name',
                contact_person = '$contact_person',
                phone = '$phone',
                email = '$email',
                address = '$address'
                WHERE supplier_id = $supplier_id";
        $conn->query($sql);
        setAlert('success', 'แก้ไขซัพพลายเออร์เรียบร้อยแล้ว');
    } else {
        // เพิ่มใหม่
        $sql = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address)
                VALUES ('$supplier_name', '$contact_person', '$phone', '$email', '$address')";
        $conn->query($sql);
        setAlert('success', 'เพิ่มซัพพลายเออร์เรียบร้อยแล้ว');
    }
    redirect('suppliers.php');
}

// ดึงข้อมูลซัพพลายเออร์สำหรับแก้ไข
$editSupplier = null;
if (isset($_GET['edit'])) {
    $supplier_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM suppliers WHERE supplier_id = $supplier_id");
    $editSupplier = $result->fetch_assoc();
}

// ดึงข้อมูลซัพพลายเออร์พร้อมนับการรับสินค้า
$suppliers = $conn->query("
    SELECT s.*, COUNT(gr.receipt_id) as receipt_count
    FROM suppliers s
    LEFT JOIN goods_receipt gr ON s.supplier_id = gr.supplier_id
    GROUP BY s.supplier_id
    ORDER BY s.supplier_name
");
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0">
                <i class="fas fa-truck text-primary me-2"></i>
                จัดการซัพพลายเออร์
            </h3>
            <p class="text-muted mb-0">ข้อมูลผู้จัดจำหน่ายและติดต่อสินค้า</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
            <i class="fas fa-plus me-2"></i>เพิ่มซัพพลายเออร์ใหม่
        </button>
    </div>
</div>

<!-- ตารางซัพพลายเออร์ -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อบริษัท</th>
                        <th>ผู้ติดต่อ</th>
                        <th>เบอร์โทร</th>
                        <th>อีเมล</th>
                        <th class="text-center">รับสินค้า</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($suppliers->num_rows > 0): ?>
                        <?php while ($row = $suppliers->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['supplier_id'] ?></strong></td>
                                <td>
                                    <strong><?= $row['supplier_name'] ?></strong>
                                    <?php if (!empty($row['address'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?= substr($row['address'], 0, 50) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['contact_person'] ?: '-' ?></td>
                                <td>
                                    <?php if (!empty($row['phone'])): ?>
                                        <i class="fas fa-phone me-1"></i><?= $row['phone'] ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['email'])): ?>
                                        <i class="fas fa-envelope me-1"></i><?= $row['email'] ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?= $row['receipt_count'] ?> ครั้ง</span>
                                </td>
                                <td class="text-center">
                                    <a href="?edit=<?= $row['supplier_id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#supplierModal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?= $row['supplier_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ต้องการลบซัพพลายเออร์นี้?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                ยังไม่มีข้อมูลซัพพลายเออร์
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขซัพพลายเออร์ -->
<div class="modal fade" id="supplierModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $editSupplier ? 'แก้ไขซัพพลายเออร์' : 'เพิ่มซัพพลายเออร์ใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <div class="modal-body">
                    <?php if ($editSupplier): ?>
                        <input type="hidden" name="supplier_id" value="<?= $editSupplier['supplier_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">ชื่อบริษัท / ร้านค้า <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" required 
                                   value="<?= $editSupplier['supplier_name'] ?? '' ?>"
                                   placeholder="บริษัท ABC จำกัด">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อผู้ติดต่อ</label>
                            <input type="text" name="contact_person" class="form-control"
                                   value="<?= $editSupplier['contact_person'] ?? '' ?>"
                                   placeholder="คุณสมชาย">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= $editSupplier['phone'] ?? '' ?>"
                                   placeholder="02-1234567, 081-2345678">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">อีเมล</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= $editSupplier['email'] ?? '' ?>"
                               placeholder="contact@supplier.com">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ที่อยู่</label>
                        <textarea name="address" class="form-control" rows="3"
                                  placeholder="ที่อยู่สำหรับจัดส่งเอกสาร"><?= $editSupplier['address'] ?? '' ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// เปิด Modal อัตโนมัติถ้ามีการแก้ไข
if ($editSupplier): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('supplierModal'));
        myModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>