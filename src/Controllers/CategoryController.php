<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Exceptions\DatabaseException;
use ECBackend\Utils\Response;

/**
 * Category Controller
 * Handles category-related API endpoints
 */
class CategoryController extends BaseController
{
    /**
     * Get all categories
     * GET /api/categories
     */
    public function index(): array
    {
        try {
            $includeProductCount = $this->getQuery('include_product_count', false);
            $includeHierarchy = $this->getQuery('include_hierarchy', false);
            
            $db = Database::getConnection();
            
            if ($includeProductCount) {
                $sql = "
                    SELECT 
                        c.*,
                        COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.is_active = 1
                    GROUP BY c.id
                    ORDER BY c.name ASC
                ";
            } else {
                $sql = "
                    SELECT *
                    FROM categories
                    WHERE is_active = 1
                    ORDER BY name ASC
                ";
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Process category data
            $categories = array_map([$this, 'processCategoryData'], $categories);
            
            // Build hierarchy if requested
            if ($includeHierarchy) {
                $categories = $this->buildCategoryHierarchy($categories);
            }
            
            $this->logRequest('categories.index', [
                'total' => count($categories),
                'include_product_count' => $includeProductCount,
                'include_hierarchy' => $includeHierarchy
            ]);
            
            return $this->success($categories, 'Categories retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve categories',
                500,
                $e,
                $sql ?? '',
                []
            );
        }
    }
    
    /**
     * Get single category by ID or slug
     * GET /api/categories/{id}
     */
    public function show(): array
    {
        try {
            $identifier = $this->getParam('id');
            $includeProducts = $this->getQuery('include_products', false);
            
            $db = Database::getConnection();
            
            // Determine if identifier is numeric (ID) or string (slug)
            if (is_numeric($identifier)) {
                $sql = "
                    SELECT 
                        c.*,
                        COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.id = ? AND c.is_active = 1
                    GROUP BY c.id
                ";
            } else {
                $sql = "
                    SELECT 
                        c.*,
                        COUNT(p.id) as product_count
                    FROM categories c
                    LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                    WHERE c.slug = ? AND c.is_active = 1
                    GROUP BY c.id
                ";
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$identifier]);
            $category = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$category) {
                throw new ApiException('Category not found', 404, null, [], 'CATEGORY_NOT_FOUND');
            }
            
            $category = $this->processCategoryData($category);
            
            // Include products if requested
            if ($includeProducts) {
                $category['products'] = $this->getCategoryProducts($category['id']);
            }
            
            // Get subcategories
            $category['subcategories'] = $this->getSubcategories($category['id']);
            
            // Get parent category if exists
            if ($category['parent_id']) {
                $category['parent'] = $this->getParentCategory($category['parent_id']);
            }
            
            $this->logRequest('categories.show', [
                'category_id' => $category['id'],
                'include_products' => $includeProducts
            ]);
            
            return $this->success($category, 'Category retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve category',
                500,
                $e,
                $sql ?? '',
                [$identifier]
            );
        }
    }
    
    /**
     * Get category tree/hierarchy
     * GET /api/categories/tree
     */
    public function tree(): array
    {
        try {
            $db = Database::getConnection();
            
            $sql = "
                SELECT 
                    c.*,
                    COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.name ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Process category data
            $categories = array_map([$this, 'processCategoryData'], $categories);
            
            // Build tree structure
            $tree = $this->buildCategoryTree($categories);
            
            $this->logRequest('categories.tree', ['total' => count($categories)]);
            
            return $this->success($tree, 'Category tree retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve category tree',
                500,
                $e,
                $sql ?? '',
                []
            );
        }
    }
    
    /**
     * Get popular categories
     * GET /api/categories/popular
     */
    public function popular(): array
    {
        try {
            $limit = min(10, max(1, (int) $this->getQuery('limit', 5)));
            
            $db = Database::getConnection();
            
            $sql = "
                SELECT 
                    c.*,
                    COUNT(p.id) as product_count
                FROM categories c
                INNER JOIN products p ON c.id = p.category_id AND p.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                HAVING product_count > 0
                ORDER BY product_count DESC, c.name ASC
                LIMIT ?
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$limit]);
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Process category data
            $categories = array_map([$this, 'processCategoryData'], $categories);
            
            $this->logRequest('categories.popular', ['limit' => $limit]);
            
            return $this->success($categories, 'Popular categories retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to retrieve popular categories',
                500,
                $e,
                $sql ?? '',
                [$limit]
            );
        }
    }
    
    /**
     * Process category data
     */
    private function processCategoryData(array $category): array
    {
        return [
            'id' => (int) $category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'parent_id' => $category['parent_id'] ? (int) $category['parent_id'] : null,
            'is_active' => (bool) ($category['is_active'] ?? true),
            'product_count' => isset($category['product_count']) ? (int) $category['product_count'] : 0,
            'created_at' => $category['created_at'],
            'updated_at' => $category['updated_at']
        ];
    }
    
    /**
     * Build category hierarchy (flat structure with parent info)
     */
    private function buildCategoryHierarchy(array $categories): array
    {
        $indexed = [];
        foreach ($categories as $category) {
            $indexed[$category['id']] = $category;
        }
        
        // Add children to each category
        foreach ($indexed as &$category) {
            $category['children'] = [];
            foreach ($indexed as $child) {
                if ($child['parent_id'] === $category['id']) {
                    $category['children'][] = $child;
                }
            }
        }
        
        // Return only root categories (they contain their children)
        return array_values(array_filter($indexed, function($category) {
            return $category['parent_id'] === null;
        }));
    }
    
    /**
     * Build category tree (recursive structure)
     */
    private function buildCategoryTree(array $categories, ?int $parentId = null): array
    {
        $tree = [];
        
        foreach ($categories as $category) {
            if ($category['parent_id'] === $parentId) {
                $category['children'] = $this->buildCategoryTree($categories, $category['id']);
                $tree[] = $category;
            }
        }
        
        return $tree;
    }
    
    /**
     * Get category products
     */
    private function getCategoryProducts(int $categoryId, int $limit = 10): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    id, name, slug, price, image_url, stock_quantity
                FROM products 
                WHERE category_id = ? AND is_active = 1
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$categoryId, $limit]);
            $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return array_map(function($product) {
                return [
                    'id' => (int) $product['id'],
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'price' => (float) $product['price'],
                    'image_url' => $product['image_url'],
                    'stock_quantity' => (int) $product['stock_quantity']
                ];
            }, $products);
        } catch (\PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get subcategories
     */
    private function getSubcategories(int $categoryId): array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
                WHERE c.parent_id = ? AND c.is_active = 1
                GROUP BY c.id
                ORDER BY c.name ASC
            ");
            $stmt->execute([$categoryId]);
            $subcategories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return array_map([$this, 'processCategoryData'], $subcategories);
        } catch (\PDOException $e) {
            return [];
        }
    }
    
    /**
     * Get parent category
     */
    private function getParentCategory(int $parentId): ?array
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, name, slug
                FROM categories 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($parent) {
                return [
                    'id' => (int) $parent['id'],
                    'name' => $parent['name'],
                    'slug' => $parent['slug']
                ];
            }
            
            return null;
        } catch (\PDOException $e) {
            return null;
        }
    }
}