-- Performance Optimization Script
-- Additional indexes and optimizations for common queries

USE ecommerce_dev_db;

-- Add optimized index for active products ordering by created_at
CREATE INDEX idx_products_active_created ON products(is_active, created_at DESC);

-- Add optimized index for featured products ordering
CREATE INDEX idx_products_active_featured_created ON products(is_active, is_featured, created_at DESC);

-- Add index for price-based queries with active status
CREATE INDEX idx_products_active_price ON products(is_active, price, sale_price);

-- Add index for stock availability queries
CREATE INDEX idx_products_active_stock ON products(is_active, stock_quantity);

-- Add index for review aggregation queries
CREATE INDEX idx_reviews_product_approved_rating ON product_reviews(product_id, is_approved, rating);

-- Add index for order statistics
CREATE INDEX idx_orders_status_created ON orders(status, created_at DESC);
CREATE INDEX idx_orders_user_status_created ON orders(user_id, status, created_at DESC);

-- Add index for cart performance
CREATE INDEX idx_cart_user_updated ON cart(user_id, updated_at DESC);
CREATE INDEX idx_cart_session_updated ON cart(session_id, updated_at DESC);

-- Add index for analytics performance
CREATE INDEX idx_product_views_product_date ON product_views(product_id, viewed_at DESC);

-- Update table statistics
ANALYZE TABLE products;
ANALYZE TABLE product_reviews;
ANALYZE TABLE orders;
ANALYZE TABLE cart;
ANALYZE TABLE product_views;

-- Performance monitoring queries
SELECT 'Performance Analysis:' as status;

-- Test the optimized product query
SELECT 'Testing product query performance...' as test;
SELECT BENCHMARK(1000, (
    SELECT COUNT(*) FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.is_active = 1 
    ORDER BY p.created_at DESC 
    LIMIT 10
)) as benchmark_result;

-- Show slow query log status
SHOW VARIABLES LIKE 'slow_query_log%';

-- Show current performance schema settings
SELECT 'Performance Schema Status:' as status;
SHOW VARIABLES LIKE 'performance_schema';

-- Show buffer pool usage
SELECT 'InnoDB Buffer Pool Status:' as status;
SHOW STATUS LIKE 'Innodb_buffer_pool%';

-- Show connection statistics
SELECT 'Connection Statistics:' as status;
SHOW STATUS LIKE 'Connections';
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';

-- Show query cache statistics (if enabled)
SELECT 'Query Performance:' as status;
SHOW STATUS LIKE 'Questions';
SHOW STATUS LIKE 'Queries';
SHOW STATUS LIKE 'Slow_queries';

-- Index usage analysis
SELECT 'Index Usage Analysis:' as status;
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    CARDINALITY,
    CASE 
        WHEN CARDINALITY = 0 THEN 'UNUSED'
        WHEN CARDINALITY < 10 THEN 'LOW_SELECTIVITY' 
        ELSE 'GOOD'
    END as index_effectiveness
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'ecommerce_dev_db' 
    AND TABLE_NAME IN ('products', 'orders', 'product_reviews', 'cart')
    AND INDEX_NAME != 'PRIMARY'
ORDER BY TABLE_NAME, CARDINALITY DESC;

SELECT 'Performance optimization completed!' as message;