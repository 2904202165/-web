<?php
// monitor.php - Monitoring Daemon & Email Sender
// Developer: Slice
// Usage: Visit http://your-domain/monitor.php?key=YOUR_SECRET_KEY via Cron Job

header("Content-Type: text/html; charset=utf-8");
require 'conn.php';

// -----------------------------------------------------------
// 🔒 SECURITY CONFIGURATION (请修改此密钥)
// -----------------------------------------------------------
$CRON_KEY = 'YOUR_SECRET_KEY'; 

// 安全拦截：防止未授权访问
if (empty($_GET['key']) || $_GET['key'] !== $CRON_KEY) {
    http_response_code(403);
    die("Access Denied: Invalid Key");
}

// -----------------------------------------------------------
// 📧 SMTP CONFIGURATION (请修改你的邮箱配置)
// -----------------------------------------------------------
$smtp_config = [
    'host' => 'smtp.qq.com',       // SMTP 服务器
    'port' => 465,                 // 端口 (SSL通常为465)
    'user' => 'your_email@qq.com', // 发件人邮箱账号
    'pass' => 'YOUR_SMTP_CODE',    // 邮箱授权码 (不是登录密码!)
    'from_name' => 'Alive.SYS'     // 邮件发送者名称
];

// 获取检测间隔设置
$interval = 24; 
$stmt = $pdo->query("SELECT value FROM settings WHERE key_name = 'check_interval'");
$row = $stmt->fetch();
if ($row) $interval = (int)$row['value'];

echo "<h3>🛡️ [Alive.SYS] Monitor Running</h3>";
echo "Time: " . date('Y-m-d H:i:s') . "<br>";
echo "Interval: {$interval}h | Strategy: 3-Strike Rule<hr>";

// 扫描逻辑：查找非 DEAD 状态的用户
$sql = "SELECT * FROM users WHERE status != 'dead'";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

$count_sent = 0;

foreach ($users as $u) {
    $last_check = strtotime($u['last_check_in']);
    if (!$last_check) continue; // 忽略无记录的新用户
    
    // 计算未报备时长
    $hours_gone = (time() - $last_check) / 3600;
    
    // 未超时，跳过
    if ($hours_gone < $interval) continue;

    // --- 熔断机制 (3-Strike Rule) ---
    if ($u['warning_count'] >= 3) {
        mark_as_dead($pdo, $u['id'], $u['username']);
        continue;
    }

    // --- 冷却机制 (防止重复发送) ---
    // 规则：距离上次发送必须超过 24 小时
    if ($u['last_notified_at']) {
        $hours_since_last_email = (time() - strtotime($u['last_notified_at'])) / 3600;
        if ($hours_since_last_email < 24) continue;
    }

    // 准备发送
    if (empty($u['email'])) {
        echo "⚠️ User [{$u['username']}] timed out but has no email set.<br>";
        continue;
    }

    $current_warn_level = $u['warning_count'] + 1;
    echo "📧 Sending alert level {$current_warn_level} to [{$u['username']}]... ";

    $subject = "【紧急】用户 {$u['username']} 异常未报备 (第{$current_warn_level}次)";
    $body = build_email_body($u, $interval, $current_warn_level);

    // 发送邮件
    $res = send_mail_smtp($u['email'], $subject, $body, $smtp_config);

    if ($res === true) {
        echo "✅ Sent.<br>";
        // 更新数据库：增加警告次数，记录时间，状态设为 warning
        $sql_update = "UPDATE users SET 
                       status = 'warning', 
                       warning_count = warning_count + 1, 
                       last_notified_at = NOW() 
                       WHERE id = ?";
        $pdo->prepare($sql_update)->execute([$u['id']]);
        $count_sent++;
    } else {
        echo "❌ Failed: $res <br>";
    }
}

if ($count_sent == 0) echo "<p style='color:green'>System is healthy. No emails sent.</p>";


// --- Helper Functions ---

function mark_as_dead($pdo, $uid, $name) {
    echo "🔴 User [{$name}] marked as DEAD (No response after 3 alerts). Stopping.<br>";
    $pdo->prepare("UPDATE users SET status = 'dead' WHERE id = ?")->execute([$uid]);
}

function build_email_body($u, $interval, $level) {
    $tips = "";
    if ($level == 3) {
        $tips = "<p style='color:red;font-weight:bold;'>【Final Notice】This is the last alert. Monitoring will stop if no response.</p>";
    }
    return "
        <h3>Alive.SYS Emergency Alert ({$level}/3)</h3>
        <p>User <b>{$u['username']}</b> has not checked in for over {$interval} hours.</p>
        <p>Last Check-in: {$u['last_check_in']}</p>
        <p>Please contact the user immediately to ensure their safety.</p>
        {$tips}
        <hr>
        <p style='font-size:12px;color:gray'>Powered by Alive.SYS</p>
    ";
}

// 轻量级 SMTP 发送函数 (无需 PHPMailer)
function send_mail_smtp($to, $subject, $body, $config) {
    $host = "ssl://{$config['host']}";
    $socket = fsockopen($host, $config['port'], $errno, $errstr, 10);
    if (!$socket) return "Connect failed: $errstr";
    
    get_response($socket);
    fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n"); get_response($socket);
    fputs($socket, "AUTH LOGIN\r\n"); get_response($socket);
    fputs($socket, base64_encode($config['user']) . "\r\n"); get_response($socket);
    fputs($socket, base64_encode($config['pass']) . "\r\n"); get_response($socket);
    fputs($socket, "MAIL FROM: <{$config['user']}>\r\n"); get_response($socket);
    fputs($socket, "RCPT TO: <$to>\r\n"); get_response($socket);
    fputs($socket, "DATA\r\n"); get_response($socket);
    
    $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=utf-8\r\n";
    $headers .= "From: =?UTF-8?B?" . base64_encode($config['from_name']) . "?= <{$config['user']}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nTo: <$to>\r\n";
    
    fputs($socket, "$headers\r\n$body\r\n.\r\n");
    $result = get_response($socket);
    fputs($socket, "QUIT\r\n"); fclose($socket);
    
    if (strpos($result, '250') !== false) return true;
    return $result;
}

function get_response($socket) {
    $data = "";
    while ($str = fgets($socket, 515)) {
        $data .= $str;
        if (substr($str, 3, 1) == " ") break;
    }
    return $data;
}
?>
