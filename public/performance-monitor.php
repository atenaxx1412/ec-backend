<?php
/**
 * Performance Monitoring Dashboard
 * Real-time monitoring of system performance metrics
 */

header('Content-Type: application/json; charset=utf-8');

$monitor_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'system' => [],
    'database' => [],
    'redis' => [],
    'api' => [],
    'email' => []
];

// System Performance
$monitor_data['system'] = [
    'php_version' => PHP_VERSION,
    'memory_usage' => memory_get_usage(true),
    'memory_peak' => memory_get_peak_usage(true),
    'memory_limit' => ini_get('memory_limit'),
    'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
    'server_load' => sys_getloadavg() ?: [0, 0, 0],
    'disk_free' => disk_free_space('.'),
    'disk_total' => disk_total_space('.')
];

// Database Performance Test
try {
    $start_time = microtime(true);
    
    $host = getenv('DB_HOST') ?: 'mysql';
    $dbname = getenv('DB_DATABASE') ?: 'ecommerce_dev_db';
    $username = getenv('DB_USERNAME') ?: 'ec_dev_user';
    $password = getenv('DB_PASSWORD') ?: 'dev_password_123';
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $connection_time = microtime(true) - $start_time;
    
    // Test query performance
    $query_start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
    $query_time = microtime(true) - $query_start;
    $product_count = $stmt->fetch()['count'];
    
    // Get database status
    $status_stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
    $threads_connected = $status_stmt->fetch()['Value'];
    
    $status_stmt = $pdo->query("SHOW STATUS LIKE 'Queries'");
    $total_queries = $status_stmt->fetch()['Value'];
    
    $status_stmt = $pdo->query("SHOW STATUS LIKE 'Uptime'");
    $uptime = $status_stmt->fetch()['Value'];
    
    $monitor_data['database'] = [
        'status' => 'connected',
        'connection_time' => round($connection_time * 1000, 2) . 'ms',
        'query_time' => round($query_time * 1000, 2) . 'ms',
        'active_products' => intval($product_count),
        'threads_connected' => intval($threads_connected),
        'total_queries' => intval($total_queries),
        'uptime_hours' => round($uptime / 3600, 1)
    ];
    
} catch (Exception $e) {
    $monitor_data['database'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

// Redis Performance Test
try {
    $redis_start = microtime(true);
    $redis = new Redis();
    $redis->connect('redis', 6379);
    $redis_connection_time = microtime(true) - $redis_start;
    
    // Performance test
    $perf_start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $redis->set("perf_test_$i", "value_$i");
    }
    $set_time = microtime(true) - $perf_start;
    
    $perf_start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $redis->get("perf_test_$i");
    }
    $get_time = microtime(true) - $perf_start;
    
    // Clean up
    for ($i = 0; $i < 100; $i++) {
        $redis->del("perf_test_$i");
    }
    
    // Get Redis info
    $redis_info = $redis->info();
    
    $monitor_data['redis'] = [
        'status' => 'connected',
        'connection_time' => round($redis_connection_time * 1000, 2) . 'ms',
        'set_100_operations' => round($set_time * 1000, 2) . 'ms',
        'get_100_operations' => round($get_time * 1000, 2) . 'ms',
        'version' => $redis_info['redis_version'] ?? 'Unknown',
        'uptime' => $redis_info['uptime_in_seconds'] ?? 0,
        'connected_clients' => $redis_info['connected_clients'] ?? 0,
        'used_memory' => $redis_info['used_memory_human'] ?? 'Unknown',
        'total_commands' => $redis_info['total_commands_processed'] ?? 0,
        'keyspace_hits' => $redis_info['keyspace_hits'] ?? 0,
        'keyspace_misses' => $redis_info['keyspace_misses'] ?? 0
    ];
    
    $redis->close();
    
} catch (Exception $e) {
    $monitor_data['redis'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

// API Performance Test
$api_start = microtime(true);

// Test multiple API endpoints
$api_tests = [
    'health' => '/api/health',
    'products' => '/api/products',
    'categories' => '/api/categories'
];

$api_results = [];
foreach ($api_tests as $test_name => $endpoint) {
    $test_start = microtime(true);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'method' => 'GET'
        ]
    ]);
    
    $result = @file_get_contents("http://localhost:8080$endpoint", false, $context);
    $test_time = microtime(true) - $test_start;
    
    $api_results[$test_name] = [
        'response_time' => round($test_time * 1000, 2) . 'ms',
        'success' => $result !== false,
        'response_size' => $result ? strlen($result) : 0
    ];
}

