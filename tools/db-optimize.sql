-- Database Optimization Script for EC Site
-- Optimizes indexes, adds performance tables, and enhances schema

USE ecommerce_dev_db;

-- Remove duplicate indexes and optimize existing ones
ALTER TABLE categories DROP INDEX idx_categories_active;

-- Add optimized composite indexes for common queries
CREATE INDEX idx_products_name_search ON products(name(20), is_active);
CREATE INDEX idx_products_price_range ON products(price, sale_price, is_active);
CREATE INDEX idx_products_created_featured ON products(created_at DESC, is_featured, is_active);
CREATE INDEX idx_products_stock_active ON products(stock_quantity, is_active);

-- Add full-text search indexes for product search
ALTER TABLE products ADD FULLTEXT(name, description);

-- Category optimization
CREATE INDEX idx_categories_hierarchy ON categories(parent_id, is_active, name(20));

-- User table optimization
CREATE INDEX idx_users_email_active ON users(email, is_active);
CREATE INDEX idx_users_created ON users(created_at DESC);

-- Shopping cart optimization
CREATE INDEX idx_cart_user_product ON cart(user_id, product_id);
CREATE INDEX idx_cart_session_product ON cart(session_id, product_id);
CREATE INDEX idx_cart_updated ON cart(updated_at DESC);

-- Address optimization
CREATE INDEX idx_addresses_user_type ON user_addresses(user_id, type, is_default);

-- Admin optimization
CREATE INDEX idx_admins_email_active ON admins(email, is_active);
CREATE INDEX idx_admins_role_active ON admins(role, is_active);

