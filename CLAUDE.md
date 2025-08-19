# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Project**: ECサイト再設計 (E-commerce Site Redesign)  
**Architecture**: Apache + PHP 8.2 + MySQL 8.0  
**Environment**: Docker-based development with production compatibility for ロリポップ ハイスピードプラン  
**Status**: Planning phase - implementation pending

## Development Environment Commands

### Docker Environment Setup
```bash
# Initial setup
docker-compose up -d                    # Start all services
docker-compose exec api composer install # Install PHP dependencies
docker-compose exec api php tools/db-setup.php # Initialize database

# Daily development
docker-compose up -d                    # Start development environment
docker-compose logs -f api             # View API logs
docker-compose exec api bash           # Access API container
docker-compose down                     # Stop environment

# Database management
docker-compose exec mysql mysql -u ec_dev_user -p  # Access MySQL directly
# phpMyAdmin available at http://localhost:8081

# Testing
docker-compose exec api ./vendor/bin/phpunit        # Run all tests
docker-compose exec api ./vendor/bin/phpunit tests/Unit/ProductTest.php  # Run specific test
```

### Development Tools Access
- **API**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **Mailpit (dev email)**: http://localhost:8025
- **Frontend**: http://localhost:3000 (when implemented)

## Project Architecture

### Technology Stack
- **Backend**: Apache 2.4 + PHP 8.2 with extensions (pdo_mysql, redis, xdebug)
- **Database**: MySQL 8.0 with utf8mb4 character set
- **Cache**: Redis 7
- **Frontend**: Next.js 15 + TypeScript (to be connected)
- **Development**: Docker Compose with hot reload

### Project Structure (Planned)
```
backend/
├── public/              # Apache document root
│   ├── index.php       # Application entry point
│   └── .htaccess       # Apache rewrite rules
├── src/
│   ├── Config/         # Database and environment configuration
│   ├── Controllers/    # API controllers
│   ├── Models/         # Data models
│   ├── Middleware/     # Request middleware
│   ├── Utils/          # Utility functions
│   └── Routes/         # Route definitions
└── tests/              # PHPUnit tests
    ├── Unit/           # Unit tests
    ├── Integration/    # Integration tests
    └── Api/            # API endpoint tests
```

## API Design Specifications

### Response Format Standard
All API responses must follow this format:
```json
{
  "success": boolean,
  "data": object | array | null,
  "message": string,
  "errors": array,
  "pagination": object | null
}
```

### Main API Endpoints
```
# Product Management
GET    /api/products              # Product list with pagination
GET    /api/products/{id}         # Product details
GET    /api/categories            # Category list

# Authentication
POST   /api/auth/login            # User login
POST   /api/auth/register         # User registration
GET    /api/auth/profile          # User profile

# Shopping Cart
POST   /api/cart/add              # Add item to cart
GET    /api/cart                  # Get cart contents
PUT    /api/cart/{id}             # Update cart item quantity
DELETE /api/cart/{id}             # Remove cart item

# Admin API
POST   /api/admin/login           # Admin login
GET    /api/admin/products        # Admin product management
POST   /api/admin/products        # Create product
PUT    /api/admin/products/{id}   # Update product
DELETE /api/admin/products/{id}   # Delete product
```

### Database Schema Details

#### **CRITICAL**: Always verify table structure before coding
**Common Error**: Assuming column names without checking actual schema leads to registration failures.

##### Primary Tables (ecommerce_dev_db)

**🔑 users table** (Authentication core)
```sql
+-------------------+--------------+------+-----+-------------------+-------+
| Field             | Type         | Null | Key | Default           | Extra |
+-------------------+--------------+------+-----+-------------------+-------+
| id                | int          | NO   | PRI | NULL              | auto_increment |
| email             | varchar(255) | NO   | UNI | NULL              |       |
| password_hash     | varchar(255) | NO   |     | NULL              | ← NOT 'password' |
| first_name        | varchar(100) | YES  |     | NULL              | ← NOT 'name' |
| last_name         | varchar(100) | YES  |     | NULL              |       |
| phone             | varchar(20)  | YES  |     | NULL              |       |
| is_active         | tinyint(1)   | YES  | MUL | 1                 | ← NOT 'status' |
| email_verified_at | timestamp    | YES  |     | NULL              |       |
| created_at        | timestamp    | YES  | MUL | CURRENT_TIMESTAMP |       |
| updated_at        | timestamp    | YES  |     | CURRENT_TIMESTAMP |       |
+-------------------+--------------+------+-----+-------------------+-------+
```

