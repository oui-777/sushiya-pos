<?php
require_once 'db.php';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

// ถ้าล็อกอินอยู่แล้ว ส่งไปหน้าหลักตามสิทธิ์
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // ตรวจสอบด้วย password_verify เท่านั้น
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true); // กัน session fixation
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: index.php");
        exit;
    } else {
        $error = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ — SUSHIYA POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="content-card p-5" style="width: 100%; max-width: 400px;">
        <div class="text-center mb-4">
            <div style="font-size: 3rem;">🍣</div>
            <h4 class="fw-bold mt-2 mb-1">SUSHIYA POS</h4>
            <p class="text-muted small">ระบบจัดการร้านซูชิและคลังสินค้า</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small rounded-3 text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label small text-muted">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control form-input-custom" placeholder="admin หรือ cashier" required>
            </div>
            <div class="mb-4">
                <label class="form-label small text-muted">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control form-input-custom" placeholder="••••" required>
            </div>
            <button type="submit" class="btn-action-primary w-100 py-2">เข้าสู่ระบบ</button>
        </form>
        
    </div>
</body>
</html>