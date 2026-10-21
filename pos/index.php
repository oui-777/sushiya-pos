<?php
require_once '../auth.php';
require_login();

// ข้อความผลลัพธ์จากการขายครั้งก่อน (ใช้ redirect กันกดรีเฟรชแล้วบันทึกบิลซ้ำ)
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$error = '';

// 1. ระบบสมัครสมาชิกด่วนจากหน้า POS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_customer'])) {
    try {
        $stmt =$pdo->prepare("INSERT INTO customers (customer_name, customer_phone) VALUES (?, ?)");
        $stmt->execute([trim($_POST['customer_name']), trim($_POST['customer_phone'])]);$message = "✅ สมัครสมาชิกสำเร็จ! ค้นหาเบอร์โทรเพื่อรับส่วนลด 10% ได้เลย";
    } catch (PDOException $e) {$error = "❌ สมัครไม่ได้: เบอร์โทรศัพท์นี้ถูกลงทะเบียนไว้แล้ว";
    }
}

// 2. ประมวลผลการชำระเงิน + ตัดสต็อกใน Transaction เดียว
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    $cart = json_decode($_POST['cart_json'], true);
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $user_id = (int)$_SESSION['user_id'];

    if (is_array($cart) && !empty($cart)) {
        try {
            // รวมรายการสินค้าซ้ำ และตรวจว่าจำนวนเป็นจำนวนเต็มบวกเท่านั้น
            $merged = [];
            foreach ($cart as $item) {
                $pid = (int)($item['id'] ?? 0);
                $qty = (int)($item['qty'] ?? 0);
                if ($pid <= 0 || $qty <= 0) {
                    throw new Exception("ข้อมูลตะกร้าไม่ถูกต้อง");
                }
                $merged[$pid] = ($merged[$pid] ?? 0) + $qty;
            }
            ksort($merged); // ล็อกแถวตามลำดับเดียวกันเสมอ ลดโอกาส deadlock

            $pdo->beginTransaction();

            // ล็อกแถวสินค้า ตรวจสต็อก และใช้ "ราคาจากฐานข้อมูล" เท่านั้น
            $check_stmt = $pdo->prepare("SELECT product_name, product_price, product_stock FROM products WHERE product_id = ? AND product_is_active = 1 FOR UPDATE");
            $subtotal = 0;
            $lines = [];
            foreach ($merged as $pid => $qty) {
                $check_stmt->execute([$pid]);
                $prod = $check_stmt->fetch();
                if (!$prod) {
                    throw new Exception("ไม่พบเมนูที่เลือก หรือเมนูถูกปิดการขายแล้ว");
                }
                if ($prod['product_stock'] < $qty) {
                    throw new Exception("เมนู '" . $prod['product_name'] . "' มีสต็อกไม่เพียงพอ!");
                }
                $subtotal += $prod['product_price'] * $qty;
                $lines[] = [$pid, $qty, $prod['product_price']];
            }

            // ส่วนลดสมาชิก 10% (ตรวจว่ามีสมาชิกคนนี้อยู่จริง)
            $discount = 0.00;
            if ($customer_id) {
                $cust_stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
                $cust_stmt->execute([$customer_id]);
                if (!$cust_stmt->fetch()) {
                    throw new Exception("ไม่พบข้อมูลสมาชิก");
                }
                $discount = round($subtotal * 0.10, 2);
            }
            $net_total = $subtotal - $discount;

            $ord_stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_id, order_subtotal, order_discount, order_total) VALUES (?, ?, ?, ?, ?)");
            $ord_stmt->execute([$user_id, $customer_id, $subtotal, $discount, $net_total]);
            $order_id = $pdo->lastInsertId();

            $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, item_quantity, item_unit_price) VALUES (?, ?, ?, ?)");
            $stock_stmt = $pdo->prepare("UPDATE products SET product_stock = product_stock - ? WHERE product_id = ?");
            foreach ($lines as [$pid, $qty, $price]) {
                $item_stmt->execute([$order_id, $pid, $qty, $price]);
                $stock_stmt->execute([$qty, $pid]);
            }

            $pdo->commit();

            $_SESSION['flash_message'] = "✅ บันทึกการขายสำเร็จ! เลขที่บิล #" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " (ยอดสุทธิ ฿" . number_format($net_total, 2) . ")";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "❌ ระบบขัดข้อง บันทึกการขายไม่สำเร็จ กรุณาลองใหม่อีกครั้ง";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "❌ เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage());
        }
    }
}

