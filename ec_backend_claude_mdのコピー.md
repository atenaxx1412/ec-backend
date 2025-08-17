# ECサイトバックエンド開発環境 - CLAUDE.md

## 📋 プロジェクト概要

**環境**: 開発環境 (Docker Desktop)  
**目的**: ECサイトのバックエンドAPI開発・テスト  
**技術スタック**: Docker + Apache + PHP 8.2 + MySQL 8.0  
**開発方針**: 本番環境（ロリポップ ハイスピードプラン）との互換性を保持

## 🏗️ 開発環境アーキテクチャ

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  Frontend       │    │  Backend API    │    │  Database       │
│  Next.js        │◄───┤  Apache + PHP   │◄───┤  MySQL 8.0      │
│  localhost:3000 │    │  localhost:8080 │    │  localhost:3306 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                ▲
                       ┌─────────────────┐
                       │  phpMyAdmin     │
                       │  localhost:8081 │
                       └─────────────────┘
```

## 🐳 Docker開発環境構築

### docker-compose.yml
```yaml
version: '3.8'
services:
  # バックエンドAPI (Apache + PHP 8.2)
  api:
    build: .
    container_name: ec_api_dev
    ports:
      - "8080:80"
    volumes:
      - ./backend:/var/www/html
      - ./logs:/var/log/apache2
      - ./config/php/php.ini:/usr/local/etc/php/php.ini
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html/public
      - PHP_ENV=development
      - PHP_MEMORY_LIMIT=512M
      - PHP_MAX_EXECUTION_TIME=300
      - PHP_DISPLAY_ERRORS=On
      - PHP_ERROR_REPORTING=E_ALL
    depends_on:
      - mysql
    networks:
      - ec_dev_network

  # MySQL 8.0 開発用データベース
  mysql:
    image: mysql:8.0
    container_name: ec_mysql_dev
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: dev_root_password
      MYSQL_DATABASE: ecommerce_dev_db
      MYSQL_USER: ec_dev_user
      MYSQL_PASSWORD: ec_dev_password
    volumes:
      - mysql_dev_data:/var/lib/mysql
      - ./database/init:/docker-entrypoint-initdb.d
      - ./database/dev-data:/docker-entrypoint-initdb.d/dev-data
    command: >
      --default-authentication-plugin=mysql_native_password
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-buffer-pool-size=256M
    networks:
      - ec_dev_network

  # phpMyAdmin (開発用GUI)
  phpmyadmin:
    image: phpmyadmin/phpmyadmin:latest
    container_name: ec_phpmyadmin_dev
    ports:
      - "8081:80"
    environment:
      PMA_HOST: mysql
      PMA_USER: root
      PMA_PASSWORD: dev_root_password
      PMA_ARBITRARY: 1
      UPLOAD_LIMIT: 64M
    depends_on:
      - mysql
    networks:
      - ec_dev_network

  # Redis (キャッシュ・セッション管理)
  redis:
    image: redis:7-alpine
    container_name: ec_redis_dev
    ports:
      - "6379:6379"
    volumes:
      - redis_dev_data:/data
    command: redis-server --appendonly yes
    networks:
      - ec_dev_network

  # Mailpit (開発用メールサーバー)
  mailpit:
    image: axllent/mailpit:latest
    container_name: ec_mailpit_dev
    ports:
      - "1025:1025"  # SMTP
      - "8025:8025"  # Web UI
    networks:
      - ec_dev_network

volumes:
  mysql_dev_data:
    driver: local
  redis_dev_data:
    driver: local

networks:
  ec_dev_network:
    driver: bridge
```

### Dockerfile (開発環境用)
```dockerfile
FROM php:8.2-apache

# 開発用パッケージとPHP拡張をインストール
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libicu-dev \
    vim \
    nano \
    htop \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    && pecl install redis xdebug \
    && docker-php-ext-enable redis xdebug

# Apache設定 (開発環境用)
RUN a2enmod rewrite headers ssl
COPY ./config/apache/dev-000-default.conf /etc/apache2/sites-available/000-default.conf

