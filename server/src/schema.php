<?php
/**
 * 数据库 Schema 定义（共享模块）
 * 被 install.php 和 init_db.php 共同引用
 */

declare(strict_types=1);

/**
 * 初始化数据库表结构
 * @param PDO $db 数据库连接
 */
function initSchema(PDO $db): void
{
    // 1. 用户表
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 2. 视频表
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

    // 3. 兼容旧视频表字段
    try { $db->exec("ALTER TABLE videos ADD COLUMN views INT DEFAULT 0"); } catch (\PDOException $e) { if ($e->errorInfo[1] !== 1060) throw $e; }
    try { $db->exec("ALTER TABLE videos ADD COLUMN duration INT DEFAULT 0"); } catch (\PDOException $e) { if ($e->errorInfo[1] !== 1060) throw $e; }

    // 4. 评论表
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

    // 5. 兼容旧评论表字段
    try { $db->exec("ALTER TABLE comments ADD COLUMN image_url TEXT DEFAULT NULL"); } catch (\PDOException $e) { if ($e->errorInfo[1] !== 1060) throw $e; }
    try { $db->exec("ALTER TABLE comments ADD COLUMN parent_id INT DEFAULT NULL"); } catch (\PDOException $e) { if ($e->errorInfo[1] !== 1060) throw $e; }
}

/**
 * 创建管理员账号（仅在不存在时创建）
 * @param PDO $db 数据库连接
 * @param string $username 管理员用户名
 * @param string $password 管理员密码（明文，函数内部自动哈希）
 */
function createAdminUser(PDO $db, string $username = 'admin', string $password = 'admin123'): void
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ((int)$stmt->fetchColumn() === 0) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')")
           ->execute([$username, $hash]);
    }
}

/**
 * 确保上传目录存在
 * @param string $baseDir 基础上传目录路径
 */
function ensureUploadDirs(string $baseDir): void
{
    if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);
    $imageDir = $baseDir . '/images';
    if (!is_dir($imageDir)) mkdir($imageDir, 0755, true);
}
