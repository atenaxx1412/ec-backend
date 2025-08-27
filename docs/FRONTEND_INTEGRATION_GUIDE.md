# 🎯 フロントエンド統合ガイド

## 📋 概要

ECサイトバックエンドAPIが完成しました！このガイドは、フロントエンド開発者（Next.js + TypeScript）向けの実装ガイドです。

## 🚀 バックエンドAPI 完成情報

### ✅ 実装完了機能
- **認証システム**: JWT認証（ユーザー登録・ログイン・ME API）
- **商品管理**: 拡張フィールド対応（新商品・おすすめ・セール等）
- **画像アップロード**: 単一・複数ファイル対応
- **管理者機能**: 商品管理・ダッシュボード
- **セキュリティ**: CORS・レート制限・入力検証

### 🌐 接続情報
```
API Base URL: http://localhost:8080/api
Documentation: /docs/API_SPECIFICATION.md
Health Check: GET /api/health
```

## 🔧 フロントエンド実装ガイド

### 1. API クライアントセットアップ

#### axios 設定例
```typescript
// lib/api.ts
import axios from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080/api';

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// リクエストインターセプター（JWT自動付与）
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// レスポンスインターセプター（エラーハンドリング）
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // トークン期限切れ時の処理
      await refreshToken();
      return apiClient.request(error.config);
    }
    return Promise.reject(error);
  }
);

export { apiClient };
```

### 2. TypeScript型定義

```typescript
// types/api.ts

// 共通レスポンス型
export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
}

// ページネーション型
export interface Pagination {
  page: number;
  limit: number;
  total: number;
  total_pages: number;
}

// 商品型（拡張フィールド対応）
export interface Product {
  id: number;
  name: string;
  description: string;
  short_description?: string;
  price: number;
  sale_price?: number;
  stock_quantity: number;
  sku: string;
  image_url?: string;
  weight?: number;
  dimensions?: string;
  is_active: boolean;
  is_featured: boolean;
  is_new: boolean;           // 新商品フラグ
  is_recommended: boolean;   // おすすめ商品フラグ
  is_on_sale: boolean;       // セール中フラグ
  is_limited: boolean;       // 限定商品フラグ
  total_sales: number;       // 総売上
  total_orders: number;      // 総注文数
  average_rating?: number;   // 平均評価
  category: {
    id: number;
    name: string;
  };
  review_count: number;
  created_at: string;
  updated_at: string;
}

// 商品一覧レスポンス型
export interface ProductsResponse {
  products: Product[];
  pagination: Pagination;
}

// ユーザー型
export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  status: string;
  created_at: string;
  updated_at: string;
}

// 認証レスポンス型
export interface AuthResponse {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  token_type: string;
}

// カテゴリー型
export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string;
  image_url?: string;
  product_count: number;
  is_active: boolean;
  sort_order: number;
  created_at: string;
}

// カート商品型
export interface CartItem {
  id: number;
  product_id: number;
  product: Product;
  quantity: number;
  price: number;
  total: number;
  attributes?: Record<string, string>;
  created_at: string;
}

// ファイルアップロードレスポンス型
export interface UploadResponse {
  filename: string;
  original_name: string;
  url: string;
  full_url: string;
  size: number;
  extension: string;
  uploaded_at: string;
}
```

### 3. API サービス関数

