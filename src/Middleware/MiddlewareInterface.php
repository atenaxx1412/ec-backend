<?php

namespace ECBackend\Middleware;

/**
 * Middleware Interface
 * Defines the contract for all middleware classes
 */
interface MiddlewareInterface
{
    /**
     * Handle the request and return response or continue to next middleware
     * 
     * @param array $request Request data
     * @param callable $next Next middleware in the chain
     * @return mixed Response or result of next middleware
     */
    public function handle(array $request, callable $next);
}