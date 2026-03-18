<?php

declare(strict_types=1);

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

// ---------- 数据库初始化 ----------
$dbPath = __DIR__ . '/../database.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA foreign_keys=ON');

// 建表
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        url TEXT NOT NULL,
        type TEXT DEFAULT 'local',
        user_id INTEGER NOT NULL,
        views INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
");

try {
    $db->exec("ALTER TABLE videos ADD COLUMN views INTEGER DEFAULT 0");
} catch (\PDOException $e) {
    // 列可能已经存在，忽略错误
}

$db->exec("
    CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        video_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        timestamp REAL NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
");

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
