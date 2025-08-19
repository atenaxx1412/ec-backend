<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;
use ECBackend\Utils\Response;

/**
 * Product Controller
 * Handles product-related API endpoints
 */
class ProductController extends BaseController
{
    /**
     * Get products list with pagination and filtering
     * GET /api/products
     */
    public function index(): array
    {
        try {
            $pagination = $this->getPagination();
            $filters = $this->getFilters();
            
            // Build query
            $queryBuilder = $this->buildProductQuery($filters);
            $countQuery = $this->buildCountQuery($filters);
            
            // Get total count
            $db = Database::getConnection();
            $stmt = $db->prepare($countQuery['sql']);
            $stmt->execute($countQuery['params']);
            $total = $stmt->fetchColumn();
            
            // Get products
            $stmt = $db->prepare($queryBuilder['sql']);
            $stmt->execute($queryBuilder['params']);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Process product data
            $products = array_map([$this, 'processProductData'], $products);
            
            $this->logRequest('products.index', [
                'total' => $total,
                'page' => $pagination['page'],
                'filters' => $filters
            ]);
            
            return $this->successWithPagination(
                $products,
                array_merge($pagination, ['total' => $total]),
                'Products retrieved successfully'
            );
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve products',
                500,
                $e,
                $queryBuilder['sql'] ?? '',
                $queryBuilder['params'] ?? []
            );
        }
    }
    
    /**
     * Get single product by ID
     * GET /api/products/{id}
     */
    public function show(): array
    {
        try {
            $id = $this->getParam('id');
            
            if (!$id || !is_numeric($id)) {
                throw new ApiException('Invalid product ID', 400, null, [], 'INVALID_PRODUCT_ID');
            }
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    p.*,
                    c.name as category_name,
                    (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.id) as review_count,
                    (SELECT AVG(pr.rating) FROM product_reviews pr WHERE pr.product_id = p.id) as average_rating
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.is_active = 1
            ");
            
            $stmt->execute([$id]);
            $product = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new ApiException('Product not found', 404, null, [], 'PRODUCT_NOT_FOUND');
            }
            
            $product = $this->processProductData($product);
            
            // Get product images
            $product['images'] = $this->getProductImages($id);
            
            // Get product attributes/variants
            $product['attributes'] = $this->getProductAttributes($id);
            
            // Get related products
            $product['related_products'] = $product['category_id'] 
                ? $this->getRelatedProducts($id, $product['category_id']) 
                : [];
            
            $this->logRequest('products.show', ['product_id' => $id]);
            
            return $this->success($product, 'Product retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve product',
                500,
                $e,
                $stmt->queryString ?? '',
                [$id]
            );
        }
    }
    
    /**
     * Search products
     * GET /api/products/search
     */
    public function search(): array
    {
        try {
            $query = $this->getQuery('q', '');
            $pagination = $this->getPagination();
            
            if (strlen($query) < 2) {
                throw new ApiException('Search query must be at least 2 characters', 400, null, [], 'INVALID_SEARCH_QUERY');
            }
            
            $db = Database::getConnection();
            
            // Search in product name, description, and category
            $sql = "
                SELECT 
                    p.*,
                    c.name as category_name,
                    MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1 
                AND (
                    MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)
                    OR p.name LIKE ?
                    OR p.description LIKE ?
                    OR c.name LIKE ?
                )
                ORDER BY relevance DESC, p.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $searchTerm = "%{$query}%";
            $params = [$query, $query, $searchTerm, $searchTerm, $searchTerm, $pagination['limit'], $pagination['offset']];
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "
                SELECT COUNT(*)
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1 
                AND (
                    MATCH(p.name, p.description) AGAINST(? IN NATURAL LANGUAGE MODE)
                    OR p.name LIKE ?
                    OR p.description LIKE ?
                    OR c.name LIKE ?
                )
            ";
            
            $stmt = $db->prepare($countSql);
            $stmt->execute([$query, $searchTerm, $searchTerm, $searchTerm]);
            $total = $stmt->fetchColumn();
            
            $products = array_map([$this, 'processProductData'], $products);
            
            $this->logRequest('products.search', [
                'query' => $query,
                'total' => $total,
                'page' => $pagination['page']
            ]);
            
            return $this->successWithPagination(
                $products,
                array_merge($pagination, ['total' => $total]),
                'Search completed successfully'
            );
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to search products',
                500,
                $e,
                $sql ?? '',
                $params ?? []
            );
        }
    }
    
    /**
     * Get products by category
     * GET /api/products/category/{slug}
     */
    public function byCategory(): array
    {
        try {
            $slug = $this->getParam('slug');
            $pagination = $this->getPagination();
            
            $db = Database::getConnection();
            
            // Get category
            $stmt = $db->prepare("SELECT id, name FROM categories WHERE slug = ?");
            $stmt->execute([$slug]);
            $category = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new ApiException('Category not found', 404, null, [], 'CATEGORY_NOT_FOUND');
            }
            
            // Get products
            $sql = "
                SELECT 
                    p.*,
                    c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = ? AND p.is_active = 1
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$category['id'], $pagination['limit'], $pagination['offset']]);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get total count
            $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND is_active = 1");
            $stmt->execute([$category['id']]);
            $total = $stmt->fetchColumn();
            
            $products = array_map([$this, 'processProductData'], $products);
            
            $this->logRequest('products.byCategory', [
                'category' => $category['name'],
                'total' => $total,
                'page' => $pagination['page']
            ]);
            
            return $this->successWithPagination(
                $products,
                array_merge($pagination, ['total' => $total]),
                "Products in category '{$category['name']}' retrieved successfully"
            );
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve products by category',
                500,
                $e,
                $sql ?? '',
                [$category['id'] ?? null, $pagination['limit'], $pagination['offset']]
            );
        }
    }
    
    /**
     * Build product query with filters
     */
    private function buildProductQuery(array $filters): array
    {
        $sql = "
            SELECT 
                p.*,
                c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
        ";
        
        $params = [];
        $conditions = [];
        
        if (!empty($filters['category_id'])) {
            $conditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['min_price'])) {
            $conditions[] = "p.price >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $conditions[] = "p.price <= ?";
            $params[] = $filters['max_price'];
        }
        
        if (!empty($filters['in_stock'])) {
            $conditions[] = "p.stock_quantity > 0";
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // Add sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'DESC';
        
        $allowedSorts = ['created_at', 'price', 'name', 'stock_quantity'];
        if (in_array($sortBy, $allowedSorts)) {
            $sql .= " ORDER BY p.{$sortBy} {$sortOrder}";
        } else {
            $sql .= " ORDER BY p.created_at DESC";
        }
        
        // Add pagination
        $pagination = $this->getPagination();
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * Build count query
     */
    private function buildCountQuery(array $filters): array
    {
        $sql = "SELECT COUNT(*) FROM products p WHERE p.is_active = 1";
        $params = [];
        $conditions = [];
        
        if (!empty($filters['category_id'])) {
            $conditions[] = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['min_price'])) {
            $conditions[] = "p.price >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $conditions[] = "p.price <= ?";
            $params[] = $filters['max_price'];
        }
        
        if (!empty($filters['in_stock'])) {
            $conditions[] = "p.stock_quantity > 0";
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        return ['sql' => $sql, 'params' => $params];
    }
    
    /**
     * Get query filters
     */
    private function getFilters(): array
    {
        return [
            'category_id' => $this->getQuery('category_id'),
            'min_price' => $this->getQuery('min_price'),
            'max_price' => $this->getQuery('max_price'),
            'in_stock' => $this->getQuery('in_stock'),
            'sort_by' => $this->getQuery('sort_by', 'created_at'),
            'sort_order' => strtoupper($this->getQuery('sort_order', 'DESC'))
        ];
    }
    
    /**
     * Process product data
     */
    private function processProductData(array $product): array
    {
        return [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => (float) $product['price'],
            'compare_price' => $product['sale_price'] ? (float) $product['sale_price'] : null,
            'stock_quantity' => (int) $product['stock_quantity'],
            'sku' => $product['sku'],
            'image_url' => $product['image_url'],
            'is_featured' => (bool) ($product['is_featured'] ?? false),
            'category' => [
                'id' => (int) $product['category_id'],
                'name' => $product['category_name'] ?? null
            ],
            'review_count' => isset($product['review_count']) ? (int) $product['review_count'] : 0,
            'average_rating' => isset($product['average_rating']) ? (float) $product['average_rating'] : null,
            'created_at' => $product['created_at'],
            'updated_at' => $product['updated_at']
        ];
    }
    
    /**
     * Get product images
     */
    private function getProductImages(int $productId): array
    {
        // Note: product_images table doesn't exist in current schema
        // Return empty array for now, can be implemented later
        return [];
    }
    
    /**
     * Get product attributes
     */
    private function getProductAttributes(int $productId): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT name, value 
                FROM product_attributes 
                WHERE product_id = ?
                ORDER BY name
            ");
            $stmt->execute([$productId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get related products
     */
    private function getRelatedProducts(int $productId, int $categoryId, int $limit = 4): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    p.id, p.name, p.slug, p.price, p.image_url,
                    c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.category_id = ? 
                AND p.id != ? 
                AND p.is_active = 1
                ORDER BY RAND()
                LIMIT ?
            ");
            $stmt->execute([$categoryId, $productId, $limit]);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return array_map(function($product) {
                return [
                    'id' => (int) $product['id'],
                    'name' => $product['name'],
                    'price' => (float) $product['price'],
                    'image_url' => $product['image_url'],
                    'category_name' => $product['category_name']
                ];
            }, $products);
        } catch (\PDOException $e) {
            return [];
        }
    }
}