-- Database initialization script for EC Site Development
-- This script creates basic tables and sample data

USE ecommerce_dev_db;

-- Set charset for this session
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_parent_id (parent_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    short_description TEXT,
    price DECIMAL(10,2) NOT NULL,
    sale_price DECIMAL(10,2) DEFAULT NULL,
    sku VARCHAR(100) UNIQUE,
    stock_quantity INT DEFAULT 0,
    category_id INT,
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    weight DECIMAL(8,2) DEFAULT NULL,
    dimensions VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_category_id (category_id),
    INDEX idx_is_active (is_active),
    INDEX idx_is_featured (is_featured),
    INDEX idx_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User addresses table
CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('shipping', 'billing') DEFAULT 'shipping',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    company VARCHAR(255),
    address_line_1 VARCHAR(255) NOT NULL,
    address_line_2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(2) DEFAULT 'JP',
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shopping cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255),
    user_id INT DEFAULT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample categories
INSERT INTO categories (name, description) VALUES
('エレクトロニクス', 'スマートフォン、PC、家電製品など'),
('ファッション', '衣服、アクセサリー、バッグなど'),
('ホーム&キッチン', '家具、キッチン用品、インテリア'),
('スポーツ&アウトドア', 'スポーツ用品、アウトドアグッズ'),
('本・雑誌', '書籍、雑誌、電子書籍'),
('美容&健康', 'コスメ、ヘルスケア用品'),
('食品&飲料', '食品、飲み物、お酒'),
('ベビー&キッズ', '子供用品、おもちゃ'),
('自動車&バイク', 'カー用品、バイク用品');

-- Insert sample products
INSERT INTO products (name, description, short_description, price, sale_price, sku, stock_quantity, category_id, is_featured) VALUES
('iPhone 15 Pro', '最新のiPhone 15 Pro。高性能なA17 Proチップ搭載。', 'Apple iPhone 15 Pro 128GB', 159800.00, 149800.00, 'IPH15P-128', 50, 1, TRUE),
('MacBook Air M2', '薄くて軽い、驚異的なパフォーマンスのMacBook Air。', 'Apple MacBook Air 13インチ M2チップ', 164800.00, NULL, 'MBA-M2-256', 30, 1, TRUE),
('ワイヤレスイヤホン', '高音質ワイヤレスイヤホン。ノイズキャンセリング機能付き。', 'Bluetooth 5.0対応ワイヤレスイヤホン', 12800.00, 9800.00, 'WE-BT50-BK', 100, 1, FALSE),
('カジュアルTシャツ', '快適な着心地のコットン100%Tシャツ。', '無地 半袖Tシャツ メンズ', 2980.00, NULL, 'TS-M-001-WH', 200, 2, FALSE),
('デニムジャケット', 'ヴィンテージ風デニムジャケット。オールシーズン対応。', 'メンズ デニムジャケット Gジャン', 8900.00, 6900.00, 'DJ-M-001-BL', 75, 2, TRUE),
('コーヒーメーカー', '全自動コーヒーメーカー。豆から挽ける本格派。', 'ドリップ式コーヒーメーカー 10杯用', 15800.00, NULL, 'CM-AUTO-10', 25, 3, FALSE),
('ヨガマット', '高品質TPE素材のヨガマット。滑り止め付き。', 'エコ素材ヨガマット 6mm厚', 4500.00, 3800.00, 'YM-TPE-6MM', 150, 4, FALSE),
('プログラミング入門書', 'JavaScript完全入門。初心者から上級者まで。', 'JavaScript プログラミング入門', 3200.00, NULL, 'BK-JS-001', 80, 5, FALSE),
('保湿クリーム', '敏感肌にも優しい天然成分配合の保湿クリーム。', 'オーガニック保湿クリーム 50ml', 2800.00, 2300.00, 'CR-ORG-50', 120, 6, FALSE),
('オーガニックコーヒー', 'フェアトレード認証オーガニックコーヒー豆。', 'コロンビア産オーガニックコーヒー 200g', 1800.00, NULL, 'CF-ORG-COL-200', 90, 7, FALSE),
('知育おもちゃ', '木製の知育パズル。創造力と論理思考を育む。', '木製知育パズル 3歳以上', 3500.00, 2800.00, 'TY-WD-PZ-3', 60, 8, TRUE),
('カーフレグランス', '車内を上品な香りで満たすカーフレグランス。', '車用芳香剤 ラベンダーの香り', 1200.00, NULL, 'CF-LAV-001', 200, 9, FALSE),
('ゲーミングキーボード', 'RGB対応メカニカルゲーミングキーボード。', '青軸メカニカルキーボード RGB', 18900.00, 15900.00, 'KB-MEC-BL-RGB', 40, 1, TRUE),
('ワンピース', 'エレガントなフォーマルワンピース。', 'レディース フォーマルワンピース', 12800.00, NULL, 'OP-W-001-BK', 85, 2, FALSE),
('空気清浄機', 'HEPA フィルター搭載の高性能空気清浄機。', '空気清浄機 20畳対応 HEPA', 24800.00, 22800.00, 'AP-HEPA-20', 20, 3, TRUE);

-- Insert sample user (for testing)
-- Password is 'password123' hashed with PHP password_hash()
INSERT INTO users (email, password_hash, first_name, last_name, email_verified_at) VALUES
('test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'テスト', 'ユーザー', CURRENT_TIMESTAMP),
('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理者', 'ユーザー', CURRENT_TIMESTAMP);

-- Create admin table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample admin user
INSERT INTO admins (email, password_hash, name, role) VALUES
('admin@ec-site-dev.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '開発管理者', 'super_admin');

-- Create database indexes for performance
CREATE INDEX idx_products_price_active ON products(price, is_active);
CREATE INDEX idx_products_category_active ON products(category_id, is_active);
CREATE INDEX idx_categories_active ON categories(is_active);

-- Display setup completion message
SELECT 'Database setup completed successfully!' as message;
SELECT COUNT(*) as category_count FROM categories;
SELECT COUNT(*) as product_count FROM products;
SELECT COUNT(*) as user_count FROM users;
SELECT COUNT(*) as admin_count FROM admins;