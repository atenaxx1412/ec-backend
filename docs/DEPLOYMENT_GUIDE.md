# ECサイトバックエンドAPI - 本番環境デプロイガイド

## 概要

このドキュメントは、ECサイトバックエンドAPIをロリポップ ハイスピードプランに本番環境としてデプロイするための手順書です。

## 前提条件

### 必要な環境
- **ホスティング**: ロリポップ ハイスピードプラン
- **PHP**: 8.2以降
- **MySQL**: 8.0以降（ロリポップ対応版）
- **ドメイン**: 独自ドメインまたはロリポップ提供ドメイン

### 必要なサービス
- ロリポップ ハイスピードプラン契約
- 独自ドメイン（推奨）
- SSL証明書（Let's Encrypt対応）

## デプロイ準備

### 1. 環境設定ファイルの準備

#### 本番環境用 .env ファイル作成
```env
# Application
APP_ENV=production
APP_URL=https://yourdomain.com
APP_DEBUG=false

# Database (ロリポップ設定例)
DB_HOST=mysql-server
DB_NAME=your_database_name
DB_USERNAME=your_db_username  
DB_PASSWORD=your_secure_db_password

# JWT Settings (本番用強力なシークレットキー)
JWT_SECRET_KEY=your_super_secure_jwt_secret_key_here_min_256_bits
JWT_ACCESS_TOKEN_TTL=3600
JWT_REFRESH_TOKEN_TTL=604800

# Redis (ロリポップでRedis利用可能な場合)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Mail Settings
MAIL_HOST=smtp.lolipop.jp
MAIL_PORT=465
MAIL_USERNAME=your_email@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=ssl

# Security
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
RATE_LIMIT_ENABLED=true

# Logging
LOG_LEVEL=error
LOG_PATH=/home/users/0/your-lolipop-account/web/logs
```

#### セキュリティ設定の強化
```php
// src/Config/AppConfig.php に追加
'security' => [
    'jwt' => [
        'secret_key' => $_ENV['JWT_SECRET_KEY'],
        'access_token_ttl' => (int)($_ENV['JWT_ACCESS_TOKEN_TTL'] ?? 3600),
        'refresh_token_ttl' => (int)($_ENV['JWT_REFRESH_TOKEN_TTL'] ?? 604800),
    ],
    'cors' => [
        'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    ],
    'rate_limit' => [
        'enabled' => $_ENV['RATE_LIMIT_ENABLED'] === 'true',
        'login_attempts' => 5,
        'login_window' => 900, // 15分
        'api_requests' => 1000,
        'api_window' => 3600, // 1時間
    ]
]
```

### 2. データベースの準備

#### データベース作成（ロリポップ管理画面）
1. ロリポップ管理画面にログイン
2. 「サーバーの管理・設定」→「データベース」
3. 「データベース作成」でMySQL 8.0を選択
4. データベース名・ユーザー・パスワードを設定

#### データベーススキーマのインポート
```sql
-- 本番用データベースセットアップ
-- tools/production-setup.sql

-- 既存のテーブル構造をベースに本番用データを準備
-- 開発用テストデータは除外し、最低限の管理者アカウントのみ作成

-- 本番用管理者アカウント（パスワードは個別に設定）
INSERT INTO admins (email, password_hash, name, role, is_active) VALUES 
('admin@yourdomain.com', '$2y$10$HashedPasswordHere', '管理者', 'super_admin', 1);

-- 本番用カテゴリ（必要に応じて調整）
INSERT INTO categories (name, slug, description, is_active) VALUES 
('エレクトロニクス', 'electronics', '電子機器・家電製品', 1),
('ファッション', 'fashion', 'アパレル・服飾雑貨', 1),
('ホーム・キッチン', 'home-kitchen', '生活用品・キッチン用品', 1);
```

### 3. ファイル構成とアップロード

#### アップロードが必要なファイル・ディレクトリ
```
production-upload/
├── public/                     # ドキュメントルート
│   ├── index.php
│   └── .htaccess
├── src/                        # アプリケーションコード
├── vendor/                     # Composer依存関係（後述）
├── config/                     # 設定ファイル（セキュリティ要注意）
├── tools/                      # デプロイスクリプト
├── .env                        # 本番環境設定
├── composer.json
└── composer.lock
```

