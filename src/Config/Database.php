<?php

namespace ECBackend\Config;

use PDO;
use PDOException;
use ECBackend\Exceptions\DatabaseException;

/**
 * Database Configuration and Connection Manager
 * Handles database connections with connection pooling and error handling
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];
    
    /**
     * Initialize database configuration
     */
    public static function init(): void
    {
        self::$config = [
            'host' => getenv('DB_HOST') ?: 'mysql',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'ecommerce_dev_db',
            'username' => getenv('DB_USERNAME') ?: 'ec_dev_user',
            'password' => getenv('DB_PASSWORD') ?: 'dev_password_123',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        ];
    }
    
    /**
     * Get database connection (singleton pattern)
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }
        
        return self::$connection;
    }
    
    /**
     * Establish database connection
     */
    private static function connect(): void
    {
        if (empty(self::$config)) {
            self::init();
        }
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['port'],
                self::$config['database'],
                self::$config['charset']
            );
            
            self::$connection = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                self::$config['options']
            );
            
            // Set timezone to match application
            self::$connection->exec("SET time_zone = '+09:00'");
            
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Database connection failed: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public static function execute(string $query, array $params = []): \PDOStatement
    {
        try {
            $connection = self::getConnection();
            $stmt = $connection->prepare($query);
            $stmt->execute($params);
            
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Query execution failed: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }
    
    /**
     * Fetch single row
     */
    public static function fetch(string $query, array $params = []): ?array
    {
        $stmt = self::execute($query, $params);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }
    
    /**
     * Fetch all rows
     */
    public static function fetchAll(string $query, array $params = []): array
    {
        $stmt = self::execute($query, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get last insert ID
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::getConnection()->rollback();
    }
    
    /**
     * Check if in transaction
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }
    
    /**
     * Close database connection
     */
    public static function close(): void
    {
        self::$connection = null;
    }
    
    /**
     * Get database configuration for debugging
     */
    public static function getConfig(): array
    {
        $config = self::$config;
        // Hide sensitive information
        unset($config['password']);
        return $config;
    }
    
    /**
     * Test database connection
     */
    public static function testConnection(): array
    {
        try {
            $connection = self::getConnection();
            
            // Test query
            $stmt = $connection->query('SELECT 1 as test, NOW() as current_time, CONNECTION_ID() as connection_id');
            $result = $stmt->fetch();
            
            // Get server info
            $version = $connection->getAttribute(PDO::ATTR_SERVER_VERSION);
            $info = $connection->getAttribute(PDO::ATTR_SERVER_INFO);
            
            return [
                'status' => 'connected',
                'test_query' => $result,
                'server_version' => $version,
                'server_info' => $info,
                'connection_id' => $result['connection_id'],
                'current_time' => $result['current_time']
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
}