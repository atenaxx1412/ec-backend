# 🔐 管理者API仕様書 - フロントエンド実装ガイド

## 📋 概要

ECサイトバックエンドAPIの管理者機能が完全実装されました！このドキュメントは、フロントエンド開発者（Next.js + TypeScript）向けの管理者API実装ガイドです。

## 🎯 実装完了済み機能

### ✅ 管理者向けAPI一覧
- **管理者認証**: JWT認証（ログイン・認証状態確認）
- **商品管理**: 一覧・詳細・作成・更新・削除・一括操作
- **画像アップロード**: 単一商品画像アップロード機能
- **ダッシュボード**: 管理者用統計情報
- **高度なフィルタリング**: 在庫状況・カテゴリー・検索等

### 🔑 認証情報
```
管理者アカウント: admin@ec-site-dev.local
パスワード: admin123
API Base URL: http://localhost:8080/api
```

## 🚀 認証フロー

### 1. 管理者ログイン

**Endpoint:** `POST /api/admin/login`

**Request:**
```json
{
  "email": "admin@ec-site-dev.local",
  "password": "admin123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Admin login successful",
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
      "role": "super_admin",
      "last_login_at": "2025-08-25 22:16:10",
      "created_at": "2025-08-18 06:30:32"
    }
  }
}
```

### 2. 管理者ダッシュボード

**Endpoint:** `GET /api/admin/dashboard`  
**認証:** Bearer Token必須

**Response:**
```json
{
  "success": true,
  "message": "Dashboard data retrieved successfully",
  "data": {
    "stats": {
      "total_products": 15,
      "active_products": 15,
      "total_orders": 50,
      "pending_orders": 5,
      "total_sales": 1250000.50,
      "low_stock_products": 2
    },
    "recent_orders": [...],
    "top_products": [...],
    "recent_activities": [...]
  }
}
```

## 🛍️ 商品管理API

### 1. 商品一覧取得（管理者用）

**Endpoint:** `GET /api/admin/products`  
**認証:** Bearer Token必須

**クエリパラメータ:**
```typescript
interface AdminProductsParams {
  page?: number;          // ページ番号 (デフォルト: 1)
  limit?: number;         // 1ページあたりの件数 (デフォルト: 20, 最大: 100)
  status?: 'active' | 'inactive';  // 商品ステータス
  category_id?: number;   // カテゴリーID
  low_stock?: boolean;    // 在庫少フィルター (10個未満)
  search?: string;        // 商品名・SKU・説明での検索
}
```

**使用例:**
```bash
GET /api/admin/products?page=1&limit=10&status=active&low_stock=true&search=iPhone
```

**Response:**
```json
{
  "success": true,
  "message": "Products retrieved successfully",
  "data": {
    "products": [
      {
        "id": 1,
        "name": "iPhone 15 Pro",
        "slug": "iphone-15-pro-1",
        "description": "最新のiPhone 15 Pro。高性能なA17 Proチップ搭載。",
        "short_description": "Apple iPhone 15 Pro 128GB",
        "price": 159800,
        "sale_price": 149800,
        "stock_quantity": 42,
        "sku": "IPH15P-128",
        "image_url": null,
        "is_active": true,
        "is_featured": true,
        "is_new": true,
        "is_recommended": true,
        "is_on_sale": true,
        "is_limited": false,
        "weight": null,
        "dimensions": null,
        "category_id": 1,
        "category": {
          "id": 1,
          "name": "エレクトロニクス",
          "slug": null,
          "description": null,
          "image_url": null,
          "is_active": true,
          "product_count": 0,
          "created_at": null,
          "updated_at": null
        },
        "total_sales": 3195200,
        "total_orders": 25,
        "review_count": 13,
        "average_rating": 4.8,
        "created_at": "2025-08-18 06:30:32",
        "updated_at": "2025-08-25 04:32:51"
      }
    ],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 15,
      "total_pages": 1
    }
  }
}
```

### 2. 商品詳細取得（管理者用）

**Endpoint:** `GET /api/admin/products/{id}`  
**認証:** Bearer Token必須

**Response:** 商品一覧と同じデータ構造の単体商品情報

### 3. 商品作成

**Endpoint:** `POST /api/admin/products`  
**認証:** Bearer Token必須

**Request:**
```json
{
  "name": "新商品名",
  "description": "商品の詳細説明",
  "short_description": "商品の短い説明",
  "price": 10000,
  "sale_price": 8000,
  "stock_quantity": 50,
  "sku": "PROD-001",
  "category_id": 1,
  "image_url": "https://example.com/image.jpg",
  "weight": 0.5,
  "dimensions": "10x10x5",
  "is_active": true,
  "is_featured": false,
  "is_new": true,
  "is_recommended": false,
  "is_on_sale": true,
  "is_limited": false
}
```

