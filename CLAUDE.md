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

### Database Schema
- Existing database: `ecommerce_db` with 15 products and 9 categories
- Development database: `ecommerce_dev_db` with test data
- Authentication: JWT or session-based with role-based access control

## Development Guidelines

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

## Troubleshooting

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

## Future Implementation Notes

- Maintain compatibility with existing frontend codebase (Next.js 15 + TypeScript)
- Preserve existing AuthContext and CartContext functionality
- Ensure mobile-responsive design requirements are met
- Plan for Stripe payment integration in Phase 4
- Consider implementing guest checkout workflow
- Maintain consistent error handling across all endpoints

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