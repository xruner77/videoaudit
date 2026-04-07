<?php
/**
 * VideoAudit 数据库初始化脚本
 * 手动运行方式：php server/init_db.php
 * 或者浏览器访问（需带上 key 参数）：http://your-domain.com/init_db.php?key=init2026
 */

declare(strict_types=1);

// 安全密钥（防止未授权访问，生产环境使用时建议修改）
$securityKey = 'init2026';

// 检查是否为 CLI 模式或提供了正确的密钥
if (php_sapi_name() !== 'cli' && ($_GET['key'] ?? '') !== $securityKey) {
    die('Unauthorized access. Use CLI or provide correct key.');
}

require __DIR__ . '/vendor/autoload.php';
$dbConfig = require __DIR__ . '/db_config.php';

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['dbname'],
        $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "--- 开始初始化数据库 ---\n";

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
    echo "Check: users table created/verified.\n";

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
    echo "Check: videos table created/verified.\n";

    // 3. 兼容旧视频表字段
    try { $db->exec("ALTER TABLE videos ADD COLUMN views INT DEFAULT 0"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE videos ADD COLUMN duration INT DEFAULT 0"); } catch (\Exception $e) {}
    echo "Check: updated videos schema.\n";

    // 4. 创建评论表
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
    echo "Check: comments table created/verified.\n";

    // 5. 兼容旧评论表字段
    try { $db->exec("ALTER TABLE comments ADD COLUMN image_url TEXT DEFAULT NULL"); } catch (\Exception $e) {}
    try { $db->exec("ALTER TABLE comments ADD COLUMN parent_id INT DEFAULT NULL"); } catch (\Exception $e) {}
    echo "Check: updated comments schema.\n";

    // 6. 预设管理员
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')")
           ->execute([$hash]);
        echo "Check: created default admin user (admin/admin123).\n";
    } else {
        echo "Check: admin user already exists.\n";
    }

    echo "--- 数据库初始化完成 ---\n";

} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