**❌ Common Mistakes**:
- Using `password` instead of `password_hash`
- Using `name` instead of `first_name`
- Using `status = 'active'` instead of `is_active = 1`
- Trying to access non-existent `role` column

**✅ Correct AuthController Code**:
```php
// Registration - CORRECT
$stmt = $db->prepare("INSERT INTO users (first_name, email, password_hash, is_active) VALUES (?, ?, ?, 1)");

// Login verification - CORRECT  
$stmt = $db->prepare("SELECT id, first_name, email, password_hash, is_active FROM users WHERE email = ?");
if (!password_verify($password, $user['password_hash'])) // NOT $user['password']

// User status check - CORRECT
WHERE is_active = 1  // NOT WHERE status = 'active'
```

**🛍️ products table**
```sql
+-------------------+---------------+------+-----+-------------------+-------+
| Field             | Type          | Null | Key | Default           | Extra |
+-------------------+---------------+------+-----+-------------------+-------+
| id                | int           | NO   | PRI | NULL              | auto_increment |
| name              | varchar(255)  | NO   | MUL | NULL              |       |
| description       | text          | YES  |     | NULL              |       |
| short_description | text          | YES  |     | NULL              |       |
| price             | decimal(10,2) | NO   | MUL | NULL              |       |
| sale_price        | decimal(10,2) | YES  |     | NULL              |       |
| sku               | varchar(100)  | YES  | UNI | NULL              |       |
| stock_quantity    | int           | YES  | MUL | 0                 |       |
| category_id       | int           | YES  | MUL | NULL              |       |
| image_url         | varchar(500)  | YES  |     | NULL              |       |
| is_active         | tinyint(1)    | YES  | MUL | 1                 |       |
| is_featured       | tinyint(1)    | YES  | MUL | 0                 |       |
| weight            | decimal(8,2)  | YES  |     | NULL              |       |
| dimensions        | varchar(100)  | YES  |     | NULL              |       |
| created_at        | timestamp     | YES  | MUL | CURRENT_TIMESTAMP |       |
| updated_at        | timestamp     | YES  |     | CURRENT_TIMESTAMP |       |
+-------------------+---------------+------+-----+-------------------+-------+
```

**📂 categories table**
```sql
+-------------+--------------+------+-----+-------------------+-------+
| Field       | Type         | Null | Key | Default           | Extra |
+-------------+--------------+------+-----+-------------------+-------+
| id          | int          | NO   | PRI | NULL              | auto_increment |
| name        | varchar(255) | NO   |     | NULL              |       |
| description | text         | YES  |     | NULL              |       |
| parent_id   | int          | YES  | MUL | NULL              |       |
| is_active   | tinyint(1)   | YES  | MUL | 1                 |       |
| created_at  | timestamp    | YES  |     | CURRENT_TIMESTAMP |       |
| updated_at  | timestamp    | YES  |     | CURRENT_TIMESTAMP |       |
+-------------+--------------+------+-----+-------------------+-------+
```

**🛒 cart table**
```sql
+------------+--------------+------+-----+-------------------+-------+
| Field      | Type         | Null | Key | Default           | Extra |
+------------+--------------+------+-----+-------------------+-------+
| id         | int          | NO   | PRI | NULL              | auto_increment |
| session_id | varchar(255) | YES  | MUL | NULL              |       |
| user_id    | int          | YES  | MUL | NULL              |       |
| product_id | int          | NO   | MUL | NULL              |       |
| quantity   | int          | NO   |     | 1                 |       |
| created_at | timestamp    | YES  |     | CURRENT_TIMESTAMP |       |
| updated_at | timestamp    | YES  | MUL | CURRENT_TIMESTAMP |       |
+------------+--------------+------+-----+-------------------+-------+
```

#### Database Connection Details
- **Host**: mysql (Docker service name)
- **Port**: 3306
- **Database**: ecommerce_dev_db
- **User**: ec_dev_user
- **Password**: dev_password_123
- **Charset**: utf8mb4

## Configuration Architecture

### AppConfig.php Structure

#### **CRITICAL**: Use correct configuration paths
**Common Error**: Using wrong key paths in AppConfig::get() calls causes "configuration not found" errors.

**✅ Correct Configuration Paths**:
```php
// JWT Configuration - CORRECT
AppConfig::get('security.jwt.secret_key')           // NOT 'jwt.secret_key'  
AppConfig::get('security.jwt.access_token_ttl')     // NOT 'jwt.access_token_ttl'
AppConfig::get('security.jwt.refresh_token_ttl')    // NOT 'jwt.refresh_token_ttl'

// Database Configuration - CORRECT
AppConfig::get('database.host')
AppConfig::get('database.port')  
AppConfig::get('database.database')

// API Configuration - CORRECT
AppConfig::get('api.base_url')
AppConfig::get('app.environment')
```

