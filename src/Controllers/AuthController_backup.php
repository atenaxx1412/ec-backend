<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\JWTHelper;
use ECBackend\Utils\Response;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;

/**
 * Authentication Controller
 * Handles user authentication and authorization
 */
class AuthController extends BaseController
{
    /**
     * User login
     * POST /api/auth/login
     */
    public function login(): array
    {
        try {
            // Validate required fields
            $this->validateRequired(['email', 'password']);
            
            $email = $this->getBody('email');
            $password = $this->getBody('password');
            $rememberMe = $this->getBody('remember_me', false);
            
            // Validate email format
            if (!SecurityHelper::validateEmail($email)) {
                throw new ApiException('Invalid email format', 400, null, [], 'INVALID_EMAIL');
            }
            
            // Rate limiting for login attempts
            $rateLimitKey = "login_attempt:" . $this->request['client_ip'];
            $rateLimit = SecurityHelper::checkRateLimit($rateLimitKey, 10, 900); // 10 attempts per 15 minutes
            
            if (!$rateLimit['allowed']) {
                SecurityHelper::logSecurityEvent('login_rate_limit_exceeded', [
                    'email' => $email,
                    'ip' => $this->request['client_ip']
                ]);
                
                throw new ApiException(
                    'Too many login attempts. Please try again later.',
                    429,
                    null,
                    ['retry_after' => $rateLimit['reset_time'] - time()],
                    'RATE_LIMIT_EXCEEDED'
                );
            }
            
            // Get user from database
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    id, name, email, password, role, status, 
                    failed_login_attempts, locked_until,
                    last_login_at, created_at
                FROM users 
                WHERE email = ?
            ");
            
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                SecurityHelper::logSecurityEvent('login_user_not_found', ['email' => $email]);
                throw new ApiException('Invalid credentials', 401, null, [], 'INVALID_CREDENTIALS');
            }
            
            // Check if account is locked
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                SecurityHelper::logSecurityEvent('login_account_locked', ['user_id' => $user['id']]);
                throw new ApiException('Account is temporarily locked', 423, null, [], 'ACCOUNT_LOCKED');
            }
            
            // Check if account is active
            if ($user['status'] !== 'active') {
                SecurityHelper::logSecurityEvent('login_inactive_account', ['user_id' => $user['id']]);
                throw new ApiException('Account is not active', 403, null, [], 'ACCOUNT_INACTIVE');
            }
            
            // Verify password
            if (!SecurityHelper::verifyPassword($password, $user['password'])) {
                $this->handleFailedLogin($user['id'], $email);
                throw new ApiException('Invalid credentials', 401, null, [], 'INVALID_CREDENTIALS');
            }
            
            // Reset failed login attempts on successful login
            $this->resetFailedLoginAttempts($user['id']);
            
