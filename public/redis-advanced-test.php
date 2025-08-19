<?php
/**
 * Advanced Redis Test for EC Site
 * Tests caching, session management, and performance features
 */

header('Content-Type: application/json; charset=utf-8');

try {
    $redis = new Redis();
    $connected = $redis->connect('redis', 6379);
    
    if (!$connected) {
        throw new Exception('Could not connect to Redis server');
    }
    
    $test_results = [];
    
    // Test 1: Basic operations
    $test_results['basic_operations'] = [];
    
    // String operations
    $redis->set('test:string', 'Hello EC Site!');
    $test_results['basic_operations']['string_set'] = true;
    $test_results['basic_operations']['string_get'] = $redis->get('test:string');
    
    // Expiration test
    $redis->setex('test:expire', 10, 'This expires in 10 seconds');
    $test_results['basic_operations']['expire_ttl'] = $redis->ttl('test:expire');
    
    // Test 2: Hash operations (for user data)
    $test_results['hash_operations'] = [];
    
    $user_data = [
        'id' => 123,
        'name' => '田中太郎',
        'email' => 'tanaka@example.com',
        'last_login' => date('Y-m-d H:i:s'),
        'cart_items' => 3,
        'wishlist_count' => 5
    ];
    
    $redis->hMSet('user:123', $user_data);
    $test_results['hash_operations']['user_set'] = true;
    $test_results['hash_operations']['user_get'] = $redis->hGetAll('user:123');
    $test_results['hash_operations']['cart_items'] = $redis->hGet('user:123', 'cart_items');
    
    // Test 3: List operations (for cart items)
    $test_results['list_operations'] = [];
    
    $cart_key = 'cart:user:123';
    $redis->del($cart_key); // Clear any existing data
    
    // Add items to cart
    $cart_items = [
        json_encode(['product_id' => 1, 'name' => 'iPhone 15 Pro', 'quantity' => 1, 'price' => 149800]),
        json_encode(['product_id' => 3, 'name' => 'ワイヤレスイヤホン', 'quantity' => 2, 'price' => 9800]),
        json_encode(['product_id' => 7, 'name' => 'ヨガマット', 'quantity' => 1, 'price' => 3800])
    ];
    
    foreach ($cart_items as $item) {
        $redis->rPush($cart_key, $item);
    }
    
    $test_results['list_operations']['cart_length'] = $redis->lLen($cart_key);
    $test_results['list_operations']['cart_items'] = array_map('json_decode', $redis->lRange($cart_key, 0, -1));
    
    // Test 4: Set operations (for categories, tags)
    $test_results['set_operations'] = [];
    
    $categories_key = 'categories:featured';
    $redis->del($categories_key);
    
    $featured_categories = ['エレクトロニクス', 'ファッション', 'ホーム&キッチン', 'スポーツ'];
    foreach ($featured_categories as $category) {
        $redis->sAdd($categories_key, $category);
    }
    
    $test_results['set_operations']['categories_count'] = $redis->sCard($categories_key);
    $test_results['set_operations']['categories_list'] = $redis->sMembers($categories_key);
    $test_results['set_operations']['is_featured'] = $redis->sIsMember($categories_key, 'エレクトロニクス');
    
    // Test 5: Sorted set operations (for rankings, popular products)
    $test_results['sorted_set_operations'] = [];
    
    $popular_key = 'products:popular';
    $redis->del($popular_key);
    
    // Add products with popularity scores
    $popular_products = [
        ['iPhone 15 Pro', 98.5],
        ['MacBook Air M2', 95.2],
        ['ワイヤレスイヤホン', 87.3],
        ['デニムジャケット', 82.1],
        ['ヨガマット', 79.8]
    ];
    
    foreach ($popular_products as $product) {
        $redis->zAdd($popular_key, $product[1], $product[0]);
    }
    
    $test_results['sorted_set_operations']['top_3_products'] = $redis->zRevRange($popular_key, 0, 2, true);
    $test_results['sorted_set_operations']['product_rank'] = $redis->zRevRank($popular_key, 'iPhone 15 Pro') + 1;
    $test_results['sorted_set_operations']['score'] = $redis->zScore($popular_key, 'iPhone 15 Pro');
    
    // Test 6: Performance test
    $test_results['performance'] = [];
    
    $start_time = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $redis->set("perf:test:$i", "value_$i");
    }
    $set_time = microtime(true) - $start_time;
    
    $start_time = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $redis->get("perf:test:$i");
    }
    $get_time = microtime(true) - $start_time;
    
    $test_results['performance']['set_1000_operations'] = round($set_time * 1000, 2) . 'ms';
    $test_results['performance']['get_1000_operations'] = round($get_time * 1000, 2) . 'ms';
    
    // Clean up performance test data
    for ($i = 0; $i < 1000; $i++) {
        $redis->del("perf:test:$i");
    }
    
    // Test 7: Session management simulation
    $test_results['session_management'] = [];
    
    $session_id = 'sess_' . bin2hex(random_bytes(16));
    $session_data = [
        'user_id' => 123,
        'username' => '田中太郎',
        'login_time' => time(),
        'cart_total' => 163400,
        'last_activity' => time(),
        'preferences' => json_encode(['theme' => 'light', 'language' => 'ja'])
    ];
    
    // Set session with 1 hour expiration
    $redis->hMSet("session:$session_id", $session_data);
    $redis->expire("session:$session_id", 3600);
    
    $test_results['session_management']['session_created'] = true;
    $test_results['session_management']['session_data'] = $redis->hGetAll("session:$session_id");
    $test_results['session_management']['session_ttl'] = $redis->ttl("session:$session_id");
    
    // Test 8: Cache simulation (product data)
    $test_results['cache_simulation'] = [];
    
    $product_cache_key = 'product:1:details';
    $product_data = [
        'id' => 1,
        'name' => 'iPhone 15 Pro',
        'price' => 149800,
        'description' => '最新のiPhone 15 Pro。高性能なA17 Proチップ搭載。',
        'stock' => 44,
        'category' => 'エレクトロニクス',
        'reviews_avg' => 4.2,
        'cached_at' => date('Y-m-d H:i:s')
    ];
    
    // Cache for 5 minutes
    $redis->setex($product_cache_key, 300, json_encode($product_data, JSON_UNESCAPED_UNICODE));
    
    $cached_product = json_decode($redis->get($product_cache_key), true);
    $test_results['cache_simulation']['product_cached'] = true;
    $test_results['cache_simulation']['cached_data'] = $cached_product;
    $test_results['cache_simulation']['cache_ttl'] = $redis->ttl($product_cache_key);
    
    // Test 9: Redis info and statistics
    $redis_info = $redis->info();
    $test_results['redis_info'] = [
        'version' => $redis_info['redis_version'] ?? 'N/A',
        'uptime' => $redis_info['uptime_in_seconds'] ?? 'N/A',
        'connected_clients' => $redis_info['connected_clients'] ?? 'N/A',
        'used_memory' => $redis_info['used_memory_human'] ?? 'N/A',
        'total_commands_processed' => $redis_info['total_commands_processed'] ?? 'N/A',
        'keyspace_hits' => $redis_info['keyspace_hits'] ?? 'N/A',
        'keyspace_misses' => $redis_info['keyspace_misses'] ?? 'N/A'
    ];
    
    // Calculate hit ratio
    $hits = intval($redis_info['keyspace_hits'] ?? 0);
    $misses = intval($redis_info['keyspace_misses'] ?? 0);
    $total = $hits + $misses;
    $hit_ratio = $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    $test_results['redis_info']['hit_ratio'] = $hit_ratio . '%';
    
    // Close connection
    $redis->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Redis advanced tests completed successfully',
        'redis_version' => $redis_info['redis_version'] ?? 'Unknown',
        'tests' => $test_results,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}