$api_total_time = microtime(true) - $api_start;

$monitor_data['api'] = [
    'total_test_time' => round($api_total_time * 1000, 2) . 'ms',
    'endpoints' => $api_results,
    'server_info' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
    ]
];

// Email Service Test (Mailpit)
try {
    $email_start = microtime(true);
    $mailpit_response = @file_get_contents('http://mailpit:8025/api/v1/info');
    $email_connection_time = microtime(true) - $email_start;
    
    if ($mailpit_response) {
        $mailpit_data = json_decode($mailpit_response, true);
        $monitor_data['email'] = [
            'status' => 'connected',
            'connection_time' => round($email_connection_time * 1000, 2) . 'ms',
            'version' => $mailpit_data['Version'] ?? 'Unknown',
            'messages' => $mailpit_data['Messages'] ?? 0,
            'unread' => $mailpit_data['Unread'] ?? 0,
            'uptime' => $mailpit_data['RuntimeStats']['Uptime'] ?? 0,
            'smtp_accepted' => $mailpit_data['RuntimeStats']['SMTPAccepted'] ?? 0,
            'smtp_rejected' => $mailpit_data['RuntimeStats']['SMTPRejected'] ?? 0
        ];
    } else {
        $monitor_data['email'] = [
            'status' => 'error',
            'error' => 'Could not connect to Mailpit API'
        ];
    }
} catch (Exception $e) {
    $monitor_data['email'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

// Calculate overall health score
$health_score = 0;
$health_components = 0;

if ($monitor_data['database']['status'] === 'connected') {
    $health_score += 25;
}
$health_components++;

if ($monitor_data['redis']['status'] === 'connected') {
    $health_score += 25;
}
$health_components++;

if ($monitor_data['email']['status'] === 'connected') {
    $health_score += 25;
}
$health_components++;

// API health based on successful endpoint tests
$successful_apis = 0;
$total_apis = count($monitor_data['api']['endpoints']);
foreach ($monitor_data['api']['endpoints'] as $endpoint) {
    if ($endpoint['success']) {
        $successful_apis++;
    }
}
$health_score += ($successful_apis / $total_apis) * 25;
$health_components++;

$monitor_data['overall_health'] = [
    'score' => round($health_score),
    'status' => $health_score >= 90 ? 'excellent' : ($health_score >= 70 ? 'good' : ($health_score >= 50 ? 'warning' : 'critical')),
    'components_healthy' => $health_components,
    'last_check' => date('Y-m-d H:i:s')
];

// Performance recommendations
$recommendations = [];

if ($monitor_data['database']['status'] === 'connected') {
    $query_time = floatval(str_replace('ms', '', $monitor_data['database']['query_time']));
    if ($query_time > 100) {
        $recommendations[] = 'データベースクエリが遅い可能性があります。インデックスの最適化を検討してください。';
    }
}

if ($monitor_data['redis']['status'] === 'connected') {
    $redis_set_time = floatval(str_replace('ms', '', $monitor_data['redis']['set_100_operations']));
    if ($redis_set_time > 50) {
        $recommendations[] = 'Redis書き込み性能が低下しています。メモリ使用量を確認してください。';
    }
}

$memory_usage = $monitor_data['system']['memory_usage'];
$memory_limit_str = $monitor_data['system']['memory_limit'];
$memory_limit = $memory_limit_str === '-1' ? PHP_INT_MAX : 
    (int)$memory_limit_str * (strpos($memory_limit_str, 'M') ? 1024*1024 : 
    (strpos($memory_limit_str, 'G') ? 1024*1024*1024 : 1));

if ($memory_usage / $memory_limit > 0.8) {
    $recommendations[] = 'メモリ使用量が高くなっています。不要なオブジェクトの解放を検討してください。';
}

$monitor_data['recommendations'] = $recommendations;

echo json_encode($monitor_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>