```typescript
// services/api.ts
import { apiClient } from '@/lib/api';
import { 
  ApiResponse, 
  ProductsResponse, 
  Product, 
  User, 
  AuthResponse,
  Category,
  CartItem,
  UploadResponse 
} from '@/types/api';

// 認証API
export const authApi = {
  // ユーザー登録
  register: async (data: { name: string; email: string; password: string }) => {
    const response = await apiClient.post<ApiResponse<AuthResponse>>('/auth/register', data);
    return response.data;
  },

  // ログイン
  login: async (data: { email: string; password: string }) => {
    const response = await apiClient.post<ApiResponse<AuthResponse>>('/auth/login', data);
    return response.data;
  },

  // 現在ユーザー情報取得
  me: async () => {
    const response = await apiClient.get<ApiResponse<User>>('/auth/me');
    return response.data;
  },

  // ログアウト
  logout: async () => {
    const response = await apiClient.post<ApiResponse<null>>('/auth/logout');
    return response.data;
  },

  // トークンリフレッシュ
  refresh: async (refreshToken: string) => {
    const response = await apiClient.post<ApiResponse<AuthResponse>>('/auth/refresh', {
      refresh_token: refreshToken,
    });
    return response.data;
  },
};

// 商品API
export const productsApi = {
  // 商品一覧取得
  getProducts: async (params?: {
    page?: number;
    limit?: number;
    category_id?: number;
    min_price?: number;
    max_price?: number;
    in_stock?: boolean;
    is_new?: boolean;        // 新商品フィルター
    is_recommended?: boolean; // おすすめ商品フィルター
    is_on_sale?: boolean;    // セール商品フィルター
    is_featured?: boolean;   // 注目商品フィルター
    sort_by?: 'created_at' | 'price' | 'name' | 'stock_quantity';
    sort_order?: 'ASC' | 'DESC';
  }) => {
    const response = await apiClient.get<ApiResponse<ProductsResponse>>('/products', {
      params,
    });
    return response.data;
  },

  // 商品詳細取得
  getProduct: async (id: number) => {
    const response = await apiClient.get<ApiResponse<Product>>(`/products/${id}`);
    return response.data;
  },

  // 商品検索
  searchProducts: async (query: string, params?: {
    page?: number;
    limit?: number;
  }) => {
    const response = await apiClient.get<ApiResponse<ProductsResponse>>('/products/search', {
      params: { q: query, ...params },
    });
    return response.data;
  },

  // カテゴリー別商品取得
  getProductsByCategory: async (slug: string, params?: {
    page?: number;
    limit?: number;
  }) => {
    const response = await apiClient.get<ApiResponse<ProductsResponse>>(`/products/category/${slug}`, {
      params,
    });
    return response.data;
  },
};

// カテゴリーAPI
export const categoriesApi = {
  // カテゴリー一覧取得
  getCategories: async () => {
    const response = await apiClient.get<ApiResponse<Category[]>>('/categories');
    return response.data;
  },

  // カテゴリーツリー取得
  getCategoryTree: async () => {
    const response = await apiClient.get<ApiResponse<Category[]>>('/categories/tree');
    return response.data;
  },
};

// カートAPI
export const cartApi = {
  // カート内容取得
  getCart: async () => {
    const response = await apiClient.get<ApiResponse<CartItem[]>>('/cart');
    return response.data;
  },

  // カートに商品追加
  addToCart: async (data: {
    product_id: number;
    quantity: number;
    attributes?: Record<string, string>;
  }) => {
    const response = await apiClient.post<ApiResponse<CartItem>>('/cart/add', data);
    return response.data;
  },

  // カート商品数量更新
  updateCartItem: async (id: number, quantity: number) => {
    const response = await apiClient.put<ApiResponse<CartItem>>(`/cart/${id}`, { quantity });
    return response.data;
  },

  // カートから商品削除
  removeFromCart: async (id: number) => {
    const response = await apiClient.delete<ApiResponse<null>>(`/cart/${id}`);
    return response.data;
  },

  // カートを空にする
  clearCart: async () => {
    const response = await apiClient.delete<ApiResponse<null>>('/cart');
    return response.data;
  },
};

// ファイルアップロードAPI
export const uploadApi = {
  // 単一画像アップロード
  uploadImage: async (file: File) => {
    const formData = new FormData();
    formData.append('image', file);
    
    const response = await apiClient.post<ApiResponse<UploadResponse>>('/upload/image', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // 複数画像アップロード
  uploadImages: async (files: File[]) => {
    const formData = new FormData();
    files.forEach((file) => {
      formData.append('images[]', file);
    });
    
    const response = await apiClient.post<ApiResponse<{
      uploaded_files: UploadResponse[];
      errors: string[];
      total_uploaded: number;
      total_errors: number;
    }>>('/upload/images', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },
};
```