**Response:**
```json
{
  "success": true,
  "message": "Product created successfully",
  "data": {
    "id": 16,
    // ... 作成された商品の全情報
  }
}
```

### 4. 商品更新

**Endpoint:** `PUT /api/admin/products/{id}`  
**認証:** Bearer Token必須

**Request:** 商品作成と同じ形式（部分更新対応）

### 5. 商品削除

**Endpoint:** `DELETE /api/admin/products/{id}`  
**認証:** Bearer Token必須

**Response:**
```json
{
  "success": true,
  "message": "Product deleted successfully",
  "data": null
}
```

### 6. 商品一括操作

**Endpoint:** `POST /api/admin/products/bulk-update`  
**認証:** Bearer Token必須

**Request:**
```json
{
  "action": "activate",  // activate, deactivate, delete, update_category, update_featured
  "product_ids": [1, 2, 3, 4],
  "data": {
    "category_id": 2,      // update_category時
    "is_featured": true    // update_featured時
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "Bulk operation completed successfully",
  "data": {
    "affected_products": 4,
    "action": "activate",
    "success_count": 4,
    "error_count": 0,
    "errors": []
  }
}
```

### 7. 商品画像アップロード

**Endpoint:** `POST /api/admin/products/upload-image`  
**認証:** Bearer Token必須  
**Content-Type:** `multipart/form-data`

**Request:** FormDataで画像ファイルを送信
```javascript
const formData = new FormData();
formData.append('image', imageFile);
```

**Response:**
```json
{
  "success": true,
  "message": "Image uploaded successfully",
  "data": {
    "filename": "product_image_1735084123_abc123.jpg",
    "original_name": "product.jpg",
    "url": "/uploads/product_image_1735084123_abc123.jpg",
    "full_url": "http://localhost:8080/uploads/product_image_1735084123_abc123.jpg",
    "size": 245760,
    "extension": "jpg",
    "uploaded_at": "2025-08-25 22:35:23"
  }
}
```

## 💻 TypeScript型定義

```typescript
// 管理者認証関連
export interface AdminLoginRequest {
  email: string;
  password: string;
}

export interface AdminTokens {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: 'super_admin' | 'admin' | 'moderator';
  last_login_at: string | null;
  created_at: string;
}

export interface AdminLoginResponse {
  tokens: AdminTokens;
  admin: AdminUser;
}

// 商品管理関連
export interface AdminProduct {
  id: number;
  name: string;
  slug: string;
  description: string;
  short_description: string | null;
  price: number;
  sale_price: number | null;
  stock_quantity: number;
  sku: string;
  image_url: string | null;
  is_active: boolean;
  is_featured: boolean;
  is_new: boolean;
  is_recommended: boolean;
  is_on_sale: boolean;
  is_limited: boolean;
  weight: number | null;
  dimensions: string | null;
  category_id: number;
  category: {
    id: number;
    name: string;
    slug: string | null;
    description: string | null;
    image_url: string | null;
    is_active: boolean;
    product_count: number;
    created_at: string | null;
    updated_at: string | null;
  };
  total_sales: number;
  total_orders: number;
  review_count: number;
  average_rating: number | null;
  created_at: string;
  updated_at: string;
}

export interface AdminProductsResponse {
  products: AdminProduct[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    total_pages: number;
  };
}

export interface CreateProductRequest {
  name: string;
  description: string;
  short_description?: string;
  price: number;
  sale_price?: number;
  stock_quantity: number;
  sku: string;
  category_id: number;
  image_url?: string;
  weight?: number;
  dimensions?: string;
  is_active?: boolean;
  is_featured?: boolean;
  is_new?: boolean;
  is_recommended?: boolean;
  is_on_sale?: boolean;
  is_limited?: boolean;
}

export interface BulkUpdateRequest {
  action: 'activate' | 'deactivate' | 'delete' | 'update_category' | 'update_featured';
  product_ids: number[];
  data?: {
    category_id?: number;
    is_featured?: boolean;
  };
}

export interface BulkUpdateResponse {
  affected_products: number;
  action: string;
  success_count: number;
  error_count: number;
  errors: string[];
}

export interface ImageUploadResponse {
  filename: string;
  original_name: string;
  url: string;
  full_url: string;
  size: number;
  extension: string;
  uploaded_at: string;
}

// ダッシュボード関連
export interface AdminDashboard {
  stats: {
    total_products: number;
    active_products: number;
    total_orders: number;
    pending_orders: number;
    total_sales: number;
    low_stock_products: number;
  };
  recent_orders: any[];
  top_products: any[];
  recent_activities: any[];
}
```

