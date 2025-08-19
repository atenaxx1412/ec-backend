<?php

namespace ECBackend;

use ECBackend\Config\Database;
use ECBackend\Config\AppConfig;
use ECBackend\Routes\Router;
use ECBackend\Utils\Response;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;
use ECBackend\Middleware\CorsMiddleware;
use ECBackend\Middleware\LoggingMiddleware;
use ECBackend\Middleware\AuthenticationMiddleware;
use ECBackend\Middleware\RateLimitMiddleware;

/**
 * Main Application Class
 * Handles request lifecycle, routing, middleware, and error handling
 */
class Application
{
    private Router $router;
    private array $middleware = [];
    private array $config = [];
    
    public function __construct()
    {
        $this->initializeApplication();
        $this->setupRouter();
        $this->registerMiddleware();
        $this->registerRoutes();
    }
    
    /**
     * Initialize application
     */
    private function initializeApplication(): void
    {
        // Initialize configuration first
        AppConfig::init();
        
        // Set error reporting
        if (AppConfig::get('app.debug', false)) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(0);
            ini_set('display_errors', 0);
        }
        
        // Set timezone
        date_default_timezone_set(AppConfig::get('app.timezone', 'UTC'));
        
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Set memory limit for large operations
        ini_set('memory_limit', '256M');
        