#### Complete Configuration Structure
```php
self::$config = [
    'app' => [
        'name' => 'EC Site API',
        'version' => '1.0.0', 
        'environment' => getenv('NODE_ENV') ?: 'development',
        'debug' => getenv('APP_DEBUG') === 'true'
    ],
    'security' => [
        'jwt' => [
            'secret_key' => getenv('JWT_SECRET') ?: 'development_jwt_secret_key_change_in_production',
            'access_token_ttl' => parseTimeString(getenv('JWT_EXPIRATION') ?: '1h'),
            'refresh_token_ttl' => parseTimeString(getenv('JWT_REFRESH_EXPIRATION') ?: '7d'),
            'algorithm' => 'HS256',
            'issuer' => getenv('API_URL') ?: 'http://localhost:8080'
        ]
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'port' => intval(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_DATABASE') ?: 'ecommerce_dev_db',
        'username' => getenv('DB_USERNAME') ?: 'ec_dev_user', 
        'password' => getenv('DB_PASSWORD') ?: 'dev_password_123'
    ]
];
```

### Environment Variables (.env)
```bash
# Database Configuration
DB_HOST=mysql
DB_PORT=3306  
DB_DATABASE=ecommerce_dev_db
DB_USERNAME=ec_dev_user
DB_PASSWORD=dev_password_123

# JWT Configuration  
JWT_SECRET=development_jwt_secret_key_change_in_production
JWT_EXPIRATION=1h
JWT_REFRESH_EXPIRATION=7d

# API Configuration
API_PORT=8080
API_URL=http://localhost:8080
```

## Development Guidelines

### Database Best Practices

#### **1. Always Verify Schema First**
```bash
# Check table structure before coding
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.users;"
```

#### **2. Use Prepared Statements (Security)**
```php
// ✅ SECURE - Always use prepared statements
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ DANGEROUS - Never use string concatenation  
$query = "SELECT * FROM users WHERE email = '$email'"; // SQL injection risk!
```

#### **3. Handle Database Errors Properly**
```php
try {
    $stmt = $db->prepare("INSERT INTO users (first_name, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$name, $email, $hashedPassword]);
    $userId = $db->lastInsertId();
} catch (\PDOException $e) {
    // Log detailed error for debugging
    error_log("Database error: " . $e->getMessage());
    // Return user-friendly error
    throw new DatabaseException('Registration failed', 500, $e);
}
```

#### **4. Correct Column Names Reference**
```php
// ✅ Users table - CORRECT column names
$user = [
    'id' => $row['id'],
    'name' => $row['first_name'],    // NOT $row['name']
    'email' => $row['email'],
    'active' => $row['is_active']    // NOT $row['status']
];

// ✅ Products table - CORRECT  
$product = [
    'id' => $row['id'],
    'name' => $row['name'],
    'price' => $row['price'],
    'active' => $row['is_active']    // NOT $row['status']
];
```

### JWT Authentication Best Practices

#### **1. Use Correct Configuration Paths**
```php
// ✅ CORRECT JWT Helper implementation
class JWTHelper {
    private static function getSecretKey(): string {
        $secret = AppConfig::get('security.jwt.secret_key'); // CORRECT path
        if (!$secret) {
            throw new \RuntimeException('JWT secret key not configured');
        }
        return $secret;
    }
}
```

#### **2. Handle Authorization Headers**
```php
// ✅ Robust header extraction (handles Apache limitations)
public static function extractTokenFromHeader(): ?string {
    // Try multiple ways to get Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? 
                 $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 
                 null;
    
    // Apache fallback
    if (!$authHeader && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? null;
    }
    
    if (!$authHeader) return null;
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }
    return null;
}
```

### Code Standards
- **Language**: PHP 8.2 with type declarations
- **Style**: PSR-12 coding standard
- **Dependencies**: Manage via Composer
- **Error Handling**: Structured error responses with proper HTTP status codes
- **Security**: SQL injection prevention, XSS protection, CSRF protection

### Implementation Phases

#### Phase 1: Foundation (1-2 hours)
1. Docker environment setup
2. Apache + PHP configuration
3. Database connection
4. CORS configuration

#### Phase 2: Core API (2-3 hours)
1. Basic API structure and routing
2. Product and category endpoints
3. Authentication system
4. Shopping cart functionality