## 🔧 APIクライアント実装例

```typescript
// services/adminApi.ts
import { apiClient } from '@/lib/api';
import {
  AdminLoginRequest,
  AdminLoginResponse,
  AdminProduct,
  AdminProductsResponse,
  CreateProductRequest,
  BulkUpdateRequest,
  BulkUpdateResponse,
  ImageUploadResponse,
  AdminDashboard
} from '@/types/admin';

export const adminApi = {
  // 認証
  login: async (credentials: AdminLoginRequest) => {
    const response = await apiClient.post<ApiResponse<AdminLoginResponse>>(
      '/admin/login', 
      credentials
    );
    return response.data;
  },

  // ダッシュボード
  getDashboard: async () => {
    const response = await apiClient.get<ApiResponse<AdminDashboard>>('/admin/dashboard');
    return response.data;
  },

  // 商品管理
  getProducts: async (params?: {
    page?: number;
    limit?: number;
    status?: 'active' | 'inactive';
    category_id?: number;
    low_stock?: boolean;
    search?: string;
  }) => {
    const response = await apiClient.get<ApiResponse<AdminProductsResponse>>(
      '/admin/products',
      { params }
    );
    return response.data;
  },

  getProduct: async (id: number) => {
    const response = await apiClient.get<ApiResponse<AdminProduct>>(
      `/admin/products/${id}`
    );
    return response.data;
  },

  createProduct: async (data: CreateProductRequest) => {
    const response = await apiClient.post<ApiResponse<AdminProduct>>(
      '/admin/products',
      data
    );
    return response.data;
  },

  updateProduct: async (id: number, data: Partial<CreateProductRequest>) => {
    const response = await apiClient.put<ApiResponse<AdminProduct>>(
      `/admin/products/${id}`,
      data
    );
    return response.data;
  },

  deleteProduct: async (id: number) => {
    const response = await apiClient.delete<ApiResponse<null>>(
      `/admin/products/${id}`
    );
    return response.data;
  },

  // 一括操作
  bulkUpdateProducts: async (data: BulkUpdateRequest) => {
    const response = await apiClient.post<ApiResponse<BulkUpdateResponse>>(
      '/admin/products/bulk-update',
      data
    );
    return response.data;
  },

  // 画像アップロード
  uploadProductImage: async (imageFile: File) => {
    const formData = new FormData();
    formData.append('image', imageFile);
    
    const response = await apiClient.post<ApiResponse<ImageUploadResponse>>(
      '/admin/products/upload-image',
      formData,
      {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      }
    );
    return response.data;
  },
};
```

## 🎛️ React Hooks実装例

```typescript
// hooks/useAdminAuth.ts
import { useState, useEffect } from 'react';
import { adminApi } from '@/services/adminApi';
import { AdminUser } from '@/types/admin';

export const useAdminAuth = () => {
  const [admin, setAdmin] = useState<AdminUser | null>(null);
  const [loading, setLoading] = useState(true);

  const login = async (email: string, password: string) => {
    try {
      const response = await adminApi.login({ email, password });
      localStorage.setItem('admin_access_token', response.data.tokens.access_token);
      localStorage.setItem('admin_refresh_token', response.data.tokens.refresh_token);
      setAdmin(response.data.admin);
      return response;
    } catch (error) {
      throw error;
    }
  };

  const logout = () => {
    localStorage.removeItem('admin_access_token');
    localStorage.removeItem('admin_refresh_token');
    setAdmin(null);
  };

  const isAuthenticated = () => {
    return admin !== null && localStorage.getItem('admin_access_token') !== null;
  };

  return { admin, loading, login, logout, isAuthenticated };
};

// hooks/useAdminProducts.ts
import { useState, useEffect } from 'react';
import { adminApi } from '@/services/adminApi';
import { AdminProductsResponse } from '@/types/admin';

interface UseAdminProductsParams {
  page?: number;
  limit?: number;
  status?: 'active' | 'inactive';
  category_id?: number;
  low_stock?: boolean;
  search?: string;
}

export const useAdminProducts = (params: UseAdminProductsParams = {}) => {
  const [data, setData] = useState<AdminProductsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProducts = async () => {
    try {
      setLoading(true);
      const response = await adminApi.getProducts(params);
      setData(response.data);
      setError(null);
    } catch (err) {
      setError('商品の取得に失敗しました');
      console.error('Error fetching admin products:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, [JSON.stringify(params)]);

  return { data, loading, error, refetch: fetchProducts };
};
```

