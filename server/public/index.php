<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// ---------- 安装检测 ----------
if (!file_exists(__DIR__ . '/../db_config.php')) {
    header('Location: /install.php');
    exit;
}


// ---------- 初始化 Slim App ----------
$app = AppFactory::create();

// ---------- 数据库配置 (MySQL) ----------
$dbConfig = require __DIR__ . '/../db_config.php';

// ---------- 解析 JSON Body ----------
$app->addBodyParsingMiddleware();

// ---------- CORS 中间件 ----------
$app->add(function ($request, $handler) use ($dbConfig) {
    $origin = $request->getHeaderLine('Origin');
    $allowedOrigins = $dbConfig['allowed_origins'] ?? [];
    
    // 如果请求源在白名单内，则允许跨域
    $originToSet = in_array($origin, $allowedOrigins) ? $origin : ($allowedOrigins[0] ?? '*');

    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $originToSet)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// 处理 OPTIONS 预检请求
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// ---------- 数据库初始化 (MySQL) ----------
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

// ---------- 确保评论图片上传目录存在 ----------
$commentImageDir = __DIR__ . '/../uploads/images';
if (!is_dir($commentImageDir)) {
    mkdir($commentImageDir, 0755, true);
}

// ---------- 注册路由 ----------
$routes = require __DIR__ . '/../src/Routes/auth.php';
$routes($app, $db, $dbConfig);

$routes = require __DIR__ . '/../src/Routes/videos.php';
$routes($app, $db, $dbConfig);

$routes = require __DIR__ . '/../src/Routes/comments.php';
$routes($app, $db, $dbConfig);

// ---------- 错误处理 ----------
// 第一个参数控制是否输出详细堆栈到 Response Body
$app->addErrorMiddleware($dbConfig['debug'] ?? false, true, true);

$app->run();