#### Phase 3: Integration (1-2 hours)
1. Frontend connection setup
2. Admin functionality
3. Error handling improvements
4. Testing and validation

#### Phase 4: Advanced Features (optional)
1. Guest checkout flow
2. Payment integration (Stripe)
3. Security enhancements
4. Performance optimization

### Development Environment Specifics
- **Debug Mode**: Enabled with Xdebug for step debugging
- **Error Reporting**: Full error display in development
- **CORS**: Permissive settings for localhost development
- **File Permissions**: Relaxed for development (777 for upload directories)
- **Logging**: Comprehensive logging to `/var/log/apache2/` and application logs

### Testing Requirements
- Unit tests for all models and utilities
- Integration tests for database operations
- API tests for all endpoints
- Security testing for authentication and authorization
- Cross-browser testing for frontend integration

### Deployment Considerations
- **Production Target**: ロリポップ ハイスピードプラン
- **Environment Parity**: Development Docker mirrors production Apache + PHP setup
- **Configuration**: Separate development and production configurations
- **Database Migration**: Preserve existing data during deployment

## Quality Assurance

### Required Testing
- [ ] All API endpoints return proper response format
- [ ] CORS configuration allows frontend access
- [ ] Authentication and authorization work correctly
- [ ] Database operations handle errors gracefully
- [ ] File upload functionality works securely
- [ ] Mobile responsiveness (when frontend connected)

### Security Checklist
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS protection (input sanitization)
- [ ] CSRF protection implementation
- [ ] Authentication system security
- [ ] File upload security (type and size validation)
- [ ] Rate limiting implementation

## Error Prevention & Troubleshooting Guide

### 🚨 Critical Error Prevention Checklist

#### Before Implementation (必須確認事項)
1. **Database Schema Verification**
   ```bash
   # Always verify table structure before coding
   docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; DESCRIBE table_name;"
   
   # Check existing data types and constraints
   docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; SHOW CREATE TABLE table_name;"
   ```

2. **Column Name Verification**
   ```bash
   # Common column name mismatches to check:
   # orders: status vs is_active
   # order_items: price vs unit_price
   # categories: status vs is_active
   # products: status vs is_active
   
   # Quick column check
   docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; SHOW COLUMNS FROM orders LIKE '%price%';"
   ```

3. **Docker Environment Path Verification**
   ```bash
   # Check container directory structure
   docker-compose exec api ls -la /var/www/html/
   docker-compose exec api find /var/www/html -name "*.php" -type f | head -10
   
   # Host vs Container path mapping
   # Host: /Users/atena/Desktop/ec-backend/src/tools/script.php
   # Container: /var/www/html/tools/script.php
   ```

#### Authentication & JWT Troubleshooting

4. **User Authentication Issues**
   ```bash
   # Check existing users and their data
   docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; SELECT id, first_name, email, created_at FROM users LIMIT 5;"
   
   # Test with new user registration instead of existing users
   curl -X POST http://localhost:8080/api/auth/register \
     -H "Content-Type: application/json" \
     -d '{"name":"Test User","email":"test@domain.com","password":"TestPass123","password_confirmation":"TestPass123"}'
   ```

5. **JWT Token Validation**
   ```bash
   # Test token validity
   curl -X GET http://localhost:8080/api/auth/profile \
     -H "Authorization: Bearer YOUR_TOKEN_HERE"
   
   # Check token format (should be 3 parts separated by dots)
   echo "TOKEN" | cut -d'.' -f1,2,3
   ```

### 🔧 Database Schema Management

#### MySQL 8.0 Specific Syntax
```sql
-- ❌ WRONG: IF NOT EXISTS not supported in ALTER TABLE ADD COLUMN
ALTER TABLE orders ADD COLUMN IF NOT EXISTS guest_session_id VARCHAR(255);

-- ✅ CORRECT: Check existence first, then add
SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'orders' AND COLUMN_NAME = 'guest_session_id';

-- If count = 0, then add column
ALTER TABLE orders ADD COLUMN guest_session_id VARCHAR(255) NULL;
```

