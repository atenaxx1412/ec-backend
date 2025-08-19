<?php

namespace ECBackend\Utils;

/**
 * Error Handler Class
 * Centralized error handling for the EC Site API
 */
class ErrorHandler
{
    private static $logFile = '/var/log/apache2/application_errors.log';

    /**
     * Handle exceptions and convert to API response
     */
    public static function handleException(\Throwable $exception): array
    {
        $isDevelopment = getenv('APP_DEBUG') === 'true' || getenv('NODE_ENV') === 'development';
        
        // Log the error
        self::logError($exception);
        
        // Determine error type and appropriate response
        $errorData = self::analyzeException($exception);
        
        return [
            'success' => false,
            'data' => null,
            'message' => $errorData['message'],
            'errors' => $isDevelopment ? $errorData['details'] : [$errorData['public_message']],
            'pagination' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'error_id' => $errorData['error_id']
        ];
    }

    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $errorData = [
            'type' => 'PHP Error',
            'severity' => self::getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ];

        self::writeLog($errorData);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle fatal errors
     */
    public static function handleFatalError(): void
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorData = [
                'type' => 'Fatal Error',
                'severity' => self::getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI'
            ];

            self::writeLog($errorData);

            // Send error response if this is a web request
            if (isset($_SERVER['REQUEST_URI'])) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'data' => null,
                    'message' => 'Internal Server Error',
                    'errors' => ['A fatal error occurred. Please try again later.'],
                    'pagination' => null,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    /**
     * Analyze exception and prepare error data
     */
    private static function analyzeException(\Throwable $exception): array
    {
        $errorId = uniqid('err_');
        
        // Determine error type and appropriate messages
        switch (true) {
            case $exception instanceof \PDOException:
                return [
                    'error_id' => $errorId,
                    'message' => 'Database Error',
                    'public_message' => 'A database error occurred. Please try again later.',
                    'details' => [
                        'type' => 'PDOException',
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine()
                    ]
                ];
                
            case $exception instanceof \InvalidArgumentException:
                return [
                    'error_id' => $errorId,
                    'message' => 'Invalid Request',
                    'public_message' => 'Invalid request parameters provided.',
                    'details' => [
                        'type' => 'InvalidArgumentException',
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine()
                    ]
                ];
                
            case $exception instanceof \RuntimeException:
                return [
                    'error_id' => $errorId,
                    'message' => 'Runtime Error',
                    'public_message' => 'A runtime error occurred. Please try again later.',
                    'details' => [
                        'type' => 'RuntimeException',
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine()
                    ]
                ];
                
            default:
                return [
                    'error_id' => $errorId,
                    'message' => 'Internal Server Error',
                    'public_message' => 'An unexpected error occurred. Please try again later.',
                    'details' => [
                        'type' => get_class($exception),
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTraceAsString()
                    ]
                ];
        }
    }

    /**
     * Log exception details
     */
    private static function logError(\Throwable $exception): void
    {
        $errorData = [
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
        ];

        self::writeLog($errorData);
    }

    /**
     * Write error data to log file
     */
    private static function writeLog(array $errorData): void
    {
        $logEntry = [
            'datetime' => $errorData['timestamp'],
            'level' => 'ERROR',
            'message' => $errorData['message'] ?? $errorData['class'] ?? 'Unknown Error',
            'context' => $errorData
        ];

        $logLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n";
        
        // Write to application log file
        error_log($logLine, 3, self::$logFile);
        
        // Also write to PHP error log
        error_log("EC_API_ERROR: " . ($errorData['message'] ?? 'Unknown error'));
    }

    /**
     * Get human-readable severity name
     */
    private static function getSeverityName(int $severity): string
    {
        $severityNames = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];

        return $severityNames[$severity] ?? 'Unknown Error';
    }

    /**
     * Send JSON error response
     */
    public static function sendErrorResponse(int $statusCode, string $message, array $errors = [], array $data = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        echo json_encode([
            'success' => false,
            'data' => $data,
            'message' => $message,
            'errors' => $errors,
            'pagination' => null,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        exit();
    }

    /**
     * Initialize error handlers
     */
    public static function initialize(): void
    {
        // Set custom error handler
        set_error_handler([self::class, 'handleError']);
        
        // Set custom exception handler
        set_exception_handler([self::class, 'handleException']);
        
        // Set fatal error handler
        register_shutdown_function([self::class, 'handleFatalError']);
        
        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Validate API request structure
     */
    public static function validateRequest(array $required_fields, array $data): array
    {
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }
        
        return $errors;
    }

    /**
     * Log API request for debugging
     */
    public static function logRequest(string $route, array $data = []): void
    {
        if (getenv('APP_DEBUG') === 'true') {
            $requestData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'route' => $route,
                'data' => $data,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];
            
            error_log('API_REQUEST: ' . json_encode($requestData));
        }
    }
}