### 4. React Hooks 実装例

```typescript
// hooks/useAuth.ts
import { useState, useEffect } from 'react';
import { authApi } from '@/services/api';
import { User } from '@/types/api';

export const useAuth = () => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('access_token');
    if (token) {
      loadUser();
    } else {
      setLoading(false);
    }
  }, []);

  const loadUser = async () => {
    try {
      const response = await authApi.me();
      setUser(response.data);
    } catch (error) {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
    } finally {
      setLoading(false);
    }
  };

  const login = async (email: string, password: string) => {
    const response = await authApi.login({ email, password });
    localStorage.setItem('access_token', response.data.access_token);
    localStorage.setItem('refresh_token', response.data.refresh_token);
    await loadUser();
    return response;
  };

  const logout = async () => {
    try {
      await authApi.logout();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
      setUser(null);
    }
  };

  return { user, loading, login, logout, loadUser };
};

// hooks/useProducts.ts
import { useState, useEffect } from 'react';
import { productsApi } from '@/services/api';
import { Product, ProductsResponse } from '@/types/api';

interface UseProductsParams {
  page?: number;
  limit?: number;
  category_id?: number;
  is_new?: boolean;
  is_recommended?: boolean;
  is_on_sale?: boolean;
  is_featured?: boolean;
}

export const useProducts = (params: UseProductsParams = {}) => {
  const [data, setData] = useState<ProductsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchProducts = async () => {
    try {
      setLoading(true);
      const response = await productsApi.getProducts(params);
      setData(response.data);
      setError(null);
    } catch (err) {
      setError('商品の取得に失敗しました');
      console.error('Error fetching products:', err);
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

### 5. コンポーネント実装例

```tsx
// components/ProductGrid.tsx
import { useProducts } from '@/hooks/useProducts';
import { Product } from '@/types/api';

interface ProductGridProps {
  filters?: {
    is_new?: boolean;
    is_recommended?: boolean;
    is_on_sale?: boolean;
    is_featured?: boolean;
  };
}

export const ProductGrid: React.FC<ProductGridProps> = ({ filters = {} }) => {
  const { data, loading, error } = useProducts(filters);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!data) return null;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {data.products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
      {/* ページネーション */}
      <Pagination 
        current={data.pagination.page}
        total={data.pagination.total_pages}
      />
    </div>
  );
};

// components/ProductCard.tsx
interface ProductCardProps {
  product: Product;
}

export const ProductCard: React.FC<ProductCardProps> = ({ product }) => {
  return (
    <div className="border rounded-lg p-4 hover:shadow-lg transition-shadow">
      {/* 商品バッジ */}
      <div className="flex gap-2 mb-2">
        {product.is_new && (
          <span className="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
            新商品
          </span>
        )}
        {product.is_recommended && (
          <span className="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">
            おすすめ
          </span>
        )}
        {product.is_on_sale && (
          <span className="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">
            セール
          </span>
        )}
        {product.is_limited && (
          <span className="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">
            限定
          </span>
        )}
      </div>

      {/* 商品画像 */}
      {product.image_url && (
        <img 
          src={product.image_url} 
          alt={product.name}
          className="w-full h-48 object-cover mb-2"
        />
      )}

      {/* 商品情報 */}
      <h3 className="font-bold text-lg mb-2">{product.name}</h3>
      <p className="text-gray-600 text-sm mb-2">{product.short_description}</p>
      
      {/* 価格 */}
      <div className="flex items-center gap-2 mb-2">
        {product.sale_price ? (
          <>
            <span className="text-red-600 font-bold">¥{product.sale_price.toLocaleString()}</span>
            <span className="text-gray-400 line-through">¥{product.price.toLocaleString()}</span>
          </>
        ) : (
          <span className="font-bold">¥{product.price.toLocaleString()}</span>
        )}
      </div>

      {/* 評価・在庫 */}
      <div className="flex justify-between items-center text-sm text-gray-600 mb-3">
        <span>⭐ {product.average_rating ?? 'N/A'} ({product.review_count})</span>
        <span>在庫: {product.stock_quantity}</span>
      </div>

      <button className="w-full bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition-colors">
        カートに追加
      </button>
    </div>
  );
};
```

### 6. エラーハンドリング

```typescript
// utils/errorHandler.ts
import { AxiosError } from 'axios';