## 🎨 コンポーネント実装例

```tsx
// components/admin/ProductManagement.tsx
import React, { useState } from 'react';
import { useAdminProducts } from '@/hooks/useAdminProducts';
import { adminApi } from '@/services/adminApi';
import { AdminProduct } from '@/types/admin';

export const ProductManagement: React.FC = () => {
  const [filters, setFilters] = useState({
    search: '',
    status: 'active' as 'active' | 'inactive',
    category_id: undefined as number | undefined,
    low_stock: false,
  });
  
  const { data, loading, error, refetch } = useAdminProducts(filters);

  const handleBulkDelete = async (productIds: number[]) => {
    try {
      await adminApi.bulkUpdateProducts({
        action: 'delete',
        product_ids: productIds,
      });
      refetch();
    } catch (error) {
      console.error('Bulk delete failed:', error);
    }
  };

  const handleImageUpload = async (file: File, productId: number) => {
    try {
      const response = await adminApi.uploadProductImage(file);
      // 商品を更新
      await adminApi.updateProduct(productId, {
        image_url: response.data.full_url,
      });
      refetch();
    } catch (error) {
      console.error('Image upload failed:', error);
    }
  };

  if (loading) return <div>読み込み中...</div>;
  if (error) return <div>エラー: {error}</div>;
  if (!data) return null;

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold">商品管理</h1>
        
        {/* フィルター */}
        <div className="flex gap-4 mt-4">
          <input
            type="text"
            placeholder="商品名・SKUで検索"
            value={filters.search}
            onChange={(e) => setFilters({ ...filters, search: e.target.value })}
            className="border px-3 py-2 rounded"
          />
          
          <select
            value={filters.status}
            onChange={(e) => setFilters({ ...filters, status: e.target.value as 'active' | 'inactive' })}
            className="border px-3 py-2 rounded"
          >
            <option value="active">有効</option>
            <option value="inactive">無効</option>
          </select>
          
          <label className="flex items-center">
            <input
              type="checkbox"
              checked={filters.low_stock}
              onChange={(e) => setFilters({ ...filters, low_stock: e.target.checked })}
              className="mr-2"
            />
            在庫少のみ表示
          </label>
        </div>
      </div>

      {/* 商品一覧 */}
      <div className="grid gap-4">
        {data.products.map((product) => (
          <ProductCard 
            key={product.id} 
            product={product}
            onImageUpload={(file) => handleImageUpload(file, product.id)}
            onDelete={() => handleBulkDelete([product.id])}
          />
        ))}
      </div>

      {/* ページネーション */}
      <div className="mt-6 flex justify-center">
        <div className="flex gap-2">
          {Array.from({ length: data.pagination.total_pages }, (_, i) => (
            <button
              key={i + 1}
              onClick={() => setFilters({ ...filters, page: i + 1 })}
              className={`px-3 py-1 rounded ${
                data.pagination.page === i + 1 
                  ? 'bg-blue-600 text-white' 
                  : 'bg-gray-200'
              }`}
            >
              {i + 1}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
};

// components/admin/ProductCard.tsx
interface ProductCardProps {
  product: AdminProduct;
  onImageUpload: (file: File) => void;
  onDelete: () => void;
}

export const ProductCard: React.FC<ProductCardProps> = ({ 
  product, 
  onImageUpload, 
  onDelete 
}) => {
  return (
    <div className="border rounded-lg p-4 bg-white shadow">
      <div className="flex justify-between items-start">
        <div className="flex-1">
          <div className="flex items-center gap-2 mb-2">
            <h3 className="font-bold text-lg">{product.name}</h3>
            
            {/* ステータスバッジ */}
            <div className="flex gap-1">
              {product.is_new && (
                <span className="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">新</span>
              )}
              {product.is_recommended && (
                <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">推奨</span>
              )}
              {product.is_on_sale && (
                <span className="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">セール</span>
              )}
              {product.is_limited && (
                <span className="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">限定</span>
              )}
            </div>
          </div>
          
          <p className="text-gray-600 mb-2">{product.short_description}</p>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>SKU: {product.sku}</div>
            <div>価格: ¥{product.price.toLocaleString()}</div>
            <div>在庫: {product.stock_quantity}</div>
            <div>売上: ¥{product.total_sales.toLocaleString()}</div>
            <div>注文数: {product.total_orders}</div>
            <div>評価: ⭐{product.average_rating ?? 'N/A'} ({product.review_count})</div>
          </div>
        </div>
        
        <div className="flex flex-col gap-2 ml-4">
          <button 
            onClick={() => document.getElementById(`file-${product.id}`)?.click()}
            className="bg-blue-600 text-white px-3 py-1 rounded text-sm"
          >
            画像アップロード
          </button>
          <input
            id={`file-${product.id}`}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) onImageUpload(file);
            }}
          />
          
          <button 
            onClick={onDelete}
            className="bg-red-600 text-white px-3 py-1 rounded text-sm"
          >
            削除
          </button>
        </div>
      </div>
    </div>
  );
};
```

