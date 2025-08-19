<?php

namespace ECBackend\Config;

/**
 * Application Configuration Manager
 * Centralized configuration for the EC Site API
 */
class AppConfig
{
    private static array $config = [];
    private static bool $initialized = false;
    
    /**
     * Initialize application configuration
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        
        self::$config = [
            'app' => [
                'name' => 'EC Site API',
                'version' => '1.0.0',
                'environment' => getenv('NODE_ENV') ?: 'development',
                'debug' => getenv('APP_DEBUG') === 'true',
                'timezone' => 'Asia/Tokyo',
                'charset' => 'UTF-8'
            ],
            
            'api' => [
                'base_url' => getenv('API_URL') ?: 'http://localhost:8080',
                'version' => 'v1',
                'rate_limit' => [
                    'enabled' => true,
                    'requests_per_minute' => 60,
                    'requests_per_hour' => 1000
                ]
            ],
            
            'cors' => [
                'enabled' => true,
                'allowed_origins' => explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:3000,http://localhost:3001'),
                'allowed_methods' => explode(',', getenv('CORS_ALLOWED_METHODS') ?: 'GET,POST,PUT,DELETE,OPTIONS'),
                'allowed_headers' => explode(',', getenv('CORS_ALLOWED_HEADERS') ?: 'Content-Type,Authorization,X-Requested-With,X-API-Key'),
                'exposed_headers' => ['X-Total-Count', 'X-Page-Count', 'X-Current-Page'],
                'credentials' => true,
                'max_age' => 86400
            ],
            
            'security' => [
                'jwt' => [
                    'secret_key' => getenv('JWT_SECRET') ?: 'development_jwt_secret_key_change_in_production',
                    'access_token_ttl' => self::parseTimeString(getenv('JWT_EXPIRATION') ?: '1h'),
                    'refresh_token_ttl' => self::parseTimeString(getenv('JWT_REFRESH_EXPIRATION') ?: '7d'),
                    'algorithm' => 'HS256',
                    'issuer' => getenv('API_URL') ?: 'http://localhost:8080'
                ],
                'session' => [
                    'lifetime' => intval(getenv('SESSION_LIFETIME') ?: 120),
                    'cookie_secure' => getenv('NODE_ENV') === 'production',
                    'cookie_httponly' => true,
                    'cookie_samesite' => 'Lax'
                ],
                'csrf' => [
                    'enabled' => true,
                    'token_lifetime' => 3600
                ],
                'headers' => [
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'DENY',
                    'X-XSS-Protection' => '1; mode=block',
                    'Referrer-Policy' => 'strict-origin-when-cross-origin'
                ]
            ],
            
            'cache' => [
                'redis' => [
                    'enabled' => true,
                    'host' => getenv('REDIS_HOST') ?: 'redis',
                    'port' => intval(getenv('REDIS_PORT') ?: 6379),
                    'password' => getenv('REDIS_PASSWORD') ?: null,
                    'database' => 0,
                    'prefix' => 'ec_cache:',
                    'default_ttl' => 3600
                ],
                'file' => [
                    'enabled' => false,
                    'path' => '/tmp/cache',
                    'default_ttl' => 3600
                ]
            ],
            
            'logging' => [
                'enabled' => true,
                'level' => getenv('LOG_LEVEL') ?: 'debug',
                'channel' => getenv('LOG_CHANNEL') ?: 'daily',
                'path' => '/var/log/ec-api',
                'max_files' => 30,
                'format' => '[%datetime%] %level_name%: %message% %context% %extra%'
            ],
            
            'mail' => [
                'enabled' => true,
                'smtp' => [
                    'host' => getenv('MAIL_HOST') ?: 'mailpit',
                    'port' => intval(getenv('MAIL_PORT') ?: 1025),
                    'username' => getenv('MAIL_USERNAME') ?: '',
                    'password' => getenv('MAIL_PASSWORD') ?: '',
                    'encryption' => getenv('MAIL_ENCRYPTION') ?: null
                ],
                'from' => [
                    'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@ec-site-dev.local',
                    'name' => getenv('MAIL_FROM_NAME') ?: 'EC Site Development'
                ]
            ],
            
            'pagination' => [
                'default_limit' => 20,
                'max_limit' => 100,
                'page_param' => 'page',
                'limit_param' => 'limit'
            ],
            
            'upload' => [
                'max_size' => getenv('UPLOAD_MAX_SIZE') ?: '64M',
                'allowed_types' => explode(',', getenv('ALLOWED_FILE_TYPES') ?: 'jpg,jpeg,png,gif,pdf'),
                'upload_path' => '/var/www/html/uploads',
                'public_path' => '/uploads'
            ],
            
            'features' => [
                'user_registration' => true,
                'guest_checkout' => true,
                'product_reviews' => true,
                'wishlist' => true,
                'coupons' => true,
                'analytics' => true,
                'search' => true,
                'recommendations' => false
            ]
        ];
        
        self::$initialized = true;
        
        // Set PHP timezone
        date_default_timezone_set(self::$config['app']['timezone']);
    }
    
    /**
     * Get configuration value
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$initialized) {
            self::init();
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     */
    public static function set(string $key, $value): void
    {
        if (!self::$initialized) {
            self::init();
        }
        
        $keys = explode('.', $key);
        $config = &self::$config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Check if feature is enabled
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        return self::get("features.{$feature}", false);
    }
    
