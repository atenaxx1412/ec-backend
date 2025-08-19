<?php
/**
 * PHP Info Page - Development Only
 * Shows PHP configuration and loaded extensions
 */

// Security check - only show in development
if (getenv('NODE_ENV') !== 'development') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'PHP Info not available in production',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Set HTML headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Configuration - EC Site Development</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 30px; }
        .section h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-box { background: #f9f9f9; padding: 15px; border-radius: 5px; }
        .info-box h3 { margin-top: 0; color: #007cba; }
        .extension-list { columns: 3; column-gap: 20px; }
        .extension-list li { break-inside: avoid; margin-bottom: 5px; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .back-link { display: inline-block; margin: 20px 0; padding: 10px 20px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; }
        .back-link:hover { background: #005a8b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 EC Site API - PHP Configuration</h1>
            <p>Development Environment Status</p>
            <a href="/api/health" class="back-link">← Back to API Health Check</a>
        </div>

        <div class="section">
            <h2>📊 System Information</h2>
            <div class="info-grid">
                <div class="info-box">
                    <h3>PHP Version</h3>
                    <p><strong><?php echo PHP_VERSION; ?></strong></p>
                    <p>SAPI: <?php echo php_sapi_name(); ?></p>
                    <p>OS: <?php echo PHP_OS; ?></p>
                </div>
                <div class="info-box">
                    <h3>Server Information</h3>
                    <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                    <p>Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></p>
                    <p>Current Time: <?php echo date('Y-m-d H:i:s T'); ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>⚙️ Configuration Settings</h2>
            <div class="info-grid">
                <div class="info-box">
                    <h3>Memory & Performance</h3>
                    <p>Memory Limit: <strong><?php echo ini_get('memory_limit'); ?></strong></p>
                    <p>Max Execution Time: <?php echo ini_get('max_execution_time'); ?>s</p>
                    <p>Upload Max Filesize: <?php echo ini_get('upload_max_filesize'); ?></p>
                    <p>Post Max Size: <?php echo ini_get('post_max_size'); ?></p>
                </div>
                <div class="info-box">
                    <h3>Error Handling</h3>
                    <p>Display Errors: <span class="<?php echo ini_get('display_errors') ? 'status-ok' : 'status-error'; ?>"><?php echo ini_get('display_errors') ? 'On' : 'Off'; ?></span></p>
                    <p>Error Reporting: <?php echo error_reporting(); ?></p>
                    <p>Log Errors: <span class="<?php echo ini_get('log_errors') ? 'status-ok' : 'status-error'; ?>"><?php echo ini_get('log_errors') ? 'On' : 'Off'; ?></span></p>
                    <p>Error Log: <?php echo ini_get('error_log') ?: 'System default'; ?></p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>🔧 Extension Status</h2>
            <div class="info-grid">
                <div class="info-box">
                    <h3>Required Extensions</h3>
                    <?php
                    $required = ['pdo_mysql', 'redis', 'mbstring', 'json', 'curl', 'gd', 'intl'];
                    foreach ($required as $ext): ?>
                        <p><?php echo $ext; ?>: <span class="<?php echo extension_loaded($ext) ? 'status-ok' : 'status-error'; ?>"><?php echo extension_loaded($ext) ? '✓ Loaded' : '✗ Missing'; ?></span></p>
                    <?php endforeach; ?>
                </div>
                <div class="info-box">
                    <h3>Development Extensions</h3>
                    <?php
                    $dev = ['xdebug', 'opcache', 'zip', 'exif'];
                    foreach ($dev as $ext): ?>
                        <p><?php echo $ext; ?>: <span class="<?php echo extension_loaded($ext) ? 'status-ok' : 'status-error'; ?>"><?php echo extension_loaded($ext) ? '✓ Loaded' : '✗ Missing'; ?></span></p>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>📦 All Loaded Extensions</h2>
            <ul class="extension-list">
                <?php foreach (get_loaded_extensions() as $extension): ?>
                    <li><?php echo $extension; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="section">
            <h2>🌐 Environment Variables</h2>
            <div class="info-box">
                <?php
                $env_vars = [
                    'NODE_ENV', 'APP_DEBUG', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME',
                    'REDIS_HOST', 'MAIL_HOST', 'FRONTEND_URL'
                ];
                foreach ($env_vars as $var):
                    $value = getenv($var);
                ?>
                    <p><strong><?php echo $var; ?>:</strong> <?php echo $value ? htmlspecialchars($value) : '<em>Not set</em>'; ?></p>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (function_exists('opcache_get_status')): ?>
        <div class="section">
            <h2>⚡ OPcache Status</h2>
            <div class="info-box">
                <?php $opcache = opcache_get_status(); ?>
                <p>Enabled: <span class="<?php echo $opcache['opcache_enabled'] ? 'status-ok' : 'status-error'; ?>"><?php echo $opcache['opcache_enabled'] ? 'Yes' : 'No'; ?></span></p>
                <?php if ($opcache['opcache_enabled']): ?>
                    <p>Cache Full: <?php echo $opcache['cache_full'] ? 'Yes' : 'No'; ?></p>
                    <p>Used Memory: <?php echo round($opcache['memory_usage']['used_memory'] / 1024 / 1024, 2); ?> MB</p>
                    <p>Free Memory: <?php echo round($opcache['memory_usage']['free_memory'] / 1024 / 1024, 2); ?> MB</p>
                    <p>Cached Scripts: <?php echo $opcache['opcache_statistics']['num_cached_scripts']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>🔗 Quick Links</h2>
            <div class="info-box">
                <p><a href="/api/health">API Health Check</a></p>
                <p><a href="/api/products">Products API</a></p>
                <p><a href="/api/categories">Categories API</a></p>
                <p><a href="http://localhost:8081" target="_blank">phpMyAdmin</a></p>
                <p><a href="http://localhost:8025" target="_blank">Mailpit</a></p>
            </div>
        </div>

        <div class="section">
            <h2>📋 Full PHP Configuration</h2>
            <details>
                <summary>Click to view full phpinfo() output</summary>
                <div style="margin-top: 20px;">
                    <?php phpinfo(); ?>
                </div>
            </details>
        </div>
    </div>
</body>
</html>