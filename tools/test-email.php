<?php
/**
 * Email Test Script for Mailpit
 * Tests email functionality through Mailpit SMTP server
 */

// SMTP Configuration for Mailpit
ini_set('SMTP', 'mailpit');
ini_set('smtp_port', '1025');
ini_set('sendmail_from', 'test@ec-site-dev.local');

// Test email details
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

// Headers for HTML email
$headers = array(
    'MIME-Version: 1.0',
    'Content-type: text/html; charset=UTF-8',
    'From: EC Site Development <noreply@ec-site-dev.local>',
    'Reply-To: noreply@ec-site-dev.local',
    'X-Mailer: PHP/' . phpversion(),
    'X-Environment: Development',
    'X-Service: Mailpit-Test'
);

echo "Sending test email...\n";
echo "To: $to\n";
echo "Subject: $subject\n";
echo "SMTP Server: mailpit:1025\n\n";

// Send email
$result = mail($to, $subject, $message, implode("\r\n", $headers));

if ($result) {
    echo "✅ Email sent successfully!\n";
    echo "Check Mailpit Web UI at: http://localhost:8025\n";
    
    // Try to get message count from Mailpit API
    sleep(1); // Wait for message to be processed
    $api_response = @file_get_contents('http://mailpit:8025/api/v1/info');
    if ($api_response) {
        $info = json_decode($api_response, true);
        echo "📧 Total messages in Mailpit: " . $info['Messages'] . "\n";
        echo "📬 Unread messages: " . $info['Unread'] . "\n";
    }
} else {
    echo "❌ Failed to send email\n";
    echo "Please check SMTP configuration and Mailpit service status\n";
}

// Additional test: Send a welcome email template
echo "\n" . str_repeat("=", 50) . "\n";
echo "Sending welcome email template...\n";

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
        
        <p style='text-align: center;'>
            <a href='http://localhost:3000' class='button'>ショッピングを開始</a>
        </p>
        
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

$welcome_result = mail($to, $welcome_subject, $welcome_message, implode("\r\n", $headers));

if ($welcome_result) {
    echo "✅ Welcome email sent successfully!\n";
} else {
    echo "❌ Failed to send welcome email\n";
}

echo "\n📧 Email testing completed. Check Mailpit UI for all messages.\n";
?>