-- Create product view statistics table
CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_views_product (product_id),
    INDEX idx_product_views_date (viewed_at DESC),
    INDEX idx_product_views_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create order tables for future implementation
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255),
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'JPY',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_method VARCHAR(50),
    shipping_cost DECIMAL(8,2) DEFAULT 0.00,
    tax_amount DECIMAL(8,2) DEFAULT 0.00,
    discount_amount DECIMAL(8,2) DEFAULT 0.00,
    notes TEXT,
    shipped_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_orders_user (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_payment (payment_status),
    INDEX idx_orders_created (created_at DESC),
    INDEX idx_orders_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product reviews table
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT,
    order_id INT,
    rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    review_text TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_reviews_product (product_id),
    INDEX idx_reviews_rating (product_id, rating),
    INDEX idx_reviews_user (user_id),
    INDEX idx_reviews_approved (is_approved, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create wishlist table
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_id VARCHAR(255),
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    UNIQUE KEY unique_session_product (session_id, product_id),
    INDEX idx_wishlist_user (user_id),
    INDEX idx_wishlist_session (session_id),
    INDEX idx_wishlist_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('percentage', 'fixed_amount') NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    minimum_amount DECIMAL(10,2) DEFAULT 0.00,
    maximum_discount DECIMAL(10,2),
    usage_limit INT,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    starts_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_coupons_code (code),
    INDEX idx_coupons_active (is_active, starts_at, expires_at),
    INDEX idx_coupons_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product categories junction table (for multiple categories per product)
CREATE TABLE IF NOT EXISTS product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_product_categories_category (category_id),
    INDEX idx_product_categories_primary (is_primary, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create product attributes table (for size, color, etc.)
CREATE TABLE IF NOT EXISTS product_attributes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    attribute_name VARCHAR(100) NOT NULL,
    attribute_value VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_attributes_product (product_id),
    INDEX idx_product_attributes_name (attribute_name),
    INDEX idx_product_attributes_sort (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create inventory tracking table
CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reason VARCHAR(255),
    reference_type VARCHAR(50), -- 'order', 'return', 'adjustment', etc.
    reference_id INT,
    created_by INT, -- admin_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_inventory_product (product_id),
    INDEX idx_inventory_type (type),
    INDEX idx_inventory_created (created_at DESC),
    INDEX idx_inventory_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create analytics summary table
CREATE TABLE IF NOT EXISTS analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    total_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,
    avg_order_value DECIMAL(10,2) DEFAULT 0.00,
    conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
    top_product_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (date),
    INDEX idx_analytics_date (date DESC),
    INDEX idx_analytics_revenue (total_revenue DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints that were missing
ALTER TABLE products 
ADD CONSTRAINT fk_products_category 
FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

-- Update existing products to have product_categories entries
INSERT INTO product_categories (product_id, category_id, is_primary)
SELECT id, category_id, TRUE 
FROM products 
WHERE category_id IS NOT NULL
ON DUPLICATE KEY UPDATE is_primary = TRUE;

-- Create views for common queries
CREATE OR REPLACE VIEW active_products AS
SELECT p.*, c.name as category_name 
FROM products p 
LEFT JOIN categories c ON p.category_id = c.id 
WHERE p.is_active = 1;

CREATE OR REPLACE VIEW featured_products AS
SELECT p.*, c.name as category_name 
FROM products p 
LEFT JOIN categories c ON p.category_id = c.id 
WHERE p.is_active = 1 AND p.is_featured = 1;

CREATE OR REPLACE VIEW products_with_reviews AS
SELECT 
    p.*,
    c.name as category_name,
    COALESCE(AVG(r.rating), 0) as avg_rating,
    COUNT(r.id) as review_count
FROM products p 
LEFT JOIN categories c ON p.category_id = c.id 
LEFT JOIN product_reviews r ON p.id = r.product_id AND r.is_approved = 1
WHERE p.is_active = 1
GROUP BY p.id;

-- Insert sample data for new tables
INSERT INTO coupons (code, name, type, value, minimum_amount, is_active, expires_at) VALUES
('WELCOME10', '新規会員10%オフ', 'percentage', 10.00, 1000.00, TRUE, DATE_ADD(NOW(), INTERVAL 30 DAY)),
('SAVE500', '500円オフクーポン', 'fixed_amount', 500.00, 3000.00, TRUE, DATE_ADD(NOW(), INTERVAL 60 DAY)),
('FREESHIP', '送料無料', 'fixed_amount', 800.00, 5000.00, TRUE, DATE_ADD(NOW(), INTERVAL 90 DAY));

-- Create database functions for common operations
DELIMITER //

CREATE FUNCTION calculate_product_rating(product_id INT) 
RETURNS DECIMAL(3,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE avg_rating DECIMAL(3,2) DEFAULT 0.0;
    SELECT COALESCE(AVG(rating), 0.0) INTO avg_rating 
    FROM product_reviews 
    WHERE product_id = product_id AND is_approved = 1;
    RETURN avg_rating;
END //

CREATE FUNCTION get_product_stock(product_id INT) 
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE stock INT DEFAULT 0;
    SELECT COALESCE(stock_quantity, 0) INTO stock 
    FROM products 
    WHERE id = product_id;
    RETURN stock;
END //

DELIMITER ;

-- Create triggers for inventory tracking
DELIMITER //

CREATE TRIGGER update_inventory_on_stock_change
AFTER UPDATE ON products
FOR EACH ROW
BEGIN
    IF OLD.stock_quantity != NEW.stock_quantity THEN
        INSERT INTO inventory_movements (
            product_id, 
            type, 
            quantity, 
            previous_stock, 
            new_stock, 
            reason
        ) VALUES (
            NEW.id,
            CASE 
                WHEN NEW.stock_quantity > OLD.stock_quantity THEN 'in'
                ELSE 'out'
            END,
            ABS(NEW.stock_quantity - OLD.stock_quantity),
            OLD.stock_quantity,
            NEW.stock_quantity,
            'Stock update'
        );
    END IF;
END //

DELIMITER ;

-- Optimize table structure
OPTIMIZE TABLE products;
OPTIMIZE TABLE categories;
OPTIMIZE TABLE users;
OPTIMIZE TABLE cart;

-- Analyze tables for better query planning
ANALYZE TABLE products;
ANALYZE TABLE categories;
ANALYZE TABLE users;
ANALYZE TABLE cart;

-- Display optimization results
SELECT 'Database optimization completed successfully!' as message;
SELECT 
    TABLE_NAME,
    ENGINE,
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as 'Size_MB'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'ecommerce_dev_db' 
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;