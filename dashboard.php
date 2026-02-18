<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'Dashboard';

// ===============================
// กรองตามช่วงเวลา
$period = $_GET['period'] ?? 'month';
$periodMap = [
    'today' => "DATE(created_at) = CURDATE()",
    'week'  => "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)",
    'month' => "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
    'all'   => "1=1",
];
$periodLabel = [
    'today' => 'วันนี้',
    'week'  => 'อาทิตย์นี้',
    'month' => 'เดือนนี้',
    'all'   => 'ทั้งหมด',
];
$where = $periodMap[$period] ?? $periodMap['month'];

// ===============================
// สถิติพื้นฐาน
$stats = ['total_products' => 0, 'total_stock' => 0, 'low_stock' => 0, 'out_stock' => 0];

$r = $conn->query("SELECT COUNT(*) AS total FROM products");
if ($r) $stats['total_products'] = (int)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT SUM(quantity) AS total FROM inventory");
if ($r) $stats['total_stock'] = (int)($r->fetch_assoc()['total'] ?? 0);

$r = $conn->query("SELECT COUNT(*) AS total FROM inventory i JOIN products p ON i.product_id = p.product_id WHERE i.quantity <= p.min_stock AND i.quantity > 0");
if ($r) $stats['low_stock'] = (int)$r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM inventory WHERE quantity = 0");
if ($r) $stats['out_stock'] = (int)$r->fetch_assoc()['total'];

// ===============================
// ยอดรับ vs เบิก (ตามช่วงเวลา)
$receiveTotal = 0;
$issueTotal   = 0;

$r = $conn->query("SELECT SUM(total_quantity) AS total FROM goods_receipts WHERE {$where}");
if ($r) $receiveTotal = (int)($r->fetch_assoc()['total'] ?? 0);

$r = $conn->query("SELECT SUM(total_quantity) AS total FROM goods_issues WHERE {$where}");
if ($r) $issueTotal = (int)($r->fetch_assoc()['total'] ?? 0);

// ===============================
// กราฟรายวัน (30 วัน) - รับ vs เบิก
$dailyLabels   = [];
$dailyReceive  = [];
$dailyIssue    = [];

