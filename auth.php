<?php
// ตัวช่วยตรวจสิทธิ์: เรียกใช้ที่บรรทัดแรกของทุกหน้า (ต่อจาก require_once '../auth.php';)
require_once __DIR__ . '/db.php';

// ต้องล็อกอินแล้ว (Admin หรือ Cashier)
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /sushiya-pos/login.php');
        exit;
    }
}

// ต้องเป็น Admin เท่านั้น (Cashier จะถูกส่งกลับหน้า POS)
function require_admin() {
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: /sushiya-pos/pos/index.php');
        exit;
    }
}
