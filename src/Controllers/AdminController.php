<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\Response;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;

/**
 * Admin Controller
 * Handles administrative operations and management functions
 */
class AdminController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        // Admin routes require authentication and admin role
        $this->requireAuth();
        $this->requireRole('admin');
    }
    
    /**
     * Admin login (separate from regular user login)
     * POST /api/admin/login
     */
    public function login(): array
    {
        try {
            // Remove auth requirement for login endpoint
            $this->user = null;
            
            $this->validateRequired(['email', 'password']);
            
            $email = $this->getBody('email');
            $password = $this->getBody('password');
            
            // Enhanced rate limiting for admin login
            $rateLimitKey = "admin_login:" . $this->request['client_ip'];
            $rateLimit = SecurityHelper::checkRateLimit($rateLimitKey, 5, 900); // 5 attempts per 15 minutes
            
            if (!$rateLimit['allowed']) {
                SecurityHelper::logSecurityEvent('admin_login_rate_limit', [
                    'email' => $email,
                    'ip' => $this->request['client_ip']
                ]);
                
                throw new ApiException(
                    'Too many admin login attempts',
                    429,
                    null,
                    ['retry_after' => $rateLimit['reset_time'] - time()],
                    'ADMIN_RATE_LIMIT_EXCEEDED'
                );
            }
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    id, name, email, password, role, status,
                    last_login_at, created_at
                FROM users 
                WHERE email = ? AND role = 'admin'
            ");
            
            $stmt->execute([$email]);
            $admin = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$admin || !SecurityHelper::verifyPassword($password, $admin['password'])) {
                SecurityHelper::logSecurityEvent('admin_login_failed', [
                    'email' => $email,
                    'ip' => $this->request['client_ip']
                ]);
                
                throw new ApiException('Invalid admin credentials', 401, null, [], 'INVALID_ADMIN_CREDENTIALS');
            }
            
            if ($admin['status'] !== 'active') {
                SecurityHelper::logSecurityEvent('admin_login_inactive', ['admin_id' => $admin['id']]);
                throw new ApiException('Admin account is not active', 403, null, [], 'ADMIN_ACCOUNT_INACTIVE');
            }
            
            // Generate admin token with shorter expiry
            $token = SecurityHelper::generateToken([
                'user_id' => $admin['id'],
                'email' => $admin['email'],
                'role' => 'admin'
            ], 28800); // 8 hours
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$admin['id']]);
            
            $adminData = [
                'id' => (int) $admin['id'],
                'name' => $admin['name'],
                'email' => $admin['email'],
                'role' => $admin['role'],
                'last_login_at' => $admin['last_login_at'],
                'created_at' => $admin['created_at']
            ];
            
            SecurityHelper::logSecurityEvent('admin_login_success', ['admin_id' => $admin['id']]);
            
            $this->logRequest('admin.login', ['admin_id' => $admin['id']]);
            
            return $this->success([
                'token' => $token,
                'admin' => $adminData,
                'expires_in' => 28800
            ], 'Admin login successful');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error during admin login',
                500,
                $e
            );
        }
    }
    
    /**
     * Get admin dashboard data
     * GET /api/admin/dashboard
     */
    public function dashboard(): array
    {
        try {
            $db = Database::getConnection();
            
            // Get basic statistics
            $stats = [
                'total_products' => $this->getCount('products', ['status' => 'active']),
                'total_categories' => $this->getCount('categories', ['status' => 'active']),
                'total_users' => $this->getCount('users', ['role' => 'user', 'status' => 'active']),
                'low_stock_products' => $this->getCount('products', ['status' => 'active'], 'stock_quantity < 10'),
                'recent_orders' => $this->getRecentOrdersCount(),
                'revenue_today' => $this->getRevenueToday(),
                'revenue_month' => $this->getRevenueMonth()
            ];
            
            // Get recent activity
            $recentProducts = $this->getRecentProducts(5);
            $recentUsers = $this->getRecentUsers(5);
            $lowStockProducts = $this->getLowStockProducts(10);
            
            $dashboardData = [
                'statistics' => $stats,
                'recent_products' => $recentProducts,
                'recent_users' => $recentUsers,
                'low_stock_products' => $lowStockProducts
            ];
            
            $this->logRequest('admin.dashboard', ['admin_id' => $this->user['id']]);
            
            return $this->success($dashboardData, 'Dashboard data retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve dashboard data',
                500,
                $e
            );
        }
    }
    
    /**
     * Get all products for admin management
     * GET /api/admin/products
     */
    public function getProducts(): array
    {
        try {
            $pagination = $this->getPagination();
            $filters = $this->getAdminProductFilters();
            
            $queryBuilder = $this->buildAdminProductQuery($filters);
            $countQuery = $this->buildAdminProductCountQuery($filters);
            
            $db = Database::getConnection();
            
            // Get total count
            $stmt = $db->prepare($countQuery['sql']);
            $stmt->execute($countQuery['params']);
            $total = $stmt->fetchColumn();
            
            // Get products
            $stmt = $db->prepare($queryBuilder['sql']);
            $stmt->execute($queryBuilder['params']);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Process product data for admin view
            $products = array_map([$this, 'processAdminProductData'], $products);
            
            $this->logRequest('admin.getProducts', [
                'admin_id' => $this->user['id'],
                'total' => $total,
                'filters' => $filters
            ]);
            
            return $this->successWithPagination(
                $products,
                array_merge($pagination, ['total' => $total]),
                'Products retrieved successfully'
            );
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve admin products',
                500,
                $e
            );
        }
    }
    
    /**
     * Create new product
     * POST /api/admin/products
     */
    public function createProduct(): array
    {
        try {
            $this->validateRequired(['name', 'price', 'category_id']);
            
            $data = $this->sanitizeInput($this->request['body']);
            $this->validateProductData($data);
            
            $db = Database::getConnection();
            $db->beginTransaction();
            
            // Generate slug
            $slug = $this->generateProductSlug($data['name']);
            
            $stmt = $db->prepare("
                INSERT INTO products (
                    name, slug, description, price, compare_price, 
                    stock_quantity, sku, category_id, image_url, 
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $data['name'],
                $slug,
                $data['description'] ?? '',
                $data['price'],
                $data['compare_price'] ?? null,
                $data['stock_quantity'] ?? 0,
                $data['sku'] ?? $this->generateSKU(),
                $data['category_id'],
                $data['image_url'] ?? null,
                $data['status'] ?? 'active'
            ]);
            
            $productId = $db->lastInsertId();
            
            // Add product attributes if provided
            if (!empty($data['attributes'])) {
                $this->addProductAttributes($productId, $data['attributes']);
            }
            
            $db->commit();
            
            // Get created product
            $product = $this->getAdminProduct($productId);
            
            $this->logRequest('admin.createProduct', [
                'admin_id' => $this->user['id'],
                'product_id' => $productId,
                'product_name' => $data['name']
            ]);
            
            return $this->success($product, 'Product created successfully', 201);
            
        } catch (\PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new DatabaseException(
                'Failed to create product',
                500,
                $e
            );
        }
    }
    
    /**
     * Update product
     * PUT /api/admin/products/{id}
     */
    public function updateProduct(): array
    {
        try {
            $productId = (int) $this->getParam('id');
            $data = $this->sanitizeInput($this->request['body']);
            
            // Check if product exists
            $existingProduct = $this->getAdminProduct($productId);
            if (!$existingProduct) {
                throw new ApiException('Product not found', 404, null, [], 'PRODUCT_NOT_FOUND');
            }
            
            $this->validateProductData($data, $productId);
            
            $db = Database::getConnection();
            $db->beginTransaction();
            
            // Build update query
            $updateFields = [];
            $updateParams = [];
            
            $allowedFields = ['name', 'description', 'price', 'compare_price', 'stock_quantity', 'sku', 'category_id', 'image_url', 'status'];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateFields[] = "{$field} = ?";
                    $updateParams[] = $data[$field];
                }
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = NOW()";
                $updateParams[] = $productId;
                
                $sql = "UPDATE products SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($updateParams);
            }
            
            // Update attributes if provided
            if (array_key_exists('attributes', $data)) {
                $this->updateProductAttributes($productId, $data['attributes']);
            }
            
            $db->commit();
            
            // Get updated product
            $product = $this->getAdminProduct($productId);
            
            $this->logRequest('admin.updateProduct', [
                'admin_id' => $this->user['id'],
                'product_id' => $productId,
                'updated_fields' => array_keys(array_intersect_key($data, array_flip($allowedFields)))
            ]);
            
            return $this->success($product, 'Product updated successfully');
            
        } catch (\PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new DatabaseException(
                'Failed to update product',
                500,
                $e
            );
        }
    }
    
    /**
     * Delete product
     * DELETE /api/admin/products/{id}
     */
    public function deleteProduct(): array
    {
        try {
            $productId = (int) $this->getParam('id');
            
            $db = Database::getConnection();
            
            // Check if product exists
            $stmt = $db->prepare("SELECT id, name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new ApiException('Product not found', 404, null, [], 'PRODUCT_NOT_FOUND');
            }
            
            // Soft delete - set status to 'deleted'
            $stmt = $db->prepare("
                UPDATE products 
                SET status = 'deleted', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$productId]);
            
            $this->logRequest('admin.deleteProduct', [
                'admin_id' => $this->user['id'],
                'product_id' => $productId,
                'product_name' => $product['name']
            ]);
            
            SecurityHelper::logSecurityEvent('product_deleted', [
                'admin_id' => $this->user['id'],
                'product_id' => $productId
            ]);
            
            return $this->success(null, 'Product deleted successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to delete product',
                500,
                $e
            );
        }
    }
    
    /**
     * Get admin-specific product data
     */
    private function getAdminProduct(int $productId): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ?
        ");
        
        $stmt->execute([$productId]);
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$product) {
            return null;
        }
        
        return $this->processAdminProductData($product);
    }
    
    /**
     * Process product data for admin view
     */
    private function processAdminProductData(array $product): array
    {
        return [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'description' => $product['description'],
            'price' => (float) $product['price'],
            'compare_price' => $product['compare_price'] ? (float) $product['compare_price'] : null,
            'stock_quantity' => (int) $product['stock_quantity'],
            'sku' => $product['sku'],
            'status' => $product['status'],
            'image_url' => $product['image_url'],
            'category' => [
                'id' => (int) $product['category_id'],
                'name' => $product['category_name'] ?? null,
                'slug' => $product['category_slug'] ?? null
            ],
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }
    
    /**
     * Get filters for admin product query
     */
    private function getAdminProductFilters(): array
    {
        return [
            'status' => $this->getQuery('status'),
            'category_id' => $this->getQuery('category_id'),
            'low_stock' => $this->getQuery('low_stock'),
            'search' => $this->getQuery('search')
        ];
    }
    
    /**
     * Build admin product query
     */
    private function buildAdminProductQuery(array $filters): array
    {
        $sql = "
            SELECT 
                p.*,
                c.name as category_name,
                c.slug as category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['low_stock'])) {
            $sql .= " AND p.stock_quantity < 10";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.updated_at DESC";
        
        // Add pagination
        $pagination = $this->getPagination();
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * Build admin product count query
     */
    private function buildAdminProductCountQuery(array $filters): array
    {
        $sql = "SELECT COUNT(*) FROM products p WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND p.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['low_stock'])) {
            $sql .= " AND p.stock_quantity < 10";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * Helper methods for dashboard statistics
     */
    private function getCount(string $table, array $conditions = [], string $extraCondition = ''): int
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $sql .= " AND {$field} = ?";
            $params[] = $value;
        }
        
        if ($extraCondition) {
            $sql .= " AND {$extraCondition}";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }
    
    private function getRecentOrdersCount(): int
    {
        // Placeholder - would need orders table
        return 0;
    }
    
    private function getRevenueToday(): float
    {
        // Placeholder - would need orders table
        return 0.0;
    }
    
    private function getRevenueMonth(): float
    {
        // Placeholder - would need orders table
        return 0.0;
    }
    
    private function getRecentProducts(int $limit): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, name, price, stock_quantity, status, created_at
            FROM products 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getRecentUsers(int $limit): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, name, email, created_at
            FROM users 
            WHERE role = 'user'
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getLowStockProducts(int $limit): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT id, name, stock_quantity, sku
            FROM products 
            WHERE status = 'active' AND stock_quantity < 10
            ORDER BY stock_quantity ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function generateProductSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        
        // Ensure uniqueness
        $db = Database::getConnection();
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $stmt = $db->prepare("SELECT id FROM products WHERE slug = ?");
            $stmt->execute([$slug]);
            
            if (!$stmt->fetch()) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    private function generateSKU(): string
    {
        return 'SKU-' . strtoupper(uniqid());
    }
    
    private function validateProductData(array $data, ?int $productId = null): void
    {
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            throw new ApiException('Invalid price', 400, null, [], 'INVALID_PRICE');
        }
        
        if (isset($data['stock_quantity']) && (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0)) {
            throw new ApiException('Invalid stock quantity', 400, null, [], 'INVALID_STOCK_QUANTITY');
        }
        
        if (isset($data['category_id'])) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM categories WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['category_id']]);
            
            if (!$stmt->fetch()) {
                throw new ApiException('Invalid category', 400, null, [], 'INVALID_CATEGORY');
            }
        }
    }
    
    private function addProductAttributes(int $productId, array $attributes): void
    {
        // Placeholder for product attributes functionality
    }
    
    private function updateProductAttributes(int $productId, array $attributes): void
    {
        // Placeholder for product attributes functionality
    }
}