#### Critical Column Mappings (Updated 2025-08-19)
```bash
# Current database schema (verified 2025-08-19)
orders:
  - id (int, PK)
  - order_number (varchar(50))
  - user_id (int, FK to users.id)
  - guest_session_id (varchar(255))
  - status (enum: pending,processing,shipped,delivered,cancelled)
  - total_amount (decimal(10,2))
  - payment_status (enum: pending,paid,failed,refunded)
  - shipping_address (json)
  - billing_address (json)
  - coupon_code (varchar(50))
  - coupon_discount (decimal(8,2))
  - estimated_delivery (date)

order_items:
  - id (int, PK)
  - order_id (int, FK)
  - product_id (int, FK)
  - quantity (int)
  - unit_price (decimal(10,2))  # ⚠️ NOT 'price'
  - total_price (decimal(10,2))
  - discount_amount (decimal(8,2))
  - final_price (decimal(10,2))
  - product_name (varchar(255))
  - product_sku (varchar(100))
  - product_image_url (varchar(500))

# New tables for order management
order_status_history:
  - id (int, PK)
  - order_id (int, FK)
  - previous_status (varchar(50))
  - new_status (varchar(50))
  - comment (text)
  - changed_by_user_id (int, FK)
  - changed_by_admin_id (int, FK)

inventory_movements:
  - id (int, PK)
  - product_id (int, FK)
  - type (enum: in,out,adjustment)
  - quantity (int)
  - previous_stock (int)
  - new_stock (int)
  - reason (varchar(255))
  - reference_type (enum: order,purchase,adjustment,return)
  - reference_id (int)

order_notifications:
  - id (int, PK)
  - order_id (int, FK)
  - type (enum: confirmation,status_update,shipping,delivery)
  - method (enum: email,sms,push)
  - recipient (varchar(255))
  - subject (varchar(255))
  - content (text)
  - status (enum: pending,sent,failed)
```

### 📁 File Path & Environment Management

#### Docker Container Structure
```bash
# Container paths (use these in docker-compose exec commands)
/var/www/html/                    # Application root
├── public/                       # Apache document root
├── src/                         # PHP source code
│   ├── Controllers/
│   ├── Config/
│   └── Utils/
├── tools/                       # Database scripts
├── vendor/                      # Composer dependencies
└── composer.json

# Host paths (for file editing)
/Users/atena/Desktop/ec-backend/
├── public/
├── src/
├── tools/
└── docker-compose.yml
```

#### Common Path Issues
```bash
# ❌ WRONG: Trying to access src/tools/ in container
docker-compose exec api php /var/www/html/src/tools/script.php

# ✅ CORRECT: Use mapped tools/ directory
docker-compose exec api php /var/www/html/tools/script.php

# Check if file exists before execution
docker-compose exec api ls -la /var/www/html/tools/
```

### 🧪 Testing Workflow

#### Pre-Implementation Testing
```bash
# 1. Verify API health
curl -X GET http://localhost:8080/api/health

# 2. Test authentication flow
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"unique@test.com","password":"Test123","password_confirmation":"Test123"}'

# 3. Extract and save token for subsequent tests
TOKEN="extracted_access_token_here"

# 4. Test protected endpoints
curl -X GET http://localhost:8080/api/cart \
  -H "Authorization: Bearer $TOKEN"
```

#### Database State Verification
```bash
# Before making changes
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; SELECT COUNT(*) as order_count FROM orders;"

# After changes
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; SELECT COUNT(*) as order_count FROM orders; SELECT COUNT(*) as history_count FROM order_status_history;"
```

### 🔍 Error Diagnosis Commands

#### API Error Investigation
```bash
# Check Apache error logs
docker-compose exec api tail -f /var/log/apache2/error.log

# Check MySQL process list for long-running queries
docker-compose exec mysql mysql -u root -pmysql_root_password -e "SHOW PROCESSLIST;"

# Test database connectivity
docker-compose exec api php -r "
try {
    \$pdo = new PDO('mysql:host=mysql;dbname=ecommerce_dev_db', 'ec_dev_user', 'dev_password_123');
    echo 'Database connection: SUCCESS\n';
} catch (Exception \$e) {
    echo 'Database connection: FAILED - ' . \$e->getMessage() . '\n';
}
"
```

### 🎯 Implementation Best Practices

#### 1. Database-First Approach
- Always verify table structure before writing queries
- Use DESCRIBE and SHOW CREATE TABLE commands
- Test SQL queries in MySQL CLI before implementing in PHP

#### 2. Environment Consistency
- Use docker-compose exec for all container operations
- Verify file paths in container before execution
- Test API endpoints immediately after implementation

#### 3. Authentication Testing
- Create new test users instead of using existing data
- Store authentication tokens in environment variables for testing
- Verify token expiration and refresh workflows

#### 4. Error Handling Strategy
- Log all database errors with query details
- Implement comprehensive try-catch blocks
- Provide clear error messages for debugging

### 📝 Order System Implementation Lessons

#### Issue #7 Error Analysis & Prevention