$r = $conn->query("
    SELECT DATE(receipt_date) AS d, SUM(total_quantity) AS total
    FROM goods_receipts
    WHERE receipt_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY d ORDER BY d
");
$receiveMap = [];
if ($r) while ($row = $r->fetch_assoc()) $receiveMap[$row['d']] = (int)$row['total'];

$r = $conn->query("
    SELECT DATE(issue_date) AS d, SUM(total_quantity) AS total
    FROM goods_issues
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY d ORDER BY d
");
$issueMap = [];
if ($r) while ($row = $r->fetch_assoc()) $issueMap[$row['d']] = (int)$row['total'];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('d/m', strtotime($date));
    $dailyLabels[]  = $label;
    $dailyReceive[] = $receiveMap[$date] ?? 0;
    $dailyIssue[]   = $issueMap[$date]   ?? 0;
}

// ===============================
// สินค้ายอดนิยม Top 5
$chartLabels = [];
$chartData   = [];
$r = $conn->query("
    SELECT p.product_name, SUM(gii.quantity) AS total_issue
    FROM goods_issue_items gii
    JOIN products p ON gii.product_id = p.product_id
    GROUP BY gii.product_id ORDER BY total_issue DESC LIMIT 5
");
if ($r) while ($row = $r->fetch_assoc()) {
    $chartLabels[] = $row['product_name'];
    $chartData[]   = (int)$row['total_issue'];
}

// ===============================
// สินค้าใกล้หมด
$lowStockProducts = $conn->query("
    SELECT p.product_code, p.product_name, i.quantity, p.min_stock, p.unit
    FROM products p JOIN inventory i ON p.product_id = i.product_id
    WHERE i.quantity <= p.min_stock ORDER BY i.quantity ASC LIMIT 10
");

// ===============================
// กิจกรรมล่าสุด (รวม รับ+เบิก)
$activities = $conn->query("
    (SELECT 'receive' AS type, gr.receipt_date AS date, gr.reference_no AS ref,
            CONCAT('รับสินค้า ', gr.total_quantity, ' รายการ') AS detail,
            u.full_name AS actor
     FROM goods_receipts gr LEFT JOIN users u ON gr.created_by = u.user_id
     ORDER BY gr.receipt_date DESC LIMIT 5)
    UNION ALL
    (SELECT 'issue' AS type, gi.issue_date AS date, gi.reference_no AS ref,
            CONCAT('เบิกสินค้า ', gi.total_quantity, ' รายการ') AS detail,
            u.full_name AS actor
     FROM goods_issues gi LEFT JOIN users u ON gi.created_by = u.user_id
     ORDER BY gi.issue_date DESC LIMIT 5)
    ORDER BY date DESC LIMIT 10
");

include 'includes/header.php';
?>

<style>
/* ===== Print ===== */
@media print {
    #sidebar, .no-print { display: none !important; }
    #content { padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    .stat-card { border: 1px solid #ddd !important; }
    body { background: #fff !important; }
}

/* ===== Period Filter ===== */
.period-btn {
    border: 2px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    padding: 7px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.period-btn:hover { border-color: #667eea; color: #667eea; }
.period-btn.active {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 4px 12px rgba(102,126,234,0.35);
}

/* ===== Stat Cards ===== */
.stat-card {
    padding: 24px;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    transition: transform 0.25s, box-shadow 0.25s;
    overflow: hidden;
    position: relative;
}
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
.stat-card .blob {
    position: absolute; right: -20px; top: -20px;
    width: 100px; height: 100px; border-radius: 50%; opacity: 0.08;
}
.stat-number {
    font-size: 2rem; font-weight: 800; line-height: 1;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.stat-card .icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center; font-size: 22px;
}

/* ===== Counter animation ===== */
.counter { display: inline-block; }

/* ===== Activity Timeline ===== */
.activity-item {
    display: flex; gap: 14px; padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    animation: fadeSlideIn 0.4s ease both;
}
.activity-item:last-child { border-bottom: none; }
.activity-dot {
    width: 38px; height: 38px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 14px;
}
.dot-receive { background: #e8f5e9; color: #388e3c; }
.dot-issue   { background: #fff3e0; color: #f57c00; }
.activity-info { flex: 1; min-width: 0; }
.activity-title { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
.activity-meta { font-size: 12px; color: #94a3b8; }

/* ===== Receive vs Issue Summary Cards ===== */
.flow-card {
    border-radius: 16px; padding: 20px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff; position: relative; overflow: hidden;
}
.flow-card.issue-card {
    background: linear-gradient(135deg, #f093fb, #f5576c);
}
.flow-card .flow-number {
    font-size: 2.4rem; font-weight: 800; line-height: 1;
}
.flow-card .flow-label { opacity: 0.85; font-size: 13px; }

/* ===== Animations ===== */
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes countUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.fade-in {
    opacity: 0;
    animation: fadeSlideIn 0.5s ease forwards;
}
.fade-in-1 { animation-delay: 0.05s; }
.fade-in-2 { animation-delay: 0.1s; }
.fade-in-3 { animation-delay: 0.15s; }
.fade-in-4 { animation-delay: 0.2s; }
.fade-in-5 { animation-delay: 0.25s; }
.fade-in-6 { animation-delay: 0.3s; }

/* ===== Chart card ===== */
.chart-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07); padding: 24px;
    margin-bottom: 22px;
}
.chart-card .chart-title {
    font-weight: 700; font-size: 15px; margin-bottom: 18px;
    display: flex; align-items: center; gap: 10px;
}

/* ===== Table ===== */
.table thead th { background: #f8fafc; font-weight: 700; font-size: 13px; }
.badge-stock { font-size: 12px; padding: 5px 10px; border-radius: 8px; font-weight: 600; }

/* ===== Page Header ===== */
.page-header-custom {
    background: #fff;
    border-radius: 16px;
    padding: 22px 26px;
    margin-bottom: 22px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
}
.page-header-custom h3 { margin: 0; font-weight: 800; font-size: 20px; }
</style>

<!-- Page Header -->
<div class="page-header-custom fade-in fade-in-1 no-print">
    <div>
        <h3><i class="fas fa-home text-primary me-2"></i> Dashboard</h3>
        <p class="text-muted mb-0 mt-1" style="font-size:13px;">ภาพรวมระบบคลังสินค้า เขมจิรา บิวตี้ช็อป</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Period Filter -->
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['today'=>'วันนี้','week'=>'อาทิตย์นี้','month'=>'เดือนนี้','all'=>'ทั้งหมด'] as $k => $v): ?>
                <a href="?period=<?= $k ?>" class="period-btn <?= $period === $k ? 'active' : '' ?>"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
        <!-- Print -->
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;">
            <i class="fas fa-print me-1"></i> พิมพ์
        </button>
    </div>
</div>

<!-- ======= Stat Cards ======= -->
<div class="row g-3 mb-4">
<?php
$cards = [
    ['สินค้าทั้งหมด',  $stats['total_products'], 'รายการ', 'fa-box',                 '#667eea', '#764ba2'],
    ['สต็อกรวม',       $stats['total_stock'],    'ชิ้น',   'fa-cubes',               '#43e97b', '#38f9d7'],
    ['สินค้าใกล้หมด',  $stats['low_stock'],      'รายการ', 'fa-exclamation-triangle', '#f6d365', '#fda085'],
    ['สินค้าหมดแล้ว',  $stats['out_stock'],      'รายการ', 'fa-times-circle',         '#f093fb', '#f5576c'],
];
foreach ($cards as $i => $c):
?>
    <div class="col-md-3 col-6 fade-in fade-in-<?= $i+1 ?>">
        <div class="stat-card">
            <div class="blob" style="background: linear-gradient(135deg, <?= $c[4] ?>, <?= $c[5] ?>);"></div>
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="text-muted mb-2" style="font-size:13px; font-weight:600;"><?= $c[0] ?></div>
                    <div class="stat-number counter" data-target="<?= $c[1] ?>"><?= number_format($c[1]) ?></div>
                    <div class="text-muted mt-1" style="font-size:12px;"><?= $c[2] ?></div>
                </div>
                <div class="icon" style="background: linear-gradient(135deg, <?= $c[4] ?>22, <?= $c[5] ?>22); color: <?= $c[4] ?>;">
                    <i class="fas <?= $c[3] ?>"></i>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ======= รับ vs เบิก Summary ======= -->
<div class="row g-3 mb-4">
    <div class="col-md-6 fade-in fade-in-3">
        <div class="flow-card">
            <div class="flow-label"><i class="fas fa-truck-loading me-2"></i>รับสินค้าเข้า (<?= $periodLabel[$period] ?>)</div>
            <div class="flow-number mt-2 counter" data-target="<?= $receiveTotal ?>"><?= number_format($receiveTotal) ?></div>
            <div class="flow-label mt-1">ชิ้น</div>
        </div>
    </div>
    <div class="col-md-6 fade-in fade-in-4">
        <div class="flow-card issue-card">
            <div class="flow-label"><i class="fas fa-dolly me-2"></i>เบิกสินค้าออก (<?= $periodLabel[$period] ?>)</div>
            <div class="flow-number mt-2 counter" data-target="<?= $issueTotal ?>"><?= number_format($issueTotal) ?></div>
            <div class="flow-label mt-1">ชิ้น</div>
        </div>
    </div>
</div>

<!-- ======= กราฟ ======= -->
<div class="row g-3 mb-2">
    <!-- กราฟรับ vs เบิก รายวัน -->
    <div class="col-lg-8 fade-in fade-in-4">
        <div class="chart-card">
            <div class="chart-title">
                <span style="background:linear-gradient(135deg,#667eea,#764ba2);width:6px;height:22px;border-radius:3px;display:inline-block;"></span>
                รับ vs เบิก รายวัน (30 วันล่าสุด)
            </div>
            <canvas id="lineChart" height="100"></canvas>
        </div>
    </div>

    <!-- กราฟสินค้ายอดนิยม -->
    <div class="col-lg-4 fade-in fade-in-5">
        <div class="chart-card">
            <div class="chart-title">
                <span style="background:linear-gradient(135deg,#f093fb,#f5576c);width:6px;height:22px;border-radius:3px;display:inline-block;"></span>
                สินค้าเบิกสูงสุด Top 5
            </div>
            <?php if (!empty($chartLabels)): ?>
                <canvas id="doughnutChart" height="200"></canvas>
            <?php else: ?>
                <div class="alert alert-info">ยังไม่มีข้อมูล</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======= ตาราง + กิจกรรม ======= -->
<div class="row g-3 mb-4">
    <!-- สินค้าใกล้หมด -->
    <div class="col-lg-7 fade-in fade-in-5">
        <div class="chart-card">
            <div class="chart-title">
                <span style="background:linear-gradient(135deg,#f6d365,#fda085);width:6px;height:22px;border-radius:3px;display:inline-block;"></span>
                <i class="fas fa-exclamation-triangle text-warning"></i> สินค้าที่ต้องแจ้งจัดซื้อ
            </div>
            <?php if ($lowStockProducts && $lowStockProducts->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อสินค้า</th>
                                <th class="text-center">คงเหลือ</th>
                                <th class="text-center">ขั้นต่ำ</th>
                                <th class="text-center">สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $lowStockProducts->fetch_assoc()): ?>
                                <tr>
                                    <td><code style="font-size:11px;"><?= htmlspecialchars($row['product_code']) ?></code></td>
                                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td class="text-center">
                                        <span class="badge-stock <?= $row['quantity'] == 0 ? 'bg-danger text-white' : 'bg-warning text-dark' ?>">
                                            <?= number_format($row['quantity']) ?> <?= $row['unit'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-muted"><?= number_format($row['min_stock']) ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?= $row['quantity'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>" style="font-size:11px;">
                                            <?= $row['quantity'] == 0 ? 'หมดสต็อก' : 'ใกล้หมด' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success mb-0"><i class="fas fa-check-circle me-2"></i>สต็อกสินค้าทุกรายการเพียงพอ</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- กิจกรรมล่าสุด -->
    <div class="col-lg-5 fade-in fade-in-6">
        <div class="chart-card">
            <div class="chart-title">
                <span style="background:linear-gradient(135deg,#43e97b,#38f9d7);width:6px;height:22px;border-radius:3px;display:inline-block;"></span>
                กิจกรรมล่าสุด
            </div>
            <?php if ($activities && $activities->num_rows > 0): ?>
                <?php $delay = 0; while ($row = $activities->fetch_assoc()): $delay += 50; ?>
                    <div class="activity-item" style="animation-delay: <?= $delay ?>ms;">
                        <div class="activity-dot <?= $row['type'] === 'receive' ? 'dot-receive' : 'dot-issue' ?>">
                            <i class="fas <?= $row['type'] === 'receive' ? 'fa-truck-loading' : 'fa-dolly' ?>"></i>
                        </div>
                        <div class="activity-info">
                            <div class="activity-title"><?= htmlspecialchars($row['detail']) ?></div>
                            <div class="activity-meta">
                                <i class="fas fa-hashtag me-1"></i><?= htmlspecialchars($row['ref']) ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($row['actor'] ?? 'ไม่ทราบ') ?>
                                &nbsp;·&nbsp;
                                <?= date('d/m/y H:i', strtotime($row['date'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>ยังไม่มีกิจกรรม</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ===== Counter Animation =====
document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.dataset.target) || 0;
    if (target === 0) return;
    let start = 0;
    const duration = 1200;
    const step = Math.ceil(target / (duration / 16));
    const timer = setInterval(() => {
        start = Math.min(start + step, target);
        el.textContent = start.toLocaleString('th-TH');
        if (start >= target) clearInterval(timer);
    }, 16);
});

// ===== Line Chart: รับ vs เบิก รายวัน =====
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($dailyLabels) ?>,
        datasets: [
            {
                label: 'รับสินค้า',
                data: <?= json_encode($dailyReceive) ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102,126,234,0.1)',
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4,
            },
            {
                label: 'เบิกสินค้า',
                data: <?= json_encode($dailyIssue) ?>,
                borderColor: '#f5576c',
                backgroundColor: 'rgba(245,87,108,0.08)',
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4,
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { usePointStyle: true, padding: 16, font: { family: 'Sarabun' } } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Sarabun' } } },
            x: { grid: { display: false }, ticks: { font: { family: 'Sarabun' }, maxTicksLimit: 10 } }
        }
    }
});

// ===== Doughnut Chart: สินค้ายอดนิยม =====
<?php if (!empty($chartLabels)): ?>
new Chart(document.getElementById('doughnutChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            data: <?= json_encode($chartData) ?>,
            backgroundColor: ['#667eea','#f5576c','#43e97b','#f6d365','#764ba2'],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 8,
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 12, font: { family: 'Sarabun', size: 12 } } }
        }
    }
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>