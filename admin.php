<?php
// admin.php - Admin Dashboard
// Developer: Slice

require 'conn.php';

// 鉴权
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 退出逻辑
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- 处理表单: 保存设置 ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    try {
        foreach ($_POST['config'] as $key => $val) {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
            $stmt->execute([trim($val), $key]);
        }
        $msg = "✅ 配置已保存";
    } catch (Exception $e) {
        $msg = "❌ 保存失败: " . $e->getMessage();
    }
}

// --- 处理删除用户 ---
if (isset($_GET['del_id'])) {
    $id = (int)$_GET['del_id'];
    if ($id != 1) { // 保护 ID 1 管理员
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        header("Location: admin.php");
        exit;
    }
}

// --- 读取数据 ---
// 1. 设置
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) { $settings[$row['key_name']] = $row; }

// 2. 用户
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// 3. 统计
$stats = ['total' => count($users), 'alive' => 0, 'warning' => 0];
foreach ($users as $u) {
    if ($u['status'] == 'alive') $stats['alive']++;
    else $stats['warning']++;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>系统管理 - Alive.SYS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans SC', sans-serif; background: #f3f4f6; }
        .nav-item { cursor: pointer; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #3B82F6; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <div class="w-64 bg-gray-900 text-white flex flex-col shadow-2xl z-20 flex-shrink-0">
        <div class="h-20 flex items-center justify-center border-b border-gray-800">
            <h1 class="text-2xl font-bold tracking-wider text-blue-500">ALIVE<span class="text-white">.SYS</span></h1>
        </div>

        <nav class="flex-1 py-6 px-4 space-y-2">
            <div onclick="switchTab('dashboard', this)" class="nav-item active px-4 py-3 rounded-r-lg text-gray-300 flex items-center">
                <span class="mr-3">📊</span> 仪表盘
            </div>
            <div onclick="switchTab('users', this)" class="nav-item px-4 py-3 rounded-r-lg text-gray-300 flex items-center">
                <span class="mr-3">👥</span> 用户管理
            </div>
            <div onclick="switchTab('settings', this)" class="nav-item px-4 py-3 rounded-r-lg text-gray-300 flex items-center">
                <span class="mr-3">⚙️</span> 系统设置
            </div>
        </nav>

        <div class="p-6 border-t border-gray-800 bg-gray-900">
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center font-bold">S</div>
                <div>
                    <p class="text-sm font-bold">Slice</p>
                    <p class="text-xs text-gray-500">Developer</p>
                </div>
            </div>
            <div class="bg-white p-2 rounded-lg">
                <img src="https://source.ictcode.com/wechat.jpg" alt="WeChat" class="w-full h-auto rounded">
                <p class="text-center text-xs text-gray-800 mt-1 font-bold">联系开发者</p>
            </div>
            <a href="?logout=1" class="block text-center text-xs text-red-500 mt-4 hover:underline">退出登录</a>
        </div>
    </div>

    <div class="flex-1 flex flex-col overflow-y-auto bg-gray-100">
        <header class="bg-white shadow h-16 flex items-center justify-between px-8 z-10 sticky top-0">
            <h2 id="pageTitle" class="text-xl font-bold text-gray-700">数据仪表盘</h2>
            <div class="text-sm text-gray-500">Admin: <?php echo $_SESSION['admin_name']; ?></div>
        </header>

        <main class="p-8">
            <?php if(isset($msg)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded"><?php echo $msg; ?></div>
            <?php endif; ?>
            
            <div id="view-dashboard" class="tab-content active space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-blue-500">
                        <p class="text-gray-400 text-sm">总用户</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-green-500">
                        <p class="text-gray-400 text-sm">正常</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo $stats['alive']; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm border-l-4 border-red-500">
                        <p class="text-gray-400 text-sm">异常</p>
                        <p class="text-4xl font-bold text-gray-800 mt-2"><?php echo $stats['warning']; ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm">
                    <h3 class="font-bold text-gray-700 mb-4">用户状态分布</h3>
                    <div id="chartPie" style="width: 100%; height: 300px;"></div>
                </div>
            </div>

            <div id="view-users" class="tab-content">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b"><h3 class="font-bold text-gray-700">用户列表</h3></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500">
                                <tr>
                                    <th class="px-6 py-3">ID</th><th class="px-6 py-3">用户</th>
                                    <th class="px-6 py-3">邮箱</th><th class="px-6 py-3">最后报备</th>
                                    <th class="px-6 py-3">警告次数</th><th class="px-6 py-3">状态</th><th class="px-6 py-3">操作</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach($users as $u): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">#<?php echo $u['id']; ?></td>
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td class="px-6 py-4"><?php echo $u['email'] ?: '<span class="text-gray-300">未设置</span>'; ?></td>
                                    <td class="px-6 py-4 text-gray-500"><?php echo $u['last_check_in']; ?></td>
                                    <td class="px-6 py-4 text-center"><?php echo $u['warning_count']; ?></td>
                                    <td class="px-6 py-4">
                                        <?php 
                                            if($u['status']=='alive') echo '<span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">正常</span>';
                                            else if($u['status']=='dead') echo '<span class="px-2 py-1 bg-gray-800 text-white rounded text-xs">DEAD</span>';
                                            else echo '<span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs">警告</span>';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if($u['role']!='admin'): ?>
                                            <a href="admin.php?del_id=<?php echo $u['id']; ?>" onclick="return confirm('确定删除?')" class="text-red-500 hover:underline">删除</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="view-settings" class="tab-content">
                <div class="bg-white rounded-lg shadow overflow-hidden max-w-2xl">
                    <div class="bg-gray-50 px-6 py-4 border-b"><h3 class="font-bold text-gray-700">⚙️ 系统设置</h3></div>
                    <form method="POST" class="p-6 space-y-4">
                        <div><label class="block text-sm font-medium mb-1">网站名称</label><input type="text" name="config[site_title]" value="<?php echo $settings['site_title']['value']??''; ?>" class="w-full border rounded p-2"></div>
                        <div><label class="block text-sm font-medium mb-1">报备间隔 (小时)</label><input type="number" name="config[check_interval]" value="<?php echo $settings['check_interval']['value']??'24'; ?>" class="w-full border rounded p-2"></div>
                        <div class="border-t pt-4"><h4 class="text-sm font-bold text-gray-500 mb-2">邮件配置 (SMTP)</h4></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">SMTP服务器</label><input type="text" name="config[smtp_host]" value="<?php echo $settings['smtp_host']['value']??''; ?>" class="w-full border rounded p-2"></div>
                            <div><label class="block text-sm font-medium">端口 (465)</label><input type="text" name="config[smtp_port]" value="<?php echo $settings['smtp_port']['value']??'465'; ?>" class="w-full border rounded p-2"></div>
                            <div><label class="block text-sm font-medium">发件邮箱</label><input type="text" name="config[smtp_user]" value="<?php echo $settings['smtp_user']['value']??''; ?>" class="w-full border rounded p-2"></div>
                            <div><label class="block text-sm font-medium">授权码</label><input type="password" name="config[smtp_pass]" value="<?php echo $settings['smtp_pass']['value']??''; ?>" class="w-full border rounded p-2"></div>
                        </div>
                        <div class="text-right mt-4"><button type="submit" name="save_settings" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded">保存设置</button></div>
                    </form>
                </div>
            </div>

        </main>
    </div>

    <script>
        function switchTab(tabName, element) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.getElementById('view-' + tabName).classList.add('active');
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            if(element) element.classList.add('active');
            const titles = {'dashboard': '数据仪表盘', 'users': '用户管理', 'settings': '系统设置'};
            document.getElementById('pageTitle').innerText = titles[tabName];
            if(tabName === 'dashboard') { setTimeout(() => { chartPie && chartPie.resize(); }, 100); }
        }

        var chartPie;
        window.onload = function() {
            chartPie = echarts.init(document.getElementById('chartPie'));
            chartPie.setOption({
                color: ['#10B981', '#EF4444'],
                tooltip: { trigger: 'item' },
                series: [{
                    type: 'pie', radius: ['40%', '70%'],
                    data: [
                        { value: <?php echo $stats['alive']; ?>, name: '正常' },
                        { value: <?php echo $stats['warning']; ?>, name: '异常' }
                    ]
                }]
            });
            window.onresize = function() { chartPie.resize(); };
        };
    </script>
</body>
</html>