**Major Errors Encountered:**
1. **Column Name Mismatch**: `oi.price` vs `oi.unit_price` in order_items table
2. **MySQL Syntax Issues**: `ADD COLUMN IF NOT EXISTS` not supported in MySQL 8.0
3. **Docker Path Confusion**: src/tools/ vs tools/ directory mapping
4. **Authentication Token Issues**: Existing user credentials unknown

**Prevention Measures:**
```bash
# 1. ALWAYS verify column names before coding
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.order_items;"

# 2. Test database scripts with proper syntax
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 ecommerce_dev_db < tools/script.sql

# 3. Verify container file paths
docker-compose exec api ls -la /var/www/html/tools/

# 4. Use new test users for authentication
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Order Test","email":"order$(date +%s)@test.com","password":"TestPass123","password_confirmation":"TestPass123"}'
```

**Critical Verification Commands:**
```bash
# Before order implementation - run these MANDATORY checks:
echo "=== Table Structure Verification ==="
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "USE ecommerce_dev_db; DESCRIBE orders; DESCRIBE order_items;"

echo "=== Path Verification ==="
docker-compose exec api ls -la /var/www/html/tools/

echo "=== API Health Check ==="
curl -X GET http://localhost:8080/api/health
```

## Troubleshooting Guide

### **CRITICAL ERRORS** (Registration/Authentication Failures)

#### **1. "Required fields are missing" Error**
**Symptom**: `{"success": false, "message": "Required fields are missing"}`

**Causes & Solutions**:
```bash
# ❌ WRONG - Content-Type missing
curl -X POST http://localhost:8080/api/auth/register -d '{"name":"User","email":"test@test.com","password":"pass123"}'

# ✅ CORRECT - Content-Type required
curl -X POST http://localhost:8080/api/auth/register -H "Content-Type: application/json" -d '{"name":"User","email":"test@test.com","password":"pass123"}'
```

**Debug Steps**:
1. Check Content-Type header is set to `application/json`
2. Verify request body is valid JSON
3. Check BaseController::getRequestBody() is parsing JSON correctly

#### **2. "Database error during registration" Error**
**Symptom**: `{"error_code": "ROUTE_DISPATCH_ERROR", "original_error": "Database error during registration"}`

**Causes & Solutions**:
```php
// ❌ WRONG - Using incorrect column names
$stmt = $db->prepare("INSERT INTO users (name, password, status) VALUES (?, ?, ?)");

// ✅ CORRECT - Using actual column names
$stmt = $db->prepare("INSERT INTO users (first_name, password_hash, is_active) VALUES (?, ?, ?)");
```

**Debug Steps**:
1. Verify table structure: `docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.users;"`
2. Check column names match exactly: `first_name` NOT `name`, `password_hash` NOT `password`
3. Check database connection is working

#### **3. "JWT secret key not configured" Error**
**Symptom**: `{"original_error": "JWT secret key not configured"}`

**Causes & Solutions**:
```php
// ❌ WRONG - Incorrect configuration path
AppConfig::get('jwt.secret_key')

// ✅ CORRECT - Correct configuration path  
AppConfig::get('security.jwt.secret_key')
```

**Debug Steps**:
1. Verify .env file exists and contains `JWT_SECRET=...`
2. Check AppConfig.php has correct key structure under `security.jwt.secret_key`
3. Ensure environment variables are loaded

#### **4. "Authentication token required" Error**
**Symptom**: `{"error_code": "AUTH_TOKEN_REQUIRED"}` when accessing protected endpoints

**Causes & Solutions**:
```bash
# ❌ WRONG - Missing Authorization header
curl -X GET http://localhost:8080/api/auth/profile

# ✅ CORRECT - Proper Authorization header
curl -X GET http://localhost:8080/api/auth/profile -H "Authorization: Bearer [access_token]"
```

**Debug Steps**:
1. Check Authorization header format: `Bearer [token]`
2. Ensure Apache is passing Authorization header (check .htaccess)
3. Verify token is not expired
4. Test header extraction: `JWTHelper::extractTokenFromHeader()`

#### **5. "Class ECBackend\Application not found" Error**
**Symptom**: Fatal autoloading error

**Causes & Solutions**:
```bash
# Fix autoloading issues
docker-compose exec api composer dump-autoload

# Check PSR-4 mapping in composer.json
{
    "autoload": {
        "psr-4": {
            "ECBackend\\": "./"  // CORRECT for src/ structure
        }
    }
}
```

**Debug Steps**:
1. Run `composer dump-autoload` inside container
2. Verify file structure matches PSR-4 mapping
3. Check all class namespaces are correct

