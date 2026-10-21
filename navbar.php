<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION['role'] ?? 'cashier';
$username = $_SESSION['username'] ?? 'Guest';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/sushiya-pos/assets/style.css">

<nav class="main-navbar py-3 mb-4">
    <div class="container d-flex flex-wrap justify-content-between align-items-center">
        <a href="<?= $role === 'admin' ? '/sushiya-pos/reports/index.php' : '/sushiya-pos/pos/index.php' ?>" class="text-decoration-none d-flex align-items-center gap-2">
            <span style="font-size: 1.6rem;">🍣</span>
            <span class="fw-bold text-dark" style="letter-spacing: -0.5px; font-size: 1.2rem;">SUSHIYA <span class="fw-light text-muted" style="font-size: 0.85rem;">POS</span></span>
        </a>

        <div class="d-flex align-items-center gap-1">
            <a href="/sushiya-pos/pos/index.php" class="menu-link">🛒 หน้าขาย (POS)</a>
            
            <?php if ($role === 'admin'): ?>
                <a href="/sushiya-pos/reports/history.php" class="menu-link">🧾 ประวัติการขาย</a>
                <a href="/sushiya-pos/products/index.php" class="menu-link">🍱 เมนู & สต็อก</a>
                <a href="/sushiya-pos/customers/index.php" class="menu-link">👥 สมาชิก (ลด 10%)</a>
                <a href="/sushiya-pos/users/index.php" class="menu-link">👤 พนักงาน</a>
                <a href="/sushiya-pos/reports/index.php" class="menu-link">📊 Dashboard</a>
            <?php endif; ?>
        </div>


        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill bg-light text-dark border px-3 py-2">
                👤 <?= htmlspecialchars($username) ?> (<?= strtoupper($role) ?>)
            </span>
            <a href="/sushiya-pos/login.php?logout=1" class="btn btn-sm btn-outline-danger rounded-pill px-3">ออกจากระบบ</a>
        </div>
    </div>
</nav>