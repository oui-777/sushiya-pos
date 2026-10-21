<?php
require_once '../auth.php';
require_admin();

$period = $_GET['period'] ?? 'all';
$search_date = $_GET['search_date'] ?? '';
$where_clause = "";
$params = [];

if (!empty($search_date)) {
    $where_clause = "WHERE DATE(o.order_datetime) = ?";
    $params[] = $search_date;
    $period = 'custom';
} elseif ($period === 'today') {
    $where_clause = "WHERE DATE(o.order_datetime) = CURDATE()";
} elseif ($period === 'this_week') {
    $where_clause = "WHERE YEARWEEK(o.order_datetime, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($period === 'this_month') {
    $where_clause = "WHERE YEAR(o.order_datetime) = YEAR(CURDATE()) AND MONTH(o.order_datetime) = MONTH(CURDATE())";
} elseif ($period === 'this_year') {
    $where_clause = "WHERE YEAR(o.order_datetime) = YEAR(CURDATE())";
}

$stmt = $pdo->prepare("
    SELECT o.*, c.customer_name, u.username 
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON o.user_id = u.user_id
    $where_clause
    ORDER BY o.order_datetime DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$item_stmt = $pdo->prepare("
    SELECT oi.*, p.product_name 
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการขาย (Sales History) — SUSHIYA</title>
    <!-- เพิ่ม script ของ Bootstrap สำหรับให้ Modal ทำงาน -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include '../navbar.php'; ?>

<div class="container pb-5">
    <div class="content-card p-4">
        
        <!-- แถบเครื่องมือ Filter (เหมือนเดิม) -->
        <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end mb-4 gap-3">
            <div>
                <h5 class="fw-bold mb-1">🧾 ประวัติการขายย้อนหลัง</h5>
                <p class="text-muted small mb-0">แสดงรายการบิลทั้งหมด (พบ <?= count($orders) ?> รายการ)</p>
            </div>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="GET" class="d-flex align-items-center gap-1 m-0 p-1 rounded-3" style="background: #F2F2F7;">
                    <input type="date" name="search_date" class="form-control border-0 bg-transparent text-muted px-2 py-0" style="font-size: 0.85rem; height: 28px; box-shadow: none;" value="<?= htmlspecialchars($search_date ?? '') ?>" required>
                    <button type="submit" class="btn btn-dark rounded-2 px-3 text-nowrap d-flex align-items-center justify-content-center" style="font-size: 0.8rem; height: 28px;">ค้นหา</button>
                    <?php if (!empty($search_date)): ?>
                        <a href="history.php" class="btn btn-white bg-white rounded-2 border px-3 text-nowrap d-flex align-items-center justify-content-center" style="font-size: 0.8rem; height: 28px; color: #1D1D1F; text-decoration: none;">ล้าง</a>
                    <?php endif; ?>
                </form>

                <div class="p-1 rounded-3 d-inline-flex" style="background: #F2F2F7;">
                    <a href="?period=all" class="btn btn-sm rounded-2 px-3 <?= ($period ?? 'all') ==='all' ? 'bg-white shadow-sm fw-semibold text-dark' : 'text-muted' ?>" style="font-size: 0.85rem;">ทั้งหมด</a>
                    <a href="?period=today" class="btn btn-sm rounded-2 px-3 <?= ($period ?? '') ==='today' ? 'bg-white shadow-sm fw-semibold text-dark' : 'text-muted' ?>" style="font-size: 0.85rem;">วันนี้</a>
                    <a href="?period=this_week" class="btn btn-sm rounded-2 px-3 <?= ($period ?? '') ==='this_week' ? 'bg-white shadow-sm fw-semibold text-dark' : 'text-muted' ?>" style="font-size: 0.85rem;">สัปดาห์นี้</a>
                    <a href="?period=this_month" class="btn btn-sm rounded-2 px-3 <?= ($period ?? '') ==='this_month' ? 'bg-white shadow-sm fw-semibold text-dark' : 'text-muted' ?>" style="font-size: 0.85rem;">เดือนนี้</a>
                    <a href="?period=this_year" class="btn btn-sm rounded-2 px-3 <?= ($period ?? '') ==='this_year' ? 'bg-white shadow-sm fw-semibold text-dark' : 'text-muted' ?>" style="font-size: 0.85rem;">ปีนี้</a>
                </div>
            </div>
        </div>

        <!-- ตารางแบบใหม่ (ซ่อนรายละเอียดรายการอาหาร) -->
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead class="table-light text-muted small">
                    <tr>
                        <th>เลขที่บิล</th>
                        <th>วัน-เวลา</th>
                        <th>ลูกค้า</th>
                        <th>พนักงานขาย</th>
                        <th class="text-end">ยอดสุทธิ</th>
                        <th class="text-center">ใบเสร็จ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <div style="font-size: 2rem; margin-bottom: 10px;">🔍</div>
                                ไม่พบข้อมูลการขายในช่วงเวลานี้
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="fw-bold text-dark">#<?= str_pad($o['order_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($o['order_datetime'])) ?></td>
                            <td>
                                <?php if ($o['customer_name']): ?>
                                    <span class="badge rounded-pill" style="background:#E8F5E9; color:#2E7D32;">👤 <?= htmlspecialchars($o['customer_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">ลูกค้าทั่วไป</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($o['username'] ?? 'System') ?></td>
                            <td class="text-end fw-bold" style="color: var(--salmon-red);">฿<?= number_format($o['order_total'], 2) ?></td>
                            <td class="text-center">
                                <!-- ปุ่มกดเพื่อเปิดป๊อปอัป -->
                                <button type="button" class="btn btn-sm btn-light rounded-pill border px-3" data-bs-toggle="modal" data-bs-target="#receiptModal<?= $o['order_id'] ?>">
                                    📄 ดูรายละเอียด
                                </button>
                            </td>
                        </tr>

                        <!-- หน้าต่างป๊อปอัปใบเสร็จ (Modal) สำหรับแต่ละบิล -->
                        <div class="modal fade" id="receiptModal<?= $o['order_id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-sm">
                                <div class="modal-content border-0 rounded-4 shadow">
                                    <div class="modal-header border-0 pb-0 justify-content-center position-relative">
                                        <h6 class="modal-title fw-bold">ใบเสร็จรับเงิน</h6>
                                        <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.7rem;"></button>
                                    </div>
                                    <div class="modal-body p-4 pt-3">
                                        <div class="text-center mb-3 border-bottom pb-3">
                                            <div class="fs-4 mb-1">🍣</div>
                                            <div class="fw-bold">SUSHIYA POS</div>
                                            <div class="small text-muted">บิลเลขที่: #<?= str_pad($o['order_id'], 4, '0', STR_PAD_LEFT) ?></div>
                                            <div class="small text-muted"><?= date('d/m/Y H:i', strtotime($o['order_datetime'])) ?></div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <?php 
                                            $item_stmt->execute([$o['order_id']]);
                                            $items = $item_stmt->fetchAll();
                                            foreach ($items as $it): 
                                            ?>
                                                <div class="d-flex justify-content-between small mb-2">
                                                    <div>
                                                        <?= htmlspecialchars($it['product_name']) ?><br>
                                                        <span class="text-muted" style="font-size: 0.75rem;">x<?= $it['item_quantity'] ?> @ ฿<?= number_format($it['item_unit_price'], 2) ?></span>
                                                    </div>
                                                    <div class="text-end fw-medium">฿<?= number_format($it['item_quantity'] * $it['item_unit_price'], 2) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="border-top pt-2">
                                            <div class="d-flex justify-content-between small text-muted mb-1">
                                                <span>ยอดรวม (Subtotal)</span>
                                                <span>฿<?= number_format($o['order_subtotal'], 2) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between small text-danger mb-2">
                                                <span>ส่วนลด (Discount)</span>
                                                <span>-฿<?= number_format($o['order_discount'], 2) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between fw-bold fs-6 mt-2 pt-2 border-top">
                                                <span>ยอดสุทธิ (Net Total)</span>
                                                <span style="color: var(--salmon-red);">฿<?= number_format($o['order_total'], 2) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- จบส่วนป๊อปอัป -->

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>