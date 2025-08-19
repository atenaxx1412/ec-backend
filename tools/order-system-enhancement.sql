-- Order System Enhancement for Epic #2
-- Comprehensive order processing system with status management

USE ecommerce_dev_db;

-- Add missing columns to orders table for comprehensive order management
ALTER TABLE orders 
ADD COLUMN guest_session_id VARCHAR(255) NULL AFTER session_id,
ADD COLUMN order_number VARCHAR(50) UNIQUE NULL AFTER id,
ADD COLUMN shipping_address JSON NULL AFTER shipping_cost,
ADD COLUMN billing_address JSON NULL AFTER shipping_address,
ADD COLUMN estimated_delivery DATE NULL AFTER delivered_at,
ADD COLUMN coupon_code VARCHAR(50) NULL AFTER discount_amount,
ADD COLUMN coupon_discount DECIMAL(8,2) DEFAULT 0.00 AFTER coupon_code;

-- Create order status history table for tracking all status changes
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    comment TEXT,
    changed_by_user_id INT NULL,
    changed_by_admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_status_history_order (order_id),
    INDEX idx_order_status_history_status (new_status),
    INDEX idx_order_status_history_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create inventory movements table for stock tracking
CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    reference_type ENUM('order', 'purchase', 'adjustment', 'return') NOT NULL,
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_inventory_movements_product (product_id),
    INDEX idx_inventory_movements_type (type),
    INDEX idx_inventory_movements_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create order notifications table for email/SMS tracking
CREATE TABLE IF NOT EXISTS order_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    type ENUM('confirmation', 'status_update', 'shipping', 'delivery') NOT NULL,
    method ENUM('email', 'sms', 'push') NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    content TEXT,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_notifications_order (order_id),
    INDEX idx_order_notifications_status (status),
    INDEX idx_order_notifications_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
ALTER TABLE orders ADD INDEX idx_orders_order_number (order_number);
ALTER TABLE orders ADD INDEX idx_orders_guest_session (guest_session_id);
ALTER TABLE orders ADD INDEX idx_orders_created_date (created_at);
ALTER TABLE orders ADD INDEX idx_orders_payment_status (payment_status);

-- Update order_items table with additional tracking
ALTER TABLE order_items 
ADD COLUMN product_image_url VARCHAR(500) NULL AFTER product_sku,
ADD COLUMN discount_amount DECIMAL(8,2) DEFAULT 0.00 AFTER total_price,
ADD COLUMN final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount;