#### ロリポップでのディレクトリ構成
```
/home/users/0/your-account/
├── web/                        # ドキュメントルート（公開領域）
│   ├── index.php              # publicディレクトリの内容
│   ├── .htaccess
│   └── assets/                # 静的ファイル
└── private/                   # 非公開領域（重要）
    ├── api/                   # APIアプリケーション本体
    │   ├── src/
    │   ├── vendor/
    │   ├── config/
    │   └── .env
    ├── logs/                  # ログファイル
    └── uploads/               # アップロードファイル
```

### 4. 本番環境用.htaccess設定

#### 本番環境用 .htaccess
```apache
# 本番環境用 .htaccess
RewriteEngine On

# セキュリティ強化
ServerTokens Prod
ServerSignature Off

# HTTPS強制リダイレクト
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# セキュリティヘッダー（本番用）
Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:;"

# CORS設定（本番用 - 必要なオリジンのみ許可）
Header always set Access-Control-Allow-Origin "https://yourdomain.com"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
Header always set Access-Control-Max-Age "86400"

# セキュリティファイルのブロック
<FilesMatch "\.(env|log|sql|md|backup|bak|old)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# 機密ディレクトリへのアクセス拒否
RewriteRule ^(config|logs|tools|src|vendor)(/|$) - [F,L]

# API routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /index.php [QSA,L]

# 本番環境PHP設定
php_flag display_errors Off
php_flag log_errors On
php_value error_log "/home/users/0/your-account/private/logs/php_errors.log"
php_value memory_limit "256M"
php_value max_execution_time "120"
php_value upload_max_filesize "32M"
php_value post_max_size "32M"

# キャッシュ設定
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType application/json "access plus 1 hour"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

## デプロイ手順

### 1. 事前準備

#### ローカル環境での最終テスト
```bash
# 依存関係の最新化
composer install --no-dev --optimize-autoloader

# セキュリティチェック
composer audit

# コードの最終確認
php -l public/index.php
```

#### 本番用設定ファイルの検証
```bash
# 設定ファイルの文法チェック
php -c /dev/null -r "
\$config = include 'src/Config/AppConfig.php';
if (isset(\$config['database']['host'])) {
    echo 'Config loaded successfully\n';
} else {
    echo 'Config error\n';
}
"
```

### 2. ファイルアップロード

#### FTP/SFTPでのアップロード手順
1. **非公開ディレクトリへのアップロード**
   ```bash
   # 非公開領域へのアップロード
   /home/users/0/your-account/private/api/
   ├── src/
   ├── vendor/            # composer install --no-dev済み
   ├── config/
   ├── tools/
   ├── .env              # 本番設定
   ├── composer.json
   └── composer.lock
   ```

2. **公開ディレクトリへのアップロード**
   ```bash
   # ドキュメントルートへのアップロード
   /home/users/0/your-account/web/
   ├── index.php         # パス調整版
   └── .htaccess         # 本番設定版
   ```

#### 本番用 index.php の調整
```php
<?php
// 本番環境用 index.php
// パスを本番環境に合わせて調整

// 本番環境のパス設定
define('APP_ROOT', '/home/users/0/your-account/private/api');
define('PUBLIC_ROOT', '/home/users/0/your-account/web');

// Composer autoload
require_once APP_ROOT . '/vendor/autoload.php';

// .env ファイルの読み込み
$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->load();

// アプリケーション実行
use ECBackend\Application;
$app = new Application();
$app->run();
?>
```

### 3. データベース設定

#### データベース接続の確認
```php
// tools/db-connection-test.php
<?php
require_once '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    echo "Database connection successful!\n";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>
```

#### 本番用データベースマイグレーション
```sql
-- 1. 開発環境からのデータベース構造をエクスポート
-- 2. 本番環境でデータベース作成
-- 3. テーブル作成とマスターデータインサート
-- 4. インデックス作成とパフォーマンス最適化

