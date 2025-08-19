<?php

namespace ECBackend\Utils;

/**
 * Security Helper Class
 * Provides security utilities for the EC Site API
 */
class SecurityHelper
{
    /**
     * Sanitize input data to prevent XSS attacks
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if (!is_string($input)) {
            return $input;
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email address
     */
    public static function validateEmail($email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate secure password hash
     */
    public static function hashPassword($password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        
        return $token;
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCSRFToken($token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate secure random token
     */
    public static function generateRandomToken($length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Validate file upload security
     */
    public static function validateFileUpload($file): array
    {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload';
            return $errors;
        }
        
        // Check file size (64MB max)
        $maxSize = 64 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size (64MB)';
        }
        
        // Check file type
        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv'
        ];
        
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = 'File extension not allowed';
        }
        
        return $errors;
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600): array
    {
        $key = "rate_limit:" . $identifier;
        
        try {
            $redis = new \Redis();
            $redis->connect('redis', 6379);
            
            $current = $redis->incr($key);
            
            if ($current === 1) {
                $redis->expire($key, $timeWindow);
            }
            
            $now = time();
            $window = floor($now / $timeWindow);
            $resetTime = ($window + 1) * $timeWindow;
            
            return [
                'allowed' => $current <= $maxRequests,
                'remaining' => max(0, $maxRequests - $current),
                'reset_time' => $resetTime
            ];
        } catch (\Exception $e) {
            // If Redis is not available, allow the request
            error_log("Rate limiting error: " . $e->getMessage());
            return [
                'allowed' => true,
                'remaining' => $maxRequests,
                'reset_time' => time() + $timeWindow
            ];
        }
    }

    /**
     * Validate JWT token structure (basic validation)
     */
    public static function validateJWTStructure($token): bool
    {
        $parts = explode('.', $token);
        return count($parts) === 3;
    }

    /**
     * Clean SQL input (basic protection, use prepared statements primarily)
     */
    public static function cleanSQLInput($input): string
    {
        return str_replace(['--', ';', '/*', '*/', 'xp_', 'sp_'], '', $input);
    }

    /**
     * Generate secure filename for uploads
     */
    public static function generateSecureFilename($originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16));
        
        return $filename . '.' . strtolower($extension);
    }

    /**
     * Check if request is from allowed origin (CORS)
     */
    public static function isAllowedOrigin($origin): bool
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:3001'
        ];
        
        return in_array($origin, $allowedOrigins);
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = []): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        error_log('SECURITY: ' . json_encode($logData));
    }
}