interface ApiError {
  success: false;
  message: string;
  error_code: string;
  errors: string[];
  timestamp: string;
}

export const handleApiError = (error: unknown): string => {
  if (error instanceof AxiosError) {
    const apiError = error.response?.data as ApiError;
    
    if (apiError?.message) {
      return apiError.message;
    }
    
    switch (error.response?.status) {
      case 401:
        return '認証が必要です。ログインしてください。';
      case 403:
        return 'アクセス権限がありません。';
      case 404:
        return 'リソースが見つかりません。';
      case 422:
        return '入力内容に不備があります。';
      case 429:
        return 'アクセス頻度が高すぎます。しばらく待ってから再試行してください。';
      case 500:
        return 'サーバーエラーが発生しました。';
      default:
        return 'エラーが発生しました。';
    }
  }
  
  return '不明なエラーが発生しました。';
};
```

## 🎯 実装のポイント

### 1. 新機能の活用
```typescript
// 新商品のみ取得
const newProducts = await productsApi.getProducts({ is_new: true });

// おすすめ商品のみ取得
const recommendedProducts = await productsApi.getProducts({ is_recommended: true });

// セール商品のみ取得
const saleProducts = await productsApi.getProducts({ is_on_sale: true });

// 複数フィルター組み合わせ
const featuredSaleProducts = await productsApi.getProducts({ 
  is_featured: true, 
  is_on_sale: true 
});
```

### 2. 画像アップロード
```tsx
const handleImageUpload = async (file: File) => {
  try {
    const response = await uploadApi.uploadImage(file);
    console.log('アップロード成功:', response.data.full_url);
  } catch (error) {
    console.error('アップロード失敗:', handleApiError(error));
  }
};
```

### 3. 認証状態管理
```tsx
const App = () => {
  const { user, loading, login, logout } = useAuth();

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      {user ? (
        <UserDashboard user={user} onLogout={logout} />
      ) : (
        <LoginForm onLogin={login} />
      )}
    </div>
  );
};
```

## 📋 チェックリスト

### 必須実装項目
- [ ] API クライアントセットアップ
- [ ] TypeScript型定義の追加
- [ ] 認証システム統合
- [ ] 商品一覧・詳細ページ
- [ ] カート機能
- [ ] エラーハンドリング

### 新機能活用項目
- [ ] 新商品フィルター (`is_new`)
- [ ] おすすめ商品フィルター (`is_recommended`)
- [ ] セール商品フィルター (`is_on_sale`)
- [ ] 限定商品表示 (`is_limited`)
- [ ] 売上・注文数表示（管理画面）
- [ ] 平均評価表示 (`average_rating`)

### オプション項目
- [ ] 画像アップロード機能
- [ ] 管理者画面統合
- [ ] リアルタイム通知
- [ ] PWA対応

## 🔧 開発環境セットアップ

```bash
# バックエンド起動
cd /path/to/ec-backend
docker-compose up -d

# フロントエンド開発
cd /path/to/your-frontend
npm install
npm run dev

# API接続テスト
curl http://localhost:8080/api/health
```

## 💡 ベストプラクティス

1. **型安全性**: TypeScript型定義を必ず使用
2. **エラーハンドリング**: 一貫したエラー処理実装
3. **ローディング状態**: UX向上のためのローディング表示
4. **認証管理**: トークンの適切な管理とリフレッシュ
5. **パフォーマンス**: 商品一覧の仮想化やページネーション
6. **アクセシビリティ**: 適切なARIAラベルとキーボード操作

---

## ✅ サポート

質問や不明点があれば、バックエンド開発者にお気軽にお声がけください。完全に動作する高品質なAPIが準備できています！

**Happy Coding! 🚀**