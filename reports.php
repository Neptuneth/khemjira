<?php


require_once 'config.php';
$pageTitle = 'รายงาน';
include 'includes/header.php';

// กำหนดช่วงวันที่ (ค่าเริ่มต้น 30 วันย้อนหลัง)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// รายงานการรับสินค้า
$receiptReport = $conn->query("
    SELECT gr.receipt_id, gr.receipt_date, s.supplier_name, u.full_name,
           p.product_code, p.product_name, gri.quantity, p.unit, gri.lot_number, gri.expiry_date
    FROM goods_receipt gr
    LEFT JOIN suppliers s ON gr.supplier_id = s.supplier_id
    LEFT JOIN users u ON gr.user_id = u.user_id
    LEFT JOIN goods_receipt_items gri ON gr.receipt_id = gri.receipt_id
    LEFT JOIN products p ON gri.product_id = p.product_id
    WHERE gr.receipt_date BETWEEN '$date_from' AND '$date_to'
    ORDER BY gr.receipt_date DESC, gr.receipt_id DESC
");

// รายงานการเบิกสินค้า
$issueReport = $conn->query("
    SELECT gi.issue_id, gi.issue_date, gi.purpose, u.full_name,
           p.product_code, p.product_name, gii.quantity, p.unit
    FROM goods_issue gi
    LEFT JOIN users u ON gi.user_id = u.user_id
    LEFT JOIN goods_issue_items gii ON gi.issue_id = gii.issue_id
    LEFT JOIN products p ON gii.product_id = p.product_id
    WHERE gi.issue_date BETWEEN '$date_from' AND '$date_to'
    ORDER BY gi.issue_date DESC, gi.issue_id DESC
");

// รายงานสินค้าเคลื่อนไหว (Top 10 สินค้าที่มีการเคลื่อนไหวมากที่สุด)
$movementReport = $conn->query("
    SELECT p.product_code, p.product_name, p.unit,
           SUM(CASE WHEN sm.movement_type = 'in' THEN sm.quantity ELSE 0 END) as total_in,
           SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as total_out,
           COUNT(*) as movement_count
    FROM stock_movement sm
    JOIN products p ON sm.product_id = p.product_id
    WHERE DATE(sm.created_at) BETWEEN '$date_from' AND '$date_to'
    GROUP BY sm.product_id
    ORDER BY movement_count DESC
    LIMIT 10
");

// สรุปภาพรวม
$summary = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM goods_receipt WHERE receipt_date BETWEEN '$date_from' AND '$date_to') as total_receipts,
        (SELECT COUNT(*) FROM goods_issue WHERE issue_date BETWEEN '$date_from' AND '$date_to') as total_issues,
        (SELECT SUM(quantity) FROM goods_receipt_items gri 
         JOIN goods_receipt gr ON gri.receipt_id = gr.receipt_id 
         WHERE gr.receipt_date BETWEEN '$date_from' AND '$date_to') as total_received,
        (SELECT SUM(quantity) FROM goods_issue_items gii 
         JOIN goods_issue gi ON gii.issue_id = gi.issue_id 
         WHERE gi.issue_date BETWEEN '$date_from' AND '$date_to') as total_issued
")->fetch_assoc();
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-chart-bar text-primary me-2"></i>
        รายงาน
    </h3>
    <p class="text-muted mb-0">รายงานการเคลื่อนไหวสินค้าในคลัง</p>
</div>

<!-- เลือกช่วงวันที่ -->
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">วันที่เริ่มต้น</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">วันที่สิ้นสุด</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>แสดงรายงาน
                </button>
                <button type="button" class="btn btn-secondary" onclick="location.href='reports.php'">
                    <i class="fas fa-redo me-2"></i>รีเซ็ต
                </button>
            </div>
        </form>
    </div>
</div>

<!-- สรุปภาพรวม -->
<div class="row">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">ครั้งที่รับสินค้า</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($summary['total_receipts'] ?? 0) ?></h3>
                    <small class="text-muted">ครั้ง</small>
                </div>
                <div class="icon bg-success-light">
                    <i class="fas fa-truck-loading"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">ครั้งที่เบิกสินค้า</h6>
                    <h3 class="fw-bold mb-0"><?= number_format($summary['total_issues'] ?? 0) ?></h3>
                    <small class="text-muted">ครั้ง</small>
                </div>
                <div class="icon bg-primary-light">
                    <i class="fas fa-dolly"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">จำนวนรับเข้า</h6>
                    <h3 class="fw-bold mb-0 text-success"><?= number_format($summary['total_received'] ?? 0) ?></h3>
                    <small class="text-muted">ชิ้น</small>
                </div>
                <div class="icon bg-success-light">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">จำนวนเบิกออก</h6>
                    <h3 class="fw-bold mb-0 text-primary"><?= number_format($summary['total_issued'] ?? 0) ?></h3>
                    <small class="text-muted">ชิ้น</small>
                </div>
                <div class="icon bg-primary-light">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- รายงานสินค้าเคลื่อนไหว Top 10 -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="fas fa-fire text-danger me-2"></i>
            Top 10 สินค้าที่มีการเคลื่อนไหวมากที่สุด
        </h5>
    </div>
    <div class="card-body">
        <?php if ($movementReport->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>รหัสสินค้า</th>
                            <th>ชื่อสินค้า</th>
                            <th class="text-center">รับเข้า</th>
                            <th class="text-center">เบิกออก</th>
                            <th class="text-center">ยอดเคลื่อนไหว</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        while ($row = $movementReport->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= $row['product_code'] ?></strong></td>
                                <td><?= $row['product_name'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success">+<?= number_format($row['total_in']) ?> <?= $row['unit'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary">-<?= number_format($row['total_out']) ?> <?= $row['unit'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= number_format($row['movement_count']) ?> ครั้ง</span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                ไม่มีข้อมูลการเคลื่อนไหวในช่วงเวลานี้
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- รายงานการรับสินค้า -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-truck-loading text-success me-2"></i>
                    รายงานการรับสินค้าเข้า
                </h5>
                <button class="btn btn-success btn-sm" onclick="exportTable('receiptTable', 'รายงานรับสินค้า')">
                    <i class="fas fa-file-excel"></i>
                </button>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php if ($receiptReport->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="receiptTable">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>วันที่</th>
                                    <th>สินค้า</th>
                                    <th class="text-end">จำนวน</th>
                                    <th>ซัพพลายเออร์</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $receiptReport->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><?= date('d/m/Y', strtotime($row['receipt_date'])) ?></small></td>
                                        <td>
                                            <small>
                                                <strong><?= $row['product_code'] ?></strong><br>
                                                <?= $row['product_name'] ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-success">
                                                +<?= number_format($row['quantity']) ?> <?= $row['unit'] ?>
                                            </span>
                                        </td>
                                        <td><small><?= $row['supplier_name'] ?? '-' ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">ไม่มีข้อมูลการรับสินค้า</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- รายงานการเบิกสินค้า -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-dolly text-primary me-2"></i>
                    รายงานการเบิกสินค้าออก
                </h5>
                <button class="btn btn-success btn-sm" onclick="exportTable('issueTable', 'รายงานเบิกสินค้า')">
                    <i class="fas fa-file-excel"></i>
                </button>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                <?php if ($issueReport->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="issueTable">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>วันที่</th>
                                    <th>สินค้า</th>
                                    <th class="text-end">จำนวน</th>
                                    <th>วัตถุประสงค์</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $issueReport->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><?= date('d/m/Y', strtotime($row['issue_date'])) ?></small></td>
                                        <td>
                                            <small>
                                                <strong><?= $row['product_code'] ?></strong><br>
                                                <?= $row['product_name'] ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-primary">
                                                -<?= number_format($row['quantity']) ?> <?= $row['unit'] ?>
                                            </span>
                                        </td>
                                        <td><small><?= $row['purpose'] ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">ไม่มีข้อมูลการเบิกสินค้า</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function exportTable(tableId, filename) {
    let table = document.getElementById(tableId);
    let html = table.outerHTML;
    let url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    let downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    downloadLink.href = url;
    downloadLink.download = filename + '_' + new Date().toISOString().slice(0,10) + '.xls';
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>

<?php include 'includes/footer.php'; ?>