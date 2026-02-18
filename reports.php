<?php
require_once 'config.php';
requireLogin();
$pageTitle = 'รายงาน';
include 'includes/header.php';

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

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

<style>
@media print {
    #sidebar, .no-print { display: none !important; }
    #content { padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; page-break-inside: avoid; }
    body { background: #fff !important; }
}

/* ===== Page Header ===== */
.rpt-header {
    background: #fff;
    border-radius: 16px;
    padding: 22px 26px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.rpt-header h3 { margin: 0; font-weight: 800; font-size: 20px; }

/* ===== Filter Card ===== */
.filter-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
}
.filter-card .form-control, .filter-card .form-select {
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    padding: 9px 14px;
    font-size: 14px;
    transition: border-color 0.2s;
}
.filter-card .form-control:focus { border-color: #667eea; box-shadow: 0 0 0 4px rgba(102,126,234,0.1); }
.form-label { font-weight: 600; font-size: 13px; color: #475569; margin-bottom: 6px; }

.btn-search {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: #fff; border-radius: 10px;
    padding: 10px 22px; font-weight: 700; font-size: 14px;
    transition: all 0.2s;
}
.btn-search:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(102,126,234,0.4); color: #fff; }
.btn-reset {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 10px; padding: 9px 18px; font-weight: 600; font-size: 14px;
    transition: all 0.2s;
}
.btn-reset:hover { border-color: #667eea; color: #667eea; }

/* ===== Quick Period Buttons ===== */
.period-chip {
    border: 2px solid #e2e8f0; background: #fff; color: #64748b;
    border-radius: 8px; padding: 5px 14px; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-block;
}
.period-chip:hover { border-color: #667eea; color: #667eea; }

/* ===== Stat Cards ===== */
.stat-card {
    background: #fff; border-radius: 16px; padding: 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative; overflow: hidden;
    margin-bottom: 22px;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,0.11); }
.stat-card .blob {
    position: absolute; right: -18px; top: -18px;
    width: 90px; height: 90px; border-radius: 50%; opacity: 0.09;
}
.stat-card .icon {
    width: 48px; height: 48px; border-radius: 13px;
    display: flex; align-items: center; justify-content: center; font-size: 20px;
}
.stat-number { font-size: 1.8rem; font-weight: 800; line-height: 1; color: #1e293b; }

/* ===== Section Cards ===== */
.rpt-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    margin-bottom: 22px; overflow: hidden;
}
.rpt-card-header {
    padding: 16px 22px; border-bottom: 1px solid #f1f5f9;
    display: flex; align-items: center; justify-content: space-between;
    background: #fff;
}
.rpt-card-header .title { font-weight: 700; font-size: 15px; display: flex; align-items: center; gap: 10px; }
.rpt-card-header .accent { width: 5px; height: 20px; border-radius: 3px; display: inline-block; }
.rpt-card-body { padding: 0; }

/* ===== Tables ===== */
.rpt-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rpt-table thead th {
    background: #f8fafc; color: #475569; font-weight: 700;
    padding: 11px 14px; border-bottom: 2px solid #e2e8f0;
    position: sticky; top: 0; z-index: 1; white-space: nowrap;
}
.rpt-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
.rpt-table tbody tr:hover { background: #f8fafc; }
.rpt-table tbody td { padding: 10px 14px; color: #334155; vertical-align: middle; }
.rpt-table tbody tr:last-child { border-bottom: none; }
.scrollable { max-height: 420px; overflow-y: auto; }

/* ===== Badges ===== */
.badge-in  { background: #e8f5e9; color: #2e7d32; border-radius: 8px; padding: 4px 10px; font-weight: 700; font-size: 12px; }
.badge-out { background: #e3f2fd; color: #1565c0; border-radius: 8px; padding: 4px 10px; font-weight: 700; font-size: 12px; }
.badge-cnt { background: #f3e5f5; color: #6a1b9a; border-radius: 8px; padding: 4px 10px; font-weight: 700; font-size: 12px; }

/* ===== Rank badge ===== */
.rank-1 { background: #FFD700; }
.rank-2 { background: #C0C0C0; }
.rank-3 { background: #CD7F32; }
.rank-badge {
    width: 26px; height: 26px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; color: #fff;
    background: #94a3b8;
}

/* ===== Export btn ===== */
.btn-export {
    background: #e8f5e9; color: #2e7d32; border: none;
    border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 700;
    transition: all 0.2s; cursor: pointer;
}
.btn-export:hover { background: #2e7d32; color: #fff; }

/* ===== Animations ===== */
.fade-in { opacity: 0; animation: fadeUp 0.45s ease forwards; }
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.10s; }
.fade-in-3 { animation-delay: 0.15s; }
.fade-in-4 { animation-delay: 0.20s; }
.fade-in-5 { animation-delay: 0.25s; }
.fade-in-6 { animation-delay: 0.30s; }
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}

.empty-state { text-align: center; padding: 40px; color: #94a3b8; }
</style>

<!-- ===== Header ===== -->
<div class="rpt-header fade-in fade-in-1 no-print">
    <div>
        <h3><i class="fas fa-chart-bar text-primary me-2"></i>รายงาน</h3>
        <p class="text-muted mb-0" style="font-size:13px;">รายงานการเคลื่อนไหวสินค้าในคลัง เขมจิรา บิวตี้ช็อป</p>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary no-print" style="border-radius:10px;">
        <i class="fas fa-print me-1"></i> พิมพ์รายงาน
    </button>
</div>

<!-- ===== Filter ===== -->
<div class="filter-card fade-in fade-in-2 no-print">
    <form method="GET" id="filterForm">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-calendar me-1 text-primary"></i>วันที่เริ่มต้น</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="fas fa-calendar me-1 text-primary"></i>วันที่สิ้นสุด</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">ช่วงเวลาด่วน</label>
                <div class="d-flex gap-2 flex-wrap mb-1">
                    <a href="#" class="period-chip" onclick="setPeriod(0)">วันนี้</a>
                    <a href="#" class="period-chip" onclick="setPeriod(7)">7 วัน</a>
                    <a href="#" class="period-chip" onclick="setPeriod(30)">30 วัน</a>
                    <a href="#" class="period-chip" onclick="setPeriod(90)">3 เดือน</a>
                    <a href="#" class="period-chip" onclick="setPeriod(365)">1 ปี</a>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn-search"><i class="fas fa-search me-2"></i>แสดงรายงาน</button>
            <button type="button" class="btn-reset" onclick="location.href='reports.php'"><i class="fas fa-redo me-1"></i>รีเซ็ต</button>
        </div>
    </form>
</div>

<!-- ===== Stat Cards ===== -->
<div class="row g-3 mb-2">
<?php
$statCards = [
    ['ครั้งที่รับสินค้า', $summary['total_receipts'] ?? 0, 'ครั้ง', 'fa-truck-loading', '#43e97b', '#38f9d7', '#e8f5e9', '#2e7d32'],
    ['ครั้งที่เบิกสินค้า', $summary['total_issues']   ?? 0, 'ครั้ง', 'fa-dolly',         '#667eea', '#764ba2', '#eef2ff', '#4338ca'],
    ['จำนวนรับเข้ารวม',  $summary['total_received']  ?? 0, 'ชิ้น',  'fa-arrow-circle-down','#43e97b','#38f9d7','#e8f5e9','#2e7d32'],
    ['จำนวนเบิกออกรวม',  $summary['total_issued']    ?? 0, 'ชิ้น',  'fa-arrow-circle-up',  '#f093fb','#f5576c','#fce7f3','#be185d'],
];
foreach ($statCards as $i => $c):
?>
<div class="col-md-3 col-6 fade-in fade-in-<?= $i+1 ?>">
    <div class="stat-card">
        <div class="blob" style="background:linear-gradient(135deg,<?= $c[4] ?>,<?= $c[5] ?>);"></div>
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="text-muted mb-2" style="font-size:12px;font-weight:600;"><?= $c[0] ?></div>
                <div class="stat-number"><?= number_format($c[1]) ?></div>
                <div class="text-muted mt-1" style="font-size:12px;"><?= $c[2] ?></div>
            </div>
            <div class="icon" style="background:<?= $c[6] ?>;color:<?= $c[7] ?>;">
                <i class="fas <?= $c[3] ?>"></i>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ===== Top 10 Movement ===== -->
<div class="rpt-card fade-in fade-in-3">
    <div class="rpt-card-header">
        <div class="title">
            <span class="accent" style="background:linear-gradient(135deg,#f5576c,#f093fb);"></span>
            <i class="fas fa-fire" style="color:#f5576c;"></i>
            Top 10 สินค้าที่มีการเคลื่อนไหวมากที่สุด
        </div>
    </div>
    <div class="rpt-card-body">
        <?php if ($movementReport && $movementReport->num_rows > 0): ?>
        <table class="rpt-table">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>รหัสสินค้า</th>
                    <th>ชื่อสินค้า</th>
                    <th class="text-center">รับเข้า</th>
                    <th class="text-center">เบิกออก</th>
                    <th class="text-center">ยอดเคลื่อนไหว</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; while ($row = $movementReport->fetch_assoc()): ?>
                <tr>
                    <td>
                        <span class="rank-badge <?= $no <= 3 ? 'rank-'.$no : '' ?>"><?= $no++ ?></span>
                    </td>
                    <td><code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:5px;"><?= htmlspecialchars($row['product_code']) ?></code></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="text-center"><span class="badge-in">+<?= number_format($row['total_in']) ?> <?= $row['unit'] ?></span></td>
                    <td class="text-center"><span class="badge-out">-<?= number_format($row['total_out']) ?> <?= $row['unit'] ?></span></td>
                    <td class="text-center"><span class="badge-cnt"><?= number_format($row['movement_count']) ?> ครั้ง</span></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="empty-state"><i class="fas fa-inbox fa-2x d-block mb-2"></i>ไม่มีข้อมูลการเคลื่อนไหวในช่วงเวลานี้</div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== Receipt & Issue side by side ===== -->
<div class="row g-3">
    <!-- รับสินค้า -->
    <div class="col-lg-6 fade-in fade-in-4">
        <div class="rpt-card">
            <div class="rpt-card-header">
                <div class="title">
                    <span class="accent" style="background:linear-gradient(135deg,#43e97b,#38f9d7);"></span>
                    <i class="fas fa-truck-loading" style="color:#2e7d32;"></i>
                    รายงานการรับสินค้าเข้า
                </div>
                <button class="btn-export" onclick="exportExcel('receiptTable','รายงานรับสินค้า')">
                    <i class="fas fa-file-excel me-1"></i>Excel
                </button>
            </div>
            <div class="rpt-card-body scrollable">
                <?php if ($receiptReport && $receiptReport->num_rows > 0): ?>
                <table class="rpt-table" id="receiptTable">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>สินค้า</th>
                            <th class="text-center">จำนวน</th>
                            <th>ซัพพลายเออร์</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $receiptReport->fetch_assoc()): ?>
                        <tr>
                            <td style="white-space:nowrap;">
                                <i class="fas fa-calendar-alt text-muted me-1" style="font-size:11px;"></i>
                                <?= date('d/m/Y', strtotime($row['receipt_date'])) ?>
                            </td>
                            <td>
                                <div style="font-weight:700;font-size:12px;color:#667eea;"><?= htmlspecialchars($row['product_code']) ?></div>
                                <div style="font-size:12px;"><?= htmlspecialchars($row['product_name']) ?></div>
                            </td>
                            <td class="text-center"><span class="badge-in">+<?= number_format($row['quantity']) ?> <?= $row['unit'] ?></span></td>
                            <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($row['supplier_name'] ?? '-') ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox fa-2x d-block mb-2"></i>ไม่มีข้อมูลการรับสินค้า</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- เบิกสินค้า -->
    <div class="col-lg-6 fade-in fade-in-5">
        <div class="rpt-card">
            <div class="rpt-card-header">
                <div class="title">
                    <span class="accent" style="background:linear-gradient(135deg,#667eea,#764ba2);"></span>
                    <i class="fas fa-dolly" style="color:#4338ca;"></i>
                    รายงานการเบิกสินค้าออก
                </div>
                <button class="btn-export" onclick="exportExcel('issueTable','รายงานเบิกสินค้า')">
                    <i class="fas fa-file-excel me-1"></i>Excel
                </button>
            </div>
            <div class="rpt-card-body scrollable">
                <?php if ($issueReport && $issueReport->num_rows > 0): ?>
                <table class="rpt-table" id="issueTable">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>สินค้า</th>
                            <th class="text-center">จำนวน</th>
                            <th>วัตถุประสงค์</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $issueReport->fetch_assoc()): ?>
                        <tr>
                            <td style="white-space:nowrap;">
                                <i class="fas fa-calendar-alt text-muted me-1" style="font-size:11px;"></i>
                                <?= date('d/m/Y', strtotime($row['issue_date'])) ?>
                            </td>
                            <td>
                                <div style="font-weight:700;font-size:12px;color:#667eea;"><?= htmlspecialchars($row['product_code']) ?></div>
                                <div style="font-size:12px;"><?= htmlspecialchars($row['product_name']) ?></div>
                            </td>
                            <td class="text-center"><span class="badge-out">-<?= number_format($row['quantity']) ?> <?= $row['unit'] ?></span></td>
                            <td style="font-size:12px;color:#64748b;"><?= htmlspecialchars($row['purpose']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox fa-2x d-block mb-2"></i>ไม่มีข้อมูลการเบิกสินค้า</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function setPeriod(days) {
    const today = new Date();
    const from  = new Date();
    from.setDate(today.getDate() - days);
    document.querySelector('[name=date_from]').value = from.toISOString().slice(0,10);
    document.querySelector('[name=date_to]').value   = today.toISOString().slice(0,10);
}

function exportExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows  = [];

    // ดึง header
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push('"' + th.innerText.trim() + '"');
    });
    rows.push(headers.join(','));

    // ดึงข้อมูล
    table.querySelectorAll('tbody tr').forEach(tr => {
        const cols = [];
        tr.querySelectorAll('td').forEach(td => {
            let text = td.innerText.trim().replace(/\n+/g, ' ').replace(/"/g, '""');
            cols.push('"' + text + '"');
        });
        if (cols.length > 0) rows.push(cols.join(','));
    });

    // BOM + CSV → Excel อ่านภาษาไทยได้
    const bom  = '\uFEFF';
    const blob = new Blob([bom + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename + '_' + new Date().toISOString().slice(0,10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>

<?php include 'includes/footer.php'; ?>