<?php
require_once '../auth.php';
require_admin();

// เลือกช่วงเวลาสรุปยอด: day (รายวัน), week (รายสัปดาห์), month (รายเดือน), year (รายปี)
$period = $_GET['period'] ?? 'day';

if ($period === 'week') {
    $group_sql = "YEARWEEK(order_datetime, 1)";
    $label_sql = "CONCAT('สัปดาห์ที่ ', WEEK(order_datetime, 1), '/', YEAR(order_datetime))";
} elseif ($period === 'month') {
    $group_sql = "DATE_FORMAT(order_datetime, '%Y-%m')";
    $label_sql = "DATE_FORMAT(order_datetime, '%m/%Y')";
} elseif ($period === 'year') {
    $group_sql = "YEAR(order_datetime)";
    $label_sql = "YEAR(order_datetime)";
} else {
    $group_sql = "DATE(order_datetime)";
    $label_sql = "DATE_FORMAT(order_datetime, '%d/%m/%Y')";
}

// 1. ดึงยอดรวมทั้งหมด (KPI Cards)
$kpi = $pdo->query("
    SELECT 
        COALESCE(SUM(order_subtotal), 0) AS gross_sales,
        COALESCE(SUM(order_discount), 0) AS total_discount,
        COALESCE(SUM(order_total), 0) AS net_sales,
        COUNT(order_id) AS total_orders
    FROM orders
")->fetch();

// 2. ดึงตารางสรุปยอดขายตามช่วงเวลา (วัน/สัปดาห์/เดือน/ปี)
$summary_rows = $pdo->query("
    SELECT 
        $label_sql AS period_label,
        COUNT(order_id) AS bill_count,
        SUM(order_subtotal) AS gross,
        SUM(order_discount) AS discount,
        SUM(order_total) AS net
    FROM orders
    GROUP BY $group_sql
    ORDER BY MAX(order_datetime) DESC
")->fetchAll();

// 3. ดึงเมนูขายดี 5 อันดับแรก (Best Sellers)
$best_sellers = $pdo->query("
    SELECT 
        p.product_name,
        p.product_category,
        SUM(oi.item_quantity) AS total_qty,
        SUM(oi.item_quantity * oi.item_unit_price) AS total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    GROUP BY p.product_id
    ORDER BY total_qty DESC
    LIMIT 5
")->fetchAll();

// เตรียมข้อมูลส่งให้กราฟ Chart.js
$chart_labels = array_reverse(array_column($summary_rows, 'period_label'));
$chart_data = array_reverse(array_column($summary_rows, 'net'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Sales Summary & Dashboard — SUSHIYA</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include '../navbar.php'; ?>

<div class="container pb-5">
    <!-- หัวข้อ + ปุ่มสลับ วัน/สัปดาห์/เดือน/ปี สไตล์ iOS Segmented Control -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-0">Sales Summary</h4>
            <p class="text-muted small mb-0">รายงานสรุปยอดขายและวิเคราะห์เมนูขายดี</p>
        </div>
        <div class="p-1 rounded-4 d-inline-flex" style="background: #E5E5EA;">
            <a href="?period=day" class="btn btn-sm rounded-3 px-3 <?= $period==='day' ? 'bg-white shadow-sm fw-semibold' : 'text-muted' ?>">รายวัน</a>
            <a href="?period=week" class="btn btn-sm rounded-3 px-3 <?= $period==='week' ? 'bg-white shadow-sm fw-semibold' : 'text-muted' ?>">รายสัปดาห์</a>
            <a href="?period=month" class="btn btn-sm rounded-3 px-3 <?= $period==='month' ? 'bg-white shadow-sm fw-semibold' : 'text-muted' ?>">รายเดือน</a>
            <a href="?period=year" class="btn btn-sm rounded-3 px-3 <?= $period==='year' ? 'bg-white shadow-sm fw-semibold' : 'text-muted' ?>">รายปี</a>
        </div>
    </div>

    <!-- 4 การ์ดสรุปยอดด้านบน -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="content-card p-4">
                <div class="text-muted small">Gross Sales (ยอดขายก่อนลด)</div>
                <h3 class="fw-bold mt-1 mb-0">฿<?= number_format($kpi['gross_sales'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card p-4">
                <div class="text-muted small">Discounts (ส่วนลดสมาชิก 10%)</div>
                <h3 class="fw-bold mt-1 mb-0 text-danger">-฿<?= number_format($kpi['total_discount'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card p-4" style="border-left: 4px solid var(--salmon-red);">
                <div class="text-muted small">Net Sales (ยอดขายสุทธิ)</div>
                <h3 class="fw-bold mt-1 mb-0" style="color: var(--salmon-red);">฿<?= number_format($kpi['net_sales'], 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="content-card p-4">
                <div class="text-muted small">จำนวนบิลทั้งหมด</div>
                <h3 class="fw-bold mt-1 mb-0"><?= number_format($kpi['total_orders']) ?> <span class="fs-6 fw-normal text-muted">ออเดอร์</span></h3>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- กราฟแนวโน้มยอดขาย (เหมือนในรูป posref2.png) -->
        <div class="col-lg-8">
            <div class="content-card p-4 h-100">
                <h6 class="fw-bold text-uppercase text-muted small mb-3">แนวโน้มยอดขายสุทธิ (Net Sales Trend)</h6>
                <canvas id="salesChart" height="110"></canvas>
            </div>
        </div>

        <!-- อันดับเมนูซูชิขายดี Top 5 -->
        <div class="col-lg-4">
            <div class="content-card p-4 h-100">
                <h6 class="fw-bold text-uppercase text-muted small mb-3">🏆 เมนูซูชิขายดี (Best Sellers)</h6>
                <?php foreach ($best_sellers as $index => $item): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span class="badge rounded-pill me-2" style="background: #F2F2F7; color: #1D1D1F;"><?= $index + 1 ?></span>
                            <span class="fw-medium small"><?= htmlspecialchars($item['product_name']) ?></span>
                        </div>
                        <div class="text-end">
                            <span class="fw-bold small" style="color: var(--matcha-green);"><?= $item['total_qty'] ?> ชิ้น</span>
                            <div class="text-muted" style="font-size: 0.75rem;">฿<?= number_format($item['total_revenue'], 2) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ตารางสรุปยอดขายแยกตามช่วงเวลา (เลียนแบบตารางด้านล่างใน posref2.png) -->
    <div class="content-card p-4">
        <h6 class="fw-bold text-uppercase text-muted small mb-3">ตารางสรุปยอดขาย (แยกตามช่วงเวลาที่เลือก)</h6>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light text-muted small">
                    <tr>
                        <th>ช่วงเวลา</th>
                        <th class="text-center">จำนวนบิล</th>
                        <th class="text-end">Gross Sales (ยอดรวม)</th>
                        <th class="text-end">Discounts (ส่วนลด 10%)</th>
                        <th class="text-end">Net Sales (ยอดสุทธิ)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary_rows as $row): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($row['period_label']) ?></td>
                        <td class="text-center"><?= $row['bill_count'] ?></td>
                        <td class="text-end">฿<?= number_format($row['gross'], 2) ?></td>
                        <td class="text-end text-danger">(฿<?= number_format($row['discount'], 2) ?>)</td>
                        <td class="text-end fw-bold">฿<?= number_format($row['net'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'ยอดขายสุทธิ (บาท)',
            data: <?= json_encode($chart_data) ?>,
            borderColor: '#D94E34',
            backgroundColor: 'rgba(217, 78, 52, 0.1)',
            borderWidth: 2.5,
            pointBackgroundColor: '#FFFFFF',
            pointBorderColor: '#D94E34',
            pointRadius: 5,
            fill: true,
            tension: 0.35
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
            x: { grid: { display: false } }
        }
    }
});
</script>
</body>
</html>