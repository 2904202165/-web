<?php
// api.php - Core Logic API
// Developer: Slice

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
require 'conn.php';

// 获取前端 POST 的 JSON 数据
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// -----------------------------------------------------------
// 🤖 AI CONFIGURATION (请在此处填入你的 API Key)
// -----------------------------------------------------------
$SILICON_KEY = 'YOUR_SILICONFLOW_KEY'; // 例如: sk-xxxxxxxx

// 统一 JSON 输出函数
function jsonOut($code, $msg, $data = []) {
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data]);
    exit;
}

try {
    // 1. 获取最新状态 (Get Status)
    if ($action == 'get_status') {
        $uid = $input['uid'] ?? 0;
        $stmt = $pdo->prepare("SELECT username, last_check_in, status, email FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();
        if ($user) jsonOut(200, 'OK', $user);
        else jsonOut(404, 'User not found');
    }

    // 2. AI 温暖提醒接口 (AI Greeting)
    if ($action == 'get_ai_warmth') {
        $username = $_GET['name'] ?? '长辈';
        
        // 如果未配置 Key，返回默认本地问候
        if ($SILICON_KEY == 'YOUR_SILICONFLOW_KEY' || empty($SILICON_KEY)) { 
            jsonOut(200, 'OK', ['text' => "{$username}，今天也要开心哦！"]); 
        }

        // 调用硅基流动 API (DeepSeek-V3)
        $url = "https://api.siliconflow.cn/v1/chat/completions";
        $data = [
            "model" => "deepseek-ai/DeepSeek-V3",
            "messages" => [
                [
                    "role" => "system", 
                    "content" => "给一位中国老人（称呼：{$username}）写一句简短、温暖的问候语。内容关于健康、心情或天气。语气尊敬亲切。25字以内。直接输出内容。"
                ]
            ],
            "stream" => false,
            "max_tokens" => 100
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $SILICON_KEY",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 设置3秒超时防止卡顿
        
        $res = curl_exec($ch);
        curl_close($ch);

        $text = json_decode($res, true)['choices'][0]['message']['content'] ?? "{$username}，祝您身体健康！";
        // 清理引号
        $text = str_replace(['"', '“', '”'], '', $text);
        jsonOut(200, 'OK', ['text' => $text]);
    }

    // 3. 初始化 (Init)
    if ($action == 'init') {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'site_title'");
        $stmt->execute();
        $title = $stmt->fetchColumn();
        jsonOut(200, 'OK', ['title' => $title ? $title : '平安报备']);
    }

    // 4. 登录/注册 (Login & Register) - 含安全防御
    if ($action == 'login') {
        $account = trim($input['account'] ?? '');
        $email   = trim($input['email'] ?? '');
        $trap    = trim($input['trap'] ?? ''); // 蜜罐字段
        
        // 🛡️ [Honeypot] 蜜罐检测：如果这个字段有值，说明是机器人
        if (!empty($trap)) {
            jsonOut(403, 'Bot detected (Honeypot triggered)');
        }

        if (empty($account)) jsonOut(400, '名字不能为空');

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$account]);
        $user = $stmt->fetch();

        // 新用户注册
        if (!$user) {
            // 🛡️ [Rate Limit] IP 限流：1小时内最多注册3个
            $ip = $_SERVER['REMOTE_ADDR'];
            $sql_limit = "SELECT COUNT(*) FROM users WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            $stmt_limit = $pdo->prepare($sql_limit);
            $stmt_limit->execute([$ip]);
            
            if ($stmt_limit->fetchColumn() >= 3) {
                jsonOut(429, '注册过于频繁，请稍后再试 (Rate limit exceeded)');
            }

            // 创建用户
            $default_pass = password_hash('123456', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, ip_address) VALUES (?, ?, ?, 'user', ?)");
            $stmt->execute([$account, $default_pass, $email, $ip]);
            
            // 重新获取
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$account]);
            $user = $stmt->fetch();
        } else {
            // 老用户更新邮箱
            if (!empty($email)) {
                $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $user['id']]);
                $user['email'] = $email;
            }
        }

        // 禁止管理员从前台登录
        if ($user['role'] == 'admin') jsonOut(403, '管理员请通过 admin.php 登录后台');
        
        jsonOut(200, '登录成功', $user);
    }

    // 5. 报平安 (Heartbeat) - 核心逻辑
    if ($action == 'heartbeat') {
        $uid = $input['uid'] ?? 0;
        if ($uid == 0) jsonOut(400, 'ID丢失');

        // 逻辑：更新时间 + 状态设为alive + 警告计数归零
        $sql = "UPDATE users SET 
                last_check_in = NOW(), 
                status = 'alive', 
                warning_count = 0 
                WHERE id = ?";
                
        if ($pdo->prepare($sql)->execute([$uid])) {
            jsonOut(200, '报备成功！');
        } else {
            jsonOut(500, '数据库更新失败');
        }
    }

} catch (Exception $e) {
    jsonOut(500, 'System Error: ' . $e->getMessage());
}
?>
