<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\Response;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;

/**
 * Order Controller
 * Handles comprehensive order processing system including:
 * - Order creation from cart
 * - Order management and status tracking
 * - Inventory integration
 * - Notification system
 * - Guest and authenticated user support
 */
class OrderController extends BaseController
{
    private const VALID_STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'
    ];
    
    private const VALID_PAYMENT_STATUSES = [
        'pending', 'paid', 'failed', 'refunded'
    ];
    
    private const SHIPPING_METHODS = [
        'standard' => ['name' => '標準配送', 'cost' => 800, 'days' => 7],
        'express' => ['name' => '速達配送', 'cost' => 1500, 'days' => 3],
        'overnight' => ['name' => '翌日配送', 'cost' => 2500, 'days' => 1]
    ];
    
    /**
     * Get user orders with pagination and filtering
     * GET /api/orders
     */
    public function index(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            $page = max(1, (int) $this->getQuery('page', 1));
            $limit = min(50, max(1, (int) $this->getQuery('limit', 10)));
            $status = $this->getQuery('status');
            $offset = ($page - 1) * $limit;
            
            $db = Database::getConnection();
            
            // Build WHERE clause
            $whereConditions = ['o.user_id = ?'];
            $params = [$userId];
            
            if ($status && in_array($status, self::VALID_STATUSES)) {
                $whereConditions[] = 'o.status = ?';
                $params[] = $status;
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) 
                FROM orders o 
                WHERE $whereClause
            ");
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();
            
            // Get orders with items
            $stmt = $db->prepare("
                SELECT 
                    o.id,
                    o.order_number,
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
                    o.notes,
                    o.estimated_delivery,
                    o.shipped_at,
                    o.delivered_at,
                    o.created_at,
                    o.updated_at,
                    COUNT(oi.id) as item_count,
                    SUM(oi.quantity) as total_quantity
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE $whereClause
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Process orders
            $processedOrders = array_map([$this, 'processOrderSummary'], $orders);
            
            $pagination = [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit),
                'has_next' => $page < ceil($totalCount / $limit),
                'has_prev' => $page > 1
            ];
            
            $this->logRequest('orders.index', [
                'user_id' => $userId,
                'page' => $page,
                'status_filter' => $status,
                'results_count' => count($processedOrders)
            ]);
            
            return $this->success([
                'orders' => $processedOrders,
                'pagination' => $pagination
            ], 'Orders retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve orders',
                500,
                $e
            );
        }
    }
    
    /**
     * Get specific order details
     * GET /api/orders/{id}
     */
    public function show(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            $orderId = (int) $this->getParam('id');
            
            if ($orderId <= 0) {
                throw new ApiException('Invalid order ID', 400, null, [], 'INVALID_ORDER_ID');
            }
            
            $order = $this->getOrderWithItems($orderId, $userId);
            
            if (!$order) {
                throw new ApiException('Order not found', 404, null, [], 'ORDER_NOT_FOUND');
            }
            
            $this->logRequest('orders.show', [
                'user_id' => $userId,
                'order_id' => $orderId
            ]);
            
            return $this->success($order, 'Order details retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve order details',
                500,
                $e
            );
        }
    }
    
    /**
     * Create new order from cart
     * POST /api/orders
     */
    public function create(): array
    {
        try {
            $this->validateRequired(['shipping_method', 'shipping_address']);
            
            $userId = $this->user['id'] ?? null;
            $guestSessionId = $this->getBody('guest_session_id');
            $shippingMethod = $this->getBody('shipping_method');
            $shippingAddress = $this->getBody('shipping_address');
            $billingAddress = $this->getBody('billing_address', $shippingAddress);
            $paymentMethod = $this->getBody('payment_method', 'pending');
            $couponCode = $this->getBody('coupon_code');
            $notes = $this->getBody('notes', '');
            
            // Validate inputs
            if (!in_array($shippingMethod, array_keys(self::SHIPPING_METHODS))) {
                throw new ApiException('Invalid shipping method', 400, null, [], 'INVALID_SHIPPING_METHOD');
            }
            
            if (!$userId && !$guestSessionId) {
                throw new ApiException('User ID or guest session ID required', 400, null, [], 'USER_OR_SESSION_REQUIRED');
            }
            
            $db = Database::getConnection();
            $db->beginTransaction();
            
            try {
                // Get cart items
                $cartItems = $this->getCartItems($userId, $guestSessionId);
                
                if (empty($cartItems)) {
                    throw new ApiException('Cart is empty', 400, null, [], 'EMPTY_CART');
                }
                
                // Validate stock availability
                $this->validateStockAvailability($cartItems);
                
                // Calculate order totals
                $totals = $this->calculateOrderTotals($cartItems, $shippingMethod, $couponCode);
                
                // Generate unique order number
                $orderNumber = $this->generateOrderNumber();
                
                // Create order
                $orderId = $this->createOrderRecord([
                    'user_id' => $userId,
                    'guest_session_id' => $guestSessionId,
                    'order_number' => $orderNumber,
                    'shipping_method' => $shippingMethod,
                    'shipping_address' => $shippingAddress,
                    'billing_address' => $billingAddress,
                    'payment_method' => $paymentMethod,
                    'coupon_code' => $couponCode,
                    'notes' => $notes,
                    'totals' => $totals
                ]);
                
                // Create order items
                $this->createOrderItems($orderId, $cartItems);
                
                // Update inventory
                $this->updateInventoryForOrder($orderId, $cartItems);
                
                // Clear cart
                $this->clearCartAfterOrder($userId, $guestSessionId);
                
                // Create initial status history
                $this->createStatusHistory($orderId, null, 'pending', 'Order created', $userId);
                
                // Schedule notification
                $this->scheduleOrderNotification($orderId, 'confirmation');
                
                $db->commit();
                
                // Get complete order details
                $order = $this->getOrderWithItems($orderId, $userId);
                
                $this->logRequest('orders.create', [
                    'user_id' => $userId,
                    'guest_session_id' => $guestSessionId,
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'total_amount' => $totals['total']
                ]);
                
                return $this->success($order, 'Order created successfully', 201);
                
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new DatabaseException(
                'Failed to create order',
                500,
                $e
            );
        }
    }
    
    /**
     * Update order status (Admin only)
     * PUT /api/orders/{id}/status
     */
    public function updateStatus(): array
    {
        $this->requireAuth();
        
        try {
            $this->validateRequired(['status']);
            
            $orderId = (int) $this->getParam('id');
            $newStatus = $this->getBody('status');
            $comment = $this->getBody('comment', '');
            $adminId = $this->user['id']; // TODO: Add admin role check
            
            if (!in_array($newStatus, self::VALID_STATUSES)) {
                throw new ApiException('Invalid status', 400, null, [], 'INVALID_STATUS');
            }
            
            $db = Database::getConnection();
            $db->beginTransaction();
            
            try {
                // Get current order
                $stmt = $db->prepare("
                    SELECT id, status, user_id 
                    FROM orders 
                    WHERE id = ?
                ");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$order) {
                    throw new ApiException('Order not found', 404, null, [], 'ORDER_NOT_FOUND');
                }
                
                $currentStatus = $order['status'];
                
                if ($currentStatus === $newStatus) {
                    throw new ApiException('Order already has this status', 400, null, [], 'STATUS_UNCHANGED');
                }
                
                // Update order status
                $updateFields = ['status = ?', 'updated_at = NOW()'];
                $updateParams = [$newStatus];
                
                // Set timestamp fields based on status
                if ($newStatus === 'shipped') {
                    $updateFields[] = 'shipped_at = NOW()';
                } elseif ($newStatus === 'delivered') {
                    $updateFields[] = 'delivered_at = NOW()';
                }
                
                $updateStmt = $db->prepare("
                    UPDATE orders 
                    SET " . implode(', ', $updateFields) . "
                    WHERE id = ?
                ");
                $updateParams[] = $orderId;
                $updateStmt->execute($updateParams);
                
                // Create status history
                $this->createStatusHistory($orderId, $currentStatus, $newStatus, $comment, null, $adminId);
                
                // Handle status-specific actions
                $this->handleStatusChange($orderId, $currentStatus, $newStatus);
                
                // Schedule notification
                $this->scheduleOrderNotification($orderId, 'status_update');
                
                $db->commit();
                
                // Get updated order
                $updatedOrder = $this->getOrderWithItems($orderId);
                
                $this->logRequest('orders.updateStatus', [
                    'admin_id' => $adminId,
                    'order_id' => $orderId,
                    'previous_status' => $currentStatus,
                    'new_status' => $newStatus
                ]);
                
                return $this->success($updatedOrder, 'Order status updated successfully');
                
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new DatabaseException(
                'Failed to update order status',
                500,
                $e
            );
        }
    }
    
    /**
     * Cancel order (User or Admin)
     * DELETE /api/orders/{id}
     */
    public function cancel(): array
    {
        $this->requireAuth();
        
        try {
            $orderId = (int) $this->getParam('id');
            $reason = $this->getBody('reason', 'User requested cancellation');
            $userId = $this->user['id'];
            
            $db = Database::getConnection();
            $db->beginTransaction();
            
            try {
                // Get order
                $stmt = $db->prepare("
                    SELECT id, status, user_id, total_amount 
                    FROM orders 
                    WHERE id = ? AND (user_id = ? OR ? = 'admin')
                ");
                $stmt->execute([$orderId, $userId, $this->user['role'] ?? 'customer']);
                $order = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$order) {
                    throw new ApiException('Order not found or access denied', 404, null, [], 'ORDER_NOT_FOUND');
                }
                
                // Check if order can be cancelled
                if (in_array($order['status'], ['delivered', 'cancelled', 'refunded'])) {
                    throw new ApiException('Order cannot be cancelled', 400, null, [], 'CANNOT_CANCEL');
                }
                
                $currentStatus = $order['status'];
                
                // Update order status to cancelled
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET status = 'cancelled', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$orderId]);
                
                // Restore inventory
                $this->restoreInventoryForOrder($orderId);
                
                // Create status history
                $this->createStatusHistory($orderId, $currentStatus, 'cancelled', $reason, $userId);
                
                // Schedule notification
                $this->scheduleOrderNotification($orderId, 'status_update');
                
                $db->commit();
                
                $this->logRequest('orders.cancel', [
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'previous_status' => $currentStatus,
                    'reason' => $reason
                ]);
                
                return $this->success(null, 'Order cancelled successfully');
                
            } catch (\Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new DatabaseException(
                'Failed to cancel order',
                500,
                $e
            );
        }
    }
    
    /**
     * Get order status history
     * GET /api/orders/{id}/history
     */
    public function history(): array
    {
        $this->requireAuth();
        
        try {
            $orderId = (int) $this->getParam('id');
            $userId = $this->user['id'];
            
            // Verify user owns the order or is admin
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT user_id 
                FROM orders 
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new ApiException('Order not found', 404, null, [], 'ORDER_NOT_FOUND');
            }
            
            if ($order['user_id'] !== $userId && ($this->user['role'] ?? 'customer') !== 'admin') {
                throw new ApiException('Access denied', 403, null, [], 'ACCESS_DENIED');
            }
            
            // Get status history
            $stmt = $db->prepare("
                SELECT 
                    h.id,
                    h.previous_status,
                    h.new_status,
                    h.comment,
                    h.created_at,
                    u.first_name as changed_by_user_name,
                    a.name as changed_by_admin_name
                FROM order_status_history h
                LEFT JOIN users u ON h.changed_by_user_id = u.id
                LEFT JOIN admins a ON h.changed_by_admin_id = a.id
                WHERE h.order_id = ?
                ORDER BY h.created_at ASC
            ");
            $stmt->execute([$orderId]);
            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $processedHistory = array_map(function($item) {
                return [
                    'id' => (int) $item['id'],
                    'previous_status' => $item['previous_status'],
                    'new_status' => $item['new_status'],
                    'comment' => $item['comment'],
                    'changed_by' => $item['changed_by_user_name'] ?: $item['changed_by_admin_name'] ?: 'System',
                    'created_at' => $item['created_at']
                ];
            }, $history);
            
            return $this->success($processedHistory, 'Order history retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve order history',
                500,
                $e
            );
        }
    }
    
    /**
     * Get order with items - helper method
     */
    private function getOrderWithItems(int $orderId, ?int $userId = null): ?array
    {
        $db = Database::getConnection();
        
        // Build WHERE clause
        $whereConditions = ['o.id = ?'];
        $params = [$orderId];
        
        if ($userId !== null) {
            $whereConditions[] = 'o.user_id = ?';
            $params[] = $userId;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get order details
        $stmt = $db->prepare("
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
                o.shipping_address,
                o.billing_address,
                o.notes,
                o.estimated_delivery,
                o.shipped_at,
                o.delivered_at,
                o.created_at,
                o.updated_at,
                u.first_name,
                u.last_name,
                u.email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE $whereClause
        ");
        $stmt->execute($params);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$order) {
            return null;
        }
        
        // Get order items
        $stmt = $db->prepare("
            SELECT 
                oi.id,
                oi.product_id,
                oi.quantity,
                oi.unit_price as price,
                oi.total_price,
                oi.product_name,
                oi.product_sku,
                oi.product_image_url,
                oi.discount_amount,
                oi.final_price,
                p.name as current_product_name,
                p.price as current_price,
                p.stock_quantity
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Process order data
        $processedOrder = $this->processOrderData($order);
        $processedOrder['items'] = array_map([$this, 'processOrderItem'], $items);
        
        return $processedOrder;
    }
    
    /**
     * Process order summary data
     */
    private function processOrderSummary(array $order): array
    {
        return [
            'id' => (int) $order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'total_amount' => (float) $order['total_amount'],
            'currency' => $order['currency'],
            'payment_status' => $order['payment_status'],
            'payment_method' => $order['payment_method'],
            'shipping_method' => $order['shipping_method'],
            'shipping_cost' => (float) ($order['shipping_cost'] ?? 0),
            'tax_amount' => (float) ($order['tax_amount'] ?? 0),
            'discount_amount' => (float) ($order['discount_amount'] ?? 0),
            'coupon_code' => $order['coupon_code'],
            'coupon_discount' => (float) ($order['coupon_discount'] ?? 0),
            'item_count' => (int) ($order['item_count'] ?? 0),
            'total_quantity' => (int) ($order['total_quantity'] ?? 0),
            'estimated_delivery' => $order['estimated_delivery'],
            'shipped_at' => $order['shipped_at'],
            'delivered_at' => $order['delivered_at'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at']
        ];
    }
    
    /**
     * Process complete order data
     */
    private function processOrderData(array $order): array
    {
        $processedOrder = $this->processOrderSummary($order);
        
        // Add detailed information
        $processedOrder['shipping_address'] = $order['shipping_address'] ? 
            json_decode($order['shipping_address'], true) : null;
        $processedOrder['billing_address'] = $order['billing_address'] ? 
            json_decode($order['billing_address'], true) : null;
        $processedOrder['notes'] = $order['notes'];
        
        // Add customer information
        if ($order['user_id']) {
            $processedOrder['customer'] = [
                'user_id' => (int) $order['user_id'],
                'name' => trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')),
                'email' => $order['email']
            ];
        } else {
            $processedOrder['customer'] = [
                'type' => 'guest',
                'session_id' => $order['guest_session_id']
            ];
        }
        
        return $processedOrder;
    }
    
    /**
     * Process order item data
     */
    private function processOrderItem(array $item): array
    {
        return [
            'id' => (int) $item['id'],
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
            'price' => (float) $item['price'],
            'total_price' => (float) $item['total_price'],
            'discount_amount' => (float) ($item['discount_amount'] ?? 0),
            'final_price' => (float) ($item['final_price'] ?? $item['total_price']),
            'product_name' => $item['product_name'],
            'product_sku' => $item['product_sku'],
            'product_image_url' => $item['product_image_url'],
            'current_product_name' => $item['current_product_name'],
            'current_price' => (float) ($item['current_price'] ?? 0),
            'stock_quantity' => (int) ($item['stock_quantity'] ?? 0),
            'savings' => (float) ($item['discount_amount'] ?? 0)
        ];
    }
    
    /**
     * Get cart items for order creation
     */
    private function getCartItems(?int $userId, ?string $guestSessionId): array
    {
        $db = Database::getConnection();
        
        if ($userId) {
            $stmt = $db->prepare("
                SELECT 
                    c.id as cart_id,
                    c.product_id,
                    c.quantity,
                    p.name,
                    p.price,
                    p.sku,
                    p.image_url,
                    p.stock_quantity,
                    p.is_active
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ? AND p.is_active = 1
                ORDER BY c.created_at
            ");
            $stmt->execute([$userId]);
        } else {
            $stmt = $db->prepare("
                SELECT 
                    c.id as cart_id,
                    c.product_id,
                    c.quantity,
                    p.name,
                    p.price,
                    p.sku,
                    p.image_url,
                    p.stock_quantity,
                    p.is_active
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.session_id = ? AND p.is_active = 1
                ORDER BY c.created_at
            ");
            $stmt->execute([$guestSessionId]);
        }
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate stock availability for cart items
     */
    private function validateStockAvailability(array $cartItems): void
    {
        foreach ($cartItems as $item) {
            if ($item['stock_quantity'] < $item['quantity']) {
                throw new ApiException(
                    "Insufficient stock for product: {$item['name']}. Available: {$item['stock_quantity']}, Requested: {$item['quantity']}",
                    400,
                    null,
                    [],
                    'INSUFFICIENT_STOCK'
                );
            }
        }
    }
    
    /**
     * Calculate order totals
     */
    private function calculateOrderTotals(array $cartItems, string $shippingMethod, ?string $couponCode = null): array
    {
        $subtotal = 0;
        
        foreach ($cartItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Apply coupon discount
        $couponDiscount = 0;
        if ($couponCode) {
            $couponDiscount = $this->calculateCouponDiscount($couponCode, $subtotal);
        }
        
        // Calculate shipping cost
        $shippingCost = self::SHIPPING_METHODS[$shippingMethod]['cost'] ?? 0;
        
        // Calculate tax (10% in Japan)
        $taxableAmount = $subtotal - $couponDiscount;
        $taxAmount = round($taxableAmount * 0.10, 2);
        
        // Calculate total
        $total = $taxableAmount + $taxAmount + $shippingCost;
        
        return [
            'subtotal' => $subtotal,
            'coupon_discount' => $couponDiscount,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'total' => $total
        ];
    }
    
    /**
     * Calculate coupon discount (simplified)
     */
    private function calculateCouponDiscount(string $couponCode, float $subtotal): float
    {
        // Simple coupon system - in production, this would check a coupons table
        $discounts = [
            'WELCOME10' => 0.10,  // 10% off
            'SAVE500' => 500,     // 500 yen off
            'FREESHIP' => 0       // Free shipping (handled separately)
        ];
        
        if (isset($discounts[$couponCode])) {
            $discount = $discounts[$couponCode];
            if ($discount < 1) {
                // Percentage discount
                return round($subtotal * $discount, 2);
            } else {
                // Fixed amount discount
                return min($discount, $subtotal);
            }
        }
        
        return 0;
    }
    
    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $db = Database::getConnection();
        
        do {
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
            $stmt->execute([$orderNumber]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $orderNumber;
    }
    
    /**
     * Create order record
     */
    private function createOrderRecord(array $orderData): int
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO orders (
                user_id, guest_session_id, order_number, status, 
                total_amount, currency, payment_status, payment_method,
                shipping_method, shipping_cost, tax_amount, discount_amount,
                coupon_code, coupon_discount, shipping_address, billing_address,
                notes, estimated_delivery, created_at, updated_at
            ) VALUES (
                ?, ?, ?, 'pending',
                ?, 'JPY', 'pending', ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, NOW(), NOW()
            )
        ");
        
        $shippingMethod = $orderData['shipping_method'];
        $estimatedDays = self::SHIPPING_METHODS[$shippingMethod]['days'] ?? 7;
        $estimatedDelivery = date('Y-m-d', strtotime("+{$estimatedDays} days"));
        
        $stmt->execute([
            $orderData['user_id'],
            $orderData['guest_session_id'],
            $orderData['order_number'],
            $orderData['totals']['total'],
            $orderData['payment_method'],
            $orderData['shipping_method'],
            $orderData['totals']['shipping_cost'],
            $orderData['totals']['tax_amount'],
            $orderData['totals']['subtotal'] - $orderData['totals']['total'] + $orderData['totals']['tax_amount'] + $orderData['totals']['shipping_cost'], // discount_amount
            $orderData['coupon_code'],
            $orderData['totals']['coupon_discount'],
            json_encode($orderData['shipping_address']),
            json_encode($orderData['billing_address']),
            $orderData['notes'],
            $estimatedDelivery
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Create order items
     */
    private function createOrderItems(int $orderId, array $cartItems): void
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, unit_price, total_price,
                product_name, product_sku, product_image_url, final_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cartItems as $item) {
            $totalPrice = $item['price'] * $item['quantity'];
            
            $stmt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $totalPrice,
                $item['name'],
                $item['sku'],
                $item['image_url'],
                $totalPrice // final_price (no item-level discounts for now)
            ]);
        }
    }
    
    /**
     * Update inventory for order
     */
    private function updateInventoryForOrder(int $orderId, array $cartItems): void
    {
        $db = Database::getConnection();
        
        foreach ($cartItems as $item) {
            // Update product stock
            $stmt = $db->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ?, updated_at = NOW()
                WHERE id = ? AND stock_quantity >= ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            
            if ($stmt->rowCount() === 0) {
                throw new ApiException(
                    "Failed to update inventory for product: {$item['name']}",
                    500,
                    null,
                    [],
                    'INVENTORY_UPDATE_FAILED'
                );
            }
            
            // Record inventory movement
            $stmt = $db->prepare("
                INSERT INTO inventory_movements (
                    product_id, type, quantity, previous_stock, new_stock,
                    reason, reference_type, reference_id
                ) VALUES (?, 'out', ?, ?, ?, ?, 'order', ?)
            ");
            
            // Get current stock to record movement
            $stockStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stockStmt->execute([$item['product_id']]);
            $currentStock = $stockStmt->fetchColumn();
            
            $stmt->execute([
                $item['product_id'],
                $item['quantity'],
                $currentStock + $item['quantity'], // previous_stock
                $currentStock, // new_stock
                "Order #{$orderId} - Product sold",
                $orderId
            ]);
        }
    }
    
    /**
     * Clear cart after order
     */
    private function clearCartAfterOrder(?int $userId, ?string $guestSessionId): void
    {
        $db = Database::getConnection();
        
        if ($userId) {
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            $stmt = $db->prepare("DELETE FROM cart WHERE session_id = ?");
            $stmt->execute([$guestSessionId]);
        }
    }
    
    /**
     * Create status history entry
     */
    private function createStatusHistory(int $orderId, ?string $previousStatus, string $newStatus, 
                                       string $comment, ?int $userId = null, ?int $adminId = null): void
    {
        $db = Database::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO order_status_history (
                order_id, previous_status, new_status, comment,
                changed_by_user_id, changed_by_admin_id
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $orderId,
            $previousStatus,
            $newStatus,
            $comment,
            $userId,
            $adminId
        ]);
    }
    
    /**
     * Handle status change actions
     */
    private function handleStatusChange(int $orderId, string $currentStatus, string $newStatus): void
    {
        // Handle status-specific business logic
        switch ($newStatus) {
            case 'cancelled':
                $this->restoreInventoryForOrder($orderId);
                break;
                
            case 'shipped':
                // Could integrate with shipping provider API here
                $this->logRequest('order.shipped', ['order_id' => $orderId]);
                break;
                
            case 'delivered':
                $this->logRequest('order.delivered', ['order_id' => $orderId]);
                break;
        }
    }
    
    /**
     * Restore inventory for cancelled/returned order
     */
    private function restoreInventoryForOrder(int $orderId): void
    {
        $db = Database::getConnection();
        
        // Get order items
        $stmt = $db->prepare("
            SELECT product_id, quantity, product_name
            FROM order_items 
            WHERE order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($orderItems as $item) {
            // Restore product stock
            $stmt = $db->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity + ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
            
            // Record inventory movement
            $stmt = $db->prepare("
                INSERT INTO inventory_movements (
                    product_id, type, quantity, previous_stock, new_stock,
                    reason, reference_type, reference_id
                ) VALUES (?, 'in', ?, ?, ?, ?, 'return', ?)
            ");
            
            // Get current stock to record movement
            $stockStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stockStmt->execute([$item['product_id']]);
            $currentStock = $stockStmt->fetchColumn();
            
            $stmt->execute([
                $item['product_id'],
                $item['quantity'],
                $currentStock - $item['quantity'], // previous_stock
                $currentStock, // new_stock
                "Order #{$orderId} cancelled - Stock restored",
                $orderId
            ]);
        }
    }
    
    /**
     * Schedule order notification
     */
    private function scheduleOrderNotification(int $orderId, string $type): void
    {
        $db = Database::getConnection();
        
        // Get order details for notification
        $stmt = $db->prepare("
            SELECT o.id, o.order_number, u.email, u.first_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$order || !$order['email']) {
            return; // Skip notification for guest orders or missing email
        }
        
        // Create notification record
        $stmt = $db->prepare("
            INSERT INTO order_notifications (
                order_id, type, method, recipient, subject, content, status
            ) VALUES (?, ?, 'email', ?, ?, ?, 'pending')
        ");
        
        $subject = $this->getNotificationSubject($type, $order['order_number']);
        $content = $this->getNotificationContent($type, $order);
        
        $stmt->execute([
            $orderId,
            $type,
            $order['email'],
            $subject,
            $content
        ]);
    }
    
    /**
     * Get notification subject
     */
    private function getNotificationSubject(string $type, string $orderNumber): string
    {
        $subjects = [
            'confirmation' => "ご注文ありがとうございます - 注文番号: {$orderNumber}",
            'status_update' => "注文状況が更新されました - 注文番号: {$orderNumber}",
            'shipping' => "商品を発送しました - 注文番号: {$orderNumber}",
            'delivery' => "商品をお届けしました - 注文番号: {$orderNumber}"
        ];
        
        return $subjects[$type] ?? "注文に関するお知らせ - 注文番号: {$orderNumber}";
    }
    
    /**
     * Get notification content
     */
    private function getNotificationContent(string $type, array $order): string
    {
        $customerName = $order['first_name'] ?? 'お客様';
        $orderNumber = $order['order_number'];
        
        $contents = [
            'confirmation' => "{$customerName}様\n\nこの度は、ご注文いただきありがとうございます。\n注文番号: {$orderNumber}\n\nご注文の確認とお支払いの処理を行っております。\n発送準備が完了次第、改めてご連絡いたします。",
            'status_update' => "{$customerName}様\n\n注文番号: {$orderNumber} の状況が更新されました。\n\n詳細については、マイページでご確認ください。",
            'shipping' => "{$customerName}様\n\n注文番号: {$orderNumber} の商品を発送いたしました。\n\nお届けまでしばらくお待ちください。",
            'delivery' => "{$customerName}様\n\n注文番号: {$orderNumber} の商品をお届けしました。\n\nこの度は、ありがとうございました。"
        ];
        
        return $contents[$type] ?? "注文に関するお知らせです。";
    }
}