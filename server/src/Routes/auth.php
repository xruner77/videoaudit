<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

/**
 * 认证路由: 注册、登录、获取当前用户
 */
return function (App $app, PDO $db) {

    $jwtSecret = 'videoaudit_jwt_secret_key_2026';

    // POST /api/auth/register
    $app->post('/api/auth/register', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $response->getBody()->write(json_encode(['error' => '用户名和密码不能为空']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (mb_strlen($username) < 2 || mb_strlen($username) > 20) {
            $response->getBody()->write(json_encode(['error' => '用户名长度应在 2-20 个字符之间']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (strlen($password) < 6) {
            $response->getBody()->write(json_encode(['error' => '密码至少 6 个字符']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 检查用户名是否已存在
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => '用户名已存在']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $stmt->execute([$username, $hash]);

        $response->getBody()->write(json_encode(['message' => '注册成功']));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    });

    // POST /api/auth/login
    $app->post('/api/auth/login', function (Request $request, Response $response) use ($db, $jwtSecret) {
        $data = $request->getParsedBody();
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            $response->getBody()->write(json_encode(['error' => '用户名和密码不能为空']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => '用户名或密码错误']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $payload = [
            'sub' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 86400 * 7, // 7 天过期
        ];

        $token = JWT::encode($payload, $jwtSecret, 'HS256');

        $response->getBody()->write(json_encode([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // GET /api/auth/me
    $app->get('/api/auth/me', function (Request $request, Response $response) use ($db) {
        $user = $request->getAttribute('user');
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => '未认证']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $stmt = $db->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$user->sub]);
        $userData = $stmt->fetch();

        if (!$userData) {
            $response->getBody()->write(json_encode(['error' => '用户不存在']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write(json_encode(['user' => $userData]));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));

    // PUT /api/auth/password - 修改密码 (需登录)
    $app->put('/api/auth/password', function (Request $request, Response $response) use ($db) {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        $oldPassword = $data['old_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (empty($oldPassword) || empty($newPassword)) {
            $response->getBody()->write(json_encode(['error' => '请填写完整信息']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (strlen($newPassword) < 6) {
            $response->getBody()->write(json_encode(['error' => '新密码至少 6 个字符']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // 验证旧密码
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user->sub]);
        $userData = $stmt->fetch();

        if (!$userData || !password_verify($oldPassword, $userData['password'])) {
            $response->getBody()->write(json_encode(['error' => '当前密码错误']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // 更新密码
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $user->sub]);

        $response->getBody()->write(json_encode(['message' => '密码修改成功']));
        return $response->withHeader('Content-Type', 'application/json');
    })->add(new \App\Middleware\AuthMiddleware($jwtSecret));
};
