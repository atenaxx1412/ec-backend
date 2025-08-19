<?php

namespace ECBackend\Utils;

use ECBackend\Config\AppConfig;

/**
 * Response Helper
 * Handles HTTP responses with proper headers and formatting
 */
class Response
{
    private static bool $headersSent = false;
    
    /**
     * Send JSON response
     */
    public static function json($data, int $statusCode = 200, array $headers = []): void
    {
        self::setHeaders($statusCode, $headers);
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send success response
     */
    public static function success($data = null, string $message = '', int $statusCode = 200, array $headers = []): void
    {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'errors' => [],
            'pagination' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::json($response, $statusCode, $headers);
    }
    
    /**
     * Send error response
     */
    public static function error(string $message, int $statusCode = 400, array $errors = [], ?string $errorCode = null, array $headers = []): void
    {
        $response = [
            'success' => false,
            'data' => null,
            'message' => $message,
            'error_code' => $errorCode,
            'errors' => $errors,
            'pagination' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::json($response, $statusCode, $headers);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, 422, $errors, 'VALIDATION_ERROR');
    }
    
    /**
     * Send not found response
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404, [], 'NOT_FOUND');
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, 401, [], 'UNAUTHORIZED');
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, 403, [], 'FORBIDDEN');
    }
    
    /**
     * Send internal server error response
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 500, [], 'INTERNAL_ERROR');
    }
    
    /**
     * Send CORS preflight response
     */
    public static function corsPreflightResponse(): void
    {
        $corsConfig = AppConfig::getCorsConfig();
        
        $headers = [];
        if ($corsConfig['enabled']) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            if (in_array('*', $corsConfig['allowed_origins']) || in_array($origin, $corsConfig['allowed_origins'])) {
                $headers['Access-Control-Allow-Origin'] = $origin ?: '*';
                $headers['Access-Control-Allow-Credentials'] = $corsConfig['credentials'] ? 'true' : 'false';
            }
            
            $headers['Access-Control-Allow-Methods'] = implode(', ', $corsConfig['allowed_methods']);
            $headers['Access-Control-Allow-Headers'] = implode(', ', $corsConfig['allowed_headers']);
            $headers['Access-Control-Max-Age'] = (string) $corsConfig['max_age'];
            
            if (!empty($corsConfig['exposed_headers'])) {
                $headers['Access-Control-Expose-Headers'] = implode(', ', $corsConfig['exposed_headers']);
            }
        }
        
        self::setHeaders(204, $headers);
        exit;
    }
    
    /**
     * Set response headers
     */
    private static function setHeaders(int $statusCode, array $customHeaders = []): void
    {
        if (self::$headersSent || headers_sent()) {
            return;
        }
        
        // Set status code
        http_response_code($statusCode);
        
        // Set content type
        header('Content-Type: application/json; charset=utf-8');
        
        // Set CORS headers
        self::setCorsHeaders();
        
        // Set security headers
        self::setSecurityHeaders();
        
        // Set custom headers
        foreach ($customHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
        
        self::$headersSent = true;
    }
    
    /**
     * Set CORS headers
     */
    private static function setCorsHeaders(): void
    {
        $corsConfig = AppConfig::getCorsConfig();
        
        if (!$corsConfig['enabled']) {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (in_array('*', $corsConfig['allowed_origins'])) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $corsConfig['allowed_origins'])) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Vary: Origin');
        }
        
        // Set credentials
        if ($corsConfig['credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }
        
        // Set exposed headers
        if (!empty($corsConfig['exposed_headers'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $corsConfig['exposed_headers']));
        }
    }
    
    /**
     * Set security headers
     */
    private static function setSecurityHeaders(): void
    {
        $securityHeaders = AppConfig::getSecurityHeaders();
        
        foreach ($securityHeaders as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Additional security headers
        header('X-Powered-By: EC Site API');
        
        if (AppConfig::isProduction()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Set cache headers
     */
    public static function setCacheHeaders(int $maxAge = 3600, bool $public = true): void
    {
        if (self::$headersSent || headers_sent()) {
            return;
        }
        
        $cacheControl = $public ? 'public' : 'private';
        $cacheControl .= ", max-age={$maxAge}";
        
        header("Cache-Control: {$cacheControl}");
        header('ETag: ' . md5($_SERVER['REQUEST_URI']));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    }
    
    /**
     * Set no-cache headers
     */
    public static function setNoCacheHeaders(): void
    {
        if (self::$headersSent || headers_sent()) {
            return;
        }
        
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    
    /**
     * Redirect
     */
    public static function redirect(string $url, int $statusCode = 302): void
    {
        if (!self::$headersSent && !headers_sent()) {
            header("Location: {$url}", true, $statusCode);
        }
        exit;
    }
    
    /**
     * Download file
     */
    public static function download(string $filePath, string $filename = null, string $contentType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
            return;
        }
        
        $filename = $filename ?: basename($filePath);
        
        if (!self::$headersSent && !headers_sent()) {
            header("Content-Type: {$contentType}");
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Content-Length: ' . filesize($filePath));
        }
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Stream large file
     */
    public static function streamFile(string $filePath, string $contentType = 'application/octet-stream'): void
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
            return;
        }
        
        $fileSize = filesize($filePath);
        $start = 0;
        $end = $fileSize - 1;
        
        // Handle range requests
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($start, $end) = self::parseRangeHeader($_SERVER['HTTP_RANGE'], $fileSize);
        }
        
        if (!self::$headersSent && !headers_sent()) {
            header("Content-Type: {$contentType}");
            header('Accept-Ranges: bytes');
            header("Content-Length: " . ($end - $start + 1));
            
            if (isset($_SERVER['HTTP_RANGE'])) {
                http_response_code(206);
                header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
            }
        }
        
        $file = fopen($filePath, 'rb');
        fseek($file, $start);
        
        $chunkSize = 8192;
        $pos = $start;
        
        while ($pos <= $end && !feof($file)) {
            $readSize = min($chunkSize, $end - $pos + 1);
            echo fread($file, $readSize);
            flush();
            $pos += $readSize;
        }
        
        fclose($file);
        exit;
    }
    
    /**
     * Parse HTTP Range header
     */
    private static function parseRangeHeader(string $range, int $fileSize): array
    {
        $range = str_replace('bytes=', '', $range);
        $ranges = explode(',', $range);
        $range = trim($ranges[0]);
        
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range);
            $start = $start === '' ? 0 : intval($start);
            $end = $end === '' ? $fileSize - 1 : intval($end);
            
            if ($start > $end || $start < 0 || $end >= $fileSize) {
                $start = 0;
                $end = $fileSize - 1;
            }
        } else {
            $start = 0;
            $end = $fileSize - 1;
        }
        
        return [$start, $end];
    }
}