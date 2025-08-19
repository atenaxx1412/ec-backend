<?php

namespace ECBackend\Middleware;

use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\Response;
use ECBackend\Exceptions\ApiException;

/**
 * Rate Limit Middleware
 * Implements rate limiting to prevent abuse and ensure fair usage
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $keyStrategy;
    
    public function __construct(int $maxRequests = 60, int $windowSeconds = 60, string $keyStrategy = 'ip')
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->keyStrategy = $keyStrategy;
    }
    
    public function handle(array $request, callable $next)
    {
        $key = $this->generateKey($request);
        $result = SecurityHelper::checkRateLimit($key, $this->maxRequests, $this->windowSeconds);
        
        // Add rate limit headers
        $this->setRateLimitHeaders($result);
        
        if (!$result['allowed']) {
            throw new ApiException(
                'Rate limit exceeded',
                429,
                null,
                [
                    'limit' => $this->maxRequests,
                    'window' => $this->windowSeconds,
                    'reset_time' => $result['reset_time']
                ],
                'RATE_LIMIT_EXCEEDED'
            );
        }
        
        return $next($request);
    }
    
    /**
     * Generate rate limit key based on strategy
     */
    private function generateKey(array $request): string
    {
        switch ($this->keyStrategy) {
            case 'user':
                $userId = $request['auth']['user_id'] ?? null;
                if ($userId) {
                    return "user:{$userId}";
                }
                // Fallback to IP if no user
                return "ip:" . $this->getClientIp();
                
            case 'user_ip':
                $userId = $request['auth']['user_id'] ?? null;
                $ip = $this->getClientIp();
                return $userId ? "user_ip:{$userId}:{$ip}" : "ip:{$ip}";
                
            case 'endpoint':
                $endpoint = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
                $ip = $this->getClientIp();
                return "endpoint:{$endpoint}:{$ip}";
                
            case 'ip':
            default:
                return "ip:" . $this->getClientIp();
        }
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
     * Set rate limit headers
     */
    private function setRateLimitHeaders(array $result): void
    {
        if (headers_sent()) {
            return;
        }
        
        header("X-RateLimit-Limit: {$this->maxRequests}");
        header("X-RateLimit-Remaining: {$result['remaining']}");
        header("X-RateLimit-Reset: {$result['reset_time']}");
        header("X-RateLimit-Window: {$this->windowSeconds}");
    }
    
    /**
     * Create rate limit middleware for different scenarios
     */
    public static function perMinute(int $requests = 60): self
    {
        return new self($requests, 60, 'ip');
    }
    
    public static function perHour(int $requests = 1000): self
    {
        return new self($requests, 3600, 'ip');
    }
    
    public static function perUser(int $requests = 100, int $windowSeconds = 60): self
    {
        return new self($requests, $windowSeconds, 'user');
    }
    
    public static function perEndpoint(int $requests = 30, int $windowSeconds = 60): self
    {
        return new self($requests, $windowSeconds, 'endpoint');
    }
    
    public static function strict(int $requests = 10, int $windowSeconds = 60): self
    {
        return new self($requests, $windowSeconds, 'user_ip');
    }
}