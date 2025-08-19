<?php

namespace ECBackend\Controllers;

use ECBackend\Config\AppConfig;
use ECBackend\Exceptions\ApiException;
use ECBackend\Utils\SecurityHelper;

/**
 * Base Controller
 * Common functionality for all API controllers
 */
abstract class BaseController
{
    protected array $request = [];
    protected array $params = [];
    protected ?array $user = null;
    
    public function __construct()
    {
        $this->initializeRequest();
    }
    
    /**
     * Initialize request data
     */
    private function initializeRequest(): void
    {
        // Get request method
        $this->request['method'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Get request headers
        $this->request['headers'] = $this->getAllHeaders();
        
        // Get query parameters
        $this->request['query'] = $_GET;
        
        // Get content type (needed before getting body)
        $this->request['content_type'] = $_SERVER['CONTENT_TYPE'] ?? '';
        
        // Get request body
        $this->request['body'] = $this->getRequestBody();
        
        // Get user agent
        $this->request['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Get client IP
        $this->request['client_ip'] = $this->getClientIp();
    }
    
    /**
     * Get all request headers
     */
    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Get request body
     */
    private function getRequestBody(): array
    {
        $body = file_get_contents('php://input');
        
        if (empty($body)) {
            return $_POST;
        }
        
        $contentType = $this->request['content_type'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode($body, true);
            return $decoded ?: [];
        }
        
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($body, $parsed);
            return $parsed;
        }
        
        return [];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Set route parameters
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }
    
    /**
     * Set authenticated user
     */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }
    
    /**
     * Get request parameter
     */
    protected function getParam(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }
    
    /**
     * Get query parameter
     */
    protected function getQuery(string $key, $default = null)
    {
        return $this->request['query'][$key] ?? $default;
    }
    
    /**
     * Get body parameter
     */
    protected function getBody(string $key, $default = null)
    {
        return $this->request['body'][$key] ?? $default;
    }
    
    /**
     * Get header value
     */
    protected function getHeader(string $key, $default = null)
    {
        return $this->request['headers'][$key] ?? $default;
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired(array $fields, array $data = null): void
    {
        $data = $data ?? $this->request['body'];
        $missing = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new ApiException(
                'Required fields are missing',
                400,
                null,
                ['missing_fields' => $missing],
                'VALIDATION_ERROR'
            );
        }
    }
    
    /**
     * Sanitize input data
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = SecurityHelper::sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get pagination parameters
     */
    protected function getPagination(): array
    {
        $page = max(1, intval($this->getQuery('page', 1)));
        $limit = min(
            AppConfig::get('pagination.max_limit', 100),
            max(1, intval($this->getQuery('limit', AppConfig::get('pagination.default_limit', 20))))
        );
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Success response
     */
    protected function success($data = null, string $message = '', int $status = 200): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => [],
            'pagination' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $status
        ];
    }
    
    /**
     * Success response with pagination
     */
    protected function successWithPagination($data, array $pagination, string $message = '', int $status = 200): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => [],
            'pagination' => [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $pagination['total'] ?? 0,
                'total_pages' => isset($pagination['total']) ? ceil($pagination['total'] / $pagination['limit']) : 1,
                'has_next' => isset($pagination['total']) ? ($pagination['page'] * $pagination['limit']) < $pagination['total'] : false,
                'has_prev' => $pagination['page'] > 1
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $status
        ];
    }
    
    /**
     * Error response
     */
    protected function error(string $message, int $status = 400, array $errors = [], ?string $errorCode = null): array
    {
        return [
            'success' => false,
            'data' => null,
            'message' => $message,
            'error_code' => $errorCode,
            'errors' => $errors,
            'pagination' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $status
        ];
    }
    
    /**
     * Check if user is authenticated
     */
    protected function requireAuth(): void
    {
        if ($this->user === null) {
            throw new ApiException(
                'Authentication required',
                401,
                null,
                [],
                'AUTH_REQUIRED'
            );
        }
    }
    
    /**
     * Check if user has specific role
     */
    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        
        if (($this->user['role'] ?? '') !== $role) {
            throw new ApiException(
                'Insufficient permissions',
                403,
                null,
                [],
                'INSUFFICIENT_PERMISSIONS'
            );
        }
    }
    
    /**
     * Rate limiting check
     */
    protected function checkRateLimit(string $key = null): void
    {
        $key = $key ?: $this->request['client_ip'];
        
        // This would be implemented with Redis
        // For now, just a placeholder
    }
    
    /**
     * Log API request
     */
    protected function logRequest(string $action, array $context = []): void
    {
        $logData = [
            'action' => $action,
            'method' => $this->request['method'],
            'user_id' => $this->user['id'] ?? null,
            'client_ip' => $this->request['client_ip'],
            'user_agent' => $this->request['user_agent'],
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context
        ];
        
        // Log to file or database
        error_log(json_encode($logData, JSON_UNESCAPED_UNICODE));
    }
}