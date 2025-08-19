<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working!\n";
echo "PHP Version: " . PHP_VERSION . "\n";

try {
    // Check autoloader
    spl_autoload_register(function ($class) {
        $class = str_replace('ECBackend\\', '', $class);
        $file = dirname(__DIR__) . '/' . str_replace('\\', '/', $class) . '.php';
        echo "Trying to load: $file\n";
        if (file_exists($file)) {
            require_once $file;
            echo "Loaded: $file\n";
        } else {
            echo "File not found: $file\n";
        }
    });
    
    // Try to instantiate Application class
    echo "Attempting to load Application class...\n";
    $app = new \ECBackend\Application();
    echo "Application class loaded successfully!\n";
    
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>