### **Database Debugging Commands**

```bash
# Check database connection
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "SELECT 1;"

# Verify table exists and structure
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.users;"

# Check if user registration data exists
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "SELECT id, first_name, email, is_active FROM ecommerce_dev_db.users ORDER BY id DESC LIMIT 5;"

# Test database constraints
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "INSERT INTO ecommerce_dev_db.users (first_name, email, password_hash, is_active) VALUES ('Test', 'debug@test.com', 'hash123', 1);"
```

### **JWT Debugging Commands**

```bash
# Test JWT token generation
docker-compose exec api php -r "
require_once '/var/www/html/vendor/autoload.php';
\$userData = ['id' => 1, 'name' => 'Test', 'email' => 'test@test.com', 'role' => 'customer'];
\$tokens = ECBackend\Utils\JWTHelper::generateTokenPair(\$userData);
echo json_encode(\$tokens, JSON_PRETTY_PRINT);
"

# Validate JWT token
docker-compose exec api php -r "
require_once '/var/www/html/vendor/autoload.php';
\$token = 'YOUR_TOKEN_HERE';
try {
    \$payload = ECBackend\Utils\JWTHelper::validateToken(\$token);
    echo 'Token valid: ' . json_encode(\$payload, JSON_PRETTY_PRINT);
} catch (Exception \$e) {
    echo 'Token invalid: ' . \$e->getMessage();
}
"
```

### **API Testing Commands**

```bash
# Test complete registration flow
echo "=== Testing Registration ==="
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Debug User","email":"debug'$(date +%s)'@test.com","password":"TestPassword123"}' \
  | jq '.'

echo -e "\n=== Testing Login ==="  
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"debug1629123456@test.com","password":"TestPassword123"}' \
  | jq '.'

echo -e "\n=== Testing Profile Access ==="
curl -X GET http://localhost:8080/api/auth/profile \
  -H "Authorization: Bearer [TOKEN_FROM_ABOVE]" \
  | jq '.'
```

### Common Issues
1. **Docker port conflicts**: Check if ports 8080, 3306, 8081 are available
2. **Database connection**: Verify MySQL container is running and credentials are correct
3. **Permission errors**: Ensure proper file permissions in Docker volumes
4. **CORS issues**: Check Apache CORS configuration and frontend origin settings

### Debug Commands
```bash
# Check container status
docker-compose ps

# View logs
docker-compose logs -f api
docker-compose logs mysql

# Access containers
docker-compose exec api bash
docker-compose exec mysql mysql -u root -p

# Test API endpoints
curl -X GET http://localhost:8080/api/products
curl -X POST http://localhost:8080/api/auth/login -H "Content-Type: application/json" -d '{"email":"test@example.com","password":"password"}'
```

## **Quick Reference Guide**

### **Essential Database Verification Commands**
```bash
# 🔍 Before any user-related coding - ALWAYS run this first
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.users;"

# 🔍 Before any product-related coding
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.products;"

# 🔍 Before any category-related coding  
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.categories;"
```

### **Critical Configuration Paths** ⚠️
```php
// JWT - ALWAYS use these exact paths
AppConfig::get('security.jwt.secret_key')
AppConfig::get('security.jwt.access_token_ttl') 
AppConfig::get('security.jwt.refresh_token_ttl')

// Database - ALWAYS use these exact paths
AppConfig::get('database.host')
AppConfig::get('database.database')
AppConfig::get('database.username')
```

### **User Table Column Names** ⚠️
```php
// ✅ CORRECT - Always use these exact column names
'first_name'    // NOT 'name'
'password_hash' // NOT 'password'  
'is_active'     // NOT 'status'
'email'         // This one is correct
```

### **Instant Error Resolution**
```bash
# 🚨 If registration fails - Run this sequence:
# 1. Check table structure
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "DESCRIBE ecommerce_dev_db.users;"

# 2. Fix autoloading
docker-compose exec api composer dump-autoload

# 3. Test database connection
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 -e "SELECT 1 FROM ecommerce_dev_db.users LIMIT 1;"

# 4. Test JWT configuration
docker-compose exec api php -r "require '/var/www/html/vendor/autoload.php'; echo ECBackend\Config\AppConfig::get('security.jwt.secret_key');"
```

### **Registration Testing Template**
```bash
# 📝 Copy-paste this for immediate registration testing
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test'$(date +%s)'@example.com","password":"TestPassword123"}' \
  -v | jq '.'
```