    /**
     * Get environment
     */
    public static function getEnvironment(): string
    {
        return self::get('app.environment', 'development');
    }
    
    /**
     * Check if in debug mode
     */
    public static function isDebug(): bool
    {
        return self::get('app.debug', false);
    }
    
    /**
     * Check if in production environment
     */
    public static function isProduction(): bool
    {
        return self::getEnvironment() === 'production';
    }
    
    /**
     * Get CORS configuration
     */
    public static function getCorsConfig(): array
    {
        return self::get('cors', []);
    }
    
    /**
     * Get security headers
     */
    public static function getSecurityHeaders(): array
    {
        return self::get('security.headers', []);
    }
    
    /**
     * Get all configuration (for debugging)
     */
    public static function all(): array
    {
        if (!self::$initialized) {
            self::init();
        }
        
        $config = self::$config;
        
        // Hide sensitive information in non-debug mode
        if (!self::isDebug()) {
            unset($config['security']['jwt']['secret']);
            unset($config['mail']['smtp']['password']);
            unset($config['cache']['redis']['password']);
        }
        
        return $config;
    }
    
    /**
     * Validate configuration
     */
    public static function validate(): array
    {
        $errors = [];
        
        // Check required configurations
        $required = [
            'app.name',
            'app.environment',
            'cors.allowed_origins',
            'security.jwt.secret'
        ];
        
        foreach ($required as $key) {
            if (empty(self::get($key))) {
                $errors[] = "Missing required configuration: {$key}";
            }
        }
        
        // Validate JWT secret in production
        if (self::isProduction() && self::get('security.jwt.secret') === 'development_jwt_secret_key_change_in_production') {
            $errors[] = 'JWT secret must be changed in production environment';
        }
        
        // Validate CORS origins
        $origins = self::get('cors.allowed_origins', []);
        if (self::isProduction() && in_array('*', $origins)) {
            $errors[] = 'CORS wildcard (*) should not be used in production';
        }
        
        return $errors;
    }
    
    /**
     * Parse time string to seconds
     */
    private static function parseTimeString(string $timeString): int
    {
        $timeString = trim($timeString);
        
        // Handle pure numbers (assume seconds)
        if (is_numeric($timeString)) {
            return (int) $timeString;
        }
        
        // Parse time units
        $units = [
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
            'w' => 604800,
            'M' => 2592000, // 30 days
            'y' => 31536000  // 365 days
        ];
        
        if (preg_match('/^(\d+)([smhdwMy])$/', $timeString, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            if (isset($units[$unit])) {
                return $value * $units[$unit];
            }
        }
        
        // Default fallback (1 hour)
        return 3600;
    }
}