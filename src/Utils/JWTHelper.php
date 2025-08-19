<?php

namespace ECBackend\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use ECBackend\Config\AppConfig;
use ECBackend\Exceptions\ApiException;

/**
 * JWT Helper Class
 * Handles JWT token generation, validation, and management
 */
class JWTHelper
{
    private static string $algorithm = 'HS256';
    
    /**
     * Generate JWT access token
     */
    public static function generateAccessToken(array $userData): string
    {
        $now = time();
        $expiration = $now + (AppConfig::get('security.jwt.access_token_ttl', 3600)); // 1 hour default
        
        $payload = [
            'iss' => AppConfig::get('app.url', 'http://localhost:8080'), // Issuer
            'aud' => AppConfig::get('app.url', 'http://localhost:8080'), // Audience
            'iat' => $now, // Issued at
            'exp' => $expiration, // Expiration
            'sub' => $userData['id'], // Subject (user ID)
            'user' => [
                'id' => $userData['id'],
                'email' => $userData['email'],
                'name' => $userData['name'],
                'role' => $userData['role'] ?? 'customer'
            ],
            'type' => 'access_token'
        ];
        
        return JWT::encode($payload, self::getSecretKey(), self::$algorithm);
    }
    
    /**
     * Generate JWT refresh token
     */
    public static function generateRefreshToken(int $userId): string
    {
        $now = time();
        $expiration = $now + (AppConfig::get('security.jwt.refresh_token_ttl', 604800)); // 7 days default
        
        $payload = [
            'iss' => AppConfig::get('app.url', 'http://localhost:8080'),
            'aud' => AppConfig::get('app.url', 'http://localhost:8080'),
            'iat' => $now,
            'exp' => $expiration,
            'sub' => $userId,
            'type' => 'refresh_token',
            'jti' => bin2hex(random_bytes(16)) // Unique token ID
        ];
        
        return JWT::encode($payload, self::getSecretKey(), self::$algorithm);
    }
    
    /**
     * Validate and decode JWT token
     */
    public static function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::$algorithm));
            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new ApiException('Token has expired', 401, null, [], 'TOKEN_EXPIRED');
        } catch (\Firebase\JWT\BeforeValidException $e) {
            throw new ApiException('Token not yet valid', 401, null, [], 'TOKEN_NOT_VALID_YET');
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new ApiException('Invalid token signature', 401, null, [], 'INVALID_TOKEN_SIGNATURE');
        } catch (\Exception $e) {
            throw new ApiException('Invalid token', 401, null, [], 'INVALID_TOKEN');
        }
    }
    
    /**
     * Extract user data from token
     */
    public static function getUserFromToken(string $token): array
    {
        $decoded = self::validateToken($token);
        
        if (!isset($decoded['user'])) {
            throw new ApiException('Invalid token format', 401, null, [], 'INVALID_TOKEN_FORMAT');
        }
        
        return (array) $decoded['user'];
    }
    
    /**
     * Extract token from Authorization header
     */
    public static function extractTokenFromHeader(): ?string
    {
        // Try multiple ways to get Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? 
                     $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 
                     null;
        
        // Try alternative methods for Apache
        if (!$authHeader && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
        
        // Try getallheaders() if available
        if (!$authHeader && function_exists('getallheaders')) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }
        
        if (!$authHeader) {
            return null;
        }
        
        // Expected format: "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Check if token is expired
     */
    public static function isTokenExpired(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::$algorithm));
            return time() >= $decoded->exp;
        } catch (\Exception $e) {
            return true; // Consider invalid tokens as expired
        }
    }
    
    /**
     * Refresh access token using refresh token
     */
    public static function refreshAccessToken(string $refreshToken): array
    {
        $decoded = self::validateToken($refreshToken);
        
        // Verify it's a refresh token
        if (!isset($decoded['type']) || $decoded['type'] !== 'refresh_token') {
            throw new ApiException('Invalid refresh token', 401, null, [], 'INVALID_REFRESH_TOKEN');
        }
        
        // Get user ID from refresh token
        $userId = $decoded['sub'];
        
        // Get user data from database
        $userData = self::getUserDataFromDatabase($userId);
        
        if (!$userData) {
            throw new ApiException('User not found', 404, null, [], 'USER_NOT_FOUND');
        }
        
        // Generate new tokens
        $newAccessToken = self::generateAccessToken($userData);
        $newRefreshToken = self::generateRefreshToken($userData['id']);
        
        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => AppConfig::get('security.jwt.access_token_ttl', 3600)
        ];
    }
    
    /**
     * Generate token pair (access + refresh)
     */
    public static function generateTokenPair(array $userData): array
    {
        $accessToken = self::generateAccessToken($userData);
        $refreshToken = self::generateRefreshToken($userData['id']);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => AppConfig::get('security.jwt.access_token_ttl', 3600)
        ];
    }
    
    /**
     * Get JWT secret key
     */
    private static function getSecretKey(): string
    {
        $secret = AppConfig::get('security.jwt.secret_key');
        
        if (!$secret) {
            throw new \RuntimeException('JWT secret key not configured');
        }
        
        return $secret;
    }
    
    /**
     * Get user data from database by user ID
     */
    private static function getUserDataFromDatabase(int $userId): ?array
    {
        try {
            $db = \ECBackend\Config\Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    id, first_name, email, is_active
                FROM users 
                WHERE id = ? AND is_active = 1
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                return null;
            }
            
            return [
                'id' => (int) $user['id'],
                'name' => $user['first_name'],
                'email' => $user['email'],
                'role' => 'customer'  // Default role
            ];
        } catch (\PDOException $e) {
            error_log("Database error in JWT getUserDataFromDatabase: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Blacklist token (for logout functionality)
     */
    public static function blacklistToken(string $token): bool
    {
        try {
            $decoded = self::validateToken($token);
            $jti = $decoded['jti'] ?? null;
            
            if (!$jti) {
                return false;
            }
            
            // Store blacklisted token in Redis/cache
            $redis = new \Redis();
            $redis->connect(AppConfig::get('redis.host', 'redis'), AppConfig::get('redis.port', 6379));
            
            $expiration = $decoded['exp'] - time();
            if ($expiration > 0) {
                $redis->setex("blacklisted_token:{$jti}", $expiration, '1');
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("Failed to blacklist token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if token is blacklisted
     */
    public static function isTokenBlacklisted(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::$algorithm));
            $jti = $decoded->jti ?? null;
            
            if (!$jti) {
                return false;
            }
            
            $redis = new \Redis();
            $redis->connect(AppConfig::get('redis.host', 'redis'), AppConfig::get('redis.port', 6379));
            
            return $redis->exists("blacklisted_token:{$jti}");
        } catch (\Exception $e) {
            return false;
        }
    }
}