-- Create function to generate unique order numbers
DELIMITER //
CREATE FUNCTION IF NOT EXISTS generate_order_number() 
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE order_num VARCHAR(50);
    DECLARE num_count INT DEFAULT 0;
    
    REPEAT
        SET order_num = CONCAT('ORD-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(FLOOR(RAND() * 10000), 4, '0'));
        SELECT COUNT(*) INTO num_count FROM orders WHERE order_number = order_num;
    UNTIL num_count = 0 END REPEAT;
    
    RETURN order_num;
END//
DELIMITER ;

-- Create stored procedure for updating inventory
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS update_product_inventory(
    IN p_product_id INT,
    IN p_quantity INT,
    IN p_reason VARCHAR(255),
    IN p_reference_type VARCHAR(50),
    IN p_reference_id INT
)
BEGIN
    DECLARE current_stock INT;
    DECLARE new_stock INT;
    
    START TRANSACTION;
    
    -- Get current stock with row lock
    SELECT stock_quantity INTO current_stock 
    FROM products 
    WHERE id = p_product_id 
    FOR UPDATE;
    
    -- Calculate new stock
    SET new_stock = current_stock - p_quantity;
    
    -- Validate stock availability
    IF new_stock < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;
    
    -- Update product stock
    UPDATE products 
    SET stock_quantity = new_stock,
        updated_at = NOW()
    WHERE id = p_product_id;
    
    -- Record inventory movement
    INSERT INTO inventory_movements (
        product_id, type, quantity, previous_stock, new_stock, 
        reason, reference_type, reference_id
    ) VALUES (
        p_product_id, 'out', p_quantity, current_stock, new_stock,
        p_reason, p_reference_type, p_reference_id
    );
    
    COMMIT;
END//
DELIMITER ;

-- Create stored procedure for order status updates
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS update_order_status(
    IN p_order_id INT,
    IN p_new_status VARCHAR(50),
    IN p_comment TEXT,
    IN p_changed_by_user_id INT,
    IN p_changed_by_admin_id INT
)
BEGIN
    DECLARE current_status VARCHAR(50);
    
    START TRANSACTION;
    
    -- Get current status
    SELECT status INTO current_status 
    FROM orders 
    WHERE id = p_order_id 
    FOR UPDATE;
    
    -- Update order status
    UPDATE orders 
    SET status = p_new_status,
        updated_at = NOW(),
        shipped_at = CASE WHEN p_new_status = 'shipped' THEN NOW() ELSE shipped_at END,
        delivered_at = CASE WHEN p_new_status = 'delivered' THEN NOW() ELSE delivered_at END
    WHERE id = p_order_id;
    
    -- Record status change history
    INSERT INTO order_status_history (
        order_id, previous_status, new_status, comment, 
        changed_by_user_id, changed_by_admin_id
    ) VALUES (
        p_order_id, current_status, p_new_status, p_comment,
        p_changed_by_user_id, p_changed_by_admin_id
    );
    
    COMMIT;
END//
DELIMITER ;

-- Create view for order summary with items
CREATE OR REPLACE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.user_id,
    o.guest_session_id,
    o.status,
    o.total_amount,
    o.currency,
    o.payment_status,
    o.payment_method,
    o.shipping_method,
    o.shipping_cost,
    o.tax_amount,
    o.discount_amount,
    o.coupon_code,
    o.coupon_discount,
    o.estimated_delivery,
    o.created_at,
    o.updated_at,
    COUNT(oi.id) as item_count,
    SUM(oi.quantity) as total_quantity,
    u.first_name,
    u.last_name,
    u.email
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
LEFT JOIN users u ON o.user_id = u.id
GROUP BY o.id;

-- Insert sample data for testing
INSERT IGNORE INTO orders (
    user_id, order_number, status, total_amount, currency, 
    payment_status, payment_method, shipping_method, shipping_cost, 
    tax_amount, notes, estimated_delivery
) VALUES 
(1, generate_order_number(), 'pending', 35600.00, 'JPY', 'pending', 'credit_card', 'standard', 800.00, 3236.36, 'Test order for development', DATE_ADD(NOW(), INTERVAL 7 DAY)),
(7, generate_order_number(), 'processing', 12800.00, 'JPY', 'paid', 'bank_transfer', 'express', 500.00, 1163.64, 'Express shipping order', DATE_ADD(NOW(), INTERVAL 3 DAY));

-- Create optimized indexes for reporting and analytics
CREATE INDEX idx_orders_status_payment ON orders(status, payment_status);
CREATE INDEX idx_orders_date_amount ON orders(created_at, total_amount);
CREATE INDEX idx_order_items_product_date ON order_items(product_id, created_at);

SELECT 'Order system enhancement completed successfully!' as message;

-- Show table structure summary
SELECT 'Enhanced Tables Summary:' as info;
SELECT 
    'orders' as table_name, 
    COUNT(*) as record_count,
    'Enhanced with guest support, addresses, order numbers' as features
FROM orders
UNION ALL
SELECT 
    'order_items' as table_name, 
    COUNT(*) as record_count,
    'Enhanced with discount tracking' as features
FROM order_items
UNION ALL
SELECT 
    'order_status_history' as table_name, 
    COUNT(*) as record_count,
    'New: Status change tracking' as features
FROM order_status_history
UNION ALL
SELECT 
    'inventory_movements' as table_name, 
    COUNT(*) as record_count,
    'New: Stock movement tracking' as features
FROM inventory_movements;