// 3. ดึงรายการสินค้าและสมาชิก
$products =$pdo->query("SELECT * FROM products WHERE product_is_active = 1 ORDER BY product_category, product_id")->fetchAll();
$customers =$pdo->query("SELECT * FROM customers ORDER BY customer_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ขายหน้าร้าน (POS) — SUSHIYA</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .sushi-grid-card {
            cursor: pointer;
            user-select: none;
            overflow: hidden;
            position: relative;
        }
        .sushi-grid-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.07);
        }
        .sushi-grid-card:active {
            transform: scale(0.96);
        }
        .sushi-grid-card.out-of-stock {
            opacity: 0.45;
            pointer-events: none;
            filter: grayscale(80%);
        }
        .stock-pill {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(6px);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .btn-check:checked + .btn {
            background-color: #FFFFFF;
            color: #1D1D1F !important;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
<?php include '../navbar.php'; ?>

<div class="container pb-5">
    <?php if ($message): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm mb-4"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- ฝั่งซ้าย: ตะกร้าสินค้า & คิดเงิน -->
        <div class="col-lg-4">
            <div class="content-card p-4 d-flex flex-column" style="min-height: 75vh;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">🛒 รายการสั่งซื้อ</h5>
                    <button type="button" class="btn btn-sm btn-light rounded-pill text-danger" onclick="clearCart()">ล้างตะกร้า</button>
                </div>

                <!-- รายการในตะกร้า -->
                <div id="cartList" class="flex-grow-1 overflow-auto border-top border-bottom py-3 my-2" style="max-height: 340px;">
                    <div class="text-center text-muted py-5 small">แตะที่รูปซูชิฝั่งขวาเพื่อเพิ่มลงตะกร้า</div>
                </div>

                <!-- ฟอร์มเลือกประเภทลูกค้า & ค้นหาสมาชิก -->
                <form method="POST" id="checkoutForm" onsubmit="return prepareCheckout()">
                    <input type="hidden" name="cart_json" id="cartJsonInput">
                    
                    <div class="mb-3 mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small text-muted fw-semibold mb-0">ประเภทลูกค้า</label>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none py-0 px-0" data-bs-toggle="modal" data-bs-target="#quickCustomerModal" style="color: var(--salmon-red);">+ สมัครสมาชิกใหม่</button>
                        </div>

                        <!-- ปุ่มสลับ (iOS Segmented Control) -->
                        <div class="d-flex gap-2 p-1 rounded-3 mb-2" style="background: #E5E5EA;">
                            <input type="radio" class="btn-check" name="customer_type" id="typeGeneral" value="general" checked onchange="toggleCustomerType()">
                            <label class="btn btn-sm w-100 rounded-3 text-muted fw-semibold py-1" for="typeGeneral">ลูกค้าทั่วไป</label>

                            <input type="radio" class="btn-check" name="customer_type" id="typeMember" value="member" onchange="toggleCustomerType()">
                            <label class="btn btn-sm w-100 rounded-3 text-muted fw-semibold py-1" for="typeMember">สมาชิก (ลด 10%)</label>
                        </div>

                        <!-- กล่องค้นหาสมาชิก (ซ่อนไว้ตอนแรก) -->
                        <div id="memberSelection" style="display: none;">
                            <input type="text" id="memberSearch" class="form-control form-input-custom py-2 mb-1" placeholder="🔍 พิมพ์เบอร์โทร หรือ ชื่อ..." onkeyup="filterMembers()" autocomplete="off">
                            
                            <select name="customer_id" id="customerSelect" class="form-select form-input-custom mt-1" size="3" style="display: none; cursor: pointer;" onchange="renderCart()">
                                <option value="" disabled selected>-- เลือกสมาชิก --</option>
                                <?php foreach ($customers as$c): ?>
                                    <option value="<?= $c['customer_id'] ?>" data-search="<?= htmlspecialchars($c['customer_phone'] . ' ' .$c['customer_name']) ?>">
                                        <?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['customer_phone']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="memberNotFound" class="text-danger small mt-1" style="display: none;">ไม่พบข้อมูลสมาชิก</div>
                        </div>
                    </div>

                    <!-- สรุปยอดเงิน -->
                    <div class="p-3 rounded-4 mb-3" style="background: #F7F6F2;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">ยอดรวม (Subtotal)</span>
                            <span id="subtotalText">฿0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-2 text-danger">
                            <span>ส่วนลดสมาชิก (10%)</span>
                            <span id="discountText">-฿0.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-5 pt-2 border-top">
                            <span>ยอดสุทธิ (Net)</span>
                            <span style="color: var(--salmon-red);" id="netTotalText">฿0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-action-primary w-100 py-3 fs-6 shadow-sm" id="payBtn" disabled>
                        ชำระเงิน (Checkout)
                    </button>
                </form>
            </div>
        </div>

        <!-- ฝั่งขวา: Grid รูปเมนูซูชิ -->
        <div class="col-lg-8">
            <div class="row g-3">
                <?php foreach ($products as$p): 
                    $out =$p['product_stock'] <= 0;
                    $img_path = "../assets/images/" . ($p['product_image'] ?? 'default.png');$fallback_img = "https://images.unsplash.com/photo-1579871494447-9811cf80d66c?w=400&auto=format&fit=crop&q=80";
                ?>
                <div class="col-6 col-md-4">
                    <div class="content-card sushi-grid-card h-100 <?= $out ? 'out-of-stock' : '' ?>"
                         onclick="addToCart(<?= $p['product_id'] ?>, '<?= htmlspecialchars(addslashes($p['product_name'])) ?>', <?= $p['product_price'] ?>, <?=$p['product_stock'] ?>)">
                        
                        <span class="stock-pill <?= $out ? 'text-danger' : 'text-dark' ?>">
                            <?= $out ? 'สินค้าหมด' : 'เหลือ ' . $p['product_stock'] ?>
                        </span>

                        <img src="<?= htmlspecialchars($img_path) ?>" 
                             onerror="this.src='<?= $fallback_img ?>'" 
                             style="width: 100%; height: 135px; object-fit: cover;">

                        <div class="p-3">
                            <div class="small text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($p['product_category']) ?></div>
                            <div class="fw-semibold text-truncate mb-1"><?= htmlspecialchars($p['product_name']) ?></div>
                            <div class="fw-bold" style="color: var(--salmon-red);">฿<?= number_format($p['product_price'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal สมัครสมาชิกด่วน -->
<div class="modal fade" id="quickCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">➕ สมัครสมาชิกใหม่ (ลด 10%)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <form method="POST">
                    <input type="hidden" name="quick_add_customer" value="1">
                    <div class="mb-3">
                        <label class="form-label small text-muted">ชื่อลูกค้า</label>
                        <input type="text" name="customer_name" class="form-control form-input-custom" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted">เบอร์โทรศัพท์</label>
                        <input type="text" name="customer_phone" class="form-control form-input-custom" required>
                    </div>
                    <button type="submit" class="btn-action-primary w-100 py-2">ลงทะเบียน</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function addToCart(id, name, price, maxStock) {
    let existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty + 1 > maxStock) {
            alert('ไม่สามารถเพิ่มได้! เมนู "' + name + '" เหลือในสต็อกเพียง ' + maxStock + ' ชิ้น');
            return;
        }
        existing.qty++;
    } else {
        cart.push({ id, name, price, qty: 1, maxStock });
    }
    renderCart();
}

function updateQty(id, delta) {
    let item = cart.find(i => i.id === id);
    if (!item) return;
    if (item.qty + delta > item.maxStock) {
        alert('เกินจำนวนสต็อกคงเหลือ (' + item.maxStock + ' ชิ้น)');
        return;
    }
    item.qty += delta;
    if (item.qty <= 0) {
        cart = cart.filter(i => i.id !== id);
    }
    renderCart();
}

function clearCart() {
    cart = [];
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartList');
    const isMember = document.getElementById('typeMember').checked && document.getElementById('customerSelect').value !== '';
    
    if (cart.length === 0) {
        list.innerHTML = '<div class="text-center text-muted py-5 small">แตะที่รูปซูชิฝั่งขวาเพื่อเพิ่มลงตะกร้า</div>';
        document.getElementById('subtotalText').innerText = '฿0.00';
        document.getElementById('discountText').innerText = '-฿0.00';
        document.getElementById('netTotalText').innerText = '฿0.00';
        document.getElementById('payBtn').disabled = true;
        return;
    }

    let subtotal = 0;
    let html = '';
    cart.forEach(item => {
        let lineTotal = item.price * item.qty;
        subtotal += lineTotal;
        html += `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div style="max-width: 55%;">
                    <div class="fw-semibold small text-truncate">${item.name}</div>
                    <div class="text-muted" style="font-size: 0.75rem;">฿${item.price.toFixed(2)} / ชิ้น</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light rounded-circle px-2 py-0 border" onclick="updateQty(${item.id}, -1)">-</button>
                    <span class="fw-bold small">${item.qty}</span>
                    <button type="button" class="btn btn-sm btn-light rounded-circle px-2 py-0 border" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
                <div class="fw-semibold small text-end" style="min-width: 65px;">฿${lineTotal.toFixed(2)}</div>
            </div>
        `;
    });

    let discount = isMember ? +(subtotal * 0.10).toFixed(2) : 0;
    let netTotal = subtotal - discount;

    list.innerHTML = html;
    document.getElementById('subtotalText').innerText = '฿' + subtotal.toFixed(2);
    document.getElementById('discountText').innerText = '-฿' + discount.toFixed(2);
    document.getElementById('netTotalText').innerText = '฿' + netTotal.toFixed(2);
    document.getElementById('payBtn').disabled = false;
}

function prepareCheckout() {
    if (cart.length === 0) return false;
    document.getElementById('cartJsonInput').value = JSON.stringify(cart);
    return true;
}

function toggleCustomerType() {
    const isMember = document.getElementById('typeMember').checked;
    const memberDiv = document.getElementById('memberSelection');
    const select = document.getElementById('customerSelect');
    const search = document.getElementById('memberSearch');

    if (isMember) {
        memberDiv.style.display = 'block';
        search.focus();
    } else {
        memberDiv.style.display = 'none';
        select.value = "";
        search.value = "";
        select.style.display = 'none';
        document.getElementById('memberNotFound').style.display = 'none';
    }
    renderCart();
}

function filterMembers() {
    const input = document.getElementById('memberSearch').value.toLowerCase();
    const select = document.getElementById('customerSelect');
    const options = select.getElementsByTagName('option');
    const notFound = document.getElementById('memberNotFound');
    let hasMatch = false;

    if (input.trim() === '') {
        select.style.display = 'none';
        notFound.style.display = 'none';
        select.value = "";
        renderCart();
        return;
    }

    select.style.display = 'block';
    for (let i = 1; i < options.length; i++) { 
        const text = options[i].getAttribute('data-search').toLowerCase();
        if (text.includes(input)) {
            options[i].style.display = '';
            hasMatch = true;
        } else {
            options[i].style.display = 'none';
        }
    }

    if (!hasMatch) {
        select.style.display = 'none';
        notFound.style.display = 'block';
    } else {
        notFound.style.display = 'none';
    }
}
</script>
</body>
</html>