            // Generate JWT tokens
            $userData = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            $tokens = JWTHelper::generateTokenPair($userData);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Prepare response
            $responseUserData = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'last_login_at' => $user['last_login_at'],
                'created_at' => $user['created_at']
            ];
            
            $this->logRequest('auth.login', ['user_id' => $user['id'], 'remember_me' => $rememberMe]);
            
            SecurityHelper::logSecurityEvent('login_success', ['user_id' => $user['id']]);
            
            return $this->success([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
                'user' => $responseUserData
            ], 'Login successful');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error during login',
                500,
                $e,
                $stmt->queryString ?? '',
                [$email]
            );
        }
    }
    
    /**
     * User registration
     * POST /api/auth/register
     */
    public function register(): array
    {
        try {
            // Validate required fields
            $this->validateRequired(['name', 'email', 'password']);
            
            $name = trim($this->getBody('name'));
            $email = trim(strtolower($this->getBody('email')));
            $password = $this->getBody('password');
            
            // Validate input
            $this->validateRegistrationInput($name, $email, $password);
            
            // Check if email already exists
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                throw new ApiException('Email already registered', 409, null, [], 'EMAIL_EXISTS');
            }
            
            // Hash password
            $hashedPassword = SecurityHelper::hashPassword($password);
            
            // Create user
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, 'customer', 'active', NOW(), NOW())
            ");
            
            $stmt->execute([$name, $email, $hashedPassword]);
            $userId = $db->lastInsertId();
            
            // Generate JWT tokens
            $userData = [
                'id' => (int) $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer'
            ];
            
            $tokens = JWTHelper::generateTokenPair($userData);
            
            // Prepare response
            $responseUserData = [
                'id' => (int) $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->logRequest('auth.register', ['user_id' => $userId]);
            
            SecurityHelper::logSecurityEvent('user_registered', ['user_id' => $userId]);
            
            return $this->success([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_type' => $tokens['token_type'],
                'expires_in' => $tokens['expires_in'],
                'user' => $responseUserData
            ], 'Registration successful', 201);
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error during registration',
                500,
                $e,
                $stmt->queryString ?? '',
                [$name, $email]
            );
        }
    }
    
    /**
     * Get user profile
     * GET /api/auth/profile
     */
    public function profile(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    id, name, email, role, status,
                    last_login_at, created_at, updated_at
                FROM users 
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new ApiException('User not found', 404, null, [], 'USER_NOT_FOUND');
            }
            
            $userData = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'],
                'last_login_at' => $user['last_login_at'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at']
            ];
            
            $this->logRequest('auth.profile', ['user_id' => $userId]);
            
            return $this->success($userData, 'Profile retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error retrieving profile',
                500,
                $e,
                $stmt->queryString ?? '',
                [$userId]
            );
        }
    }
    
    /**
     * Update user profile
     * PUT /api/auth/profile
     */
    public function updateProfile(): array
    {
        $this->requireAuth();
        
        try {
            $userId = $this->user['id'];
            $name = trim($this->getBody('name'));
            
            if (empty($name)) {
                throw new ApiException('Name is required', 400, null, [], 'NAME_REQUIRED');
            }
            
            if (strlen($name) < 2 || strlen($name) > 100) {
                throw new ApiException('Name must be between 2 and 100 characters', 400, null, [], 'INVALID_NAME_LENGTH');
            }
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE users 
                SET name = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new ApiException('Profile update failed', 500, null, [], 'UPDATE_FAILED');
            }
            
            // Get updated user data
            $stmt = $db->prepare("
                SELECT id, name, email, role, status, updated_at
                FROM users 
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $userData = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'],
                'updated_at' => $user['updated_at']
            ];
            
            $this->logRequest('auth.updateProfile', ['user_id' => $userId]);
            
            return $this->success($userData, 'Profile updated successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error updating profile',
                500,
                $e,
                $stmt->queryString ?? '',
                [$name, $userId]
            );
        }
    }
    
    /**
     * Change password
     * PUT /api/auth/password
     */
    public function changePassword(): array
    {
        $this->requireAuth();
        
        try {
            $this->validateRequired(['current_password', 'new_password']);
            
            $userId = $this->user['id'];
            $currentPassword = $this->getBody('current_password');
            $newPassword = $this->getBody('new_password');
            
            // Validate new password strength
            $passwordStrength = SecurityHelper::checkPasswordStrength($newPassword);
            if ($passwordStrength['score'] < 4) {
                throw new ApiException(
                    'Password is too weak',
                    400,
                    null,
                    ['suggestions' => $passwordStrength['suggestions']],
                    'WEAK_PASSWORD'
                );
            }
            
            // Get current password hash
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();
            
            if (!SecurityHelper::verifyPassword($currentPassword, $currentHash)) {
                SecurityHelper::logSecurityEvent('password_change_wrong_current', ['user_id' => $userId]);
                throw new ApiException('Current password is incorrect', 400, null, [], 'INVALID_CURRENT_PASSWORD');
            }
            
            // Hash new password
            $newHash = SecurityHelper::hashPassword($newPassword);
            
            // Update password
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$newHash, $userId]);
            
            SecurityHelper::logSecurityEvent('password_changed', ['user_id' => $userId]);
            
            $this->logRequest('auth.changePassword', ['user_id' => $userId]);
            
            return $this->success(null, 'Password changed successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error changing password',
                500,
                $e,
                $stmt->queryString ?? '',
                [$userId]
            );
        }
    }
    
    
    /**
     * Validate registration input
     */
    private function validateRegistrationInput(string $name, string $email, string $password): void
    {
        // Validate name
        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new ApiException('Name must be between 2 and 100 characters', 400, null, [], 'INVALID_NAME');
        }
        
        // Validate email
        if (!SecurityHelper::validateEmail($email)) {
            throw new ApiException('Invalid email format', 400, null, [], 'INVALID_EMAIL');
        }
        
        // Validate password strength
        $passwordStrength = SecurityHelper::checkPasswordStrength($password);
        if ($passwordStrength['score'] < 3) {
            throw new ApiException(
                'Password is too weak',
                400,
                null,
                ['suggestions' => $passwordStrength['suggestions']],
                'WEAK_PASSWORD'
            );
        }
    }
    
    /**
     * Handle failed login attempt
     */
    private function handleFailedLogin(int $userId, string $email): void
    {
        try {
            $db = Database::getConnection();
            
            // Increment failed login attempts
            $stmt = $db->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                        ELSE locked_until
                    END
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);
            
            SecurityHelper::logSecurityEvent('login_failed', [
                'user_id' => $userId,
                'email' => $email
            ]);
            
        } catch (\PDOException $e) {
            error_log("Failed to update login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Reset failed login attempts
     */
    private function resetFailedLoginAttempts(int $userId): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE users 
                SET failed_login_attempts = 0, locked_until = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            error_log("Failed to reset login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Update last login timestamp
     */
    private function updateLastLogin(int $userId): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }
    
    /**
     * Refresh access token
     * POST /api/auth/refresh
     */
    public function refresh(): array
    {
        try {
            $this->validateRequired(['refresh_token']);
            
            $refreshToken = $this->getBody('refresh_token');
            
            // Validate refresh token
            $decoded = JWTHelper::validateToken($refreshToken);
            
            if (!isset($decoded['type']) || $decoded['type'] !== 'refresh_token') {
                throw new ApiException('Invalid refresh token', 401, null, [], 'INVALID_REFRESH_TOKEN');
            }
            
            if (JWTHelper::isTokenBlacklisted($refreshToken)) {
                throw new ApiException('Refresh token has been revoked', 401, null, [], 'TOKEN_REVOKED');
            }
            
            $userId = $decoded['sub'];
            
            // Get fresh user data
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, name, email, role, status 
                FROM users 
                WHERE id = ? AND status = 'active'
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new ApiException('User not found or inactive', 404, null, [], 'USER_NOT_FOUND');
            }
            
            $userData = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            // Generate new token pair
            $tokens = JWTHelper::refreshAccessToken($refreshToken, $userData);
            
            // Blacklist old refresh token
            JWTHelper::blacklistToken($refreshToken);
            
            $this->logRequest('auth.refresh', ['user_id' => $userId]);
            
            return $this->success($tokens, 'Token refreshed successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error during token refresh',
                500,
                $e,
                $stmt->queryString ?? '',
                [$userId ?? null]
            );
        }
    }
    
    /**
     * Logout user
     * POST /api/auth/logout
     */
    public function logout(): array
    {
        try {
            $token = JWTHelper::extractTokenFromHeader();
            
            if (!$token) {
                throw new ApiException('No token provided', 401, null, [], 'NO_TOKEN');
            }
            
            // Validate and get user info
            $userData = JWTHelper::getUserFromToken($token);
            
            // Blacklist the token
            $blacklisted = JWTHelper::blacklistToken($token);
            
            if (!$blacklisted) {
                error_log("Failed to blacklist token during logout for user: " . $userData['id']);
            }
            
            // Also blacklist refresh token if provided
            $refreshToken = $this->getBody('refresh_token');
            if ($refreshToken) {
                JWTHelper::blacklistToken($refreshToken);
            }
            
            $this->logRequest('auth.logout', ['user_id' => $userData['id']]);
            
            SecurityHelper::logSecurityEvent('logout_success', ['user_id' => $userData['id']]);
            
            return $this->success(null, 'Logout successful');
            
        } catch (\Exception $e) {
            // Log error but don't expose details
            error_log("Logout error: " . $e->getMessage());
            return $this->success(null, 'Logout completed');
        }
    }
    
    /**
     * Get user profile
     * GET /api/auth/profile
     */
    public function profile(): array
    {
        try {
            $token = JWTHelper::extractTokenFromHeader();
            
            if (!$token) {
                throw new ApiException('No token provided', 401, null, [], 'NO_TOKEN');
            }
            
            $userData = JWTHelper::getUserFromToken($token);
            $userId = $userData['id'];
            
            // Get fresh user data from database
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    id, name, email, role, status,
                    last_login_at, created_at, updated_at
                FROM users 
                WHERE id = ?
            ");
            
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new ApiException('User not found', 404, null, [], 'USER_NOT_FOUND');
            }
            
            $profileData = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status'],
                'last_login_at' => $user['last_login_at'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at']
            ];
            
            $this->logRequest('auth.profile', ['user_id' => $userId]);
            
            return $this->success($profileData, 'Profile retrieved successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error retrieving profile',
                500,
                $e,
                $stmt->queryString ?? '',
                [$userId ?? null]
            );
        }
    }
}