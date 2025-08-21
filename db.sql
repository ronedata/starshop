-- CREATE DATABASE starshop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE starshop_db;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  compare_at_price DECIMAL(10,2) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  tags VARCHAR(255) DEFAULT NULL,
  specifications TEXT,
  image VARCHAR(400) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(20) UNIQUE,
  customer_name VARCHAR(255) NOT NULL,
  mobile VARCHAR(30) NOT NULL,
  address TEXT NOT NULL,
  shipping_area ENUM('dhaka','nationwide') NOT NULL DEFAULT 'dhaka',
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_method ENUM('COD') NOT NULL DEFAULT 'COD',
  note TEXT,
  status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed
INSERT INTO categories (name) VALUES ('পোশাক'), ('ইলেকট্রনিক্স'), ('অ্যাক্সেসরিজ');

INSERT INTO products (category_id, name, description, price, compare_at_price, stock, image) VALUES
(1, 'ক্লাসিক টি-শার্ট', '১০০% কটন, আরামদায়ক।', 450, 520, 120, 'uploads/tshirt.jpg'),
(2, 'ওয়্যারলেস ইয়ারবাড', 'ব্লুটুথ ৫.০, ২০ ঘন্টা ব্যাটারি।', 1650, NULL, 45, 'uploads/earbuds.jpg'),
(3, 'স্মার্টফোন কেস', 'শকপ্রুফ প্রটেকশন।', 250, 300, 200, 'uploads/case.jpg'),
(1, 'কটন হুডি', 'শীতের জন্য উষ্ণ, নরম।', 890, NULL, 75, 'uploads/hoodie.jpg');

INSERT INTO settings (`key`, `value`) VALUES
('store_name', 'স্টার শপ'),
('store_phone', '01812345678'),
('store_email', 'info@starshop.com'),
('store_about', 'বাংলাদেশের সেরা অনলাইন শপিং প্ল্যাটফর্ম। ক্যাশ অন ডেলিভারি সুবিধা সহ।'),
('delivery_dhaka', '60'),
('delivery_nationwide', '120'),
('cod_note', 'সারা দেশে ক্যাশ অন ডেলিভারি প্রযোজ্য।');
