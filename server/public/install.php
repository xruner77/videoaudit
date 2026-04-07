<?php
/**
 * VideoAudit 安装引导程序
 * 类似 WordPress 的图形化安装向导
 * 功能：检测环境 → 填写数据库信息 → 测试连接 → 生成配置 → 初始化数据库
 */

declare(strict_types=1);

// ==================== 安装锁 ====================
$configPath = __DIR__ . '/../db_config.php';
if (file_exists($configPath)) {
    // 已安装，禁止再次访问
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>VideoAudit</title></head><body style="background:#0f0f1a;color:#e0e0e0;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">';
    echo '<div style="text-align:center;"><h2>✅ 系统已安装</h2><p style="color:#888;">如需重新安装，请先删除服务器上的 <code style="color:#6c5ce7;">db_config.php</code> 文件。</p><a href="/" style="color:#6c5ce7;">返回首页</a></div>';
    echo '</body></html>';
    exit;
}

// ==================== 处理表单提交 ====================
$error = '';
$success = false;
$manualConfig = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host     = trim($_POST['host'] ?? '127.0.0.1');
    $port     = (int)($_POST['port'] ?? 3306);
    $dbname   = trim($_POST['dbname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $jwtSecret = trim($_POST['jwt_secret'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? 'admin123';

    // 基础验证
    if (empty($dbname) || empty($username)) {
        $error = '数据库名和用户名不能为空。';
    } elseif (empty($adminUser) || strlen($adminPass) < 6) {
        $error = '管理员用户名不能为空，且密码至少 6 位。';
    } else {
        // 自动生成 JWT 密钥
        if (empty($jwtSecret)) {
            $jwtSecret = 'va_' . bin2hex(random_bytes(16));
        }

        // 测试数据库连接
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
            $db = new PDO($dsn, $username, $password);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 生成配置文件内容
            $configContent = "<?php\nreturn [\n"
                . "    'host'     => " . var_export($host, true) . ",\n"
                . "    'port'     => $port,\n"
                . "    'dbname'   => " . var_export($dbname, true) . ",\n"
                . "    'username' => " . var_export($username, true) . ",\n"
                . "    'password' => " . var_export($password, true) . ",\n"
                . "    'charset'    => 'utf8mb4',\n"
                . "    'jwt_secret' => " . var_export($jwtSecret, true) . ",\n"
                . "    'debug'      => false,\n"
                . "    'allowed_origins' => [\n"
                . "        'https://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost'),\n"
                . "        'http://' . (\$_SERVER['HTTP_HOST'] ?? 'localhost'),\n"
                . "        'http://localhost:5173',\n"
                . "        'http://127.0.0.1:5173',\n"
                . "    ],\n"
                . "];\n";

            // 尝试写入配置文件
            $written = @file_put_contents($configPath, $configContent);

            if ($written === false) {
                // 写入失败，展示手动保存提示
                $manualConfig = $configContent;
                $error = '⚠️ 无法自动写入配置文件（权限不足）。请手动将以下内容保存为 <code>server/db_config.php</code>：';
            } else {
                // ==================== 初始化数据库 ====================

                // 1. 创建用户表
                $db->exec("
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        role VARCHAR(20) DEFAULT 'user',
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                // 2. 创建视频表
                $db->exec("
                    CREATE TABLE IF NOT EXISTS videos (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        url TEXT NOT NULL,
                        type VARCHAR(20) DEFAULT 'local',
                        user_id INT NOT NULL,
                        views INT DEFAULT 0,
                        duration INT DEFAULT 0,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                // 3. 创建评论表
                $db->exec("
                    CREATE TABLE IF NOT EXISTS comments (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        video_id INT NOT NULL,
                        user_id INT NOT NULL,
                        content TEXT,
                        `timestamp` DOUBLE NOT NULL,
                        image_url TEXT DEFAULT NULL,
                        parent_id INT DEFAULT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");

                // 4. 创建上传目录
                $uploadDir = __DIR__ . '/../uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $imageDir = $uploadDir . '/images';
                if (!is_dir($imageDir)) mkdir($imageDir, 0755, true);

                // 5. 创建管理员账号
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$adminUser]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                    $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')")
                       ->execute([$adminUser, $hash]);
                }

                $success = true;
            }
        } catch (\PDOException $e) {
            $error = '数据库连接失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VideoAudit 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0f0f1a;
            color: #e0e0e0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .installer {
            width: 100%;
            max-width: 520px;
            padding: 20px;
        }
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #6c5ce7, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .logo p {
            color: #888;
            font-size: 14px;
            margin-top: 8px;
        }
        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 32px;
            backdrop-filter: blur(10px);
        }
        .section-title {
            font-size: 15px;
            color: #a0a0b8;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-row {
            display: flex;
            gap: 12px;
        }
        .form-row .form-group { flex: 1; }
        label {
            display: block;
            font-size: 13px;
            color: #a0a0b8;
            margin-bottom: 6px;
        }
        input[type="text"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #e0e0e0;
            padding: 10px 14px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus {
            border-color: #6c5ce7;
        }
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        .btn-install {
            width: 100%;
            background: linear-gradient(135deg, #6c5ce7, #a855f7);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 24px;
            transition: opacity 0.15s, transform 0.15s;
        }
        .btn-install:hover { opacity: 0.9; }
        .btn-install:active { transform: scale(0.98); }
        .error {
            background: rgba(231,76,60,0.15);
            border: 1px solid rgba(231,76,60,0.3);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 20px;
            color: #ff6b6b;
            font-size: 14px;
        }
        .success-card {
            text-align: center;
            padding: 48px 32px;
        }
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .success-card h2 {
            color: #e0e0e0;
            margin-bottom: 12px;
        }
        .success-card p {
            color: #888;
            margin-bottom: 24px;
        }
        .btn-enter {
            display: inline-block;
            background: linear-gradient(135deg, #6c5ce7, #a855f7);
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            padding: 12px 40px;
            font-size: 16px;
            font-weight: 600;
        }
        pre.config-block {
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 16px;
            font-size: 12px;
            color: #a0d995;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin-top: 12px;
        }
        .divider {
            height: 1px;
            background: rgba(255,255,255,0.06);
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="logo">
            <h1>🎬 VideoAudit</h1>
            <p>视频审核系统 · 安装向导</p>
        </div>

        <?php if ($success): ?>
            <div class="card success-card">
                <div class="success-icon">🎉</div>
                <h2>安装完成！</h2>
                <p>数据库已初始化，管理员账号已创建。</p>
                <table style="width:100%;text-align:left;margin-bottom:24px;font-size:14px;">
                    <tr><td style="color:#888;padding:4px 0;">管理员账号</td><td style="color:#6c5ce7;font-weight:600;"><?= htmlspecialchars($adminUser) ?></td></tr>
                    <tr><td style="color:#888;padding:4px 0;">管理员密码</td><td style="color:#6c5ce7;font-weight:600;"><?= htmlspecialchars($adminPass) ?></td></tr>
                </table>
                <p style="color:#f59e0b;font-size:12px;">⚠️ 请立即登录后修改默认密码</p>
                <a href="/" class="btn-enter">进入系统</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error">
                    <?= $error ?>
                    <?php if ($manualConfig): ?>
                        <pre class="config-block"><?= htmlspecialchars($manualConfig) ?></pre>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="card">
                <div class="section-title">📦 数据库配置</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>主机地址</label>
                        <input type="text" name="host" value="<?= htmlspecialchars($_POST['host'] ?? '127.0.0.1') ?>" placeholder="127.0.0.1">
                    </div>
                    <div class="form-group" style="max-width:100px;">
                        <label>端口</label>
                        <input type="number" name="port" value="<?= htmlspecialchars($_POST['port'] ?? '3306') ?>" placeholder="3306">
                    </div>
                </div>

                <div class="form-group">
                    <label>数据库名称</label>
                    <input type="text" name="dbname" value="<?= htmlspecialchars($_POST['dbname'] ?? 'videoaudit') ?>" placeholder="videoaudit">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>数据库用户</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="root">
                    </div>
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="password" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>" placeholder="••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label>JWT 密钥</label>
                    <input type="text" name="jwt_secret" value="<?= htmlspecialchars($_POST['jwt_secret'] ?? '') ?>" placeholder="留空自动生成">
                    <div class="hint">用于生成登录令牌，留空将自动生成一个安全密钥。</div>
                </div>

                <div class="divider"></div>
                <div class="section-title">👤 管理员账号</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" placeholder="admin">
                    </div>
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_pass" value="<?= htmlspecialchars($_POST['admin_pass'] ?? '') ?>" placeholder="至少 6 位">
                        <div class="hint">默认密码 admin123，建议修改。</div>
                    </div>
                </div>

                <button type="submit" class="btn-install">🚀 开始安装</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
