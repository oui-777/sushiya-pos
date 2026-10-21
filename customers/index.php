<?php
require_once '../auth.php';
require_admin();

$error = '';
// จัดการเพิ่ม / แก้ไข / ลบ ลูกค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO customers (customer_name, customer_phone) VALUES (?, ?)");
            $stmt->execute([trim($_POST['customer_name']), trim($_POST['customer_phone'])]);
        } 
        elseif ($action === 'edit') {
            $stmt = $pdo->prepare("UPDATE customers SET customer_name = ?, customer_phone = ? WHERE customer_id = ?");
            $stmt->execute([trim($_POST['customer_name']), trim($_POST['customer_phone']), $_POST['customer_id']]);
        } 
        elseif ($action === 'delete') {
            // สมาชิกที่เคยมีประวัติการซื้อ ห้ามลบ (กันประวัติการขายสูญเสียข้อมูลลูกค้า)
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
            $cnt->execute([$_POST['customer_id']]);
            if ((int)$cnt->fetchColumn() > 0) {
                throw new RuntimeException("สมาชิกคนนี้มีประวัติการซื้อแล้ว จึงไม่สามารถลบได้");
            }
            $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->execute([$_POST['customer_id']]);
        }
        header("Location: index.php");
        exit;
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "เบอร์โทรศัพท์นี้ถูกลงทะเบียนเป็นสมาชิกไปแล้ว";
    }
}

// ค้นหาสมาชิก
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_name LIKE ? OR customer_phone LIKE ? ORDER BY customer_id DESC");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY customer_id DESC");
}
$customers = $stmt->fetchAll();

// ดึงข้อมูลกรณีคลิกปุ่มแก้ไข
$edit_customer = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_customer = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการลูกค้าสมาชิก — SUSHIYA</title>
</head>
<body>
<?php include '../navbar.php'; ?>

<div class="container pb-5">
    <div class="row g-4">
        <!-- ฟอร์มเพิ่ม / แก้ไขสมาชิก -->
        <div class="col-lg-4">
            <div class="content-card p-4">
                <h5 class="fw-bold mb-1">
                    <?= $edit_customer ? '✏️ แก้ไขข้อมูลสมาชิก' : '➕ สมัครสมาชิกใหม่' ?>
                </h5>
                <p class="text-muted small mb-4">สมาชิกรับสิทธิ์ส่วนลด 10% ทุกยอดบิล</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 small rounded-3"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="<?= $edit_customer ? 'edit' : 'add' ?>">
                    <?php if ($edit_customer): ?>
                        <input type="hidden" name="customer_id" value="<?= $edit_customer['customer_id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small text-muted">ชื่อ - นามสกุล ลูกค้า</label>
                        <input type="text" name="customer_name" class="form-control form-input-custom" 
                               value="<?= htmlspecialchars($edit_customer['customer_name'] ?? '') ?>" 
                               placeholder="เช่น คุณสมชาย สายซูชิ" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted">เบอร์โทรศัพท์ (ใช้ค้นหาตอนลด 10%)</label>
                        <input type="text" name="customer_phone" class="form-control form-input-custom" 
                               value="<?= htmlspecialchars($edit_customer['customer_phone'] ?? '') ?>" 
                               placeholder="08X-XXX-XXXX" required>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-action-primary w-100 py-2">
                            <?= $edit_customer ? 'บันทึกการแก้ไข' : 'ลงทะเบียนสมาชิก' ?>
                        </button>
                        <?php if ($edit_customer): ?>
                            <a href="index.php" class="btn btn-light rounded-3 px-3 pt-2">ยกเลิก</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ตารางรายชื่อลูกค้าสมาชิก -->
        <div class="col-lg-8">
            <div class="content-card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h5 class="fw-bold mb-0">👥 รายชื่อลูกค้าสมาชิก (<?= count($customers) ?> คน)</h5>
                    </div>
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control form-input-custom py-1" placeholder="🔍 ค้นหาชื่อ หรือ เบอร์โทร..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-dark rounded-3 px-3">ค้นหา</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="text-muted small">
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อลูกค้า</th>
                                <th>เบอร์โทรศัพท์</th>
                                <th>สิทธิพิเศษ</th>
                                <th>วันที่สมัคร</th>
                                <th class="text-end">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="text-muted">#<?= $c['customer_id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($c['customer_name']) ?></td>
                                <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($c['customer_phone']) ?></span></td>
                                <td><span class="badge rounded-pill" style="background:#E8F5E9; color:#2E7D32;">ส่วนลด 10%</span></td>
                                <td class="small text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                                <td class="text-end">
                                    <a href="index.php?edit_id=<?= $c['customer_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">แก้ไข</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันการลบลูกค้ารายนี้?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="customer_id" value="<?= $c['customer_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">ลบ</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>