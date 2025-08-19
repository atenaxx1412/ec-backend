<?php

/**
 * Order System Enhancement Script
 * Executes database enhancements for Epic #2 Order Processing System
 */

require_once __DIR__ . '/../Config/Database.php';

use ECBackend\Config\Database;

try {
    echo "🚀 Starting Order System Enhancement...\n";
    
    // Initialize database configuration
    Database::init();
    $pdo = Database::getConnection();
    
    echo "✅ Database connection established\n";
    
    // Enable multiple statement execution
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $sqlStatements = [
        // Add missing columns to orders table
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS guest_session_id VARCHAR(255) NULL AFTER session_id",
        
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS order_number VARCHAR(50) NULL AFTER id",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS shipping_address JSON NULL AFTER shipping_cost",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS billing_address JSON NULL AFTER shipping_address",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS estimated_delivery DATE NULL AFTER delivered_at",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(50) NULL AFTER discount_amount",
         
        "ALTER TABLE orders 
         ADD COLUMN IF NOT EXISTS coupon_discount DECIMAL(8,2) DEFAULT 0.00 AFTER coupon_code",
        
        // Create order status history table
        "CREATE TABLE IF NOT EXISTS order_status_history (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Create inventory movements table
        "CREATE TABLE IF NOT EXISTS inventory_movements (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Create order notifications table
        "CREATE TABLE IF NOT EXISTS order_notifications (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Add indexes for better performance
        "ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_guest_session (guest_session_id)",
        "ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_created_date (created_at)",
        "ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_orders_payment_status (payment_status)",
        
        // Update order_items table with additional tracking
        "ALTER TABLE order_items 
         ADD COLUMN IF NOT EXISTS product_image_url VARCHAR(500) NULL AFTER product_sku",
         
        "ALTER TABLE order_items 
         ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(8,2) DEFAULT 0.00 AFTER total_price",
         
        "ALTER TABLE order_items 
         ADD COLUMN IF NOT EXISTS final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_amount"
    ];
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($sqlStatements as $i => $sql) {
        try {
            $pdo->exec($sql);
            $successCount++;
            echo "✅ Statement " . ($i + 1) . " executed successfully\n";
        } catch (PDOException $e) {
            $errorCount++;
            // Only show error if it's not about column already existing
            if (strpos($e->getMessage(), 'Duplicate column name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                echo "⚠️ Statement " . ($i + 1) . " warning: " . $e->getMessage() . "\n";
            } else {
                echo "ℹ️ Statement " . ($i + 1) . " skipped (already exists)\n";
            }
        }
    }
    
    // Generate unique order numbers for existing orders without them
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM orders WHERE order_number IS NULL OR order_number = ''
        ");
        $stmt->execute();
        $ordersWithoutNumbers = $stmt->fetchAll();
        
        foreach ($ordersWithoutNumbers as $order) {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Ensure uniqueness
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
            $checkStmt->execute([$orderNumber]);
            
            while ($checkStmt->fetchColumn() > 0) {
                $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $checkStmt->execute([$orderNumber]);
            }
            
            $updateStmt = $pdo->prepare("UPDATE orders SET order_number = ? WHERE id = ?");
            $updateStmt->execute([$orderNumber, $order['id']]);
        }
        
        if (count($ordersWithoutNumbers) > 0) {
            echo "✅ Generated order numbers for " . count($ordersWithoutNumbers) . " existing orders\n";
        }
        
    } catch (PDOException $e) {
        echo "⚠️ Error generating order numbers: " . $e->getMessage() . "\n";
    }
    
    // Show table statistics
    $tables = ['orders', 'order_items', 'order_status_history', 'inventory_movements', 'order_notifications'];
    echo "\n📊 Table Statistics:\n";
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "   $table: $count records\n";
        } catch (PDOException $e) {
            echo "   $table: Table not found or error\n";
        }
    }
    
    echo "\n🎉 Order System Enhancement Completed!\n";
    echo "✅ Success: $successCount statements\n";
    echo "⚠️ Warnings: $errorCount statements\n";
    echo "\n📋 New Features Added:\n";
    echo "   • Order status history tracking\n";
    echo "   • Inventory movement logging\n";
    echo "   • Order notification system\n";
    echo "   • Guest order support\n";
    echo "   • Enhanced order items tracking\n";
    echo "   • Address management (shipping/billing)\n";
    echo "   • Coupon system support\n";
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}