# ECサイト再設計仕様書

## 概要
OpenLiteSpeed設定問題により、より安定した Apache + PHP + MySQL 構成で再構築する。

## アーキテクチャ設計

### 全体構成
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│(Frontend)       │    │(Backend API)    │    │(Database)       │
│  Next.js        │◄───┤  Apache + PHP   │◄───┤  MySQL 8.0      │
│  Port: 3000     │    │  Port: 8080     │    │  Port: 3306     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### フロントエンド (既存活用 + 改善)
**保持する部分：**
- ✅ Next.js 15 + TypeScript
- ✅ AuthContext, CartContext
- ✅ レスポンシブデザイン
- ✅ エラーハンドリング
- ✅ 管理画面UI

**改善する部分：**
- 🔧 API接続部分の整理
- 🔧 環境変数の統一
- 🔧 型定義の強化

### バックエンド (新規作成)
**技術スタック：**
```dockerfile
FROM php:8.2-apache
- Apache 2.4
- PHP 8.2 + 必要な拡張
- Composer
- 適切なCORS設定
```

**API設計原則：**
1. **RESTful設計**
2. **統一されたレスポンス形式**
3. **適切なHTTPステータスコード**
4. **バリデーション強化**

## API仕様設計

### 1. レスポンス形式統一
```json
{
  "success": boolean,
  "data": object | array | null,
  "message": string,
  "errors": array,
  "pagination": object | null
}
```

### 2. 主要エンドポイント
```
GET    /api/products              # 商品一覧
GET    /api/products/{id}         # 商品詳細
GET    /api/categories            # カテゴリ一覧
POST   /api/auth/login            # ログイン
POST   /api/auth/register         # 新規登録
GET    /api/auth/profile          # プロフィール取得
POST   /api/cart/add              # カート追加
GET    /api/cart                  # カート内容
PUT    /api/cart/{id}             # カート数量更新
DELETE /api/cart/{id}             # カート削除

# 管理者API
POST   /api/admin/login           # 管理者ログイン
GET    /api/admin/products        # 商品管理
POST   /api/admin/products        # 商品作成
PUT    /api/admin/products/{id}   # 商品更新
DELETE /api/admin/products/{id}   # 商品削除
```

### 3. 認証・認可
```php
# JWT または Session ベース
# Role-based access control (customer, admin)
# CSRF保護
# Rate limiting
```

## データベース設計

### 既存テーブル活用
```sql
-- 既存のデータベース構造は保持
-- ecommerce_db (商品15件、カテゴリ9件)
-- 必要に応じてインデックス追加
-- パフォーマンス最適化
```

## Docker構成

### docker-compose.yml (新版)
```yaml
version: '3.8'
services:
  web:
    build: .
    ports:
      - "8080:80"
    volumes:
      - ./backend:/var/www/html
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html/public
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: ecommerce_db
    volumes:
      - mysql_data:/var/lib/mysql

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    ports:
      - "8081:80"
    environment:
      - PMA_HOST=mysql
    depends_on:
      - mysql

volumes:
  mysql_data:
```

### Dockerfile (Apache + PHP)
```dockerfile
FROM php:8.2-apache

# 必要な拡張をインストール
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Apache設定
RUN a2enmod rewrite
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Composer インストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# CORS ヘッダー設定
RUN echo 'Header always set Access-Control-Allow-Origin "*"' >> /etc/apache2/apache2.conf
RUN echo 'Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"' >> /etc/apache2/apache2.conf
RUN echo 'Header always set Access-Control-Allow-Headers "Content-Type, Authorization"' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
```

## 開発フロー

### Phase 1: 基盤構築 (1-2時間)
1. ✅ Docker環境作成
2. ✅ Apache + PHP設定
3. ✅ データベース接続確認
4. ✅ CORS設定

### Phase 2: API実装 (2-3時間)
1. ✅ 基本API構造
2. ✅ 商品・カテゴリAPI
3. ✅ 認証API
4. ✅ カートAPI

### Phase 3: 連携・テスト (1-2時間)
1. ✅ フロントエンド接続修正
2. ✅ 管理者機能復旧
3. ✅ エラーハンドリング
4. ✅ 動作確認

### Phase 4: 追加機能 (必要に応じて)
1. ⭐ ゲスト購入フロー
2. ⭐ Stripe決済
3. ⭐ セキュリティ強化

## 品質保証

### テスト項目
- [ ] API全エンドポイント動作確認
- [ ] CORS設定確認
- [ ] 認証・認可確認
- [ ] フロントエンド連携確認
- [ ] モバイル対応確認
- [ ] エラーハンドリング確認

### セキュリティチェック
- [ ] SQL Injection対策
- [ ] XSS対策
- [ ] CSRF対策
- [ ] 認証システム確認

## 期待される効果

### 安定性向上
- ✅ 実績のあるApache + PHP構成
- ✅ シンプルで理解しやすい設定
- ✅ トラブルシューティングが容易

### 開発効率向上
- ✅ 統一されたAPI設計
- ✅ 明確なエラーメッセージ
- ✅ 型安全な通信

### 保守性向上
- ✅ ドキュメント完備
- ✅ 標準的な技術スタック
- ✅ 拡張しやすい設計

---

この仕様書に基づいて、確実に動作するeコマースサイトを構築します。