# Composer インストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 開発用CORS設定 (緩い設定)
RUN echo 'Header always set Access-Control-Allow-Origin "*"' >> /etc/apache2/conf-available/cors-dev.conf && \
    echo 'Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH"' >> /etc/apache2/conf-available/cors-dev.conf && \
    echo 'Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With, Accept, Origin"' >> /etc/apache2/conf-available/cors-dev.conf && \
    echo 'Header always set Access-Control-Allow-Credentials "true"' >> /etc/apache2/conf-available/cors-dev.conf && \
    echo 'Header always set Access-Control-Max-Age "86400"' >> /etc/apache2/conf-available/cors-dev.conf && \
    a2enconf cors-dev

# PHP開発設定
RUN echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'post_max_size = 64M' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'display_errors = On' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'error_reporting = E_ALL' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'log_errors = On' >> /usr/local/etc/php/conf.d/dev-php-config.ini && \
    echo 'error_log = /var/log/apache2/php_errors.log' >> /usr/local/etc/php/conf.d/dev-php-config.ini

# Xdebug設定 (開発用デバッグ)
RUN echo 'xdebug.mode=develop,debug' >> /usr/local/etc/php/conf.d/dev-xdebug.ini && \
    echo 'xdebug.client_host=host.docker.internal' >> /usr/local/etc/php/conf.d/dev-xdebug.ini && \
    echo 'xdebug.client_port=9003' >> /usr/local/etc/php/conf.d/dev-xdebug.ini && \
    echo 'xdebug.start_with_request=yes' >> /usr/local/etc/php/conf.d/dev-xdebug.ini

WORKDIR /var/www/html

# 開発用権限設定 (緩い設定)
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 777 /var/www/html

EXPOSE 80
```

## 📁 開発環境プロジェクト構造

```
ec-backend-dev/
├── backend/                    # バックエンドソースコード
│   ├── public/                # ドキュメントルート
│   │   ├── index.php         # 開発用エントリーポイント
│   │   ├── .htaccess         # 開発用Apache設定
│   │   └── uploads/          # 開発用アップロード
│   ├── src/
│   │   ├── Config/
│   │   │   ├── Database.php  # 開発DB接続設定
│   │   │   └── DevConfig.php # 開発環境設定
│   │   ├── Controllers/      # コントローラー
│   │   ├── Models/          # データモデル
│   │   ├── Middleware/      # ミドルウェア
│   │   ├── Utils/           # ユーティリティ
│   │   └── Routes/          # ルーティング
│   ├── tests/               # テストコード
│   │   ├── Unit/           # ユニットテスト
│   │   ├── Integration/    # 統合テスト
│   │   └── Api/           # APIテスト
│   └── vendor/            # Composer依存関係
├── config/
│   ├── apache/
│   │   └── dev-000-default.conf  # 開発用Apache設定
│   ├── php/
│   │   └── php.ini              # 開発用PHP設定
│   └── environment/
│       └── .env.development     # 開発環境変数
├── database/
│   ├── init/                    # 初期テーブル作成
│   │   ├── 01_create_tables.sql
│   │   └── 02_seed_data.sql
│   ├── dev-data/               # 開発用テストデータ
│   │   └── test_products.sql
│   └── migrations/             # マイグレーション
├── logs/                       # 開発用ログ
│   ├── apache2/
│   ├── php/
│   └── application/
├── tools/                      # 開発ツール
│   ├── dev-setup.sh           # 開発環境セットアップ
│   ├── test-runner.sh         # テスト実行
│   └── db-reset.sh           # DB初期化
├── docker-compose.yml         # 開発環境Docker設定
├── docker-compose.override.yml # ローカル設定上書き
├── Dockerfile                 # 開発環境用Docker
├── .env.development          # 開発環境変数
├── composer.json             # PHP依存関係 (dev含む)
└── README.md                 # 開発環境セットアップガイド
```

## 🔧 開発環境セットアップ

### 1. 初回セットアップ
```bash
# リポジトリクローン
git clone <repository-url> ec-backend-dev
cd ec-backend-dev

# 環境変数設定
cp .env.development.example .env.development
# 必要に応じて設定を調整

# Docker環境構築
docker-compose up -d

# 依存関係インストール (開発用含む)
docker-compose exec api composer install

# データベース初期化
docker-compose exec api php tools/db-setup.php

# 開発用テストデータ投入
docker-compose exec api php tools/seed-test-data.php
```

### 2. 日常的な開発フロー
```bash
# 開発環境起動
docker-compose up -d

# ログ確認
docker-compose logs -f api

