-- =============================================
-- GAMEVAULT DATABASE SCHEMA
-- MySQL 8.0+
-- =============================================

CREATE DATABASE IF NOT EXISTS gamevault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gamevault;

-- ---- USERS ----
CREATE TABLE users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,        -- bcrypt hash
  phone       VARCHAR(20),
  role        ENUM('user','admin') DEFAULT 'user',
  balance     DECIMAL(12,2) DEFAULT 0.00,   -- saldo internal (opsional)
  is_verified TINYINT(1) DEFAULT 0,
  avatar      VARCHAR(255),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email)
) ENGINE=InnoDB;

-- ---- GAME CATEGORIES ----
CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(120) NOT NULL UNIQUE,
  icon        VARCHAR(10),                  -- emoji
  banner      VARCHAR(255),
  sort_order  SMALLINT DEFAULT 0,
  is_active   TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, icon) VALUES
('Mobile Legends', 'mobile-legends', '🗡️'),
('Free Fire',      'free-fire',      '🔫'),
('PUBG Mobile',    'pubg',           '🪖'),
('Genshin Impact', 'genshin',        '✨'),
('Valorant',       'valorant',       '🎯'),
('Voucher Game',   'voucher',        '🎟️');

-- ---- PRODUCTS ----
CREATE TABLE products (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name        VARCHAR(200) NOT NULL,
  description TEXT,
  price       DECIMAL(12,2) NOT NULL,
  stock       INT DEFAULT -1,               -- -1 = unlimited
  sold        INT DEFAULT 0,
  emoji       VARCHAR(10) DEFAULT '🎮',
  image       VARCHAR(255),
  is_active   TINYINT(1) DEFAULT 1,
  sort_order  SMALLINT DEFAULT 0,
  meta        JSON,                         -- data tambahan (e.g. denom, qty)
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_category (category_id),
  INDEX idx_active (is_active)
) ENGINE=InnoDB;

INSERT INTO products (category_id, name, price, emoji, sold, meta) VALUES
(1, 'Diamond ML 100+5',       18000,  '🗡️', 12000, '{"denom":105}'),
(1, 'Diamond ML 250+30',      38000,  '🗡️', 8500,  '{"denom":280}'),
(1, 'Diamond ML 565+70',      80000,  '🗡️', 4200,  '{"denom":635}'),
(2, 'Diamond FF 100',         15000,  '🔫', 9200,  '{"denom":100}'),
(2, 'Diamond FF 310',         45000,  '🔫', 3100,  '{"denom":310}'),
(3, 'UC PUBG 60',             17000,  '🪖', 6100,  '{"denom":60}'),
(3, 'UC PUBG 325',            80000,  '🪖', 2200,  '{"denom":325}'),
(4, 'Genesis Crystal 160',    24000,  '✨', 4300,  '{"denom":160}'),
(4, 'Genesis Crystal 980',   130000,  '✨', 1800,  '{"denom":980}'),
(5, 'Valorant Points 1000',   75000,  '🎯', 2800,  '{"denom":1000}'),
(6, 'Google Play Rp 50.000',  52000,  '🎟️', 15000, '{"type":"google_play","amount":50000}'),
(6, 'Google Play Rp 100.000', 103000, '🎟️', 8900,  '{"type":"google_play","amount":100000}'),
(6, 'Steam Wallet $5',        80000,  '🎟️', 7600,  '{"type":"steam","amount":5}'),
(6, 'Steam Wallet $20',       310000, '🎟️', 3200,  '{"type":"steam","amount":20}');

-- ---- ORDERS ----
CREATE TABLE orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED,              -- NULL = guest
  order_code      VARCHAR(30) NOT NULL UNIQUE,
  status          ENUM('pending','paid','processing','completed','cancelled','refunded') DEFAULT 'pending',
  subtotal        DECIMAL(12,2) NOT NULL,
  fee             DECIMAL(12,2) DEFAULT 0,
  total           DECIMAL(12,2) NOT NULL,
  payment_method  VARCHAR(50),               -- shopeepay, qris, bca, dll
  payment_ref     VARCHAR(100),              -- referensi dari payment gateway
  paid_at         TIMESTAMP NULL,
  note            TEXT,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_order_code (order_code),
  INDEX idx_status (status),
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ---- ORDER ITEMS ----
CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  product_name VARCHAR(200) NOT NULL,        -- snapshot saat order
  price       DECIMAL(12,2) NOT NULL,
  qty         SMALLINT NOT NULL DEFAULT 1,
  -- Data game target
  game_user_id   VARCHAR(100),               -- ID akun dalam game
  game_server    VARCHAR(50),                -- server/zona
  game_username  VARCHAR(100),               -- username (opsional)
  delivery_code  TEXT,                       -- kode voucher / bukti pengiriman
  delivered_at   TIMESTAMP NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- ---- PAYMENTS (log dari payment gateway) ----
CREATE TABLE payments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        INT UNSIGNED NOT NULL,
  gateway         VARCHAR(50) NOT NULL,      -- 'shopeepay', 'midtrans', 'xendit'
  gateway_txn_id  VARCHAR(150),              -- ID transaksi dari gateway
  amount          DECIMAL(12,2) NOT NULL,
  status          ENUM('pending','success','failed','expired') DEFAULT 'pending',
  payload         JSON,                      -- raw response dari gateway
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  INDEX idx_gateway_txn (gateway_txn_id),
  INDEX idx_order (order_id)
) ENGINE=InnoDB;

-- ---- REVIEWS ----
CREATE TABLE reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  rating      TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment     TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uq_review (order_item_id, user_id)
) ENGINE=InnoDB;

-- ---- ADMIN LOG ----
CREATE TABLE admin_logs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT UNSIGNED,
  action      VARCHAR(100) NOT NULL,
  target_type VARCHAR(50),
  target_id   INT UNSIGNED,
  detail      TEXT,
  ip          VARCHAR(45),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
