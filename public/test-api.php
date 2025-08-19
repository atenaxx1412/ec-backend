<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test API...\n";

try {
    // Define application root
    define('APP_ROOT', dirname(__DIR__));
    
    // Autoloader
    spl_autoload_register(function ($class) {
        $class = str_replace('ECBackend\\', '', $class);
        $file = APP_ROOT . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
    
    echo "Autoloader registered\n";
    
    // Load environment configuration
    $envFile = APP_ROOT . '/.env';
    if (file_exists($envFile)) {
        echo "Loading .env file\n";
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
                putenv(trim($key) . '=' . trim($value));
            }
        }
    }
    
    echo "Environment loaded\n";
    
    // Initialize and run the application
    echo "Creating Application instance\n";
    $app = new \ECBackend\Application();
    echo "Application created successfully\n";
    
    // Test health endpoint directly
    echo "Testing health endpoint\n";
    $health = $app->healthCheck();
    echo "Health check result:\n";
    var_dump($health);
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>