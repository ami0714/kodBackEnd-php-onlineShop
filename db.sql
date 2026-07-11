-- ======================================================
-- NAMA DATABASE: online_shop_db (MySQL Version)
-- ======================================================
CREATE DATABASE IF NOT EXISTS online_shop_db;
USE online_shop_db;

-- 1. JADUAL users (menyimpan maklumat pengguna dan admin)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. JADUAL products (Maklumat am produk tanpa saiz/stok)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL, -- Harga asas produk
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE, -- Soft delete: Set FALSE kalau kedai tak nak jual dah
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. JADUAL product_variants (BARU: Menyimpan variasi saiz & stok)
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size VARCHAR(50) NOT NULL,      -- Contoh: 'S', 'M', 'L', 'XL'
    color VARCHAR(50) DEFAULT NULL, -- Boleh letak warna kalau ada (cth: 'Hitam', 'Putih')
    stock INT NOT NULL DEFAULT 0,   -- Pengurusan stok khusus untuk saiz ini
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 4. JADUAL cart_items (Troli: Rujuk kepada variant_id, bukan product_id)
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    variant_id INT NOT NULL,        -- Ditukar supaya tahu saiz apa user pilih
    quantity INT NOT NULL CHECK (quantity > 0),
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
);

-- 5. JADUAL orders (Pesanan utama)
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address VARCHAR(100),
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid','shipped', 'delivered') DEFAULT 'pending',
    billcode VARCHAR(100),          -- Integrasi ToyyibPay
    tracking_number VARCHAR(100) DEFAULT NULL,
    courier VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. JADUAL order_items (Item pesanan: Rujuk kepada variant_id)
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    variant_id INT NOT NULL,        -- Ditukar supaya sejarah jualan simpan saiz yang dibeli
    quantity INT NOT NULL CHECK (quantity > 0),
    price DECIMAL(10, 2) NOT NULL,  -- Harga kekal masa checkout
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE
);