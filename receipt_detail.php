<?php


require_once 'config.php';
$pageTitle = 'รายละเอียดใบรับสินค้า';
include 'includes/header.php';

// ตรวจสอบว่ามี ID หรือไม่
if (!isset($_GET['id'])) {
    setAlert('danger', 'ไม่พบข้อมูลใบรับสินค้า');
    redirect('receive.php');
}

$receipt_id = (int)$_GET['id'];

// ดึงข้อมูลหัวใบรับสินค้า
$receiptQuery = $conn->query("
    SELECT gr.*, s.supplier_name, s.phone, s.email, u.full_name as received_by
    FROM goods_receipt gr
    LEFT JOIN suppliers s ON gr.supplier_id = s.supplier_id
    LEFT JOIN users u ON gr.user_id = u.user_id
    WHERE gr.receipt_id = $receipt_id
");

if ($receiptQuery->num_rows == 0) {
    setAlert('danger', 'ไม่พบข้อมูลใบรับสินค้า');
    redirect('receive.php');
}

$receipt = $receiptQuery->fetch_assoc();

// ดึงรายการสินค้าในใบรับ
$items = $conn->query("
    SELECT gri.*, p.product_code, p.product_name, p.unit
    FROM goods_receipt_items gri
    JOIN products p ON gri.product_id = p.product_id
    WHERE gri.receipt_id = $receipt_id
    ORDER BY gri.item_id
");

// นับยอดรวม
$totalQty = 0;
$totalItems = $items->num_rows;
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0">
                <i class="fas fa-file-invoice text-primary me-2"></i>
                ใบรับสินค้า #<?= str_pad($receipt_id, 5, '0', STR_PAD_LEFT) ?>
            </h3>
            <p class="text-muted mb-0">รายละเอียดการรับสินค้าเข้าคลัง</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print me-2"></i>พิมพ์
            </button>
            <a href="receive.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>กลับ
            </a>
        </div>
    </div>
</div>

<!-- ข้อมูลใบรับสินค้า -->
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>ข้อมูลการรับสินค้า</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong class="text-muted">เลขที่ใบรับสินค้า:</strong>
                        <p class="mb-0"><strong>#<?= str_pad($receipt_id, 5, '0', STR_PAD_LEFT) ?></strong></p>
                    </div>
                    <div class="col-md-6">
                        <strong class="text-muted">วันที่รับสินค้า:</strong>
                        <p class="mb-0"><?= date('d/m/Y', strtotime($receipt['receipt_date'])) ?></p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong class="text-muted">ซัพพลายเออร์:</strong>
                        <p class="mb-0"><?= $receipt['supplier_name'] ?? 'ไม่ระบุ' ?></p>
                        <?php if (!empty($receipt['phone'])): ?>
                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?= $receipt['phone'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <strong class="text-muted">ผู้บันทึก:</strong>
                        <p class="mb-0"><?= $receipt['received_by'] ?></p>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($receipt['created_at'])) ?></small>
                    </div>
                </div>

                <?php if (!empty($receipt['note'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <strong class="text-muted">หมายเหตุ:</strong>
                            <p class="mb-0 p-3 bg-light rounded"><?= nl2br($receipt['note']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- รายการสินค้า -->
        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>รายการสินค้าที่รับเข้า</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">#</th>
                                <th width="15%">รหัสสินค้า</th>
                                <th>ชื่อสินค้า</th>
                                <th width="12%">จำนวน</th>
                                <th width="15%">เลขล็อต</th>
                                <th width="15%">วันหมดอายุ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($item = $items->fetch_assoc()): 
                                $totalQty += $item['quantity'];
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><strong><?= $item['product_code'] ?></strong></td>
                                    <td><?= $item['product_name'] ?></td>
                                    <td class="text-end">
                                        <strong><?= number_format($item['quantity']) ?></strong> <?= $item['unit'] ?>
                                    </td>
                                    <td><?= $item['lot_number'] ?: '-' ?></td>
                                    <td>
                                        <?php if (!empty($item['expiry_date'])): ?>
                                            <?= date('d/m/Y', strtotime($item['expiry_date'])) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3" class="text-end">รวมทั้งหมด:</th>
                                <th class="text-end"><?= number_format($totalQty) ?> ชิ้น</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- สรุปด้านขวา -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>สรุปการรับสินค้า</h5>
            </div>
            <div class="card-body">
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">จำนวนรายการ:</span>
                        <strong><?= number_format($totalItems) ?> รายการ</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">จำนวนสินค้ารวม:</span>
                        <strong class="text-success"><?= number_format($totalQty) ?> ชิ้น</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">สถานะ:</span>
                        <span class="badge bg-success">รับสินค้าเรียบร้อย</span>
                    </div>
                </div>

                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    <small>สต็อกได้รับการอัพเดทแล้ว</small>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>ประวัติ</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item mb-3">
                        <i class="fas fa-check-circle text-success"></i>
                        <div class="ms-3">
                            <strong>รับสินค้าเรียบร้อย</strong>
                            <br>
                            <small class="text-muted">
                                <?= date('d/m/Y H:i', strtotime($receipt['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                    <div class="timeline-item">
                        <i class="fas fa-user-circle text-info"></i>
                        <div class="ms-3">
                            <strong>สร้างใบรับสินค้า</strong>
                            <br>
                            <small class="text-muted">โดย <?= $receipt['received_by'] ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .page-header button, .page-header a,
    .sidebar, .card:last-child {
        display: none !important;
    }
    .col-md-8 {
        width: 100% !important;
    }
}
.timeline-item {
    position: relative;
    padding-left: 30px;
}
.timeline-item i {
    position: absolute;
    left: 0;
    font-size: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>