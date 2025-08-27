# ECサイト データベース仕様書（フロントエンド開発者向け）

**Version**: 2.0  
**Date**: 2025-08-27  
**Database**: ecommerce_dev_db (MySQL 8.0)  
**Target**: Frontend Engineers  
**Current Data**: 商品15件, カテゴリ9件, ユーザー14件, 注文31件

## 📚 目次
1. [概要](#overview)
2. [ユーザー管理システム](#user-system)
3. [商品管理システム](#product-system)
4. [カート・注文システム](#cart-order-system)
5. [レビュー・評価システム](#review-system)
6. [クーポン・割引システム](#coupon-system)
7. [注文履歴・追跡システム](#order-history-system)
8. [在庫管理システム](#inventory-system)
9. [管理者システム](#admin-system)
10. [API レスポンス形式](#api-response-format)
11. [実データサンプル](#data-samples)
12. [フロントエンド実装時の注意点](#frontend-notes)

---

## <a name="overview"></a>📋 1. 概要

このデータベースは、現代的なECサイトの機能を網羅するよう設計されています。
主要な機能として、**ユーザー管理**、**商品管理**、**カート・注文管理**、**レビューシステム**、**管理者機能**を提供します。

### 🔗 データベース接続情報
```
Host: mysql (Docker環境) / localhost (本番環境)
Database: ecommerce_dev_db
Port: 3306
Character Set: utf8mb4
```

---

## <a name="user-system"></a>👥 2. ユーザー管理システム

### 📊 users テーブル（ユーザー基本情報）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | ユーザーID（主キー） | ユーザー識別、API呼び出し |
| email | varchar(255) | ✅ | メールアドレス（重複不可） | ログイン、プロフィール表示 |
| password_hash | varchar(255) | ✅ | パスワードハッシュ | **フロントエンドでは使用しない** |
| first_name | varchar(100) | | 名前 | プロフィール、注文者名 |
| last_name | varchar(100) | | 姓 | プロフィール、注文者名 |
| phone | varchar(20) | | 電話番号 | 連絡先、注文確認 |
| is_active | tinyint(1) | | アクティブ状態（1=有効, 0=無効） | アカウント状態表示 |
| email_verified_at | timestamp | | メール認証日時 | 認証バッジ表示 |
| created_at | timestamp | | 登録日時 | メンバー歴表示 |
| updated_at | timestamp | | 更新日時 | 最終更新情報 |

**フロントエンド実装ポイント:**
- `first_name` + `last_name` を組み合わせてフルネーム表示
- `is_active = 0` のユーザーはログイン不可
- パスワードは常にハッシュ化されて保存（フロントエンドでの取得は不要）

### 📍 user_addresses テーブル（ユーザー住所情報）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 住所ID（主キー） | 住所選択、編集時の識別 |
| user_id | int | ✅ | ユーザーID（外部キー） | ユーザーとの紐付け |
| type | enum | | 住所タイプ（'shipping', 'billing'） | 配送先・請求先の区別 |
| first_name | varchar(100) | | 配送先名前 | 配送ラベル表示 |
| last_name | varchar(100) | | 配送先姓 | 配送ラベル表示 |
| company | varchar(255) | | 会社名 | 法人配送時の表示 |
| address_line_1 | varchar(255) | ✅ | 住所1（必須） | 配送先住所の主要部分 |
| address_line_2 | varchar(255) | | 住所2（建物名等） | 詳細住所 |
| city | varchar(100) | ✅ | 市区町村 | 住所表示 |
| state | varchar(100) | | 都道府県 | 住所表示 |
| postal_code | varchar(20) | ✅ | 郵便番号 | 郵便番号入力・表示 |
| country | varchar(2) | | 国コード（デフォルト: JP） | 国際配送対応時に使用 |
| is_default | tinyint(1) | | デフォルト住所フラグ | 初期選択住所の判定 |

**フロントエンド実装ポイント:**
- `is_default = 1` の住所を初期選択
- 住所タイプ別に配送先・請求先を区別して表示
- 住所編集時は全フィールドの検証が必要

---

## <a name="product-system"></a>🛍️ 3. 商品管理システム

### 🎁 products テーブル（商品基本情報）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 商品ID（主キー） | 商品ページのURL、カート追加 |
| name | varchar(255) | ✅ | 商品名 | 商品タイトル、検索表示 |
| description | text | | 詳細説明 | 商品詳細ページの説明文 |
| short_description | text | | 短い説明 | 商品一覧での概要表示 |
| price | decimal(10,2) | ✅ | 通常価格（円） | 価格表示 |
| sale_price | decimal(10,2) | | セール価格（円） | セール時の価格表示 |
| sku | varchar(100) | | 商品コード | 在庫管理、注文追跡 |
| stock_quantity | int | | 在庫数 | 在庫表示、購入可能数制限 |
| category_id | int | | カテゴリーID | カテゴリーフィルター |
| image_url | varchar(500) | | メイン画像URL | 商品画像表示 |
| is_active | tinyint(1) | | 公開状態（1=公開, 0=非公開） | 商品表示制御 |
| is_featured | tinyint(1) | | おすすめ商品フラグ | おすすめ商品セクション |
| is_new | tinyint(1) | | 新商品フラグ | NEW バッジ表示 |
| is_recommended | tinyint(1) | | 推奨商品フラグ | おすすめバッジ表示 |
| is_on_sale | tinyint(1) | | セール中フラグ | SALE バッジ表示 |
| is_limited | tinyint(1) | | 限定商品フラグ | LIMITED バッジ表示 |
| weight | decimal(8,2) | | 重量（kg） | 配送料計算 |
| dimensions | varchar(100) | | サイズ（縦x横x高さcm） | 商品詳細情報 |
| total_sales | decimal(12,2) | | 累計売上金額 | 人気度指標（管理者画面） |
| total_orders | int | | 累計注文数 | 人気度指標 |
| average_rating | decimal(3,2) | | 平均評価（1.00-5.00） | 星評価表示 |
| created_at | timestamp | | 登録日時 | 新商品ソート |
| updated_at | timestamp | | 更新日時 | 最終更新情報 |

**フロントエンド実装ポイント:**
- `sale_price` が設定されている場合は、セール価格を表示し、`price` に取り消し線
- `stock_quantity` が 0 の場合は「売り切れ」表示
- バッジ表示: `is_new`, `is_on_sale`, `is_limited` 等のフラグを使用
- 評価表示: `average_rating` を星（⭐）で視覚化

### 📂 categories テーブル（商品カテゴリー）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | カテゴリーID（主キー） | カテゴリーフィルター |
| name | varchar(255) | ✅ | カテゴリー名 | ナビゲーション、フィルター表示 |
| description | text | | カテゴリー説明 | カテゴリーページの説明 |
| parent_id | int | | 親カテゴリーID | 階層構造ナビゲーション |
| is_active | tinyint(1) | | 有効状態 | カテゴリー表示制御 |
| created_at | timestamp | | 作成日時 | |
| updated_at | timestamp | | 更新日時 | |

**フロントエンド実装ポイント:**
- `parent_id` を使用して階層構造のカテゴリーナビゲーションを構築
- `is_active = 0` のカテゴリーは非表示

### ⭐ product_reviews テーブル（商品レビュー）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | レビューID | レビュー識別 |
| product_id | int | ✅ | 商品ID | 商品別レビュー表示 |
| user_id | int | | ユーザーID | レビュー投稿者情報 |
| order_id | int | | 注文ID | 購入済み確認 |
| rating | tinyint | ✅ | 評価（1-5） | 星評価表示 |
| title | varchar(255) | | レビュータイトル | レビューヘッダー |
| review_text | text | | レビュー本文 | レビュー内容表示 |
| is_verified | tinyint(1) | | 購入済み確認フラグ | 「購入済み」バッジ |
| is_approved | tinyint(1) | | 承認済みフラグ | 表示制御 |
| helpful_count | int | | 「参考になった」カウント | 参考度表示 |
| created_at | timestamp | | 投稿日時 | レビュー投稿日表示 |

**フロントエンド実装ポイント:**
- `is_approved = 1` のレビューのみ表示
- `is_verified = 1` の場合、「購入済み」バッジを表示
- `rating` を星評価で視覚化

---

## <a name="cart-order-system"></a>🛒 4. カート・注文システム

### 🛍️ cart テーブル（ショッピングカート）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | カートID | カートアイテム識別 |
| session_id | varchar(255) | | セッションID | ゲストユーザーのカート |
| user_id | int | | ユーザーID | ログインユーザーのカート |
| product_id | int | ✅ | 商品ID | 商品情報の取得 |
| quantity | int | ✅ | 数量 | カート内数量表示・更新 |
| created_at | timestamp | | 追加日時 | カート履歴 |
| updated_at | timestamp | | 更新日時 | 最終更新時間 |

**フロントエンド実装ポイント:**
- ログインユーザー: `user_id` でカート管理
- ゲストユーザー: `session_id` でカート管理
- カート同期: ログイン時にゲストカートをユーザーカートにマージ

### 📦 orders テーブル（注文情報）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 注文ID | 注文詳細ページ |
| order_number | varchar(50) | | 注文番号 | 注文追跡表示 |
| user_id | int | | ユーザーID（登録ユーザー） | ユーザーの注文履歴 |
| session_id | varchar(255) | | セッションID | ログインユーザーのセッション |
| guest_session_id | varchar(255) | | ゲストセッションID | ゲスト注文追跡 |
| status | enum | | 注文状態 | 注文ステータス表示 |
| total_amount | decimal(10,2) | ✅ | 注文合計金額 | 金額表示 |
| currency | varchar(3) | | 通貨（デフォルト:JPY） | 通貨表示 |
| payment_status | enum | | 支払状態 | 支払ステータス表示 |
| payment_method | varchar(50) | | 支払方法 | 支払方法表示 |
| shipping_method | varchar(50) | | 配送方法 | 配送方法表示 |
| shipping_cost | decimal(8,2) | | 送料 | 送料表示 |
| shipping_address | json | | 配送先住所 | 配送先情報表示 |
| billing_address | json | | 請求先住所 | 請求先情報表示 |
| tax_amount | decimal(8,2) | | 消費税額 | 税額表示 |
| discount_amount | decimal(8,2) | | 割引額 | 割引情報表示 |
| coupon_code | varchar(50) | | クーポンコード | 使用クーポン表示 |
| coupon_discount | decimal(8,2) | | クーポン割引額 | クーポン割引表示 |
| notes | text | | 注文メモ | 配送指示等の表示 |
| shipped_at | timestamp | | 発送日時 | 発送通知表示 |
| delivered_at | timestamp | | 配達完了日時 | 配達完了通知 |
| estimated_delivery | date | | 配達予定日 | 配達予定表示 |
| created_at | timestamp | | 注文日時 | 注文日表示 |

**注文ステータス (status):**
- `pending`: 注文確認中
- `processing`: 処理中
- `shipped`: 発送済み
- `delivered`: 配達完了
- `cancelled`: キャンセル

**支払ステータス (payment_status):**
- `pending`: 支払い待ち
- `paid`: 支払い完了
- `failed`: 支払い失敗
- `refunded`: 返金済み

**フロントエンド実装ポイント:**
- JSON形式の住所データをパース必要
- ステータス別の表示色・アイコンを設定
- 配送追跡: `status` の進捗バーを表示

### 🛍️ order_items テーブル（注文商品詳細）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 注文商品ID | 明細識別 |
| order_id | int | ✅ | 注文ID | 注文との紐付け |
| product_id | int | ✅ | 商品ID | 商品情報リンク |
| quantity | int | ✅ | 数量 | 購入数量表示 |
| unit_price | decimal(10,2) | ✅ | 単価 | 購入時価格表示 |
| total_price | decimal(10,2) | ✅ | 小計 | 行合計表示 |
| discount_amount | decimal(8,2) | | 割引額 | 商品別割引表示 |
| final_price | decimal(10,2) | ✅ | 最終価格 | 割引後価格表示 |
| product_name | varchar(255) | ✅ | 商品名（注文時点） | 商品名表示（商品削除対応） |
| product_sku | varchar(100) | | 商品コード（注文時点） | SKU表示 |
| product_image_url | varchar(500) | | 商品画像URL（注文時点） | 商品画像表示 |

**フロントエンド実装ポイント:**
- 注文時点の商品情報を保持（商品削除・価格変更に対応）
- 現在の商品情報と比較して変更点を表示可能

---

## <a name="coupon-system"></a>💰 5. クーポン・割引システム

### 🎫 coupons テーブル（クーポン情報）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | クーポンID | クーポン識別 |
| code | varchar(50) | ✅ | クーポンコード | クーポン入力フィールド |
| name | varchar(255) | ✅ | クーポン名 | クーポン表示名 |
| description | text | | クーポン説明 | 利用条件説明 |
| type | enum | ✅ | 割引タイプ | 割引計算方式 |
| value | decimal(10,2) | ✅ | 割引値 | 割引率または割引額 |
| minimum_amount | decimal(10,2) | | 最小注文金額 | 利用条件表示 |
| maximum_discount | decimal(10,2) | | 最大割引額 | 割引上限表示 |
| usage_limit | int | | 使用回数制限 | 残り使用可能回数 |
| used_count | int | | 使用済み回数 | 使用状況表示 |
| is_active | tinyint(1) | | 有効状態 | クーポン利用可否 |
| starts_at | timestamp | | 開始日時 | 利用開始日表示 |
| expires_at | timestamp | | 終了日時 | 利用期限表示 |

**割引タイプ (type):**
- `percentage`: パーセント割引（例: 10% OFF）
- `fixed_amount`: 固定額割引（例: 500円 OFF）

**フロントエンド実装ポイント:**
- クーポン適用前に期限・利用条件をチェック
- `percentage`: `value`% の割引、`fixed_amount`: `value`円の割引
- 使用制限: `used_count` < `usage_limit` で利用可能

---

## <a name="order-history-system"></a>📊 6. 注文履歴・追跡システム

### 📈 order_status_history テーブル（注文ステータス履歴）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 履歴ID | 履歴識別 |
| order_id | int | ✅ | 注文ID | 注文との紐付け |
| previous_status | varchar(50) |  | 変更前ステータス | 変更履歴表示 |
| new_status | varchar(50) | ✅ | 変更後ステータス | 現在ステータス表示 |
| comment | text |  | コメント・理由 | 変更理由表示 |
| changed_by_user_id | int |  | 変更者（ユーザー）| 誰が変更したか |
| changed_by_admin_id | int |  | 変更者（管理者）| 管理者による変更 |
| created_at | timestamp | ✅ | 変更日時 | タイムライン表示 |

**フロントエンド実装ポイント:**
- 注文詳細ページでタイムライン表示
- ステータス変更の理由やコメントを表示
- 管理者変更 vs ユーザー変更の区別

**APIレスポンス例（注文追跡）:**
```json
{
  "order_id": 1,
  "current_status": "shipped",
  "tracking_timeline": [
    {
      "status": "pending",
      "timestamp": "2025-08-18T10:30:00Z",
      "comment": "注文を受け付けました"
    },
    {
      "status": "processing", 
      "timestamp": "2025-08-18T14:20:00Z",
      "comment": "商品の準備中です",
      "changed_by": "admin"
    },
    {
      "status": "shipped",
      "timestamp": "2025-08-19T09:15:00Z", 
      "comment": "商品を発送しました。追跡番号: ABC123456",
      "changed_by": "system"
    }
  ]
}
```

---

## <a name="inventory-system"></a>📦 7. 在庫管理システム

### 📊 inventory_movements テーブル（在庫移動履歴）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 移動履歴ID | 履歴識別 |
| product_id | int | ✅ | 商品ID | 商品との紐付け |
| type | enum | ✅ | 移動タイプ | 在庫変動種別表示 |
| quantity | int | ✅ | 移動数量 | 変動数表示 |
| previous_stock | int | ✅ | 変動前在庫 | 変動前の状態 |
| new_stock | int | ✅ | 変動後在庫 | 変動後の状態 |
| reason | varchar(255) |  | 変動理由 | 理由表示 |
| reference_type | varchar(50) |  | 参照タイプ | 関連情報の種別 |
| reference_id | int |  | 参照ID | 関連する注文・入荷ID |
| created_by | int |  | 実行者 | 誰が変更したか |
| created_at | timestamp | ✅ | 実行日時 | 変動日時表示 |

**在庫移動タイプ (type):**
- `in`: 入庫（仕入れ、返品受領等）
- `out`: 出庫（販売、不良品等）
- `adjustment`: 在庫調整

**参照タイプ (reference_type):**
- `order`: 注文による出庫
- `purchase`: 仕入による入庫  
- `return`: 返品による入庫
- `adjustment`: 手動調整

**フロントエンド実装ポイント:**
- 商品詳細ページで在庫推移グラフ表示
- 在庫切れアラートの履歴表示
- 管理画面での在庫変動レポート

**APIレスポンス例（在庫履歴）:**
```json
{
  "product_id": 1,
  "current_stock": 42,
  "stock_movements": [
    {
      "type": "in",
      "quantity": 50,
      "previous_stock": 0,
      "new_stock": 50,
      "reason": "初回入荷",
      "reference_type": "purchase",
      "created_at": "2025-08-18T09:00:00Z"
    },
    {
      "type": "out",
      "quantity": -8,
      "previous_stock": 50,
      "new_stock": 42,
      "reason": "注文による販売",
      "reference_type": "order",
      "reference_id": 25,
      "created_at": "2025-08-25T14:30:00Z"
    }
  ]
}
```

---

## <a name="admin-system"></a>👨‍💼 8. 管理者システム

### 🔐 admins テーブル（管理者情報）

| フィールド | 型 | 必須 | 説明 | フロントエンド使用例 |
|-----------|---|-----|-----|-------------------|
| id | int | ✅ | 管理者ID | 管理者識別 |
| email | varchar(255) | ✅ | 管理者メールアドレス | ログイン、表示 |
| password_hash | varchar(255) | ✅ | パスワードハッシュ | **使用しない** |
| name | varchar(255) | ✅ | 管理者名 | 管理画面での名前表示 |
| role | enum | | 権限レベル | 機能制限 |
| is_active | tinyint(1) | | 有効状態 | ログイン可否 |
| last_login_at | timestamp | | 最終ログイン | ログイン履歴 |

**管理者権限 (role):**
- `super_admin`: 全権限
- `admin`: 一般管理者権限
- `moderator`: 限定的な権限

---

## <a name="api-response-format"></a>📡 9. API レスポンス形式

### 🔄 標準APIレスポンス形式
```json
{
  "success": true,
  "data": {},
  "message": "Success message",
  "errors": [],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  }
}
```

### 📊 主要エンドポイントのレスポンス例

#### GET /api/products（商品一覧）
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "商品名",
      "short_description": "短い説明",
      "price": 2500.00,
      "sale_price": 2000.00,
      "image_url": "https://example.com/image.jpg",
      "is_new": true,
      "is_on_sale": true,
      "average_rating": 4.5,
      "stock_quantity": 10,
      "category": {
        "id": 1,
        "name": "カテゴリー名"
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

#### GET /api/cart（カート内容）
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 5,
        "quantity": 2,
        "product": {
          "id": 5,
          "name": "商品名",
          "price": 2500.00,
          "sale_price": 2000.00,
          "image_url": "https://example.com/image.jpg",
          "stock_quantity": 10
        },
        "subtotal": 4000.00
      }
    ],
    "total_items": 3,
    "total_quantity": 5,
    "subtotal": 12000.00,
    "tax_amount": 1200.00,
    "total_amount": 13200.00
  }
}
```

#### GET /api/orders（注文履歴）
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "order_number": "EC-2025-001",
      "status": "delivered",
      "payment_status": "paid",
      "total_amount": 15000.00,
      "created_at": "2025-08-15T10:30:00Z",
      "shipped_at": "2025-08-16T14:20:00Z",
      "delivered_at": "2025-08-18T11:45:00Z",
      "items_count": 3,
      "shipping_address": {
        "first_name": "田中",
        "last_name": "太郎",
        "address_line_1": "東京都渋谷区1-2-3",
        "city": "渋谷区",
        "postal_code": "150-0001"
      }
    }
  ]
}
```

---

## <a name="data-samples"></a>📊 10. 実データサンプル

このセクションでは、現在のデータベースに保存されている実際のデータを基に、フロントエンドで扱うAPIレスポンスのサンプルを提供します。

### 🛍️ 実際の商品データサンプル

#### iPhone 15 Pro (ID: 1)
```json
{
  "id": 1,
  "name": "iPhone 15 Pro",
  "description": "最新のiPhone 15 Pro。高性能なA17 Proチップ搭載。",
  "short_description": "Apple iPhone 15 Pro 128GB",
  "price": 159800,
  "sale_price": 149800,
  "discount_percentage": 6.3,
  "sku": "IPH15P-128",
  "stock_quantity": 42,
  "category_id": 1,
  "category_name": "エレクトロニクス",
  "image_url": null,
  "badges": {
    "is_new": true,
    "is_recommended": true,
    "is_on_sale": true,
    "is_featured": true,
    "is_limited": false
  },
  "stats": {
    "average_rating": 4.8,
    "total_sales": 3195200,
    "total_orders": 25,
    "stock_status": "in_stock"
  },
  "created_at": "2025-08-18T06:30:32Z",
  "updated_at": "2025-08-25T04:32:51Z"
}
```

#### MacBook Air M2 (ID: 2)
```json
{
  "id": 2,
  "name": "MacBook Air M2",
  "description": "薄くて軽い、驚異的なパフォーマンスのMacBook Air。",
  "short_description": "Apple MacBook Air 13インチ M2チップ",
  "price": 164800,
  "sale_price": null,
  "discount_percentage": 0,
  "sku": "MBA-M2-256",
  "stock_quantity": 29,
  "category_id": 1,
  "category_name": "エレクトロニクス",
  "image_url": null,
  "badges": {
    "is_new": true,
    "is_recommended": false,
    "is_on_sale": false,
    "is_featured": true,
    "is_limited": false
  },
  "stats": {
    "average_rating": 4.6,
    "total_sales": 164800,
    "total_orders": 15,
    "stock_status": "in_stock"
  }
}
```

### 📂 実際のカテゴリデータ

```json
[
  {
    "id": 1,
    "name": "エレクトロニクス",
    "description": "スマートフォン、PC、家電製品など",
    "parent_id": null,
    "is_active": true,
    "product_count": 5,
    "children": []
  },
  {
    "id": 2,
    "name": "ファッション",
    "description": "衣服、アクセサリー、バッグなど",
    "parent_id": null,
    "is_active": true,
    "product_count": 3,
    "children": []
  },
  {
    "id": 3,
    "name": "ホーム&キッチン",
    "description": "家具、キッチン用品、インテリア",
    "parent_id": null,
    "is_active": true,
    "product_count": 2,
    "children": []
  }
]
```

### 👤 実際のユーザーデータサンプル

```json
{
  "id": 1,
  "email": "test@example.com",
  "name": "テスト ユーザー",
  "first_name": "テスト",
  "last_name": "ユーザー",
  "phone": null,
  "is_active": true,
  "email_verified": true,
  "member_since": "2025-08-18T06:30:32Z",
  "last_login": "2025-08-27T10:15:00Z",
  "order_count": 5,
  "total_spent": 425000
}
```

### 📦 実際の注文データサンプル

#### 最新注文の例
```json
{
  "id": 31,
  "order_number": "ORD-20250827-031",
  "status": "delivered",
  "status_label": "配達完了",
  "payment_status": "paid",
  "payment_status_label": "支払済み",
  "total_amount": 149800,
  "currency": "JPY",
  "payment_method": "credit_card",
  "shipping_method": "standard",
  "shipping_cost": 500,
  "tax_amount": 13618,
  "items": [
    {
      "product_id": 1,
      "product_name": "iPhone 15 Pro",
      "product_sku": "IPH15P-128",
      "quantity": 1,
      "unit_price": 149800,
      "total_price": 149800,
      "product_image": null
    }
  ],
  "shipping_address": {
    "name": "山田 太郎",
    "postal_code": "150-0001",
    "address": "東京都渋谷区神宮前1-1-1",
    "phone": "03-1234-5678"
  },
  "timeline": [
    {
      "status": "pending",
      "timestamp": "2025-08-25T10:30:00Z",
      "comment": "注文を承りました"
    },
    {
      "status": "processing",
      "timestamp": "2025-08-25T14:20:00Z",
      "comment": "商品を準備中です"
    },
    {
      "status": "shipped",
      "timestamp": "2025-08-26T09:15:00Z",
      "comment": "商品を発送いたしました"
    },
    {
      "status": "delivered",
      "timestamp": "2025-08-27T11:45:00Z",
      "comment": "商品をお届けしました"
    }
  ],
  "created_at": "2025-08-25T10:30:00Z",
  "updated_at": "2025-08-27T11:45:00Z"
}
```

### 📊 データベース統計（2025年8月27日時点）

```json
{
  "database_stats": {
    "total_products": 15,
    "active_products": 15,
    "total_categories": 9,
    "total_users": 14,
    "active_users": 13,
    "total_orders": 31,
    "completed_orders": 28,
    "pending_orders": 2,
    "cancelled_orders": 1,
    "total_revenue": 4850000,
    "average_order_value": 156451
  },
  "top_products": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "total_orders": 25,
      "total_revenue": 3195200
    },
    {
      "id": 2,
      "name": "MacBook Air M2", 
      "total_orders": 15,
      "total_revenue": 164800
    }
  ],
  "category_distribution": [
    {"name": "エレクトロニクス", "product_count": 5, "revenue": 4200000},
    {"name": "ファッション", "product_count": 3, "revenue": 450000},
    {"name": "ホーム&キッチン", "product_count": 2, "revenue": 200000}
  ]
}
```

---

## <a name="frontend-notes"></a>⚠️ 12. フロントエンド実装時の注意点

### 🔐 セキュリティ注意事項
1. **パスワードハッシュ**: `password_hash` フィールドはフロントエンドで使用・表示しない
2. **JWTトークン**: localStorage ではなく httpOnly Cookie での保存を推奨
3. **XSS対策**: ユーザー入力内容（レビューテキストなど）は適切にエスケープ

### 💾 データ型と精度
1. **金額**: `decimal(10,2)` 形式 → JavaScript では文字列として受け取り、Number()で変換
2. **日時**: ISO 8601 形式 (`YYYY-MM-DDTHH:mm:ssZ`) で返却
3. **Boolean**: `tinyint(1)` → `0` または `1` で返却、Boolean に変換必要

### 🔄 状態管理のポイント
1. **カート同期**: ログイン時にゲストカートとユーザーカートのマージ処理
2. **在庫表示**: リアルタイム在庫情報の取得とUI更新
3. **注文ステータス**: WebSocket やポーリングでのリアルタイム更新
4. **住所管理**: デフォルト住所の自動選択とユーザビリティ向上

### 📱 レスポンシブデザイン対応
1. **商品画像**: `image_url` に加えて、サムネイル用の小さい画像も提供予定
2. **テーブルビュー**: 注文履歴や住所管理はモバイルで見やすいカードビューに変更
3. **フィルター**: カテゴリーや価格帯フィルターのモバイルUI最適化

### 🔍 検索・フィルタリング機能
1. **商品検索**: `name`、`description`、`short_description` を対象とした全文検索
2. **カテゴリーフィルター**: 階層構造に対応したカテゴリー選択
3. **価格帯フィルター**: `price` または `sale_price` での範囲検索
4. **ソート機能**: 価格、評価、新着、人気度でのソート対応

### 🎨 UI/UX 推奨事項
1. **ローディング状態**: API呼び出し中のスケルトンUI表示
2. **エラーハンドリング**: ユーザーフレンドリーなエラーメッセージ表示
3. **バッジ表示**: NEW、SALE、LIMITED等の視覚的なバッジでユーザビリティ向上
4. **評価表示**: `average_rating` を星評価（⭐⭐⭐⭐☆）で直感的に表示

---

## 📞 サポート・連絡先

**バックエンド開発者**: Backend Team  
**API仕様書**: `/docs/API_SPECIFICATION.md`  
**環境セットアップ**: `/docs/DEVELOPMENT_SETUP.md`

### 🔗 重要なAPI エンドポイント一覧

#### 認証・ユーザー管理
- `POST /api/auth/register` - ユーザー登録
- `POST /api/auth/login` - ログイン
- `GET /api/auth/profile` - プロフィール取得
- `PUT /api/auth/profile` - プロフィール更新
- `POST /api/auth/logout` - ログアウト

#### 商品・カテゴリ
- `GET /api/products` - 商品一覧（ページング、フィルター対応）
- `GET /api/products/{id}` - 商品詳細
- `GET /api/categories` - カテゴリ一覧
- `GET /api/products/featured` - おすすめ商品
- `GET /api/products/search` - 商品検索

#### カート・注文
- `GET /api/cart` - カート内容取得
- `POST /api/cart/add` - カートに追加
- `PUT /api/cart/{id}` - カート数量更新
- `DELETE /api/cart/{id}` - カートから削除
- `POST /api/orders` - 注文作成
- `GET /api/orders` - 注文履歴
- `GET /api/orders/{id}` - 注文詳細
- `GET /api/orders/{id}/tracking` - 注文追跡

#### その他
- `POST /api/coupons/validate` - クーポン検証
- `GET /api/health` - ヘルスチェック

### 📱 モバイル対応のポイント

1. **レスポンシブ画像**
   - 商品画像は複数サイズを用意予定
   - `image_url` に加え、`thumbnail_url`, `large_url` を追加予定

2. **タッチ操作最適化**
   - カート数量変更はタッチフレンドリーなUI
   - 長いリスト（商品一覧、注文履歴）は無限スクロール対応

3. **オフライン対応**
   - カートの内容はローカルストレージに保存
   - ネットワーク復旧時に自動同期

### 🚀 パフォーマンス最適化

1. **データローディング戦略**
   - 商品一覧: 初回20件、スクロールで追加ロード
   - 商品詳細: 基本情報を先読み、レビューは遅延ロード
   - カート: リアルタイム同期（WebSocketまたはポーリング）

2. **キャッシュ戦略**
   - 商品情報: 5分間キャッシュ
   - カテゴリ: 1時間キャッシュ
   - ユーザーメタデータ: セッション間キャッシュ

### 🎯 今後の拡張予定

1. **近日追加予定の機能**
   - 商品レビューシステム（現在テーブルのみ作成済み）
   - お気に入り・ウィッシュリスト機能
   - 商品画像の複数枚対応
   - リアルタイム在庫更新通知

2. **将来的な拡張**
   - 多言語対応（商品名・説明の国際化）
   - 決済システム統合（Stripe等）
   - 配送業者API連携
   - AIベースの商品推奨システム

---

## 📞 サポート・連絡先

**バックエンド開発者**: Backend Team  
**API仕様書**: `/docs/API_SPECIFICATION.md`  
**管理API仕様書**: `/docs/ADMIN_API_SPECIFICATION.md`  
**フロントエンド統合ガイド**: `/docs/FRONTEND_INTEGRATION_GUIDE.md`  
**開発環境セットアップ**: `README.md`

### 🆘 トラブルシューティング

#### よくある問題と解決策

1. **CORS エラー**
   - 開発環境: `http://localhost:3000` は許可済み
   - 本番環境: 適切なOriginヘッダーを確認

2. **認証エラー**
   - JWT トークンの有効期限を確認（デフォルト1時間）
   - Authorization ヘッダーの形式: `Bearer {token}`

3. **データ型エラー**
   - 金額: `decimal(10,2)` → 数値として処理
   - 日時: ISO 8601 形式 → Date オブジェクトに変換
   - Boolean: `0/1` → `true/false` に変換

4. **在庫関連エラー**
   - 在庫数をリアルタイム確認
   - カート追加時に在庫チェック必須

---

**💡 このドキュメントは開発進行に合わせて更新されます。**  
**最新版は常に `/docs/DATABASE_SPECIFICATION_FOR_FRONTEND.md` を参照してください。**

**🗓️ 最終更新**: 2025年8月27日  
**📊 データベース**: 15商品、9カテゴリ、14ユーザー、31注文を保有