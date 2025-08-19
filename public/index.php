<?php

/**
 * EC Site API Entry Point
 * 
 * This file serves as the entry point for all API requests
 * and initializes the application with proper error handling.
 */

// Start output buffering to prevent any accidental output
ob_start();

// Set proper headers early
header('Content-Type: application/json; charset=utf-8');
header('X-Powered-By: EC Site API v1.0');

try {
    // Define application root
    define('APP_ROOT', dirname(__DIR__));
    
    // Check for Composer autoloader first
    $autoloadPath = APP_ROOT . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    } else {
        // Fallback to manual autoloader for development
        spl_autoload_register(function ($class) {
            $class = str_replace('ECBackend\\', '', $class);
            $file = APP_ROOT . '/src/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
    
    // Load environment configuration
    $envFile = APP_ROOT . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
                putenv(trim($key) . '=' . trim($value));
            }
        }
    }
    
    // Clean any output that might have been generated
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Initialize and run the application
    $app = new \ECBackend\Application();
    $app->run();
    
} catch (\ECBackend\Exceptions\ApiException $e) {
    // Handle API exceptions
    ob_clean();
    
    http_response_code($e->getCode());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getErrorCode(),
        'errors' => $e->getErrors(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\ECBackend\Exceptions\DatabaseException $e) {
    // Handle database exceptions
    ob_clean();
    
    error_log("Database Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\Exception $e) {
    // Handle generic exceptions
    ob_clean();
    
    error_log("Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (\Throwable $e) {
    // Handle any other throwable (PHP 7+)
    ob_clean();
    
    error_log("Fatal Throwable: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Critical system error',
        'error_code' => 'CRITICAL_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
} finally {
    // Clean up output buffer
    if (ob_get_level()) {
        ob_end_flush();
    }
}