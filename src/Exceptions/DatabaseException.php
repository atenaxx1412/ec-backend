<?php

namespace ECBackend\Exceptions;

use Exception;

/**
 * Database Exception
 * Custom exception for database-related errors
 */
class DatabaseException extends Exception
{
    protected string $query = '';
    protected array $params = [];
    
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, string $query = '', array $params = [])
    {
        parent::__construct($message, $code, $previous);
        
        $this->query = $query;
        $this->params = $params;
    }
    
    public function getQuery(): string
    {
        return $this->query;
    }
    
    public function getParams(): array
    {
        return $this->params;
    }
}