# コンテナ内でのコマンド実行
docker-compose exec api bash

# テスト実行
docker-compose exec api ./vendor/bin/phpunit

# 開発環境停止
docker-compose down
```

## 🔌 開発用API設定

### 開発環境用設定
```php
// config/DevConfig.php
<?php
class DevConfig {
    const DB_HOST = 'mysql';
    const DB_NAME = 'ecommerce_dev_db';
    const DB_USER = 'ec_dev_user';
    const DB_PASS = 'ec_dev_password';
    
    const REDIS_HOST = 'redis';
    const REDIS_PORT = 6379;
    
    const MAIL_HOST = 'mailpit';
    const MAIL_PORT = 1025;
    
    const DEBUG_MODE = true;
    const LOG_LEVEL = 'DEBUG';
    
    const CORS_ORIGINS = ['http://localhost:3000', 'http://localhost:3001'];
}
```

### 開発用エンドポイント (デバッグ用)
```
# 開発専用エンドポイント
GET    /api/dev/info              # システム情報
GET    /api/dev/phpinfo           # PHP情報
GET    /api/dev/db-status         # DB接続状況
GET    /api/dev/logs              # エラーログ
POST   /api/dev/reset-db          # DB初期化
POST   /api/dev/seed-data         # テストデータ投入
GET    /api/dev/test-email        # メールテスト
```

## 🧪 開発環境テスト

### テスト環境
```bash
# 全テスト実行
docker-compose exec api ./vendor/bin/phpunit

# 特定テスト実行
docker-compose exec api ./vendor/bin/phpunit tests/Unit/ProductTest.php

# カバレッジ付きテスト
docker-compose exec api ./vendor/bin/phpunit --coverage-html coverage/

# APIテスト (Postman/Insomnia用)
curl -X GET http://localhost:8080/api/v1/products
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"dev@example.com","password":"password123"}'
```

### 開発用チェックリスト
- [ ] Docker環境正常起動
- [ ] データベース接続確認
- [ ] phpMyAdmin接続確認 (http://localhost:8081)
- [ ] API基本動作確認
- [ ] CORS設定確認
- [ ] エラーログ出力確認
- [ ] Xdebugデバッグ確認
- [ ] メール送信確認 (http://localhost:8025)

## 🐛 開発環境トラブルシューティング

### よくある問題と解決策

1. **Docker起動エラー**
   ```bash
   # ポート競合確認
   netstat -tlnp | grep :8080
   # 既存コンテナ削除
   docker-compose down -v
   docker system prune -f
   ```

2. **データベース接続エラー**
   ```bash
   # MySQL接続確認
   docker-compose exec mysql mysql -u ec_dev_user -p
   # ログ確認
   docker-compose logs mysql
   ```

3. **PHP エラー**
   ```bash
   # PHPログ確認
   docker-compose exec api tail -f /var/log/apache2/php_errors.log
   # Apache設定確認
   docker-compose exec api apache2ctl configtest
   ```

4. **権限エラー**
   ```bash
   # 権限修正
   docker-compose exec api chown -R www-data:www-data /var/www/html
   docker-compose exec api chmod -R 777 /var/www/html/public/uploads
   ```

## 📊 開発用ツール

### デバッグツール
- **Xdebug**: ステップ実行デバッグ
- **phpMyAdmin**: データベース管理 (http://localhost:8081)
- **Mailpit**: メール確認 (http://localhost:8025)
- **Redis CLI**: キャッシュ確認

### ログ管理
```bash
# リアルタイムログ監視
docker-compose logs -f api

# エラーログのみ表示
docker-compose exec api tail -f /var/log/apache2/php_errors.log

# アプリケーションログ
docker-compose exec api tail -f /var/www/html/logs/application.log
```

## 🔄 開発環境の更新・メンテナンス

### 定期メンテナンス
```bash
# Dockerイメージ更新
docker-compose pull
docker-compose up -d --build

# Composer依存関係更新
docker-compose exec api composer update

# データベーススキーマ更新
docker-compose exec api php database/migrate.php

# ログローテーション
docker-compose exec api logrotate /etc/logrotate.d/apache2
```

---

**環境**: 開発環境 (Docker Desktop)  
**最終更新**: 2025-08-18  
**次のステップ**: [本番環境CLAUDE.md] を参照してデプロイ準備