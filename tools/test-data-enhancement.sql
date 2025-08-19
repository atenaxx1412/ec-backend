-- Test Data Enhancement Script for EC Site
-- Adds realistic test data for comprehensive testing

USE ecommerce_dev_db;

-- Add test users with different roles (avoiding duplicates)
INSERT IGNORE INTO users (email, password_hash, first_name, last_name, phone, is_active, email_verified_at) VALUES
('customer1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '田中', '太郎', '090-1111-2222', 1, NOW()),
('customer2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '佐藤', '花子', '090-3333-4444', 1, NOW()),
('premium@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'プレミアム', 'ユーザー', '090-5555-6666', 1, NOW()),
('vip@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'VIP', 'ユーザー', '090-7777-8888', 1, NOW());

-- Add user addresses (using correct user IDs)
INSERT INTO user_addresses (user_id, type, postal_code, state, city, address_line_1, address_line_2, first_name, last_name, is_default) VALUES
(1, 'shipping', '150-0001', '東京都', '渋谷区', '神宮前1-1-1', 'ABCビル101', 'テスト', 'ユーザー', 1),
(1, 'billing', '150-0001', '東京都', '渋谷区', '神宮前1-1-1', 'ABCビル101', 'テスト', 'ユーザー', 1),
(7, 'shipping', '160-0023', '東京都', '新宿区', '西新宿1-2-3', 'XYZマンション205', '田中', '太郎', 1),
(8, 'shipping', '530-0001', '大阪府', '大阪市北区', '梅田1-4-5', 'ユメタワー302', '佐藤', '花子', 1),
(9, 'shipping', '231-0023', '神奈川県', '横浜市中区', 'みなとみらい2-3-4', 'プレミアムレジデンス501', 'プレミアム', 'ユーザー', 1);

-- Add sample orders with different statuses (using correct user IDs)
INSERT INTO orders (user_id, status, total_amount, currency, payment_status, payment_method, shipping_method, shipping_cost, tax_amount, notes, created_at) VALUES
(1, 'delivered', 172580.00, 'JPY', 'paid', 'credit_card', 'standard', 800.00, 15689.09, '初回注文ありがとうございます', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 'shipped', 12800.00, 'JPY', 'paid', 'bank_transfer', 'express', 500.00, 1163.64, '', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(8, 'processing', 8900.00, 'JPY', 'paid', 'credit_card', 'standard', 800.00, 808.18, '', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(9, 'pending', 25600.00, 'JPY', 'pending', 'cash_on_delivery', 'standard', 800.00, 2327.27, 'プレミアム会員特別価格', NOW()),
(1, 'cancelled', 3200.00, 'JPY', 'refunded', 'credit_card', 'standard', 800.00, 290.91, 'お客様都合によるキャンセル', DATE_SUB(NOW(), INTERVAL 14 DAY));

-- Add order items for the orders
INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, product_name, product_sku) VALUES
-- Order 1 (delivered): iPhone 15 Pro + MacBook Air
(1, 1, 1, 149800.00, 149800.00, 'iPhone 15 Pro', 'IPH15P-128'),
(1, 2, 1, 164800.00, 164800.00, 'MacBook Air M2', 'MBA-M2-256'),
-- Order 2 (shipped): Wireless Earphones
(2, 3, 1, 9800.00, 9800.00, 'ワイヤレスイヤホン', 'WE-BT50-BK'),
-- Order 3 (processing): Denim Jacket
(3, 5, 1, 6900.00, 6900.00, 'デニムジャケット', 'DJ-M-001-BL'),
-- Order 4 (pending): Multiple items
(4, 3, 2, 9800.00, 19600.00, 'ワイヤレスイヤホン', 'WE-BT50-BK'),
(4, 7, 1, 3800.00, 3800.00, 'ヨガマット', 'YM-TPE-6MM'),
-- Order 5 (cancelled): Programming Book
(5, 8, 1, 3200.00, 3200.00, 'プログラミング入門書', 'BK-JS-001');

-- Add product reviews (using correct user IDs)
INSERT INTO product_reviews (product_id, user_id, order_id, rating, title, review_text, is_verified, is_approved) VALUES
(1, 1, 1, 5, '最高のスマートフォン！', 'カメラの性能が素晴らしく、動作もサクサクです。値段は高いですが、それに見合う価値があります。', 1, 1),
(2, 1, 1, 4, 'MacBook Air M2は軽くて高性能', 'とても軽くて持ち運びに便利です。バッテリーも長持ちします。ただし、端子が少ないのが少し不便。', 1, 1),
(3, 7, 2, 4, 'コスパ良いワイヤレスイヤホン', '音質も良く、ノイズキャンセリングも効果的です。この価格帯では非常に優秀。', 1, 1),
(5, 8, 3, 5, 'ヴィンテージ感が最高', 'デザインが気に入って購入。質感も良く、着心地も快適です。', 1, 1),
(1, 8, NULL, 3, '期待したほどではない', 'iPhone 14からの買い替えですが、そこまで大きな違いを感じません。', 0, 1);

-- Add items to cart (simulating current shopping sessions)
INSERT INTO cart (user_id, session_id, product_id, quantity, created_at, updated_at) VALUES
(7, NULL, 6, 1, NOW(), NOW()),
(7, NULL, 10, 2, NOW(), NOW()),
(NULL, 'guest_session_123', 4, 3, NOW(), NOW()),
(NULL, 'guest_session_123', 9, 1, NOW(), NOW()),
(9, NULL, 1, 1, NOW(), NOW());

-- Add items to wishlist
INSERT IGNORE INTO wishlist (user_id, session_id, product_id) VALUES
(1, NULL, 6),
(1, NULL, 12),
(7, NULL, 1),
(7, NULL, 14),
(8, NULL, 8),
(NULL, 'guest_session_456', 2),
(NULL, 'guest_session_456', 15);

-- Add product attributes for better product information
INSERT INTO product_attributes (product_id, attribute_name, attribute_value, sort_order) VALUES
-- iPhone 15 Pro attributes
(1, 'Color', 'ナチュラルチタニウム', 1),
(1, 'Storage', '128GB', 2),
(1, 'Display', '6.1インチ Super Retina XDR', 3),
(1, 'OS', 'iOS 17', 4),
-- MacBook Air M2 attributes
(2, 'Color', 'スターライト', 1),
(2, 'Memory', '8GB', 2),
(2, 'Storage', '256GB SSD', 3),
(2, 'Display', '13.6インチ Liquid Retina', 4),
-- Wireless Earphones attributes
(3, 'Color', 'ブラック', 1),
(3, 'Battery Life', '最大8時間', 2),
(3, 'Bluetooth', '5.0', 3),
(3, 'Water Resistance', 'IPX4', 4),
-- T-shirt attributes
(4, 'Size', 'M', 1),
(4, 'Color', 'ホワイト', 2),
(4, 'Material', 'コットン100%', 3);

-- Add product categories (many-to-many relationships)
INSERT IGNORE INTO product_categories (product_id, category_id, is_primary) VALUES
-- Electronics can also be in featured categories
(1, 8, 0), -- iPhone in New Arrivals
(2, 8, 0), -- MacBook in New Arrivals
(3, 9, 0), -- Wireless earphones in Sale items
-- Fashion items
(4, 2, 1), -- T-shirt primary category
(5, 2, 1), -- Denim jacket primary category
(5, 9, 0), -- Denim jacket also in Sale
-- Multi-category items
(7, 4, 1), -- Yoga mat in Sports
(7, 6, 0), -- Yoga mat also in Health & Beauty
(9, 6, 1), -- Moisturizer in Health & Beauty
(9, 9, 0); -- Moisturizer in Sale

-- Add product views for analytics
INSERT INTO product_views (product_id, ip_address, user_agent, viewed_at) VALUES
(1, '192.168.1.100', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(1, '192.168.1.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, '192.168.1.102', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, '192.168.1.100', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, '192.168.1.103', 'Mozilla/5.0 (Android 13)', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(3, '192.168.1.104', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, '192.168.1.105', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', DATE_SUB(NOW(), INTERVAL 2 HOUR));

-- Add daily analytics summary
INSERT IGNORE INTO analytics_daily (date, total_views, unique_visitors, total_orders, total_revenue, avg_order_value, conversion_rate) VALUES
(CURDATE(), 45, 28, 4, 219780.00, 54945.00, 0.1429),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 52, 35, 1, 8900.00, 8900.00, 0.0286),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 38, 24, 1, 12800.00, 12800.00, 0.0417),
(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 67, 42, 1, 172580.00, 172580.00, 0.0238);

-- Add admin users for testing (avoiding duplicates)
INSERT IGNORE INTO admins (email, password_hash, name, role, is_active) VALUES
('manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ストアマネージャー', 'admin', 1),
('staff@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'スタッフ', 'moderator', 1),
('supervisor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'スーパーバイザー', 'super_admin', 1);

-- Update inventory movements with more realistic data
INSERT INTO inventory_movements (product_id, type, quantity, previous_stock, new_stock, reason, reference_type, reference_id, created_at) VALUES
(1, 'out', 1, 45, 44, 'Order fulfillment', 'order', 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 'out', 1, 30, 29, 'Order fulfillment', 'order', 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(3, 'out', 1, 100, 99, 'Order fulfillment', 'order', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'out', 1, 75, 74, 'Order fulfillment', 'order', 3, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'in', 20, 99, 119, 'Stock replenishment', 'purchase', 1001, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7, 'adjustment', -5, 150, 145, 'Damaged items removal', 'adjustment', NULL, DATE_SUB(NOW(), INTERVAL 5 DAY));

-- Add more coupons with different conditions
INSERT INTO coupons (code, name, description, type, value, minimum_amount, maximum_discount, usage_limit, used_count, is_active, starts_at, expires_at) VALUES
('SUMMER2024', '夏のセール2024', '夏商品対象の15%オフ', 'percentage', 15.00, 5000.00, 3000.00, 100, 12, 1, DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('FIRST1000', '初回限定1000円オフ', '新規会員限定1000円割引', 'fixed_amount', 1000.00, 3000.00, NULL, 500, 156, 1, DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_ADD(NOW(), INTERVAL 90 DAY)),
('MEMBER200', 'メンバー限定200円オフ', '会員限定クーポン', 'fixed_amount', 200.00, 1000.00, NULL, NULL, 2847, 1, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY)),
('EXPIRED', '期限切れテスト', 'テスト用期限切れクーポン', 'percentage', 20.00, 1000.00, NULL, 50, 5, 0, DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Update products stock quantities to reflect inventory movements
UPDATE products SET stock_quantity = 44 WHERE id = 1;
UPDATE products SET stock_quantity = 29 WHERE id = 2;
UPDATE products SET stock_quantity = 119 WHERE id = 3;
UPDATE products SET stock_quantity = 74 WHERE id = 5;
UPDATE products SET stock_quantity = 145 WHERE id = 7;

SELECT 'Test data enhancement completed successfully!' as message;

-- Show summary of enhanced data
SELECT 'Data Summary:' as section;
SELECT 'Users:' as table_name, COUNT(*) as count FROM users
UNION ALL
SELECT 'Orders:' as table_name, COUNT(*) as count FROM orders
UNION ALL
SELECT 'Order Items:' as table_name, COUNT(*) as count FROM order_items
UNION ALL
SELECT 'Reviews:' as table_name, COUNT(*) as count FROM product_reviews
UNION ALL
SELECT 'Cart Items:' as table_name, COUNT(*) as count FROM cart
UNION ALL
SELECT 'Wishlist Items:' as table_name, COUNT(*) as count FROM wishlist
UNION ALL
SELECT 'Product Attributes:' as table_name, COUNT(*) as count FROM product_attributes
UNION ALL
SELECT 'Product Views:' as table_name, COUNT(*) as count FROM product_views
UNION ALL
SELECT 'Coupons:' as table_name, COUNT(*) as count FROM coupons
UNION ALL
SELECT 'Admins:' as table_name, COUNT(*) as count FROM admins;