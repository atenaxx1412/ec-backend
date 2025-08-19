# ECサイト API仕様書

## 概要

**プロジェクト**: ECサイト再設計 バックエンドAPI  
**バージョン**: 1.0.0  
**ベースURL**: `http://localhost:8080/api`  
**認証方式**: JWT Bearer Token  
**レスポンス形式**: JSON

## 共通仕様

### レスポンス形式
すべてのAPIレスポンスは以下の形式に従います：

```json
{
  "success": boolean,
  "data": object | array | null,
  "message": string,
  "errors": array,
  "pagination": object | null,
  "timestamp": string,
  "status_code": number
}
```

### 認証ヘッダー
認証が必要なエンドポイントでは以下のヘッダーが必要：

```
Authorization: Bearer {access_token}
```

### ページネーション
リスト取得エンドポイントでは以下のクエリパラメータが利用可能：

- `page`: ページ番号（デフォルト: 1）
- `limit`: 1ページあたりの件数（デフォルト: 20、最大: 100）

## エンドポイント一覧

### 1. ヘルスチェック

#### `GET /health`
API の稼働状況を確認

**パラメータ**: なし  
**認証**: 不要

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2025-08-19 19:01:28",
    "version": "1.0.0",
    "environment": "development",
    "services": {
      "database": "healthy",
      "redis": "unavailable"
    }
  },
  "message": "Health check completed"
}
```

### 2. 商品管理

#### `GET /products`
商品一覧を取得

**パラメータ**:
- `page` (optional): ページ番号
- `limit` (optional): 1ページあたりの件数
- `category_id` (optional): カテゴリIDで絞り込み
- `featured` (optional): 注目商品のみ (1/0)
- `search` (optional): 商品名での検索

**認証**: 不要

**レスポンス例**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "description": "最新のiPhone 15 Pro。高性能なA17 Proチップ搭載。",
      "price": 159800,
      "compare_price": 149800,
      "stock_quantity": 42,
      "sku": "IPH15P-128",
      "image_url": null,
      "is_featured": true,
      "category": {
        "id": 1,
        "name": "エレクトロニクス"
      },
      "review_count": 0,
      "average_rating": null,
      "created_at": "2025-08-18 06:30:32",
      "updated_at": "2025-08-19 14:37:10"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 5,
    "total": 15,
    "total_pages": 3,
    "has_next": true,
    "has_prev": false
  }
}
```

#### `GET /products/{id}`
商品詳細を取得

**パラメータ**:
- `id`: 商品ID

**認証**: 不要

#### `GET /categories`
カテゴリ一覧を取得

**認証**: 不要

### 3. 認証・ユーザー管理

#### `POST /auth/register`
ユーザー登録

**パラメータ**:
```json
{
  "name": "Test User",
  "first_name": "Test",
  "last_name": "User", 
  "email": "test@example.com",
  "password": "TestPassword123!"
}
```

**認証**: 不要

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "message": "Registration successful"
}
```

#### `POST /auth/login`
ユーザーログイン

**パラメータ**:
```json
{
  "email": "user@example.com",
  "password": "password"
}
```

#### `POST /auth/logout`
ユーザーログアウト

**認証**: 必要

#### `GET /auth/profile`
ユーザープロフィール取得

**認証**: 必要

### 4. ショッピングカート

#### `POST /cart/add`
カートに商品を追加

**パラメータ**:
```json
{
  "product_id": 1,
  "quantity": 2
}
```

**認証**: 必要

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "id": 30,
    "quantity": 2,
    "subtotal": 319600,
    "savings": 0,
    "product": {
      "id": 1,
      "name": "iPhone 15 Pro",
      "price": 159800,
      "stock_quantity": 42,
      "is_active": 1,
      "category_name": "エレクトロニクス"
    }
  },
  "message": "Item added to cart successfully"
}
```

#### `GET /cart`
カート内容を取得