-- パフォーマンス向上のためのインデックス追加例
CREATE INDEX idx_products_category_active ON products(category_id, is_active);
CREATE INDEX idx_products_featured ON products(is_featured, is_active);
CREATE INDEX idx_orders_user_date ON orders(user_id, created_at);
CREATE INDEX idx_cart_user ON cart_items(user_id, created_at);
```

### 4. セキュリティ設定

#### SSL証明書の設定
- ロリポップ管理画面で「独自SSL証明書導入」
- Let's Encryptを利用して無料SSL証明書を取得
- HTTPS強制リダイレクトの確認

#### ファイル権限の設定
```bash
# 推奨権限設定
chmod 755 /home/users/0/your-account/web/
chmod 644 /home/users/0/your-account/web/index.php
chmod 644 /home/users/0/your-account/web/.htaccess
chmod 750 /home/users/0/your-account/private/
chmod 600 /home/users/0/your-account/private/api/.env
chmod 755 /home/users/0/your-account/private/logs/
```

#### セキュリティチェックリスト
- [ ] .envファイルが公開ディレクトリ外に配置されている
- [ ] データベース認証情報が適切に設定されている
- [ ] JWT秘密鍵が十分に複雑である
- [ ] CORS設定が本番ドメインのみを許可している
- [ ] エラー表示が無効化されている
- [ ] ログファイルが適切な場所に配置されている
- [ ] SSL証明書が正しく設定されている

## 本番環境テスト

### 1. 基本動作確認
```bash
# ヘルスチェック
curl -X GET https://yourdomain.com/api/health

# 商品一覧取得
curl -X GET https://yourdomain.com/api/products

# CORS確認
curl -X OPTIONS https://yourdomain.com/api/products \
  -H "Origin: https://yourdomain.com" \
  -H "Access-Control-Request-Method: GET"
```

### 2. 管理者機能テスト
```bash
# 管理者ログイン
curl -X POST https://yourdomain.com/api/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@yourdomain.com","password":"your_admin_password"}'
```

### 3. パフォーマンステスト
- APIレスポンス時間の測定
- データベースクエリパフォーマンスの確認
- メモリ使用量の監視

## 運用・保守

### 1. ログ監視
```bash
# エラーログの確認
tail -f /home/users/0/your-account/private/logs/php_errors.log
tail -f /home/users/0/your-account/private/logs/api.log
```

### 2. バックアップ戦略
- データベースの定期バックアップ（ロリポップ自動バックアップ + 手動エクスポート）
- アプリケーションファイルのバックアップ
- 設定ファイルのセキュアなバックアップ

### 3. アップデート手順
1. 開発環境での動作確認
2. ステージング環境でのテスト（可能であれば）
3. データベースバックアップ
4. メンテナンスモード有効化
5. ファイルの差分アップロード
6. データベースマイグレーション（必要な場合）
7. 動作確認
8. メンテナンスモード解除

### 4. 監視項目
- API可用性（ヘルスチェック）
- レスポンス時間
- エラー率
- データベース接続状況
- ディスク使用量

## トラブルシューティング

### よくある問題と対処法

#### 1. 500 Internal Server Error
- PHP構文エラーの確認
- .htaccess設定の検証
- ファイル権限の確認
- PHPエラーログの確認

#### 2. データベース接続エラー
- 接続情報の再確認
- データベースサーバーの稼働状況確認
- ネットワーク接続の確認

#### 3. CORS エラー
- .htaccess のCORS設定確認
- ドメイン設定の確認
- HTTPSリダイレクトの確認

#### 4. JWT認証エラー
- 秘密鍵の設定確認
- トークンの有効期限確認
- Authorizationヘッダーの形式確認

## 連絡先・サポート

### 緊急時連絡先
- 開発チーム: [開発者メール]
- サーバー管理: ロリポップサポート

### 参考資料
- [ロリポップ ハイスピードプラン仕様](https://lolipop.jp/service/highspeed/)
- [PHP 8.2 ドキュメント](https://www.php.net/manual/ja/)
- [MySQL 8.0 リファレンス](https://dev.mysql.com/doc/refman/8.0/ja/)

---

**注意**: このデプロイガイドは本番環境への安全なデプロイを目的としています。実際のデプロイ前に、必ずテスト環境での検証を行ってください。