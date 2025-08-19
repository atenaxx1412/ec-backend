<?php
/**
 * Email Test Script for Mailpit
 * Accessible via web interface for testing
 */

header('Content-Type: application/json; charset=utf-8');

// SMTP Configuration for Mailpit
ini_set('SMTP', 'mailpit');
ini_set('smtp_port', '1025');
ini_set('sendmail_from', 'test@ec-site-dev.local');

$test_results = [];

// Test 1: Basic HTML email
$to = 'customer@example.com';
$subject = 'EC Site - Email Test from Development Environment';
$message = "
<html>
<head>
    <title>EC Site Email Test</title>
</head>
<body>
    <h2>Email System Test</h2>
    <p>This is a test email from the EC Site development environment.</p>
    
    <h3>System Information:</h3>
    <ul>
        <li><strong>Environment:</strong> Development</li>
        <li><strong>SMTP Server:</strong> Mailpit</li>
        <li><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</li>
        <li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>
    </ul>
    
    <h3>Features Test:</h3>
    <p>✅ Japanese text support: こんにちは、ECサイトです！</p>
    <p>✅ HTML formatting works correctly</p>
    <p>✅ SMTP connection established</p>
    
    <hr>
    <p><em>This email was sent automatically by the EC Site development system.</em></p>
</body>
</html>
";

$headers = array(
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: EC Site Development <noreply@ec-site-dev.local>',
    'Reply-To: noreply@ec-site-dev.local',
    'X-Mailer: PHP/' . phpversion(),
    'X-Environment: Development',
    'X-Service: Mailpit-Test'
);

// Send first test email
$result1 = mail($to, $subject, $message, implode("\r\n", $headers));
$test_results['basic_email'] = [
    'success' => $result1,
    'to' => $to,
    'subject' => $subject
];

// Test 2: Welcome email template
$welcome_subject = 'Welcome to EC Site - アカウント登録完了';
$welcome_message = "
<html>
<head>
    <title>Welcome to EC Site</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background-color: #4a90e2; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 0.9em; }
        .button { display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>ECサイトへようこそ！</h1>
    </div>
    
    <div class='content'>
        <h2>アカウント登録が完了しました</h2>
        <p>お客様のアカウントが正常に作成されました。以下の機能をご利用いただけます：</p>
        
        <ul>
            <li>商品の閲覧・購入</li>
            <li>お気に入りリストの管理</li>
            <li>注文履歴の確認</li>
            <li>レビューの投稿</li>
        </ul>
        
        <p>今すぐショッピングを開始しましょう！</p>
        
        <p><strong>アカウント情報:</strong></p>
        <ul>
            <li>メールアドレス: $to</li>
            <li>登録日時: " . date('Y年m月d日 H:i') . "</li>
            <li>会員ID: TEST-" . rand(100000, 999999) . "</li>
        </ul>
    </div>
    
    <div class='footer'>
        <p>このメールに心当たりがない場合は、お手数ですが削除してください。</p>
        <p>© 2025 EC Site Development. All rights reserved.</p>
    </div>
</body>
</html>
";

$result2 = mail($to, $welcome_subject, $welcome_message, implode("\r\n", $headers));
$test_results['welcome_email'] = [
    'success' => $result2,
    'to' => $to,
    'subject' => $welcome_subject
];

// Test 3: Order confirmation email
$order_subject = 'ご注文確認 - 注文番号 #' . rand(1000, 9999);
$order_message = "
<html>
<head>
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .order-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .order-table th, .order-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .order-table th { background-color: #f8f9fa; }
        .total { font-weight: bold; background-color: #f8f9fa; }
        .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>ご注文ありがとうございます</h1>
    </div>
    
    <div class='content'>
        <h2>注文内容の確認</h2>
        <p>以下の内容でご注文を承りました：</p>
        
        <table class='order-table'>
            <tr>
                <th>商品名</th>
                <th>数量</th>
                <th>単価</th>
                <th>小計</th>
            </tr>
            <tr>
                <td>iPhone 15 Pro</td>
                <td>1</td>
                <td>¥149,800</td>
                <td>¥149,800</td>
            </tr>
            <tr>
                <td>ワイヤレスイヤホン</td>
                <td>1</td>
                <td>¥9,800</td>
                <td>¥9,800</td>
            </tr>
            <tr class='total'>
                <td colspan='3'>送料</td>
                <td>¥800</td>
            </tr>
            <tr class='total'>
                <td colspan='3'>合計</td>
                <td>¥160,400</td>
            </tr>
        </table>
        
        <h3>配送先情報</h3>
        <p>
            〒150-0001<br>
            東京都渋谷区神宮前1-1-1<br>
            テスト太郎 様
        </p>
        
        <p>配送予定日：" . date('Y年m月d日', strtotime('+3 days')) . "</p>
    </div>
    
    <div class='footer'>
        <p>ご不明な点がございましたら、お気軽にお問い合わせください。</p>
        <p>© 2025 EC Site Development. All rights reserved.</p>
    </div>
</body>
</html>
";

$result3 = mail($to, $order_subject, $order_message, implode("\r\n", $headers));
$test_results['order_email'] = [
    'success' => $result3,
    'to' => $to,
    'subject' => $order_subject
];

// Get Mailpit statistics
sleep(1); // Wait for messages to be processed
$mailpit_info = null;
$api_response = @file_get_contents('http://mailpit:8025/api/v1/info');
if ($api_response) {
    $mailpit_info = json_decode($api_response, true);
}

// Return results
echo json_encode([
    'success' => true,
    'message' => 'Email tests completed',
    'tests' => $test_results,
    'mailpit_info' => $mailpit_info,
    'webui_url' => 'http://localhost:8025',
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>