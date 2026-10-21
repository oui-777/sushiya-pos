CREATE DATABASE IF NOT EXISTS sushiya_pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE sushiya_pos_db;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'cashier') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    product_category VARCHAR(50) NOT NULL DEFAULT 'Nigiri',
    product_price DECIMAL(10,2) NOT NULL,
    product_stock INT NOT NULL DEFAULT 0,
    product_image VARCHAR(255) DEFAULT 'default.png',
    product_is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    customer_id INT NULL,
    order_subtotal DECIMAL(10,2) NOT NULL,
    order_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    order_total DECIMAL(10,2) NOT NULL,
    order_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    item_quantity INT NOT NULL,
    item_unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

INSERT INTO users (username, password, role) VALUES 
-- รหัสผ่านเริ่มต้นของทั้งสองบัญชีคือ 1234 (เก็บเป็น bcrypt hash)
('admin', '$2y$10$..6VtBFVxoiSPaTFlydvCugZZqmFwXDmVPLG7bowF5Y7yVqtJ1h4q', 'admin'),
('cashier', '$2y$10$..6VtBFVxoiSPaTFlydvCugZZqmFwXDmVPLG7bowF5Y7yVqtJ1h4q', 'cashier');

INSERT INTO products (product_name, product_category, product_price, product_stock, product_image) VALUES
('Salmon Nigiri (ซูชิแซลมอน)', 'Nigiri', 45.00, 30, 'salmon.jpg'),
('Maguro Nigiri (ซูชิทูน่า)', 'Nigiri', 55.00, 20, 'maguro.jpg'),
('Unagi Roll (โรลปลาไหลย่าง)', 'Roll', 120.00, 15, 'unagi.jpg'),
('California Roll (แคลิฟอร์เนียโรล)', 'Roll', 95.00, 25, 'california.jpg'),
('Hotate Nigiri (ซูชิหอยเชลล์)', 'Nigiri', 75.00, 10, 'hotate.jpg'),
('Miso Soup (ซุปมิโซะ)', 'Soup', 35.00, 40, 'miso.jpg');

INSERT INTO customers (customer_name, customer_phone) VALUES
('คุณสมชาย สายกินจุ', '0812345678'),
('คุณแพรวา วาซาบิ', '0898765432'),
('คุณกานดา เลิฟเวอร์', '0823334444');

INSERT INTO orders (user_id, customer_id, order_subtotal, order_discount, order_total, order_datetime) VALUES
(1, 1, 300.00, 30.00, 270.00, NOW() - INTERVAL 2 DAY),
(2, NULL, 225.00, 0.00, 225.00, NOW() - INTERVAL 1 DAY),
(1, 2, 445.00, 44.50, 400.50, NOW());

INSERT INTO order_items (order_id, product_id, item_quantity, item_unit_price) VALUES
(1, 1, 4, 45.00),
(1, 3, 1, 120.00),
(2, 4, 2, 95.00),
(2, 6, 1, 35.00),
(3, 1, 6, 45.00),
(3, 3, 1, 120.00),
(3, 2, 1, 55.00);