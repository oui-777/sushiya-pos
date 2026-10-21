<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header("Location: reports/index.php");
} else {
    header("Location: pos/index.php");
}
exit;
?>