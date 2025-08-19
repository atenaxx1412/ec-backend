<?php
/**
 * Direct SMTP Test for Mailpit
 * Tests SMTP connection and email sending directly
 */

header('Content-Type: application/json; charset=utf-8');

// Simple SMTP test function
function sendSMTPEmail($host, $port, $from, $to, $subject, $body) {
    $socket = fsockopen($host, $port, $errno, $errstr, 30);
    if (!$socket) {
        return ['success' => false, 'error' => "Could not connect to SMTP server: $errstr ($errno)"];
    }
    
    // Read initial response
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Unexpected SMTP response: $response"];
    }
    
    // SMTP conversation
    $commands = [
        "HELO ec-site-dev.local\r\n",
        "MAIL FROM: <$from>\r\n",
        "RCPT TO: <$to>\r\n",
        "DATA\r\n"
    ];
    
    foreach ($commands as $command) {
        fputs($socket, $command);
        $response = fgets($socket, 512);
        
        // Check for error responses
        if (substr($response, 0, 1) == '4' || substr($response, 0, 1) == '5') {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP error: $response"];
        }
    }
    
    // Send email content
    $email_content = "Subject: $subject\r\n";
    $email_content .= "From: $from\r\n";
    $email_content .= "To: $to\r\n";
    $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
    $email_content .= "\r\n";
    $email_content .= $body;
    $email_content .= "\r\n.\r\n";
    
    fputs($socket, $email_content);
    $response = fgets($socket, 512);
    
    // Close connection
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return ['success' => substr($response, 0, 3) == '250', 'response' => trim($response)];
}

// Test parameters
$smtp_host = 'mailpit';
$smtp_port = 1025;
$from_email = 'test@ec-site-dev.local';
$to_email = 'customer@example.com';

$results = [];

// Test 1: Simple test email
$subject1 = 'SMTP Test - Development Environment';
$body1 = '<h1>SMTP Test Email</h1><p>This email was sent directly via SMTP to Mailpit.</p><p>Japanese text: こんにちは！</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';

$results['smtp_test'] = sendSMTPEmail($smtp_host, $smtp_port, $from_email, $to_email, $subject1, $body1);

// Test 2: Order confirmation email
$subject2 = 'Order Confirmation - 注文確認';
$body2 = '
<html>
<body>
    <h2>ご注文ありがとうございます</h2>
    <p>注文番号: #' . rand(1000, 9999) . '</p>
    <table border="1" style="border-collapse: collapse;">
        <tr>
            <th>商品</th>
            <th>価格</th>
        </tr>
        <tr>
            <td>iPhone 15 Pro</td>
            <td>¥149,800</td>
        </tr>
    </table>
    <p>お届け予定日: ' . date('Y年m月d日', strtotime('+3 days')) . '</p>
</body>
</html>';

$results['order_test'] = sendSMTPEmail($smtp_host, $smtp_port, $from_email, $to_email, $subject2, $body2);

// Test using PHP mail() function with proper configuration
ini_set('SMTP', $smtp_host);
ini_set('smtp_port', $smtp_port);

$subject3 = 'PHP mail() Function Test';
$message3 = '<h1>PHP mail() Test</h1><p>Testing PHP mail() function with Mailpit.</p>';
$headers3 = "MIME-Version: 1.0\r\n";
$headers3 .= "Content-type: text/html; charset=UTF-8\r\n";
$headers3 .= "From: $from_email\r\n";

$mail_result = mail($to_email, $subject3, $message3, $headers3);
$results['php_mail_test'] = ['success' => $mail_result];

// Get Mailpit statistics
sleep(1);
$mailpit_info = null;
$api_response = @file_get_contents('http://mailpit:8025/api/v1/info');
if ($api_response) {
    $mailpit_info = json_decode($api_response, true);
}

// Return comprehensive results
echo json_encode([
    'success' => true,
    'message' => 'SMTP tests completed',
    'smtp_config' => [
        'host' => $smtp_host,
        'port' => $smtp_port,
        'from' => $from_email,
        'to' => $to_email
    ],
    'tests' => $results,
    'mailpit_info' => $mailpit_info,
    'php_info' => [
        'smtp_ini' => ini_get('SMTP'),
        'smtp_port_ini' => ini_get('smtp_port'),
        'sendmail_path' => ini_get('sendmail_path')
    ],
    'webui_url' => 'http://localhost:8025',
    'timestamp' => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>