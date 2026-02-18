<?php
require_once 'config.php';
$pageTitle = 'รายละเอียดใบเบิกสินค้า';
include 'includes/header.php';

if (!isset($_GET['id'])) {
    setAlert('danger', 'ไม่พบข้อมูลใบเบิกสินค้า');
    redirect('issue.php');
}

$issue_id = (int)$_GET['id'];

/* ===============================
   หัวใบเบิก
================================ */
$issueQ = $conn->query("
    SELECT gi.*, u.full_name AS issued_by
    FROM goods_issue gi
    LEFT JOIN users u ON gi.user_id = u.user_id
    WHERE gi.issue_id = $issue_id
");

if ($issueQ->num_rows === 0) {
    setAlert('danger', 'ไม่พบข้อมูลใบเบิกสินค้า');
    redirect('issue.php');
}

$issue = $issueQ->fetch_assoc();

/* ===============================
   รายการสินค้า
================================ */
$items = $conn->query("
    SELECT 
        gii.*, 
        p.product_code,
        p.product_name,
        p.unit
    FROM goods_issue_items gii
    JOIN products p ON gii.product_id = p.product_id
    WHERE gii.issue_id = $issue_id
    ORDER BY gii.item_id
");

$totalQty   = 0;
$totalItems = $items->num_rows;
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h3 class="mb-0">
            <i class="fas fa-file-export text-primary me-2"></i>
            ใบเบิกสินค้า #<?= str_pad($issue_id, 5, '0', STR_PAD_LEFT) ?>
        </h3>
        <p class="text-muted mb-0">รายละเอียดการเบิกสินค้าออกจากคลัง</p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print me-2"></i>พิมพ์
        </button>
        <a href="issue.php" class="btn btn-primary">
            <i class="fas fa-arrow-left me-2"></i>กลับ
        </a>
    </div>
</div>

<!-- ข้อมูลใบเบิก -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>วันที่เบิก:</strong><br>
                <?= date('d/m/Y', strtotime($issue['issue_date'])) ?>
            </div>
            <div class="col-md-4">
                <strong>วัตถุประสงค์:</strong><br>
                <span class="badge bg-info"><?= $issue['purpose'] ?></span>
            </div>
            <div class="col-md-4">
                <strong>ผู้บันทึก:</strong><br>
                <?= $issue['issued_by'] ?>
            </div>
        </div>

        <?php if ($issue['note']): ?>
            <hr>
            <strong>หมายเหตุ:</strong>
            <div class="bg-light p-2 rounded">
                <?= nl2br($issue['note']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ตารางสินค้า -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>รายการสินค้าที่เบิก</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="5%">#</th>
                        <th width="15%">รหัส</th>
                        <th>สินค้า</th>
                        <th class="text-end" width="12%">ก่อนเบิก</th>
                        <th class="text-end text-danger" width="10%">เบิก</th>
                        <th class="text-end text-success" width="12%">หลังเบิก</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $items->fetch_assoc()): 
                        $totalQty += $row['quantity'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><strong><?= $row['product_code'] ?></strong></td>
                        <td><?= $row['product_name'] ?></td>
                        <td class="text-end">
                            <?= number_format($row['stock_before']) ?> <?= $row['unit'] ?>
                        </td>
                        <td class="text-end text-danger">
                            -<?= number_format($row['quantity']) ?>
                        </td>
                        <td class="text-end text-success fw-bold">
                            <?= number_format($row['stock_after']) ?> <?= $row['unit'] ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="4" class="text-end">รวมทั้งหมด:</th>
                        <th colspan="2" class="text-end text-primary">
                            <?= number_format($totalQty) ?> ชิ้น
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .page-header button,
    .page-header a,
    .sidebar {
        display: none !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