## 🛡️ セキュリティ考慮事項

### 認証・認可
- **JWT認証**: Bearer Tokenを全ての管理者APIリクエストに含める
- **ロールベース**: super_admin, admin, moderator の権限レベル
- **トークン有効期限**: アクセストークン1時間、リフレッシュトークン7日
- **自動ログアウト**: トークン期限切れ時の自動処理

### データ保護
- **入力検証**: 全ての入力データを適切に検証・サニタイズ
- **ファイルアップロード**: 許可された画像形式・サイズ制限
- **SQLインジェクション**: プリペアドステートメント使用済み
- **XSS保護**: 出力時のエスケープ処理

## 🐛 エラーハンドリング

### 共通エラーレスポンス形式
```json
{
  "success": false,
  "message": "エラーメッセージ",
  "error_code": "ERROR_CODE",
  "errors": ["詳細エラー1", "詳細エラー2"],
  "timestamp": "2025-08-25 22:35:23"
}
```

### よくあるエラー
| HTTPステータス | エラーコード | 説明 |
|--------------|------------|------|
| 401 | UNAUTHORIZED | 認証が必要/トークン無効 |
| 403 | FORBIDDEN | アクセス権限不足 |
| 404 | NOT_FOUND | リソースが存在しない |
| 422 | VALIDATION_ERROR | 入力データ検証エラー |
| 429 | RATE_LIMIT_EXCEEDED | レート制限超過 |
| 500 | INTERNAL_ERROR | サーバー内部エラー |

### エラーハンドリング実装例
```typescript
// utils/adminErrorHandler.ts
export const handleAdminApiError = (error: any): string => {
  if (error.response?.status === 401) {
    // トークン期限切れ - ログアウト処理
    adminAuthStore.logout();
    window.location.href = '/admin/login';
    return '認証が期限切れです。再度ログインしてください。';
  }
  
  if (error.response?.status === 403) {
    return 'この操作を実行する権限がありません。';
  }
  
  if (error.response?.data?.message) {
    return error.response.data.message;
  }
  
  return '予期しないエラーが発生しました。';
};
```

## 📋 実装チェックリスト

### 🔐 認証機能
- [ ] 管理者ログインフォーム
- [ ] JWT トークン管理
- [ ] 自動ログアウト処理
- [ ] 権限チェック

### 🛍️ 商品管理
- [ ] 商品一覧ページ（フィルター・検索対応）
- [ ] 商品詳細・編集ページ
- [ ] 新規商品作成ページ
- [ ] 一括操作機能
- [ ] 画像アップロード機能

### 📊 ダッシュボード
- [ ] 統計情報表示
- [ ] 最近の注文一覧
- [ ] 人気商品一覧
- [ ] アクティビティログ

### 🎨 UI/UX
- [ ] レスポンシブデザイン
- [ ] ローディング状態表示
- [ ] エラーメッセージ表示
- [ ] 成功メッセージ表示

## 🚀 パフォーマンス最適化

### 推奨事項
1. **仮想化**: 大量商品一覧でのreact-windowの使用
2. **キャッシュ**: React QueryやSWRでのAPIレスポンスキャッシュ
3. **遅延読み込み**: 画像の lazy loading
4. **デバウンス**: 検索入力のデバウンス処理
5. **ページネーション**: 適切なページサイズ設定（推奨: 20件）

## 💡 実装のベストプラクティス

1. **型安全性**: TypeScript型定義の完全活用
2. **エラーハンドリング**: 一貫したエラー処理パターン
3. **ユーザビリティ**: 適切なローディング・フィードバック
4. **セキュリティ**: 管理者権限の適切なチェック
5. **パフォーマンス**: 不要な再レンダリングの回避

---

## ✅ サポート

**バックエンドAPI**: 完全実装済み・テスト済み  
**認証システム**: JWT認証で完全セキュア  
**ドキュメント**: 完全版・実装例付き  

質問や実装サポートが必要な場合は、バックエンド開発チームまでお気軽にご連絡ください！

**Happy Admin Coding! 🚀**