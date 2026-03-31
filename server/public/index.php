<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// ---------- 初始化 Slim App ----------
$app = AppFactory::create();

// ---------- 解析 JSON Body ----------
$app->addBodyParsingMiddleware();

// ---------- CORS 中间件 ----------
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// 处理 OPTIONS 预检请求
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// ---------- 数据库初始化 (MySQL) ----------
$dbConfig = require __DIR__ . '/../db_config.php';
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

// 建表 (MySQL 语法)
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

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

try {
    $db->exec("ALTER TABLE videos ADD COLUMN views INT DEFAULT 0");
} catch (\PDOException $e) {}

try {
    $db->exec("ALTER TABLE videos ADD COLUMN duration INT DEFAULT 0");
} catch (\PDOException $e) {}

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

// 兼容旧表：尝试添加新列
try { $db->exec("ALTER TABLE comments ADD COLUMN image_url TEXT DEFAULT NULL"); } catch (\PDOException $e) {}
try { $db->exec("ALTER TABLE comments ADD COLUMN parent_id INT DEFAULT NULL"); } catch (\PDOException $e) {}

// 确保评论图片上传目录存在
$commentImageDir = __DIR__ . '/../uploads/images';
if (!is_dir($commentImageDir)) {
    mkdir($commentImageDir, 0755, true);
}

// 预设管理员 (仅首次运行时创建)
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
$stmt->execute();
if ((int)$stmt->fetchColumn() === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (username, password, role) VALUES ('admin', ?, 'admin')")
       ->execute([$hash]);
}

// ---------- 注册路由 ----------
$routes = require __DIR__ . '/../src/Routes/auth.php';
$routes($app, $db);

$routes = require __DIR__ . '/../src/Routes/videos.php';
$routes($app, $db);

$routes = require __DIR__ . '/../src/Routes/comments.php';
$routes($app, $db);

// ---------- 错误处理 ----------
$app->addErrorMiddleware(true, true, true);

$app->run();
