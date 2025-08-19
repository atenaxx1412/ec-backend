<?php

namespace ECBackend\Middleware;

use ECBackend\Config\AppConfig;

/**
 * Logging Middleware
 * Logs API requests and responses for monitoring and debugging
 */
class LoggingMiddleware implements MiddlewareInterface
{
    private array $config;
    private float $startTime;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'log_requests' => true,
            'log_responses' => true,
            'log_body' => false,
            'log_headers' => false,
            'sensitive_fields' => ['password', 'token', 'secret', 'key'],
            'max_body_length' => 1000
        ], $config);
        
        $this->startTime = microtime(true);
    }
    
    public function handle(array $request, callable $next)
    {
        $requestId = $this->generateRequestId();
        
        // Log incoming request
        if ($this->config['log_requests']) {
            $this->logRequest($requestId, $request);
        }
        
        // Process request
        $response = $next($request);
        
        // Log response
        if ($this->config['log_responses']) {
            $this->logResponse($requestId, $response);
        }
        
        return $response;
    }
    
    /**
     * Log incoming request
     */
    private function logRequest(string $requestId, array $request): void
    {
        $logData = [
            'type' => 'request',
            'request_id' => $requestId,
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'client_ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $request['auth']['user_id'] ?? null,
            'query_params' => $_GET,
        ];
        
        // Add headers if enabled
        if ($this->config['log_headers']) {
            $logData['headers'] = $this->sanitizeHeaders($this->getAllHeaders());
        }
        
        // Add body if enabled
        if ($this->config['log_body']) {
            $body = file_get_contents('php://input');
            if ($body) {
                $logData['body'] = $this->sanitizeBody($body);
            }
        }
        
        $this->writeLog($logData);
    }
    
    /**
     * Log response
     */
    private function logResponse(string $requestId, $response): void
    {
        $executionTime = microtime(true) - $this->startTime;
        
        $logData = [
            'type' => 'response',
            'request_id' => $requestId,
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_time' => round($executionTime * 1000, 2), // milliseconds
            'status_code' => http_response_code(),
            'memory_usage' => memory_get_peak_usage(true),
        ];
        
        // Add response body if enabled and response is array
        if ($this->config['log_body'] && is_array($response)) {
            $logData['response'] = $this->sanitizeResponse($response);
        }
        
        $this->writeLog($logData);
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
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
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
     * Sanitize headers by removing sensitive information
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            
            if (in_array($lowerName, ['authorization', 'cookie', 'x-api-key'])) {
                $sanitized[$name] = '[REDACTED]';
            } else {
                $sanitized[$name] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize request body by removing sensitive fields
     */
    private function sanitizeBody(string $body): string
    {
        if (strlen($body) > $this->config['max_body_length']) {
            $body = substr($body, 0, $this->config['max_body_length']) . '...[TRUNCATED]';
        }
        
        // Try to parse as JSON and sanitize
        $decoded = json_decode($body, true);
        if ($decoded !== null) {
            $sanitized = $this->sanitizeArray($decoded);
            return json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        }
        
        // For non-JSON data, check for sensitive patterns
        foreach ($this->config['sensitive_fields'] as $field) {
            $body = preg_replace('/(' . preg_quote($field) . '\s*[=:]\s*)[^&\s\n]*/i', '$1[REDACTED]', $body);
        }
        
        return $body;
    }
    
    /**
     * Sanitize response by removing sensitive fields
     */
    private function sanitizeResponse(array $response): array
    {
        return $this->sanitizeArray($response);
    }
    
    /**
     * Recursively sanitize array by removing sensitive fields
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            if (in_array($lowerKey, $this->config['sensitive_fields'])) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Write log entry
     */
    private function writeLog(array $logData): void
    {
        $logLevel = AppConfig::get('logging.level', 'info');
        $logFormat = AppConfig::get('logging.format', 'json');
        
        if ($logFormat === 'json') {
            $logEntry = json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            $logEntry = sprintf(
                "[%s] %s %s %s - %s\n",
                $logData['timestamp'],
                $logData['type'],
                $logData['method'] ?? '',
                $logData['uri'] ?? '',
                $logData['request_id']
            );
        }
        
        // Write to log file
        $logFile = AppConfig::get('logging.file', '/var/log/apache2/api.log');
        error_log($logEntry, 3, $logFile);
        
        // Also log to system error log in development
        if (AppConfig::get('app.debug', false)) {
            error_log("API_LOG: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
        }
    }
    
    /**
     * Create middleware instance for different log levels
     */
    public static function minimal(): self
    {
        return new self([
            'log_requests' => true,
            'log_responses' => false,
            'log_body' => false,
            'log_headers' => false
        ]);
    }
    
    public static function detailed(): self
    {
        return new self([
            'log_requests' => true,
            'log_responses' => true,
            'log_body' => true,
            'log_headers' => true
        ]);
    }
    
    public static function debug(): self
    {
        return new self([
            'log_requests' => true,
            'log_responses' => true,
            'log_body' => true,
            'log_headers' => true,
            'max_body_length' => 5000
        ]);
    }
}