        // Set execution time limit
        set_time_limit(120);
    }
    
    /**
     * Setup router
     */
    private function setupRouter(): void
    {
        $this->router = new Router('/api');
    }
    
    /**
     * Register global middleware
     */
    private function registerMiddleware(): void
    {
        // CORS middleware (first to handle preflight requests)
        $this->middleware[] = new CorsMiddleware();
        
        // Logging middleware
        if (AppConfig::get('logging.enabled', true)) {
            $this->middleware[] = AppConfig::get('app.debug', false) 
                ? LoggingMiddleware::debug()
                : LoggingMiddleware::minimal();
        }
        
        // Global rate limiting
        $this->middleware[] = RateLimitMiddleware::perMinute(120); // 120 requests per minute
    }
    
    /**
     * Register all API routes
     */
    private function registerRoutes(): void
    {
        // Public routes (no authentication required)
        $this->registerPublicRoutes();
        
        // Protected routes (authentication required)
        $this->registerProtectedRoutes();
        
        // Admin routes (admin role required)
        $this->registerAdminRoutes();
    }
    
    /**
     * Register public routes
     */
    private function registerPublicRoutes(): void
    {
        // Health check
        $this->router->get('/health', function() { return $this->healthCheck(); });
        
        // Authentication routes
        $this->router->post('/auth/login', [\ECBackend\Controllers\AuthController::class, 'login']);
        $this->router->post('/auth/register', [\ECBackend\Controllers\AuthController::class, 'register']);
        $this->router->post('/auth/refresh', [\ECBackend\Controllers\AuthController::class, 'refresh']);
        
        // Product routes (public)
        $this->router->get('/products', [\ECBackend\Controllers\ProductController::class, 'index']);
        $this->router->get('/products/search', [\ECBackend\Controllers\ProductController::class, 'search']);
        $this->router->get('/products/category/{slug}', [\ECBackend\Controllers\ProductController::class, 'byCategory']);
        $this->router->get('/products/{id}', [\ECBackend\Controllers\ProductController::class, 'show']);
        
        // Category routes (public)
        $this->router->get('/categories', [\ECBackend\Controllers\CategoryController::class, 'index']);
        $this->router->get('/categories/tree', [\ECBackend\Controllers\CategoryController::class, 'tree']);
        $this->router->get('/categories/popular', [\ECBackend\Controllers\CategoryController::class, 'popular']);
        $this->router->get('/categories/{id}', [\ECBackend\Controllers\CategoryController::class, 'show']);
        
        // Admin public routes
        $this->router->post('/admin/login', [\ECBackend\Controllers\AdminController::class, 'login']);
    }
    
    /**
     * Register protected routes (require authentication)
     */
    private function registerProtectedRoutes(): void
    {
        $authMiddleware = [AuthenticationMiddleware::required()];
        
        // User profile routes
        $this->router->group('/auth', $authMiddleware, function($router) {
            $router->get('/profile', [\ECBackend\Controllers\AuthController::class, 'profile']);
            $router->put('/profile', [\ECBackend\Controllers\AuthController::class, 'updateProfile']);
            $router->put('/password', [\ECBackend\Controllers\AuthController::class, 'changePassword']);
            $router->post('/logout', [\ECBackend\Controllers\AuthController::class, 'logout']);
        });
        
        // Shopping cart routes
        $this->router->group('/cart', $authMiddleware, function($router) {
            $router->get('/', [\ECBackend\Controllers\CartController::class, 'index']);
            $router->get('/summary', [\ECBackend\Controllers\CartController::class, 'summary']);
            $router->post('/add', [\ECBackend\Controllers\CartController::class, 'add']);
            $router->put('/{id}', [\ECBackend\Controllers\CartController::class, 'update']);
            $router->delete('/{id}', [\ECBackend\Controllers\CartController::class, 'remove']);
            $router->delete('/', [\ECBackend\Controllers\CartController::class, 'clear']);
        });
    }
    
    /**
     * Register admin routes (require admin role)
     */
    private function registerAdminRoutes(): void
    {
        $adminMiddleware = [
            AuthenticationMiddleware::required(),
            RateLimitMiddleware::perUser(50, 60) // More restrictive for admin
        ];
        
        $this->router->group('/admin', $adminMiddleware, function($router) {
            // Dashboard
            $router->get('/dashboard', [\ECBackend\Controllers\AdminController::class, 'dashboard']);
            
            // Product management
            $router->get('/products', [\ECBackend\Controllers\AdminController::class, 'getProducts']);
            $router->post('/products', [\ECBackend\Controllers\AdminController::class, 'createProduct']);
            $router->put('/products/{id}', [\ECBackend\Controllers\AdminController::class, 'updateProduct']);
            $router->delete('/products/{id}', [\ECBackend\Controllers\AdminController::class, 'deleteProduct']);
        });
    }
    
    /**
     * Run the application
     */
    public function run(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            
            // Handle preflight requests
            if ($method === 'OPTIONS') {
                Response::corsPreflightResponse();
            }
            
            // Dispatch request through middleware chain
            $response = $this->dispatchRequest($method, $uri);
            
            // Send response
            if ($response !== null) {
                Response::json($response);
            }
            
        } catch (ApiException $e) {
            $this->handleApiException($e);
        } catch (DatabaseException $e) {
            $this->handleDatabaseException($e);
        } catch (\Throwable $e) {
            $this->handleGenericException($e);
        }
    }
    
    /**
     * Dispatch request through middleware chain
     */
    private function dispatchRequest(string $method, string $uri)
    {
        // Build middleware chain
        $middlewareChain = $this->buildMiddlewareChain();
        
        // Execute middleware chain
        return $middlewareChain(['method' => $method, 'uri' => $uri]);
    }
    
    /**
     * Build middleware execution chain
     */
    private function buildMiddlewareChain(): callable
    {
        $middleware = array_reverse($this->middleware);
        
        // Final handler - route dispatch
        $handler = function($request) {
            return $this->dispatchRoute($request['method'], $request['uri']);
        };
        
        // Build chain from right to left
        foreach ($middleware as $mw) {
            $handler = function($request) use ($mw, $handler) {
                return $mw->handle($request, $handler);
            };
        }
        
        return $handler;
    }
    
    /**
     * Dispatch route
     */
    private function dispatchRoute(string $method, string $uri)
    {
        try {
            $routeInfo = $this->router->dispatch($method, $uri);
            
            // Execute route middleware
            $routeMiddleware = $routeInfo['middleware'] ?? [];
            $request = ['method' => $method, 'uri' => $uri];
            
            // Build route-specific middleware chain
            if (!empty($routeMiddleware)) {
                $handler = function($request) use ($routeInfo) {
                    return $this->executeController($routeInfo);
                };
                
                foreach (array_reverse($routeMiddleware) as $mw) {
                    $handler = function($request) use ($mw, $handler) {
                        return $mw->handle($request, $handler);
                    };
                }
                
                return $handler($request);
            } else {
                return $this->executeController($routeInfo);
            }
            
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Show detailed error for debugging
            throw new ApiException('Route dispatch error: ' . $e->getMessage(), 500, $e, [
                'original_error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'ROUTE_DISPATCH_ERROR');
        }
    }
    
    /**
     * Execute controller method
     */
    private function executeController(array $routeInfo)
    {
        $handler = $routeInfo['handler'];
        $params = $routeInfo['params'] ?? [];
        
        if (is_array($handler)) {
            [$controllerClass, $method] = $handler;
            
            if (!class_exists($controllerClass)) {
                throw new ApiException('Controller not found', 500, null, [], 'CONTROLLER_NOT_FOUND');
            }
            
            $controller = new $controllerClass();
            
            if (!method_exists($controller, $method)) {
                throw new ApiException('Controller method not found', 500, null, [], 'METHOD_NOT_FOUND');
            }
            
            // Set route parameters
            if (method_exists($controller, 'setParams')) {
                $controller->setParams($params);
            }
            
            // Set authenticated user if available from request (JWT-based auth)
            if (method_exists($controller, 'setUser')) {
                // Check for user in request context (set by AuthenticationMiddleware)
                global $__request_context;
                if (isset($__request_context['user'])) {
                    $controller->setUser($__request_context['user']);
                }
                // Fallback to session-based auth
                elseif (isset($_SESSION['user'])) {
                    $controller->setUser($_SESSION['user']);
                }
            }
            
            return $controller->$method();
            
        } elseif (is_callable($handler)) {
            return $handler($params);
        } else {
            throw new ApiException('Invalid route handler', 500, null, [], 'INVALID_HANDLER');
        }
    }
    
    /**
     * Health check endpoint
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
            'environment' => AppConfig::get('app.env', 'development'),
            'services' => []
        ];
        
        // Check database connection
        try {
            $db = Database::getConnection();
            $stmt = $db->query("SELECT 1");
            $health['services']['database'] = 'healthy';
        } catch (\Exception $e) {
            $health['services']['database'] = 'unhealthy';
            $health['status'] = 'unhealthy';
        }
        
        // Check Redis connection
        try {
            $redis = new \Redis();
            $redis->connect('localhost', 6379);
            $redis->ping();
            $health['services']['redis'] = 'healthy';
            $redis->close();
        } catch (\Exception $e) {
            $health['services']['redis'] = 'unavailable';
        }
        
        return [
            'success' => true,
            'data' => $health,
            'message' => 'Health check completed'
        ];
    }
    
    /**
     * Handle API exceptions
     */
    private function handleApiException(ApiException $e): void
    {
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => $e->getErrorCode(),
            'errors' => $e->getErrors(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Log error
        if ($e->getCode() >= 500) {
            error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
        
        Response::json($response, $e->getCode());
    }
    
    /**
     * Handle database exceptions
     */
    private function handleDatabaseException(DatabaseException $e): void
    {
        error_log("Database Error: " . $e->getMessage());
        error_log("Query: " . $e->getQuery());
        error_log("Params: " . json_encode($e->getParams()));
        
        if (AppConfig::get('app.debug', false)) {
            $response = [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'DATABASE_ERROR',
                'query' => $e->getQuery(),
                'params' => $e->getParams(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Database error occurred',
                'error_code' => 'DATABASE_ERROR',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        Response::json($response, 500);
    }
    
    /**
     * Handle generic exceptions
     */
    private function handleGenericException(\Throwable $e): void
    {
        error_log("Unhandled Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Always show debug info for now
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'INTERNAL_ERROR',
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'debug_enabled' => AppConfig::get('app.debug', false),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        Response::json($response, 500);
    }
}