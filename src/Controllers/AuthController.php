<?php

namespace ECBackend\Controllers;

use ECBackend\Config\Database;
use ECBackend\Utils\Response;
use ECBackend\Utils\SecurityHelper;
use ECBackend\Utils\JWTHelper;
use ECBackend\Exceptions\ApiException;
use ECBackend\Exceptions\DatabaseException;

/**
 * Authentication Controller
 * Handles user authentication and JWT token management
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
            $this->validateRequired(['email', 'password']);
            
            $email = trim($this->getBody('email'));
            $password = $this->getBody('password');
            
            if (empty($email) || empty($password)) {
                throw new ApiException('Email and password are required', 400, null, [], 'MISSING_CREDENTIALS');
            }
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT id, first_name, email, password_hash, is_active, 
                       created_at, updated_at
                FROM users 
                WHERE email = ?
            ");
            
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                // Prevent user enumeration - same response for invalid email/password
                throw new ApiException('Invalid credentials', 401, null, [], 'INVALID_CREDENTIALS');
            }
            
            // Check if account is active
            if (!$user['is_active']) {
                throw new ApiException('Account is not active', 403, null, [], 'ACCOUNT_INACTIVE');
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                throw new ApiException('Invalid credentials', 401, null, [], 'INVALID_CREDENTIALS');
            }
            
            // Generate JWT tokens
            $userData = [
                'id' => (int) $user['id'],
                'name' => $user['first_name'],
                'email' => $user['email'],
                'role' => 'customer'  // Default role since table doesn't have role column
            ];
            
            $tokens = JWTHelper::generateTokenPair($userData);
            
            $this->logRequest('auth.login', ['user_id' => $user['id']]);
            
            return $this->success($tokens, 'Login successful');
            
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
            $this->validateRequired(['name', 'email', 'password']);
            
            $name = trim($this->getBody('name'));
            $email = trim(strtolower($this->getBody('email')));
            $password = $this->getBody('password');
            
            $this->validateRegistrationInput($name, $email, $password);
            
            $db = Database::getConnection();
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                throw new ApiException('Email already registered', 409, null, [], 'EMAIL_EXISTS');
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $db->prepare("
                INSERT INTO users (first_name, email, password_hash, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            
            $stmt->execute([$name, $email, $hashedPassword]);
            $userId = $db->lastInsertId();
            
            // Generate JWT tokens for immediate login
            $userData = [
                'id' => (int) $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer'
            ];
            
            $tokens = JWTHelper::generateTokenPair($userData);
            
            $this->logRequest('auth.register', ['user_id' => $userId]);
            
            SecurityHelper::logSecurityEvent('user_registered', ['user_id' => $userId]);
            
            return $this->success($tokens, 'Registration successful');
            
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
     * Refresh access token
     * POST /api/auth/refresh
     */
    public function refresh(): array
    {
        try {
            $this->validateRequired(['refresh_token']);
            
            $refreshToken = $this->getBody('refresh_token');
            
            if (empty($refreshToken)) {
                throw new ApiException('Refresh token is required', 400, null, [], 'REFRESH_TOKEN_REQUIRED');
            }
            
            // Validate refresh token and get new access token
            $newTokens = JWTHelper::refreshAccessToken($refreshToken);
            
            $this->logRequest('auth.refresh', ['token_refreshed' => true]);
            
            return $this->success($newTokens, 'Token refreshed successfully');
            
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Token refresh failed', 401, $e, [], 'REFRESH_FAILED');
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
            
            SecurityHelper::logSecurityEvent('user_logout', ['user_id' => $userData['id']]);
            
            return $this->success(null, 'Logged out successfully');
            
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ApiException('Logout failed', 500, $e, [], 'LOGOUT_FAILED');
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
                    id, first_name, email, is_active,
                    created_at, updated_at
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
                'name' => $user['first_name'],
                'email' => $user['email'],
                'role' => 'customer',  // Default role
                'status' => $user['is_active'] ? 'active' : 'inactive',
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
    
    /**
     * Update user profile
     * PUT /api/auth/profile
     */
    public function updateProfile(): array
    {
        try {
            $token = JWTHelper::extractTokenFromHeader();
            
            if (!$token) {
                throw new ApiException('No token provided', 401, null, [], 'NO_TOKEN');
            }
            
            $userData = JWTHelper::getUserFromToken($token);
            $userId = $userData['id'];
            
            $this->validateRequired(['name']);
            $name = trim($this->getBody('name'));
            
            if (strlen($name) < 2 || strlen($name) > 100) {
                throw new ApiException('Name must be between 2 and 100 characters', 400, null, [], 'INVALID_NAME_LENGTH');
            }
            
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE users 
                SET first_name = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new ApiException('User not found or no changes made', 404, null, [], 'USER_NOT_FOUND');
            }
            
            $this->logRequest('auth.update_profile', ['user_id' => $userId]);
            
            return $this->success(null, 'Profile updated successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error updating profile',
                500,
                $e,
                $stmt->queryString ?? '',
                [$name ?? '', $userId ?? null]
            );
        }
    }
    
    /**
     * Change password
     * PUT /api/auth/password
     */
    public function changePassword(): array
    {
        try {
            $token = JWTHelper::extractTokenFromHeader();
            
            if (!$token) {
                throw new ApiException('No token provided', 401, null, [], 'NO_TOKEN');
            }
            
            $userData = JWTHelper::getUserFromToken($token);
            $userId = $userData['id'];
            
            $this->validateRequired(['current_password', 'new_password']);
            $currentPassword = $this->getBody('current_password');
            $newPassword = $this->getBody('new_password');
            
            // Get current password hash
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                throw new ApiException('Current password is incorrect', 400, null, [], 'INVALID_CURRENT_PASSWORD');
            }
            
            // Validate new password
            $this->validatePassword($newPassword);
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$hashedPassword, $userId]);
            
            $this->logRequest('auth.change_password', ['user_id' => $userId]);
            
            SecurityHelper::logSecurityEvent('password_changed', ['user_id' => $userId]);
            
            return $this->success(null, 'Password changed successfully');
            
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Database error changing password',
                500,
                $e,
                $stmt->queryString ?? '',
                [$userId ?? null]
            );
        }
    }
    
    /**
     * Validate registration input
     */
    private function validateRegistrationInput(string $name, string $email, string $password): void
    {
        if (empty($name) || empty($email) || empty($password)) {
            throw new ApiException('All fields are required', 400, null, [], 'MISSING_FIELDS');
        }
        
        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new ApiException('Name must be between 2 and 100 characters', 400, null, [], 'INVALID_NAME');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Invalid email format', 400, null, [], 'INVALID_EMAIL');
        }
        
        $this->validatePassword($password);
    }
    
    /**
     * Validate password strength
     */
    private function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new ApiException('Password must be at least 8 characters long', 400, null, [], 'PASSWORD_TOO_SHORT');
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            throw new ApiException('Password must contain at least one uppercase letter', 400, null, [], 'PASSWORD_NO_UPPERCASE');
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            throw new ApiException('Password must contain at least one lowercase letter', 400, null, [], 'PASSWORD_NO_LOWERCASE');
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            throw new ApiException('Password must contain at least one number', 400, null, [], 'PASSWORD_NO_NUMBER');
        }
    }
    
    /**
     * Increment failed login attempts
     */
    private function incrementFailedLoginAttempts(int $userId): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    locked_until = CASE 
                        WHEN failed_login_attempts + 1 >= 5 
                        THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                        ELSE locked_until 
                    END
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            error_log("Failed to increment login attempts: " . $e->getMessage());
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
     * Update user's last login timestamp
     * Note: Disabled because last_login_at column doesn't exist in current table
     */
    private function updateLastLogin(int $userId): void
    {
        // Disabled - last_login_at column doesn't exist in current table structure
        // try {
        //     $db = Database::getConnection();
        //     $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        //     $stmt->execute([$userId]);
        // } catch (\PDOException $e) {
        //     error_log("Failed to update last login: " . $e->getMessage());
        // }
    }
}