**認証**: 必要

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 30,
        "quantity": 2,
        "subtotal": 319600,
        "savings": 0,
        "product": {
          "id": 1,
          "name": "iPhone 15 Pro",
          "price": 159800,
          "stock_quantity": 42,
          "is_active": 1,
          "category_name": "エレクトロニクス"
        }
      }
    ],
    "summary": {
      "item_count": 1,
      "total_quantity": 2,
      "subtotal": 319600,
      "tax": 31960,
      "shipping": 0,
      "total_savings": 0,
      "total": 351560,
      "out_of_stock_items": 0,
      "has_issues": false,
      "free_shipping_eligible": true,
      "free_shipping_remaining": 0
    }
  },
  "message": "Cart retrieved successfully"
}
```

#### `PUT /cart/{id}`
カート商品の数量を更新

**パラメータ**:
```json
{
  "quantity": 3
}
```

**認証**: 必要

#### `DELETE /cart/{id}`
カートから商品を削除

**認証**: 必要

#### `DELETE /cart/clear`
カートを空にする

**認証**: 必要

### 5. 注文管理

#### `POST /orders`
注文を作成

**パラメータ**:
```json
{
  "shipping_address": {
    "first_name": "太郎",
    "last_name": "田中",
    "company": "",
    "address_line1": "東京都渋谷区1-2-3",
    "address_line2": "マンション101号",
    "city": "渋谷区",
    "state": "東京都",
    "postal_code": "150-0001",
    "country": "JP",
    "phone": "03-1234-5678"
  },
  "billing_address": {
    // 同じ形式
  },
  "payment_method": "credit_card",
  "notes": "置き配希望"
}
```

**認証**: 必要

#### `GET /orders`
ユーザーの注文履歴を取得

**認証**: 必要

#### `GET /orders/{id}`
注文詳細を取得

**認証**: 必要

## 管理者API

### 1. 管理者認証

#### `POST /admin/login`
管理者ログイン

**パラメータ**:
```json
{
  "email": "admin@ec-site-dev.local",
  "password": "password"
}
```

**認証**: 不要

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "tokens": {
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "token_type": "Bearer",
      "expires_in": 3600
    },
    "admin": {
      "id": 1,
      "name": "開発管理者",
      "email": "admin@ec-site-dev.local",
      "role": "super_admin"
    }
  },
  "message": "Admin login successful"
}
```

### 2. 管理者ダッシュボード

#### `GET /admin/dashboard`
ダッシュボード統計情報を取得

**認証**: 必要（管理者権限）

**レスポンス例**:
```json
{
  "success": true,
  "data": {
    "statistics": {
      "total_products": 15,
      "total_categories": 9,
      "total_users": 12,
      "low_stock_products": 0,
      "recent_orders": 19,
      "revenue_today": 352360,
      "revenue_month": 1518040
    },
    "recent_products": [...],
    "recent_users": [...],
    "low_stock_products": [...]
  }
}
```

### 3. 管理者商品管理

#### `GET /admin/products`
管理者用商品一覧を取得

**パラメータ**:
- `page`, `limit`: ページネーション
- `status`: active/inactive フィルタ
- `category_id`: カテゴリフィルタ
- `low_stock`: 在庫少商品フィルタ
- `search`: 商品名・SKU・説明文での検索

**認証**: 必要（管理者権限）

#### `POST /admin/products`
新規商品を作成

**パラメータ**:
```json
{
  "name": "新商品名",
  "description": "商品説明",
  "price": 10000,
  "compare_price": 12000,
  "stock_quantity": 50,
  "sku": "CUSTOM-SKU-001",
  "category_id": 1,
  "image_url": "https://example.com/image.jpg",
  "status": "active"
}
```

**認証**: 必要（管理者権限）

#### `PUT /admin/products/{id}`
商品情報を更新

**認証**: 必要（管理者権限）

#### `DELETE /admin/products/{id}`
商品を削除（ソフトデリート）

**認証**: 必要（管理者権限）

## エラーレスポンス

エラー時は以下の形式でレスポンスを返します：

```json
{
  "success": false,
  "message": "エラーメッセージ",
  "error_code": "ERROR_CODE",
  "errors": {
    "field_name": ["エラー詳細"]
  },
  "timestamp": "2025-08-19T19:01:28Z"
}
```

### 主要なエラーコード

- `VALIDATION_ERROR`: バリデーションエラー
- `AUTH_TOKEN_REQUIRED`: 認証トークンが必要
- `AUTH_TOKEN_INVALID`: 無効な認証トークン
- `TOKEN_EXPIRED`: トークンの有効期限切れ
- `INSUFFICIENT_PERMISSIONS`: 権限不足
- `RESOURCE_NOT_FOUND`: リソースが見つからない
- `ADMIN_PERMISSIONS_REQUIRED`: 管理者権限が必要
- `RATE_LIMIT_EXCEEDED`: レート制限超過

## 開発環境

### Docker環境
```bash
# 環境起動
docker-compose up -d

# ログ確認
docker-compose logs -f api

# データベースアクセス
docker-compose exec mysql mysql -u ec_dev_user -pdev_password_123 ecommerce_dev_db
```

### アクセス情報
- **API**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **Mailpit（開発用メール）**: http://localhost:8025

### テスト用アカウント
- **管理者**: admin@ec-site-dev.local / password
- **一般ユーザー**: 登録APIで作成

## セキュリティ

### 実装済みセキュリティ機能
- JWT認証（アクセストークン・リフレッシュトークン）
- レート制限（ログイン試行回数制限）
- SQLインジェクション対策（プリペアドステートメント）
- XSS対策（入力サニタイゼーション）
- CORS設定
- セキュリティログ機能

### セキュリティヘッダー
本番環境では以下のヘッダーを設定推奨：
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Strict-Transport-Security: max-age=31536000`

## 更新履歴

- **v1.0.0** (2025-08-19): 初版リリース
  - 基本的な商品・カート・注文・管理者機能
  - JWT認証システム
  - 管理者ダッシュボード機能