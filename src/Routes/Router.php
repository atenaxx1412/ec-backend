<?php

namespace ECBackend\Routes;

use ECBackend\Config\AppConfig;
use ECBackend\Exceptions\ApiException;

/**
 * API Router
 * Handles routing for RESTful API endpoints
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    
    public function __construct(string $prefix = '')
    {
        $this->prefix = rtrim($prefix, '/');
    }
    
    /**
     * Add GET route
     */
    public function get(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $pattern, $handler, $middleware);
    }
    
    /**
     * Add POST route
     */
    public function post(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $pattern, $handler, $middleware);
    }
    
    /**
     * Add PUT route
     */
    public function put(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $pattern, $handler, $middleware);
    }
    
    /**
     * Add DELETE route
     */
    public function delete(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }
    
    /**
     * Add OPTIONS route
     */
    public function options(string $pattern, callable|array $handler, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $pattern, $handler, $middleware);
    }
    
    /**
     * Add route for multiple methods
     */
    public function route(array $methods, string $pattern, callable|array $handler, array $middleware = []): self
    {
        foreach ($methods as $method) {
            $this->addRoute($method, $pattern, $handler, $middleware);
        }
        return $this;
    }
    
    /**
     * Add route group with prefix and middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): self
    {
        $originalPrefix = $this->prefix;
        $originalMiddleware = $this->middleware;
        
        $this->prefix = $this->prefix . '/' . trim($prefix, '/');
        $this->middleware = array_merge($this->middleware, $middleware);
        
        $callback($this);
        
        $this->prefix = $originalPrefix;
        $this->middleware = $originalMiddleware;
        
        return $this;
    }
    
    /**
     * Add middleware to all routes
     */
    public function middleware(array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }
    
    /**
     * Add single route
     */
    private function addRoute(string $method, string $pattern, callable|array $handler, array $middleware = []): self
    {
        $pattern = $this->prefix . '/' . ltrim($pattern, '/');
        $pattern = rtrim($pattern, '/') ?: '/';
        
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $middleware),
            'params' => $this->extractParams($pattern)
        ];
        
        return $this;
    }
    
    /**
     * Extract parameter names from pattern
     */
    private function extractParams(string $pattern): array
    {
        preg_match_all('/\{([^}]+)\}/', $pattern, $matches);
        return $matches[1] ?? [];
    }
    
    /**
     * Dispatch request
     */
    public function dispatch(string $method, string $uri): array
    {
        $method = strtoupper($method);
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';
        
        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            $params = $this->matchRoute($route['pattern'], $uri);
            if ($params !== false) {
                return [
                    'handler' => $route['handler'],
                    'middleware' => $route['middleware'],
                    'params' => $params,
                    'route' => $route
                ];
            }
        }
        
        throw new ApiException('Route not found', 404, null, [], 'ROUTE_NOT_FOUND');
    }
    
    /**
     * Match route pattern against URI
     */
    private function matchRoute(string $pattern, string $uri): array|false
    {
        // Convert pattern to regex
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        if (!preg_match($regex, $uri, $matches)) {
            return false;
        }
        
        // Extract parameter names and values
        $params = [];
        $paramNames = $this->extractParams($pattern);
        
        for ($i = 1; $i < count($matches); $i++) {
            if (isset($paramNames[$i - 1])) {
                $params[$paramNames[$i - 1]] = $matches[$i];
            }
        }
        
        return $params;
    }
    
    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
    
    /**
     * Get routes summary for documentation
     */
    public function getRoutesSummary(): array
    {
        $summary = [];
        
        foreach ($this->routes as $route) {
            $summary[] = [
                'method' => $route['method'],
                'pattern' => $route['pattern'],
                'params' => $route['params'],
                'middleware_count' => count($route['middleware'])
            ];
        }
        
        return $summary;
    }
    
    /**
     * Generate URL for named route (if implemented)
     */
    public function url(string $name, array $params = []): string
    {
        // This would be implemented if we had named routes
        // For now, just return the base URL
        return AppConfig::get('api.base_url');
    }
}