<?php

namespace ECBackend\Middleware;

use ECBackend\Utils\SecurityHelper;
use ECBackend\Exceptions\ApiException;

/**
 * Validation Middleware
 * Validates request data against defined rules
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private array $rules;
    private array $options;
    
    public function __construct(array $rules = [], array $options = [])
    {
        $this->rules = $rules;
        $this->options = array_merge([
            'sanitize' => true,
            'stop_on_first_error' => false,
            'validate_method' => null, // GET, POST, PUT, DELETE, etc.
        ], $options);
    }
    
    public function handle(array $request, callable $next)
    {
        // Check HTTP method if specified
        if ($this->options['validate_method']) {
            $currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if ($currentMethod !== $this->options['validate_method']) {
                return $next($request);
            }
        }
        
        // Get request data
        $data = $this->getRequestData();
        
        // Sanitize input if enabled
        if ($this->options['sanitize']) {
            $data = SecurityHelper::sanitizeInput($data);
        }
        
        // Validate data
        $errors = $this->validateData($data);
        
        if (!empty($errors)) {
            throw new ApiException(
                'Validation failed',
                422,
                null,
                $errors,
                'VALIDATION_ERROR'
            );
        }
        
        // Add validated data to request
        $request['validated'] = $data;
        
        return $next($request);
    }
    
    /**
     * Get request data based on method
     */
    private function getRequestData(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        switch ($method) {
            case 'GET':
                return $_GET;
                
            case 'POST':
            case 'PUT':
            case 'PATCH':
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                
                if (strpos($contentType, 'application/json') !== false) {
                    $body = file_get_contents('php://input');
                    $decoded = json_decode($body, true);
                    return $decoded ?: [];
                } else {
                    return $_POST;
                }
                
            case 'DELETE':
                parse_str(file_get_contents('php://input'), $data);
                return $data;
                
            default:
                return [];
        }
    }
    
    /**
     * Validate data against rules
     */
    private function validateData(array $data): array
    {
        $errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $fieldErrors = $this->validateField($field, $data[$field] ?? null, $rules);
            
            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
                
                if ($this->options['stop_on_first_error']) {
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate single field
     */
    private function validateField(string $field, $value, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $rule) {
            $error = $this->applyRule($field, $value, $rule);
            if ($error) {
                $errors[] = $error;
            }
        }
        
        return $errors;
    }
    
    /**
     * Apply validation rule
     */
    private function applyRule(string $field, $value, string $rule): ?string
    {
        // Parse rule and parameters
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];
        
        switch ($ruleName) {
            case 'required':
                return $this->validateRequired($field, $value);
                
            case 'email':
                return $this->validateEmail($field, $value);
                
            case 'min':
                return $this->validateMin($field, $value, $parameters[0] ?? 0);
                
            case 'max':
                return $this->validateMax($field, $value, $parameters[0] ?? 100);
                
            case 'string':
                return $this->validateString($field, $value);
                
            case 'integer':
                return $this->validateInteger($field, $value);
                
            case 'numeric':
                return $this->validateNumeric($field, $value);
                
            case 'url':
                return $this->validateUrl($field, $value);
                
            case 'in':
                return $this->validateIn($field, $value, $parameters);
                
            case 'regex':
                return $this->validateRegex($field, $value, $parameters[0] ?? '');
                
            case 'unique':
                return $this->validateUnique($field, $value, $parameters[0] ?? '', $parameters[1] ?? 'id');
                
            case 'exists':
                return $this->validateExists($field, $value, $parameters[0] ?? '', $parameters[1] ?? 'id');
                
            default:
                return "Unknown validation rule: {$ruleName}";
        }
    }
    
    private function validateRequired(string $field, $value): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return "The {$field} field is required";
        }
        return null;
    }
    
    private function validateEmail(string $field, $value): ?string
    {
        if ($value !== null && !SecurityHelper::validateEmail($value)) {
            return "The {$field} must be a valid email address";
        }
        return null;
    }
    
    private function validateMin(string $field, $value, $min): ?string
    {
        if ($value === null) return null;
        
        if (is_string($value) && strlen($value) < $min) {
            return "The {$field} must be at least {$min} characters";
        }
        
        if (is_numeric($value) && $value < $min) {
            return "The {$field} must be at least {$min}";
        }
        
        return null;
    }
    
    private function validateMax(string $field, $value, $max): ?string
    {
        if ($value === null) return null;
        
        if (is_string($value) && strlen($value) > $max) {
            return "The {$field} may not be greater than {$max} characters";
        }
        
        if (is_numeric($value) && $value > $max) {
            return "The {$field} may not be greater than {$max}";
        }
        
        return null;
    }
    
    private function validateString(string $field, $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            return "The {$field} must be a string";
        }
        return null;
    }
    
    private function validateInteger(string $field, $value): ?string
    {
        if ($value !== null && !is_int($value) && !ctype_digit($value)) {
            return "The {$field} must be an integer";
        }
        return null;
    }
    
    private function validateNumeric(string $field, $value): ?string
    {
        if ($value !== null && !is_numeric($value)) {
            return "The {$field} must be numeric";
        }
        return null;
    }
    
    private function validateUrl(string $field, $value): ?string
    {
        if ($value !== null && !SecurityHelper::validateUrl($value)) {
            return "The {$field} must be a valid URL";
        }
        return null;
    }
    
    private function validateIn(string $field, $value, array $allowed): ?string
    {
        if ($value !== null && !in_array($value, $allowed)) {
            $allowedStr = implode(', ', $allowed);
            return "The {$field} must be one of: {$allowedStr}";
        }
        return null;
    }
    
    private function validateRegex(string $field, $value, string $pattern): ?string
    {
        if ($value !== null && !preg_match($pattern, $value)) {
            return "The {$field} format is invalid";
        }
        return null;
    }
    
    private function validateUnique(string $field, $value, string $table, string $column = 'id'): ?string
    {
        if ($value === null) return null;
        
        try {
            $db = \ECBackend\Config\Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$field} = ?");
            $stmt->execute([$value]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                return "The {$field} has already been taken";
            }
        } catch (\PDOException $e) {
            return "Database error during validation";
        }
        
        return null;
    }
    
    private function validateExists(string $field, $value, string $table, string $column = 'id'): ?string
    {
        if ($value === null) return null;
        
        try {
            $db = \ECBackend\Config\Database::getConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);
            $count = $stmt->fetchColumn();
            
            if ($count === 0) {
                return "The selected {$field} is invalid";
            }
        } catch (\PDOException $e) {
            return "Database error during validation";
        }
        
        return null;
    }
    
    /**
     * Static factory methods for common validation scenarios
     */
    public static function loginValidation(): self
    {
        return new self([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6']
        ]);
    }
    
    public static function registerValidation(): self
    {
        return new self([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8']
        ]);
    }
    
    public static function productValidation(): self
    {
        return new self([
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['string', 'max:1000']
        ]);
    }
}