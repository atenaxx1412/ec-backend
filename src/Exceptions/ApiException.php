<?php

namespace ECBackend\Exceptions;

use Exception;

/**
 * API Exception
 * Custom exception for API-related errors
 */
class ApiException extends Exception
{
    protected array $errors = [];
    protected ?string $errorCode = null;
    
    public function __construct(string $message = '', int $code = 400, ?Exception $previous = null, array $errors = [], ?string $errorCode = null)
    {
        parent::__construct($message, $code, $previous);
        
        $this->errors = $errors;
        $this->errorCode = $errorCode;
    }
    
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
    
    public function toArray(): array
    {
        return [
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->getErrorCode(),
            'errors' => $this->getErrors(),
            'status_code' => $this->getCode()
        ];
    }
}