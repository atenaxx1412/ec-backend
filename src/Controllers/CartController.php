<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\Response;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;

/**
 * Cart Controller
 * Handles shopping cart operations
 */
class CartController extends BaseController
{
    /**
     * Get cart contents
     * GET /api/cart
     */
    public function index(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            $cartItems = $this->getCartItems($userId);
            $cartSummary = $this->calculateCartSummary($cartItems);
            
            $this->logRequest('cart.index', [
                'user_id' => $userId,
                'item_count' => count($cartItems)
            ]);
            
            return $this->success([
                'items' => $cartItems,
                'summary' => $cartSummary
            ], 'Cart retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve cart',
                500,
                $e
            );
        }
    }
    
    /**
     * Add item to cart
     * POST /api/cart/add
     */
    public function add(): array
    {
        $this->requireAuth();
        
        try {
            $this->validateRequired(['product_id', 'quantity']);
            
            $userId = $this->user['id'];
            $productId = (int) $this->getBody('product_id');
            $quantity = (int) $this->getBody('quantity');
            
            // Validate quantity
            if ($quantity <= 0 || $quantity > 100) {
                throw new ApiException('Invalid quantity', 400, null, [], 'INVALID_QUANTITY');
            }
            
            // Get product details and check availability
            $product = $this->getProduct($productId);
            
            if (!$product) {
                throw new ApiException('Product not found', 404, null, [], 'PRODUCT_NOT_FOUND');
            }
            
            if ($product['status'] !== 'active') {
                throw new ApiException('Product is not available', 400, null, [], 'PRODUCT_UNAVAILABLE');
            }
            
            if ($product['stock_quantity'] < $quantity) {
                throw new ApiException(
                    'Insufficient stock',
                    400,
                    null,
                    [
                        'available_quantity' => $product['stock_quantity'],
                        'requested_quantity' => $quantity
                    ],
                    'INSUFFICIENT_STOCK'
                );
            }
            
            $db = Database::getConnection();
            $db->beginTransaction();
            
            // Check if item already exists in cart
            $stmt = $db->prepare("
                SELECT id, quantity 
                FROM cart_items 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$userId, $productId]);
            $existingItem = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingItem) {
                // Update existing item
                $newQuantity = $existingItem['quantity'] + $quantity;
                
                if ($newQuantity > $product['stock_quantity']) {
                    throw new ApiException(
                        'Total quantity exceeds available stock',
                        400,
                        null,
                        [
                            'available_quantity' => $product['stock_quantity'],
                            'current_in_cart' => $existingItem['quantity'],
                            'requested_to_add' => $quantity
                        ],
                        'EXCEEDS_STOCK'
                    );
                }
                
                $stmt = $db->prepare("
                    UPDATE cart_items 
                    SET quantity = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $existingItem['id']]);
                $cartItemId = $existingItem['id'];
                
            } else {
                // Add new item
                $stmt = $db->prepare("
                    INSERT INTO cart_items (user_id, product_id, quantity, created_at, updated_at) 
                    VALUES (?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $productId, $quantity]);
                $cartItemId = $db->lastInsertId();
            }
            
            $db->commit();
            
            // Get updated cart item details
            $cartItem = $this->getCartItem($cartItemId);
            
            $this->logRequest('cart.add', [
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'was_existing' => !empty($existingItem)
            ]);
            
            return $this->success($cartItem, 'Item added to cart successfully', 201);
            
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new DatabaseException(
                'Failed to add item to cart',
                500,
                $e
            );
        }
    }
    
    /**
     * Update cart item quantity
     * PUT /api/cart/{id}
     */
    public function update(): array
    {
        $this->requireAuth();
        
        try {
            $this->validateRequired(['quantity']);
            
            $userId = $this->user['id'];
            $cartItemId = (int) $this->getParam('id');
            $quantity = (int) $this->getBody('quantity');
            
            if ($quantity <= 0 || $quantity > 100) {
                throw new ApiException('Invalid quantity', 400, null, [], 'INVALID_QUANTITY');
            }
            
            $db = Database::getConnection();
            
            // Get cart item
            $stmt = $db->prepare("
                SELECT ci.*, p.stock_quantity, p.status, p.name
                FROM cart_items ci
                JOIN products p ON ci.product_id = p.id
                WHERE ci.id = ? AND ci.user_id = ?
            ");
            $stmt->execute([$cartItemId, $userId]);
            $cartItem = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$cartItem) {
                throw new ApiException('Cart item not found', 404, null, [], 'CART_ITEM_NOT_FOUND');
            }
            
            if ($cartItem['status'] !== 'active') {
                throw new ApiException('Product is no longer available', 400, null, [], 'PRODUCT_UNAVAILABLE');
            }
            
            if ($cartItem['stock_quantity'] < $quantity) {
                throw new ApiException(
                    'Insufficient stock',
                    400,
                    null,
                    [
                        'available_quantity' => $cartItem['stock_quantity'],
                        'requested_quantity' => $quantity
                    ],
                    'INSUFFICIENT_STOCK'
                );
            }
            
            // Update quantity
            $stmt = $db->prepare("
                UPDATE cart_items 
                SET quantity = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $cartItemId]);
            
            // Get updated cart item
            $updatedItem = $this->getCartItem($cartItemId);
            
            $this->logRequest('cart.update', [
                'user_id' => $userId,
                'cart_item_id' => $cartItemId,
                'old_quantity' => $cartItem['quantity'],
                'new_quantity' => $quantity
            ]);
            
            return $this->success($updatedItem, 'Cart item updated successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to update cart item',
                500,
                $e
            );
        }
    }
    
    /**
     * Remove item from cart
     * DELETE /api/cart/{id}
     */
    public function remove(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            $cartItemId = (int) $this->getParam('id');
            
            $db = Database::getConnection();
            
            // Check if item exists and belongs to user
            $stmt = $db->prepare("
                SELECT id, product_id 
                FROM cart_items 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$cartItemId, $userId]);
            $cartItem = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$cartItem) {
                throw new ApiException('Cart item not found', 404, null, [], 'CART_ITEM_NOT_FOUND');
            }
            
            // Delete cart item
            $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ?");
            $stmt->execute([$cartItemId]);
            
            $this->logRequest('cart.remove', [
                'user_id' => $userId,
                'cart_item_id' => $cartItemId,
                'product_id' => $cartItem['product_id']
            ]);
            
            return $this->success(null, 'Item removed from cart successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to remove cart item',
                500,
                $e
            );
        }
    }
    
    /**
     * Clear entire cart
     * DELETE /api/cart
     */
    public function clear(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            
            $db = Database::getConnection();
            
            // Get count before clearing
            $stmt = $db->prepare("SELECT COUNT(*) FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            $itemCount = $stmt->fetchColumn();
            
            // Clear cart
            $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            $this->logRequest('cart.clear', [
                'user_id' => $userId,
                'items_removed' => $itemCount
            ]);
            
            return $this->success(null, 'Cart cleared successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to clear cart',
                500,
                $e
            );
        }
    }
    
    /**
     * Get cart summary
     * GET /api/cart/summary
     */
    public function summary(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            $cartItems = $this->getCartItems($userId);
            $summary = $this->calculateCartSummary($cartItems);
            
            $this->logRequest('cart.summary', ['user_id' => $userId]);
            
            return $this->success($summary, 'Cart summary retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve cart summary',
                500,
                $e
            );
        }
    }
    
    /**
     * Get cart items for user
     */
    private function getCartItems(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                ci.id,
                ci.quantity,
                ci.created_at,
                ci.updated_at,
                p.id as product_id,
                p.name,
                p.slug,
                p.price,
                p.compare_price,
                p.image_url,
                p.stock_quantity,
                p.status,
                c.name as category_name
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ci.user_id = ?
            ORDER BY ci.created_at ASC
        ");
        
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map([$this, 'processCartItem'], $items);
    }
    
    /**
     * Get single cart item with details
     */
    private function getCartItem(int $cartItemId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                ci.id,
                ci.quantity,
                ci.created_at,
                ci.updated_at,
                p.id as product_id,
                p.name,
                p.slug,
                p.price,
                p.compare_price,
                p.image_url,
                p.stock_quantity,
                p.status,
                c.name as category_name
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ci.id = ?
        ");
        
        $stmt->execute([$cartItemId]);
        $item = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $this->processCartItem($item);
    }
    
    /**
     * Get product details
     */
    private function getProduct(int $productId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, name, price, stock_quantity, status
            FROM products 
            WHERE id = ?
        ");
        
        $stmt->execute([$productId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Process cart item data
     */
    private function processCartItem(array $item): array
    {
        $subtotal = $item['price'] * $item['quantity'];
        $savings = 0;
        
        if ($item['compare_price'] && $item['compare_price'] > $item['price']) {
            $savings = ($item['compare_price'] - $item['price']) * $item['quantity'];
        }
        
        return [
            'id' => (int) $item['id'],
            'quantity' => (int) $item['quantity'],
            'subtotal' => round($subtotal, 2),
            'savings' => round($savings, 2),
            'product' => [
                'id' => (int) $item['product_id'],
                'name' => $item['name'],
                'slug' => $item['slug'],
                'price' => (float) $item['price'],
                'compare_price' => $item['compare_price'] ? (float) $item['compare_price'] : null,
                'image_url' => $item['image_url'],
                'stock_quantity' => (int) $item['stock_quantity'],
                'status' => $item['status'],
                'category_name' => $item['category_name']
            ],
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at']
        ];
    }
    
    /**
     * Calculate cart summary
     */
    private function calculateCartSummary(array $cartItems): array
    {
        $totalItems = 0;
        $subtotal = 0;
        $totalSavings = 0;
        $outOfStockItems = 0;
        
        foreach ($cartItems as $item) {
            $totalItems += $item['quantity'];
            $subtotal += $item['subtotal'];
            $totalSavings += $item['savings'];
            
            if ($item['product']['stock_quantity'] < $item['quantity'] || $item['product']['status'] !== 'active') {
                $outOfStockItems++;
            }
        }
        
        // Calculate tax (example: 10%)
        $taxRate = 0.10;
        $tax = round($subtotal * $taxRate, 2);
        
        // Calculate shipping (free shipping over $100)
        $shippingCost = $subtotal >= 100 ? 0 : 10.00;
        
        $total = round($subtotal + $tax + $shippingCost, 2);
        
        return [
            'item_count' => count($cartItems),
            'total_quantity' => $totalItems,
            'subtotal' => round($subtotal, 2),
            'tax' => $tax,
            'shipping' => $shippingCost,
            'total_savings' => round($totalSavings, 2),
            'total' => $total,
            'out_of_stock_items' => $outOfStockItems,
            'has_issues' => $outOfStockItems > 0,
            'free_shipping_eligible' => $subtotal >= 100,
            'free_shipping_remaining' => max(0, 100 - $subtotal)
        ];
    }
}