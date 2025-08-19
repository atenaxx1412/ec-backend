<?php

namespace ECBackend\Middleware;

use ECBackend\Config\AppConfig;
use ECBackend\Utils\Response;

/**
 * CORS Middleware
 * Handles Cross-Origin Resource Sharing (CORS) headers and preflight requests
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(array $request, callable $next)
    {
        $corsConfig = AppConfig::getCorsConfig();
        
        if (!$corsConfig['enabled']) {
            return $next($request);
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Handle preflight requests
        if ($method === 'OPTIONS') {
            $this->handlePreflight($origin, $corsConfig);
            return null; // Terminate here for preflight
        }
        
        // Set CORS headers for actual requests
        $this->setCorsHeaders($origin, $corsConfig);
        
        return $next($request);
    }
    
    /**
     * Handle CORS preflight request
     */
    private function handlePreflight(string $origin, array $corsConfig): void
    {
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin, $corsConfig)) {
            http_response_code(403);
            exit;
        }
        
        // Set preflight headers
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: ' . implode(', ', $corsConfig['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $corsConfig['allowed_headers']));
        header('Access-Control-Max-Age: ' . $corsConfig['max_age']);
        
        if ($corsConfig['credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }
        
        if (!empty($corsConfig['exposed_headers'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $corsConfig['exposed_headers']));
        }
        
        http_response_code(204);
        exit;
    }
    
    /**
     * Set CORS headers for actual requests
     */
    private function setCorsHeaders(string $origin, array $corsConfig): void
    {
        if (!$this->isOriginAllowed($origin, $corsConfig)) {
            return;
        }
        
        // Set basic CORS headers
        if (in_array('*', $corsConfig['allowed_origins'])) {
            header('Access-Control-Allow-Origin: *');
        } else {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }
        
        if ($corsConfig['credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }
        
        if (!empty($corsConfig['exposed_headers'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $corsConfig['exposed_headers']));
        }
    }
    
    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(string $origin, array $corsConfig): bool
    {
        if (empty($origin)) {
            return true; // Allow same-origin requests
        }
        
        if (in_array('*', $corsConfig['allowed_origins'])) {
            return true;
        }
        
        return in_array($origin, $corsConfig['allowed_origins']);
    }
}