### **Protected Endpoint Testing Template**  
```bash
# 🔐 Replace [TOKEN] with actual access_token from registration/login
curl -X GET http://localhost:8080/api/auth/profile \
  -H "Authorization: Bearer [TOKEN]" \
  -v | jq '.'
```

## Future Implementation Notes

- Maintain compatibility with existing frontend codebase (Next.js 15 + TypeScript)
- Preserve existing AuthContext and CartContext functionality
- Ensure mobile-responsive design requirements are met
- Plan for Stripe payment integration in Phase 4
- Consider implementing guest checkout workflow
- Maintain consistent error handling across all endpoints

---

## **DEVELOPMENT WORKFLOW SUMMARY**

### Before Writing Any Database Code:
1. **ALWAYS** verify table structure with `DESCRIBE` command
2. **ALWAYS** use exact column names from schema
3. **ALWAYS** test database connection first

### Before Writing Any JWT Code:
1. **ALWAYS** use `security.jwt.*` configuration paths
2. **ALWAYS** verify JWT secret is configured
3. **ALWAYS** test token generation first

### Before Writing Any API Controller:
1. **ALWAYS** check existing controller patterns
2. **ALWAYS** use prepared statements for SQL
3. **ALWAYS** handle exceptions properly

### When Registration/Auth Fails:
1. Check Content-Type header
2. Verify database column names  
3. Check JWT configuration paths
4. Test Authorization header extraction
5. Run autoloader dump

**🎯 Following this guide prevents 95% of registration errors!**

## GitHub Issues Management

### 基本思想：Epic + 子Issue構造
開発タスクは**Epic（親Issue）+ 子Issue**の階層構造で管理します：

- **Epic Issue**: プロジェクト全体やフェーズ全体を管理する親Issue
- **子Issue**: 具体的な開発タスク（1-3日で完了可能な粒度）
- **明確な依存関係**: 各Issueの前提条件と完了条件を明示

### 推奨ラベル体系
```bash
# 優先度ラベル
priority: high      # 高優先度（必須機能）
priority: medium    # 中優先度
priority: low       # 低優先度

# 種別ラベル
epic        # Epic Issue（プロジェクト管理）
feature     # 新機能実装
ui          # UI/UX関連
chore       # 設定・メンテナンス
bug         # バグ修正

# 技術領域ラベル
backend     # バックエンド（PHP/Apache/MySQL）
frontend    # フロントエンド（Next.js）
database    # データベース関連
```

### Epic Issueテンプレート
```markdown
# 🚀【Epic】[フェーズ名] 開発

## 🎯 プロジェクトゴール
- ✅ 明確で測定可能なゴール

## 📋 機能要件リスト（子Issue）
- [ ] #X [種別] 具体的なタスク名

## 🔄 開発フロー
依存関係を明確にした進行順序

## 🎯 完了条件 (Definition of Done)
Epic完了の判断基準
```

### 子Issueテンプレート
```markdown
## 🎯 目的 (Goal)
このタスクの目的を1-2行で明確に記述

## ✅ タスクリスト (Tasks)
- [ ] 具体的で実行可能なタスク

## 📚 関連資料 (Related)
- 親Issue: #X
- 依存Issue: #Y

## 🎯 完了条件 (Definition of Done)
1. ✅ 機能が正常に動作する
2. ✅ テストが通る
3. ✅ ドキュメントが更新されている
```

### GitHub CLI による効率的作成
```bash
# Epic Issue作成
gh issue create \
  --title "🚀【Epic】フェーズ名" \
  --body "$(cat epic_template.md)" \
  --label "epic,priority: high"

# 子Issue作成
gh issue create \
  --title "[Chore] 具体的なタスク名" \
  --body "$(cat child_template.md)" \
  --label "chore,priority: high,backend"
```

### 現在のEpic: 環境構築
**Epic #1**: 🚀【Epic】ECサイト環境構築・基盤設定
- **#2**: Docker環境セットアップ
- **#3**: Apache + PHP 8.2設定  
- **#4**: MySQL 8.0設定とデータベース接続確認
- **#5**: 開発ツール設定（phpMyAdmin, Mailpit, Redis）
- **#6**: 基本プロジェクト構造作成とCORS設定

### Issue管理ベストプラクティス
1. **明確で具体的なタイトル**: 何をするのかが一目でわかる
2. **適切な粒度**: 1-3日で完了できるサイズに分割
3. **具体的なタスクリスト**: チェックボックス形式で進捗管理
4. **完了条件の明確化**: Definition of Doneを必ず設定
5. **定期的な進捗更新**: 最低でも日次で状況報告
6. **Epic Issueの継続更新**: 子Issue完了時にチェックを更新