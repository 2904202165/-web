<?php
// conn.php - Database Connection Configuration
// Developer: Slice

header("Content-Type: text/html; charset=utf-8");

// -----------------------------------------------------------
// ⚠️ CONFIGURATION REQUIRED (请在部署时修改以下配置)
// -----------------------------------------------------------
$db_host = 'localhost';
$db_name = 'live_check';        // 你的数据库名
$db_user = 'root';              // 数据库账号
$db_pass = 'YOUR_DB_PASSWORD';  // 数据库密码 

try {
    // 创建 PDO 连接
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    
    // 设置错误模式为异常，方便调试
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 默认以关联数组形式返回数据
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 开启 Session (用于登录和验证码)
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

} catch (PDOException $e) {
    // 生产环境建议只提示“连接失败”，不要打印具体错误详情以防泄露路径
    die("<h3>Database Connection Failed</h3>Please check conn.php configuration.");
}
?>
