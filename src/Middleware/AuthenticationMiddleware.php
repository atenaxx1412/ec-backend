<?php

namespace ECBackend\Middleware;

use ECBackend\Config\Database;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\JWTHelper;
use ECBackend\Utils\Response;
use ECBackend\Exceptions\ApiException;

/**
 * Authentication Middleware
 * Handles JWT token validation and user authentication
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    private bool $required;
    private array $excludeRoutes;
    
    public function __construct(bool $required = true, array $excludeRoutes = [])
    {
        $this->required = $required;
        $this->excludeRoutes = $excludeRoutes;
    }
    
    public function handle(array $request, callable $next)
    {
        // Skip authentication for excluded routes
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($this->isExcludedRoute($currentPath)) {
            return $next($request);
        }
        
        // Get token from Authorization header
        $token = JWTHelper::extractTokenFromHeader();
        
        if (!$token) {
            if ($this->required) {
                throw new ApiException('Authentication token required', 401, null, [], 'AUTH_TOKEN_REQUIRED');
            }
            return $next($request);
        }
        
        // Check if token is blacklisted
        if (JWTHelper::isTokenBlacklisted($token)) {
            throw new ApiException('Token has been revoked', 401, null, [], 'TOKEN_REVOKED');
        }
        
        // Verify and decode JWT token
        try {
            $payload = JWTHelper::validateToken($token);
            $userData = JWTHelper::getUserFromToken($token);
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Invalid token', 401, null, [], 'AUTH_TOKEN_INVALID');
        }
        
        // Verify user exists and is active in database
        $user = $this->verifyUserStatus($userData['id'], $userData['role'] ?? 'customer');
        
        if (!$user) {
            throw new ApiException('User not found or inactive', 401, null, [], 'AUTH_USER_NOT_FOUND');
        }
        
        // Add user to request for controllers
        $request['user'] = $user;
        $request['auth'] = [
            'token' => $token,
            'payload' => $payload,
            'user_id' => $user['id'],
            'role' => $user['role'] // Use role from database/token
        ];
        
        // Store user in global context for controller access
        global $__request_context;
        $__request_context = $request;
        
        return $next($request);
    }
    
    /**
     * Verify user status in database
     */
    private function verifyUserStatus(int $userId, string $role = 'customer'): ?array
    {
        try {
            $db = Database::getConnection();
            
            // Determine which table to query based on role
            $isAdminRole = in_array($role, ['admin', 'super_admin', 'moderator']);
            
            if ($isAdminRole) {
                // Query admins table
                $stmt = $db->prepare("
                    SELECT 
                        id, 
                        name,
                        email, 
                        role,
                        is_active,
                        created_at,
                        updated_at
                    FROM admins 
                    WHERE id = ? AND is_active = 1
                ");
            } else {
                // Query users table
                $stmt = $db->prepare("
                    SELECT 
                        id, 
                        first_name, 
                        last_name,
                        email, 
                        is_active,
                        created_at,
                        updated_at
                    FROM users 
                    WHERE id = ? AND is_active = 1
                ");
            }
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user) {
                if ($isAdminRole) {
                    // Admin user - role already exists in the table
                    // name field is already set in admins table
                } else {
                    // Regular user - combine first_name and last_name into name for JWT compatibility
                    $user['name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                    $user['role'] = 'customer'; // Default role for regular users
                }
            }
            
            return $user ?: null;
        } catch (\PDOException $e) {
            error_log("Database error in authentication: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if current route is excluded from authentication
     */
    private function isExcludedRoute(string $path): bool
    {
        foreach ($this->excludeRoutes as $route) {
            if (fnmatch($route, $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Create middleware instance for optional authentication
     */
    public static function optional(array $excludeRoutes = []): self
    {
        return new self(false, $excludeRoutes);
    }
    
    /**
     * Create middleware instance for required authentication
     */
    public static function required(array $excludeRoutes = []): self
    {
        return new self(true, $excludeRoutes);
    }
}